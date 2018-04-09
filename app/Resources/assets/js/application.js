/**
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */

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
        var $template = $("<div class='form-group " + column + "-row'>" +
            $(rowClass + '__template').html() +
            "</div>");

        // Insert after the last row.
        $(rowClass + ':last').after($template);

        var $newRow = $(rowClass + ':last');

        // Add name attribute to the input of the new row and remove unwanted inner elements.
        $newRow.find('input').prop('name', 'form[' + column + 's][' + rowCount + ']')
            .prop('id', 'form_' + column + 's_' + rowCount)
            .val('');
        $newRow.find('.invalid-input').remove();

        // Increment count so the next added row will have the correct name attribute.
        rowCount++;

        // Add listener to remove the row.
        $newRow.find('.remove-' + column).on('click', function () {
            $newRow.remove();
        });

        // Setup autocompletion on the new row (must use a fresh selector).
        if ($newRow.hasClass('user-input')) {
            setupAutocompletion($(rowClass + ':last').find('input'));
        }
    });
}

/**
 * Setup autocompletion of pages if a page input field is present.
 */
function setupAutocompletion($userInput)
{
    if ($userInput === undefined) {
        var $userInput = $('.user-input');
    }

    // Make sure typeahead-compatible fields are present.
    if (!$userInput[0]) {
        return;
    }

    // Initialize only on focus, since there can be a ton of usernames.
    $userInput.one('focus', function () {
        // Destroy any existing instances.
        if ($(this).data('typeahead')) {
            $(this).data('typeahead').destroy();
        }

        // Defaults for typeahead options. preDispatch and preProcess will be
        // set accordingly for each typeahead instance.
        var typeaheadOpts = {
            url: 'https://meta.wikimedia.org/w/api.php',
            timeout: 200,
            triggerLength: 1,
            method: 'get'
        };

        $userInput.typeahead({
            ajax: Object.assign(typeaheadOpts, {
                preDispatch: function (query) {
                    query = query.charAt(0).toUpperCase() + query.slice(1);
                    return {
                        action: 'query',
                        list: 'allusers',
                        format: 'json',
                        aufrom: query,
                        origin: '*'
                    };
                },
                preProcess: function (data) {
                    return data.query.allusers.map(function (elem) {
                        return elem.name;
                    });
                }
            })
        });

        // Needed because of https://github.com/bassjobsen/Bootstrap-3-Typeahead/issues/150
        $(this).trigger('focus');
    });
}

function setupColumnSorting()
{
    var sortDirection, sortColumn;

    $('.sort-link').on('click', function () {
        sortDirection = sortColumn === $(this).data('column') ? -sortDirection : 1;

        $('.sort-link').removeClass('sort-link--asc sort-link--desc');
        $(this).addClass(sortDirection === 1 ? 'sort-link--asc' : 'sort-link--desc');

        sortColumn = $(this).data('column');
        var $table = $(this).parents('table');
        var entries = $table.find('.sort-entry--' + sortColumn).parent();

        if (!entries.length) {
            return;
        }

        entries.sort(function (a, b) {
            var before = $(a).find('.sort-entry--' + sortColumn).data('value'),
                after = $(b).find('.sort-entry--' + sortColumn).data('value');

            // test data type, assumed to be string if can't be parsed as float
            if (!isNaN(parseFloat(before, 10))) {
                before = parseFloat(before, 10);
                after = parseFloat(after, 10);
            }

            if (before < after) {
                return sortDirection;
            } else if (before > after) {
                return -sortDirection;
            } else {
                return 0;
            }
        });

        $table.find('tbody').html($(entries));
    });
}
