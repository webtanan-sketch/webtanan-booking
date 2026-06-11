(function ($) {
    'use strict';

    const renderSinglePreview = function ($preview, attachment) {
        $preview.empty();
        if (attachment && attachment.sizes) {
            const src = (attachment.sizes.thumbnail || attachment.sizes.medium || attachment).url;
            if (src) {
                $('<img>', { src, alt: attachment.alt || '' }).appendTo($preview);
            }
        }
    };

    const renderGalleryPreview = function ($preview, attachments) {
        $preview.empty();
        attachments.forEach(function (attachment) {
            if (!attachment || !attachment.sizes) {
                return;
            }

            const src = (attachment.sizes.thumbnail || attachment.sizes.medium || attachment).url;
            if (src) {
                $('<img>', { src, alt: attachment.alt || '', 'data-id': attachment.id }).appendTo($preview);
            }
        });
    };

    $(document).on('click', '.webtanan-media-upload', function (event) {
        event.preventDefault();
        if (!window.wp || !wp.media) {
            return;
        }

        const $button = $(this);
        const $target = $($button.data('target'));
        const $preview = $($button.data('preview'));
        const frame = wp.media({
            title: 'انتخاب تصویر پزشک',
            button: { text: 'استفاده از تصویر' },
            multiple: false
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            $target.val(attachment.id);
            renderSinglePreview($preview, attachment);
        });

        frame.open();
    });

    $(document).on('click', '.webtanan-gallery-upload', function (event) {
        event.preventDefault();
        if (!window.wp || !wp.media) {
            return;
        }

        const $button = $(this);
        const $target = $($button.data('target'));
        const $preview = $($button.data('preview'));
        const frame = wp.media({
            title: 'انتخاب تصاویر گالری مطب',
            button: { text: 'استفاده از تصاویر' },
            multiple: true
        });

        frame.on('select', function () {
            const attachments = frame.state().get('selection').map(function (attachment) {
                return attachment.toJSON();
            });
            $target.val(attachments.map(function (attachment) { return attachment.id; }).join(','));
            renderGalleryPreview($preview, attachments);
        });

        frame.open();
    });

    $(document).on('click', '.webtanan-media-clear', function (event) {
        event.preventDefault();
        $($(this).data('target')).val('');
        $($(this).data('preview')).empty();
    });

    $(document).on('click', '.webtanan-font-upload', function (event) {
        event.preventDefault();
        if (!window.wp || !wp.media) {
            return;
        }

        const $button = $(this);
        const $target = $($button.data('target'));
        const $urlTarget = $($button.data('url-target'));
        const frame = wp.media({
            title: 'انتخاب فونت افزونه',
            button: { text: 'استفاده از فونت' },
            multiple: false
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            $target.val(attachment.id || '');
            $urlTarget.val(attachment.url || '');
        });

        frame.open();
    });

    const initUserPicker = function () {
        const cfg = window.WebtananBookingAdmin || {};
        $('input[name="user_id"][type="number"]').each(function () {
            const $id = $(this);
            if ($id.data('webtananUserPicker')) {
                return;
            }
            $id.data('webtananUserPicker', true).attr('type', 'hidden').addClass('webtanan-user-id');

            const $picker = $('<div class="webtanan-user-picker-ui" />');
            const $search = $('<input type="search" class="widefat webtanan-user-search" autocomplete="off" placeholder="نام، موبایل، ایمیل یا ID کاربر را جستجو کنید">');
            const $selected = $('<div class="webtanan-user-selected" />');
            const $results = $('<div class="webtanan-user-search-results" hidden />');
            let timer = null;

            $picker.append($search, $selected, $results);
            $id.after($picker);

            $search.on('input', function () {
                const q = $search.val().trim();
                clearTimeout(timer);
                if (q.length < 2 && !/^\d+$/.test(q)) {
                    $results.empty().prop('hidden', true);
                    return;
                }
                timer = setTimeout(function () {
                    $.get(cfg.ajaxUrl || ajaxurl, {
                        action: 'webtanan_booking_user_search',
                        nonce: cfg.nonce || '',
                        q
                    }).done(function (response) {
                        const items = response && response.success && Array.isArray(response.data) ? response.data : [];
                        $results.empty();
                        if (!items.length) {
                            $('<button type="button" disabled>کاربری پیدا نشد</button>').appendTo($results);
                        } else {
                            items.forEach(function (item) {
                                $('<button type="button" />')
                                    .text(item.text)
                                    .attr('data-id', item.id)
                                    .appendTo($results);
                            });
                        }
                        $results.prop('hidden', false);
                    });
                }, 250);
            });

            $results.on('click', 'button[data-id]', function () {
                const $button = $(this);
                $id.val($button.data('id'));
                $selected.text($button.text());
                $results.empty().prop('hidden', true);
                $search.val('');
            });
        });
    };

    const syncSettlementTracking = function ($form) {
        const status = $form.find('select[name="status"]').val();
        const $tracking = $form.find('input[name="bank_tracking_number"]');
        const required = status === 'paid';

        $tracking.prop('required', required);
        $tracking.toggleClass('webtanan-required-field', required);
    };

    $(document).on('change', '.webtanan-settlement-actions select[name="status"]', function () {
        syncSettlementTracking($(this).closest('.webtanan-settlement-actions'));
    });

    $(document).on('submit', '.webtanan-settlement-actions', function (event) {
        const $form = $(this);
        syncSettlementTracking($form);

        if ($form.find('select[name="status"]').val() === 'paid' && !$form.find('input[name="bank_tracking_number"]').val().trim()) {
            event.preventDefault();
            $form.find('input[name="bank_tracking_number"]').trigger('focus');
        }
    });

    $('.webtanan-settlement-actions').each(function () {
        syncSettlementTracking($(this));
    });

    initUserPicker();
})(jQuery);
