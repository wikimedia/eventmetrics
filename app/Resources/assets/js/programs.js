$(function () {
    if ($('body').hasClass('program-new')) {
        setupOrganizerForm();
    }
});

function setupOrganizerForm()
{
    $('.add-organizer').on('click', function (e) {
        e.preventDefault();

        var $orgRow = $('.organizer-row__template')
            .clone()
            .removeClass('hidden')
            .removeClass('organizer-row__template');

        // $orgRow.insertAfter('.organizer-row:last');
        $('.organizer-row:last').after($orgRow);

        var $newRow = $('.organizer-row:last');
        $newRow.find('.remove-organizer').on('click', function () {
            $newRow.remove();
        });
    });
}
