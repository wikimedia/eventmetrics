$(function () {
    // Only run on event pages.
    if (!$('body').hasClass('event')) {
        return;
    }

    // Setup the add/remove wiki fields when creating or editing a new event.
    if ($('body').hasClass('event-new') || $('body').hasClass('event-edit')) {
        setupAddRemove('event', 'wiki');
    }

    $('.event-action__delete').on('click', function () {
        return window.confirm(
            $.i18n('confirm-deletion', $(this).data('title'))
        );
    });

    // Add listeners.
    $('#form_enableTime').on('change', function () {
        $('.event__time').toggleClass('disabled', !$(this).is(':checked'));
    });

    // Trigger change to set initial state, if enableTime was already set on page load.
    $('#form_enableTime').trigger('change');
});
