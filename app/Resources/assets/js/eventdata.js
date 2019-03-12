$(function () {
    // Only run on event pages.
    if (!$('body').hasClass('eventdata')) {
        return;
    }

    $('.event-metric-desc').tooltip();

    eventmetrics.eventshow.setupCalculateStats();
    eventmetrics.eventshow.setupReportModal();
});
