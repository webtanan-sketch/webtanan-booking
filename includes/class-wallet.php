<?php
/**
 * Wallet ledger operations.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Wallet {
    /**
     * Advisory locks held until the surrounding DB transaction commits/rolls back.
     *
     * @var array<string,bool>
     */
    private static $locks = array();

    /**
     * @var bool
     */
    private static $shutdown_registered = false;

    public static function balance(int $user_id, string $user_type): float {
        global $wpdb;

        $balance = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT balance_after FROM ' . DB::table('wallets_ledger') . ' WHERE user_id = %d AND user_type = %s ORDER BY id DESC LIMIT 1',
                $user_id,
                sanitize_key($user_type)
            )
        );

        return null === $balance ? 0.0 : (float) $balance;
    }

    public static function ledger(int $user_id, string $user_type, int $limit = 50): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::table('wallets_ledger') . ' WHERE user_id = %d AND user_type = %s ORDER BY id DESC LIMIT %d',
                $user_id,
                sanitize_key($user_type),
                max(1, min(200, $limit))
            ),
            ARRAY_A
        );
    }

    public static function add_entry(array $args) {
        global $wpdb;

        $user_id = absint($args['user_id'] ?? 0);
        $user_type = sanitize_key($args['user_type'] ?? 'patient');
        $entry_type = sanitize_key($args['entry_type'] ?? 'credit');
        $amount = abs((float) ($args['amount'] ?? 0));

        if ($amount <= 0) {
            return new \WP_Error('webtanan_wallet_invalid_amount', __('مبلغ کیف پول باید بیشتر از صفر باشد.', 'webtanan-booking'), array('status' => 400));
        }

        $lock = self::acquire_lock($user_id, $user_type);
        if (is_wp_error($lock)) {
            return $lock;
        }

        $negative_entries = array('debit', 'settlement', 'wallet_payment');
        $signed_amount = in_array($entry_type, $negative_entries, true) ? -1 * $amount : $amount;
        $balance_after = self::balance($user_id, $user_type) + $signed_amount;

        $inserted = $wpdb->insert(
            DB::table('wallets_ledger'),
            array(
                'user_id' => $user_id,
                'user_type' => $user_type,
                'related_appointment_id' => absint($args['related_appointment_id'] ?? 0),
                'related_transaction_id' => absint($args['related_transaction_id'] ?? 0),
                'entry_type' => $entry_type,
                'amount' => $signed_amount,
                'balance_after' => $balance_after,
                'description' => sanitize_textarea_field($args['description'] ?? ''),
                'created_at' => DB::now(),
            ),
            array('%d', '%s', '%d', '%d', '%s', '%f', '%f', '%s', '%s')
        );

        if (!$inserted) {
            return new \WP_Error('webtanan_wallet_insert_failed', __('ثبت دفتر کل کیف پول انجام نشد.', 'webtanan-booking'), array('status' => 500));
        }

        return array(
            'ledger_id' => (int) $wpdb->insert_id,
            'balance_after' => $balance_after,
        );
    }

    public static function pay_for_appointment(int $appointment_id, string $lock_token) {
        return Booking::confirm_wallet_payment($appointment_id, $lock_token);
    }

    public static function release_locks(): void {
        global $wpdb;

        foreach (array_keys(self::$locks) as $lock_key) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_key));
            unset(self::$locks[$lock_key]);
        }
    }

    private static function acquire_lock(int $user_id, string $user_type) {
        global $wpdb;

        $lock_key = substr('webtanan_wallet_' . $user_type . '_' . $user_id, 0, 64);
        if (isset(self::$locks[$lock_key])) {
            return true;
        }

        $lock_acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_key));
        if (1 !== $lock_acquired) {
            return new \WP_Error('webtanan_wallet_lock_timeout', __('قفل دفتر کل کیف پول انجام نشد. لطفاً دوباره تلاش کنید.', 'webtanan-booking'), array('status' => 409));
        }

        self::$locks[$lock_key] = true;
        if (!self::$shutdown_registered) {
            self::$shutdown_registered = true;
            register_shutdown_function(array(__CLASS__, 'release_locks'));
        }

        return true;
    }
}
