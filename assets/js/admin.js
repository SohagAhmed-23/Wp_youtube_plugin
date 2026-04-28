/**
 * YTFlix Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Media uploader for image fields
        $('.ytflix-upload-btn').on('click', function(e) {
            e.preventDefault();

            var targetId = $(this).data('target');
            var frame = wp.media({
                title: 'Select Image',
                button: { text: 'Use This Image' },
                multiple: false,
                library: { type: 'image' },
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#' + targetId).val(attachment.url);
                $('#' + targetId + '_preview').html(
                    '<img src="' + attachment.url + '" style="max-width:300px;margin-top:10px;border-radius:4px">'
                );
            });

            frame.open();
        });
    });

})(jQuery);
