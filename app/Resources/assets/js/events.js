$(function () {
    // Only run on event pages.
    if (!$('body').hasClass('event')) {
        return;
    }

    // Setup the add/remove wiki fields when creating or editing a new event.
    if ($('body').hasClass('event-new') || $('body').hasClass('event-edit') || $('body').hasClass('event-copy')) {
        setupAddRemove('event', 'wiki');
    }

    // Add/remove participants hooks for when viewing an event.
    if ($('body').hasClass('event-show')) {
        setupAddRemove('event', 'participant');
    }

    var startDate = moment($('#form_start').val()).utc(),
        endDate = moment($('#form_end').val()).utc();

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
    });

    // Attempt to default the timezone to the user's timezone.
    if ($('body').hasClass('event-new')) {
        var timezome = 'UTC';
        try {
            timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if ($("#form_timezone option[value='" + timezone + "']").length) {
                $('#form_timezone').val(timezone);
            }
        } catch (_error) {
        }
    }

    /**
     * Populate hidden start/end datetime fields on form submission.
     */
    $('#event_form').on('submit', function () {
        var rangeData = $('#form_time').data().daterangepicker;
        $('#form_start').val(rangeData.startDate.format('YYYY-MM-DDTHH:mm:00-00:00'));
        $('#form_end').val(rangeData.endDate.format('YYYY-MM-DDTHH:mm:00-00:00'));
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
     * Listener for special wiki options.
     */
    $('.special-wiki').on('click', function (e) {
        e.preventDefault();

        var $lastWiki = $('.event__wikis').find('input').last();

        if ($lastWiki.val().trim() !== '') {
            $('.add-wiki').trigger('click');
            $lastWiki = $('.event__wikis').find('input').last();
        }

        $lastWiki.val($(e.target).data('value'));
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

    setupAutocompletion();
    setupColumnSorting();

    $('[data-toggle="tooltip"]').tooltip();
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

        // 'All Wikipedias' option
        validWikis.push('*.wikipedia');

        // Commons.
        validWikis.push('commons.wikimedia');

        dfd.resolve(validWikis);
    });

    return dfd;
}
