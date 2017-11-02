$(function () {
    if ($('body').hasClass('program-new')) {
        setupOrganizerForm();
    }
});

function setupOrganizerForm()
{
    // keep track of how many email fields have been rendered
    var organizerCount = $('.program__organizers .organizer-row').length;

    $('.add-organizer').on('click', function (e) {
        e.preventDefault();

        // Clone the row and correct CSS classes.
        var $orgRow = $('.organizer-row__template')
            .clone()
            .removeClass('hidden')
            .removeClass('organizer-row__template');

        // Insert after the last row.
        $('.organizer-row:last').after($orgRow);

        var $newRow = $('.organizer-row:last');

        // Add name attribute to the input of the new row.
        $newRow.find('input').prop('name', 'form[organizerNames][' + organizerCount + ']');

        // Increment count so the next added row will have the correct name attribute.
        organizerCount++;

        // Add listener to remove the row.
        $newRow.find('.remove-organizer').on('click', function () {
            $newRow.remove();
        });
    });
}
