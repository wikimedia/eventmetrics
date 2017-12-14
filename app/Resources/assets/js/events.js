$(function () {
    // Only run on event pages.
    if (!$('body').hasClass('event')) {
        return;
    }

    // Setup the add/remove wiki fields when creating or editing a new event.
    if ($('body').hasClass('event-new') || $('body').hasClass('event-edit')) {
        setupAddRemove('event', 'wiki');
    }

    // Add/remove participants hooks for when viewing an event.
    if ($('body').hasClass('event-show')) {
        setupAddRemove('event', 'participant');
    }

    $('.event-action__delete').on('click', function () {
        return window.confirm(
            $.i18n('confirm-deletion', $(this).data('title'))
        );
    });
});
