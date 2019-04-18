$(function () {
    // Only run on the homepage.
    if (!$('body').hasClass('default')) {
        return;
    }

    $.get(baseUrl + 'api/background/' + $(window).outerWidth()).done(function (imageInfo) {
        $('body.default-index').attr(
            'style',
            'background-image:url(' + imageInfo.thumburl + ');'
        );
        $('#background_commons_link').text(imageInfo.canonicaltitle)
            .prop('href', imageInfo.descriptionurl);
    });
});
