$(function () {
    // Only run on the homepage.
    if (!$('body').hasClass('default')) {
        return;
    }

    $.get('/api/background/' + $(window).outerWidth()).done(function (url) {
        $('body.default-index').attr('style', 'background-image:url(' + url + ');');
    });
});
