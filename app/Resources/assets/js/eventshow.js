grantmetrics.eventshow = {};

$(function () {
    // Only run on event page.
    if (!$('body').hasClass('event-show')) {
        return;
    }

    grantmetrics.application.setupAddRemove('event', 'participant');
    grantmetrics.application.setupAddRemove('event', 'category');
    grantmetrics.application.setupAutocompletion();

    // The event page contains multiple forms. Here we jump to the one with errors,
    // if any, since the user may otherwise not see it.
    var erroneousForm = $('.has-error').parents('.panel').get(0);
    if (erroneousForm) {
        erroneousForm.scrollIntoView();
    }

    // Setup column sorting for stats.
    grantmetrics.application.setupColumnSorting();

    grantmetrics.eventshow.setupCalculateStats();
});

/**
 * Listener for calculate statistics button, which hits the
 * process event endpoint, firing off a job.
 */
grantmetrics.eventshow.setupCalculateStats = function () {
    $('.event-process-btn').on('click', function () {
        document.activeElement.blur();

        $(this).addClass('disabled').text($.i18n('updating'));
        $('.event-export-btn').addClass('disabled');
        $('.event-stats-status').text($.i18n('updating-desc'));

        $.get(baseUrl + 'events/process/' + $(this).data('event-id')).done(function (data) {
            $(this).removeClass('disabled').text($.i18n('update-data'));
            $('.event-export-btn').removeClass('disabled');
            $('.event-stats-status').text('');

            // TODO: Make controller return HTML and update view with
            // rendered Twig template, rather than having to refresh.
            window.location.reload(true);
        }.bind(this)).fail(function (data) {
            $('.event-process-btn').text($.i18n('error-failed'))
                .addClass('btn-danger');
            var feedbackLink = "<a target='_blank' href='https://meta.wikimedia.org/wiki/Talk:Grant_Metrics'>" +
                'meta:Talk:Grant Metrics</a>';
            $('.event-stats-status').html(
                "<strong class='text-danger'>" +
                $.i18n('error-internal', feedbackLink) +
                '</strong>'
            );
        });
    });

    // Link to process event in message shown when stats have not yet been generated.
    $('.event-process-link').on('click', function (e) {
        $('.event-process-btn').trigger('click');
        $('.event-wiki-stats--empty').html('&nbsp;');
        e.preventDefault();
    });
};
