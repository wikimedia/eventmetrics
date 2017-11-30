(function () {
    // Load translations with 'en.json' as a fallback
    var messagesToLoad = {};

    /** global: i18nLang */
    /** global: i18nPath */
    messagesToLoad[i18nLang] = i18nPath;

    /** global: i18nEnPath */
    if (i18nLang !== 'en') {
        messagesToLoad.en = i18nEnPath;
    }

    $.i18n({
        locale: i18nLang
    }).load(messagesToLoad);
})();

/**
 * Setup form handling for adding/removing arbitrary number of text fields.
 * This is used for adding/removing organizers to a program, and wikis to an event.
 * @param  {string} model  Model name, either 'program' or 'event'.
 * @param  {string} column Column name, either 'organizer' or 'wiki'.
 */
function setupAddRemove(model, column)
{
    // Keep track of how many fields have been rendered.
    var rowCount = $('.' + model + '__' + column + 's .' + column + '-row').length;

    // Class name for the individual rows.
    var rowClass = '.' + column + '-row';

    // Add listeners to existing Remove buttons on the form.
    $('.remove-' + column).on('click', function (e) {
        e.preventDefault();
        $(this).parents(rowClass).remove();
    });

    // Listener to add a row.
    $('.add-' + column).on('click', function (e) {
        e.preventDefault();

        // Clone the template row and correct CSS classes.
        var $template = $(rowClass + '__template')
            .clone()
            .removeClass('hidden')
            .removeClass(column + '-row__template');

        // Insert after the last row.
        $(rowClass + ':last').after($template);

        var $newRow = $(rowClass + ':last');

        // Add name attribute to the input of the new row.
        $newRow.find('input').prop('name', 'form[' + column + 's][' + rowCount + ']')
            .prop('id', 'form_' + column + 's_' + rowCount);

        // Increment count so the next added row will have the correct name attribute.
        rowCount++;

        // Add listener to remove the row.
        $newRow.find('.remove-' + column).on('click', function () {
            $newRow.remove();
        });
    });
}
