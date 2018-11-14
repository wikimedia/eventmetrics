eventmetrics.eventshow = {};

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
 * Listener for calculate statistics button, which hits the
 * process event endpoint, firing off a job.
 */
eventmetrics.eventshow.setupCalculateStats = function () {
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
            var feedbackLink = "<a target='_blank' href='https://meta.wikimedia.org/wiki/Talk:Event_Metrics'>" +
                'meta:Talk:Event Metrics</a>';
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
