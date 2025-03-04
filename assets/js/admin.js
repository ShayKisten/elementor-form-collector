jQuery(document).ready(function($) {
    $('.efc-submission-row').on('click', function() {
        var url = $(this).data('url');
        if (url) {
            window.location.href = url;
        }
    });
});