$(function () {
    if ($('body').hasClass('program-new') || $('body').hasClass('program-edit')) {
        setupAddRemove('program', 'organizer');
    }

    $('.program-action__delete').on('click', function () {
        return window.confirm(
            $.i18n('confirm-deletion', $(this).data('title'))
        );
    });
});
