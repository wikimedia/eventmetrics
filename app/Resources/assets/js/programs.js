$(function () {
    if ($('body').hasClass('program-new') || $('body').hasClass('program-edit')) {
        eventmetrics.application.setupAddRemove('program', 'organizer');
        eventmetrics.application.setupAutocompletion();
    }

    $('.program-action__delete, .event-action__delete').on('click', function (e) {
        if ($(this).hasClass('disabled')) {
            e.preventDefault();
            return window.alert($.i18n('error-' + $(this).data('model') + '-undeletable'));
        } else {
            return window.confirm(
                $.i18n('confirm-deletion', $(this).data('title'))
            );
        }
    });

    eventmetrics.application.setupAutocompletion();
    eventmetrics.application.setupColumnSorting();
});
