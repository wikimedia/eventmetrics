$(function () {
    // Only run on event pages.
    if (!$('body').hasClass('eventdata')) {
        return;
    }

    eventmetrics.eventshow.setupCalculateStats();
    eventmetrics.eventshow.setupReportModal();
});
