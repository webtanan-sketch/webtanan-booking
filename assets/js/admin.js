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
})(jQuery);
