grantmetrics = {};

grantmetrics.application = {};

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
 * Add tooltips to panel toggle links.
 * See also views/macros/layout.html.twig and assets/css/application.scss
 */
$(function () {
    // Panels default to expanded, so we add the collapse message.
    $('.panel .panel-title a').attr('title', $.i18n('hide-section'));
    // Toggle when clicked.
    $('.panel')
        .on("hidden.bs.collapse", function (event) {
            $(this).find('.panel-title a').attr('title', $.i18n('show-section'));
        })
        .on("shown.bs.collapse", function (event) {
            $(this).find('.panel-title a').attr('title', $.i18n('hide-section'));
        });
});

/**
 * Setup form handling for adding/removing arbitrary number of text fields.
 * This is used for adding/removing organizers to a program, wikis to an event, etc.
 * @param {string} model Model name, either 'program' or 'event'.
 * @param {string} column Column name, either 'organizer' or 'wiki'.
 */
grantmetrics.application.setupAddRemove = function (model, column) {
    // Keep track of how many fields have been rendered.
    var columnPluralized = column.substr(-1) === 'y' ? column.replace(/y$/, 'ies') : column + 's',
        rowCount = $('.' + model + '__' + columnPluralized + ' .' + column + '-row').length;

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

        // Clone the template row, correct CSS classes, then insert after the last row.
        var $newRow = $(rowClass + '__template').clone()
            .removeClass('hidden ' + column + '-row__template')
            .insertAfter(rowClass + ':last');

        // Go through all the inputs and update the indexing in the name and id attributes.
        $newRow.find('input').each(function (_index, el) {
            var name = $(el).prop('name'),
                id = $(el).prop('id');

            // Bump the index in the 'id' and 'name' attributes (rowCount should be one more than there is already).
            // This has the caveat that numbers cannot be in the name of the form, model, or column, but I don't think
            // we'd ever want to do that.
            $(el).prop('name', name.replace(/\[\d+]/, '[' + rowCount + ']'))
                .prop('id', id.replace(/_\d+/, '_' + rowCount))
                // Clear out existing value.
                .val('');
        });

        // Remove unwanted inner elements.
        $newRow.find('.invalid-input').remove();

        // Increment count so the next added row will have the correct index in the name and id attributes.
        rowCount++;

        // Add listener to remove the row.
        $newRow.find('.remove-' + column).on('click', function () {
            $newRow.remove();
        });

        // Setup autocompletion on the new row (must use a fresh selector).
        if ($newRow.find('input').hasClass('user-input')) {
            grantmetrics.application.setupAutocompletion($(rowClass + ':last').find('input'));
        }
    });
};

/**
 * Setup autocompletion of pages if a page input field is present.
 */
grantmetrics.application.setupAutocompletion = function ($userInput) {
    if ($userInput === undefined) {
        $userInput = $('.user-input');
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
};

grantmetrics.application.setupColumnSorting = function () {
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
};

/**
 * Prevent the insertion of emojis etc.
 * See also TitleUserTrait::setTitle() for the same replacement being made server-side.
 */
$(function () {
    function replaceHighUtf8Strings()
    {
        var newVal = $(this).val().replace(/[\u{010000}-\u{10FFFF}]/gu, String.fromCodePoint(0xFFFD));
        $(this).val(newVal);
    }
    $('input, textarea').on({ keydown: replaceHighUtf8Strings, change: replaceHighUtf8Strings });
});
