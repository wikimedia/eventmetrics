eventmetrics.eventshow = {};
eventmetrics.eventshow.jobPoller = null;
eventmetrics.eventshow.state = 'initial';

$(function () {
    // Only run on event page.
    if (!$('body').hasClass('event-show')) {
        return;
    }

    eventmetrics.application.setupAddRemove('event', 'participant');
    eventmetrics.application.setupAddRemove('event', 'category', function ($newRow) {
        // Fill in the wiki with the last valid one.
        const lastWiki = $('.event__categories .wiki-input').eq(-2).val();

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
    const erroneousForm = $('.has-error').parents('.panel').get(0);
    if (erroneousForm) {
        erroneousForm.scrollIntoView();
    }

    // Setup column sorting for stats.
    eventmetrics.application.setupColumnSorting();

    eventmetrics.eventshow.setupWikiInputs();
    eventmetrics.eventshow.setupCalculateStats();
    eventmetrics.eventshow.setupReportModal();
});

/**
 * Listener for calculate statistics button, which hits the process event endpoint, firing off a job.
 */
eventmetrics.eventshow.setupCalculateStats = function () {
    $('.event-process-btn').on('click', function () {
        document.activeElement.blur();

        $.post(baseUrl + 'events/process/' + $(this).data('event-id'));

        // Set the state to 'started', which shows the modal, and show/hides messages accordingly.
        eventmetrics.eventshow.setState('started');

        // Continually check the progress of the job and update the view accordingly.
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
 * Set the state of the event. This will update the view accordingly, including messages.
 * @param {String} state
 */
eventmetrics.eventshow.setState = function (state) {
    if (state === eventmetrics.eventshow.state) {
        // Nothing to do.
        return;
    }

    // Keep track of the state.
    eventmetrics.eventshow.state = state;

    if ('complete' === state) {
        // For display purposes, complete is the same as initial (though the page will be refreshed anyway).
        state = 'initial';
    }

    // Show/hide messages accordingly.
    $('.event-state--initial, .event-state--started, .event-state--failed-timeout, .event-state--failed-unknown').hide();
    $('.event-state--' + state).show();

    // Disable the form/buttons/etc. accordingly.
    $('body').toggleClass('disabled-state', ['queued', 'started'].includes(state));

    // Show the modal if needed.
    if (['failed-timeout', 'failed-unknown'].includes(state)) {
        $('#progress_modal').modal('show').on('hide.bs.modal', function () {
            // Delete the job now that they've seen the message.
            $.ajax({
                url: baseUrl + 'events/delete-job/' + $('.event-process-btn').data('event-id'),
                type: 'DELETE',
            });
        });
    }
};

/**
 * Continually poll the server to get the status of the Job associated with the given Event.
 * @param {Number} eventId
 */
eventmetrics.eventshow.pollJob = function (eventId) {
    // If for some reason this gets called more than once, this will clear out the old poller.
    clearInterval(eventmetrics.eventshow.jobPoller);

    const pollFunc = function () {
        $.get('/events/job-status/' + eventId).done(function (response) {
            if (response.status.includes('failed')) {
                clearInterval(eventmetrics.eventshow.jobPoller);
            } else if ('complete' === response.status) {
                return window.location.reload(true);
            }
            eventmetrics.eventshow.setState(response.status);
        });
    };

    // First execute immediately.
    pollFunc();

    // Poll the server every 3 seconds, updating the view accordingly.
    eventmetrics.eventshow.jobPoller = setInterval(pollFunc, 3000);
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

/**
 * Model for downloading reports.
 * The title and links of the model are modified when the user selects a report from the menu.
 */
eventmetrics.eventshow.setupReportModal = function () {
    $('#download-modal').on('show.bs.modal', function (event) {
        var modal = $(this),
            reportLink = $(event.relatedTarget);
        modal.find('.modal-title').text(reportLink.data('modal-title'));
        modal.find('.wikitext-link').attr('href', reportLink.data('wikitext-link'));
        modal.find('.csv-link').attr('href', reportLink.data('csv-link'));
        // Also append the event status, duplicated from the underlying page.
        modal.find('.event-status').html($('.event-stats-status').html());
    })
};
