eventmetrics.eventshow = {};
eventmetrics.eventshow.jobPoller = null;

$(function () {
    // Only run on event page.
    if (!$('body').hasClass('event-show')) {
        return;
    }

    eventmetrics.application.setupAddRemove('event', 'participant');
    eventmetrics.application.setupAddRemove('event', 'category', function ($newRow) {
        // Fill in the wiki with the last valid one.
        var lastWiki = $('.event__categories .wiki-input').eq(-2).val();

        if (lastWiki) {
            $newRow.find('.wiki-input').val(lastWiki);
            $newRow.find('.category-input').focus();
        } else {
            $newRow.find('.wiki-input').focus();
        }
    });
    eventmetrics.application.setupAutocompletion();

    // The event page contains multiple forms. Here we jump to the one with errors,
    // if any, since the user may otherwise not see it.
    var erroneousForm = $('.has-error').parents('.panel').get(0);
    if (erroneousForm) {
        erroneousForm.scrollIntoView();
    }

    // Setup column sorting for stats.
    eventmetrics.application.setupColumnSorting();

    eventmetrics.eventshow.setupWikiInputs();
    eventmetrics.eventshow.setupCalculateStats();
});

/**
 * Listener for calculate statistics button, which hits the process event endpoint, firing off a job.
 */
eventmetrics.eventshow.setupCalculateStats = function () {
    $('.event-process-btn').on('click', function () {
        document.activeElement.blur();

        $('.event-export-btn').addClass('disabled');
        $(this).addClass('disabled').text($.i18n('queued'));
        $('.event-stats-status').text($.i18n('queued-desc'));

        $.get(baseUrl + 'events/process/' + $(this).data('event-id'));

        eventmetrics.eventshow.pollJob($(this).data('event-id'));
    });

    // Link to process event in message shown when stats have not yet been generated.
    $('.event-process-link').on('click', function (e) {
        $('.event-process-btn').trigger('click');
        $('.event-wiki-stats--empty').html('&nbsp;');
        e.preventDefault();
    });
};

/**
 * Continually poll the server to get the status of the Job associated with the given Event.
 * @param {Number} eventId
 */
eventmetrics.eventshow.pollJob = function (eventId) {
    // If for some reason this gets called more than once, this will clear out the old poller.
    clearInterval(eventmetrics.eventshow.jobPoller);

    // Poll the server every 3 seconds, updating the view accordingly.
    eventmetrics.eventshow.jobPoller = setInterval(function () {
        $.get('/events/job-status/' + eventId).done(function (response) {
            if ('running' === response.status) {
                $(this).addClass('disabled').text($.i18n('updating'));
                $('.event-stats-status').text($.i18n('updating-desc'));
            } else if ('complete' === response.status) {
                $(this).removeClass('disabled').text($.i18n('update-data'));
                $('.event-export-btn').removeClass('disabled');
                $('.event-stats-status').text('');

                // TODO: Have controller update view with rendered Twig template, rather than having to refresh.
                window.location.reload(true);
            }
        });
    }, 3000);
};

/**
 * Attach typeaheads to the wiki inputs. These will only autocomplete to wikis configured on the Event.
 */
eventmetrics.eventshow.setupWikiInputs = function () {
    eventmetrics.application.populateValidWikis().then(function (validWikis) {
        $('.event__categories').on('focus', '.wiki-input', function () {
            if ($(this).data().typeahead) {
                return;
            }

            $(this).typeahead({
                source: validWikis.filter(function (wiki) {
                    return eventmetrics.eventshow.availableWikiPattern.test(wiki);
                })
            });
        });
    });
};
