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

    var startDate = moment($('#form_start').val()),
        endDate = moment($('#form_end').val());

    // Set defaults if invalid or blank -- next week.
    startDate = startDate.isValid() ? startDate : moment().add(7, 'days').startOf('week');
    endDate = endDate.isValid() ? endDate : moment().add(7, 'days').endOf('week');

    $('#form_time').daterangepicker({
        timePicker: true,
        timePicker24Hour: is24HourFormat(),
        startDate: startDate,
        endDate: endDate,
        locale: {
            format: getLocaleDatePattern() + ' ' + getLocaleTimePattern(),
            applyLabel: $.i18n('apply'),
            cancelLabel: $.i18n('cancel'),
            customRangeLabel: $.i18n('custom-range'),
            daysOfWeek: getWeekdayNames(),
            monthNames: getMonthNames()
        }
    }, function (start, end, label) {
        // Populate hidden fields with ISO-8601 format.
        $('#form_start').val(start.format('YYYY-MM-DDTHH:mm:00Z'));
        $('#form_end').val(end.format('YYYY-MM-DDTHH:mm:00Z'));
    });

    populateValidWikis().then(function (validWikis) {
        $('.event__wikis').on('focus', '.event-wiki-input', function () {
            if ($(this).data().typeahead) {
                return;
            }

            $(this).typeahead({
                source: validWikis
            });
        });
    });

    /**
     * Listener for calculate statistics button, which hits the
     * process event endpoint, firing off a job.
     */
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
        }.bind(this));
    });
});

/**
 * Makes an API request to the sitematrix API, returning a promise
 * that resolves with the shortened domain names of all the Wikipedias.
 * @return {Deferred}
 */
function populateValidWikis()
{
    var dfd = $.Deferred();

    $.ajax({
        url: 'https://meta.wikimedia.org/w/api.php?action=sitematrix&' +
            'formatversion=2&smsiteprop=url&smlangprop=site&format=json',
        dataType: 'jsonp'
    }).then(function (ret) {
        delete ret.sitematrix.count;
        var validWikis = [];

        for (var lang in ret.sitematrix) {
            var family = ret.sitematrix[lang];
            if (!family.site) {
                continue;
            }

            family.site.forEach(function (site) {
                if (!site.closed && site.url.indexOf('.wikipedia.org') !== -1) {
                    validWikis.push(
                        site.url.replace(/\.org$/, '').replace(/^https?:\/\//, '')
                    );
                }
            })
        }

        dfd.resolve(validWikis);
    });

    return dfd;
}
