grantmetrics = {};
grantmetrics.application = {};

/**
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */

/**
 * Sets up jQuery.i18n. This gets ran immediately on page load, before the DOM is ready.
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
 * This gets called when the DOM is ready, on every page. Add only things that should be done globally.
 */
$(function () {
    grantmetrics.application.setupPanelTooltips();
    grantmetrics.application.preventHighUtf8Strings();

    // Activate Bootstrap tooltips.
    $('[data-toggle="tooltip"]').tooltip();
});

/**
 * Add tooltips to panel toggle links.
 * See also views/macros/layout.html.twig and assets/css/application.scss
 */
grantmetrics.application.setupPanelTooltips = function () {
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
};

/**
 * Setup form handling for adding/removing arbitrary number of text fields.
 * This is used for adding/removing organizers to a program, wikis to an event, etc.
 * To make this work, you need a wrapper around the inputs with the class '.model__column' (e.g. '.event__categories').
 * Each individual row in the list should have a class like '.model-row' (e.g. '.category-row').
 * Finally, you need a template that can be copied to generate a new row. That should have the class
 *   '.column-row__template', such as '.category-row__template'. This template could be an existing, visible input.
 * @param {string} model Model name, either 'program' or 'event'.
 * @param {string} column Column name, either 'organizer' or 'wiki'.
 * @param {Function} [callback] Optional callback that is executed after the new row is added, passing in $newRow.
 */
grantmetrics.application.setupAddRemove = function (model, column, callback) {
    // Keep track of how many fields have been rendered.
    var columnPluralized = column.substr(-1) === 'y' ? column.replace(/y$/, 'ies') : column + 's',
        // Class name for the individual rows.
        rowClass = '.' + column + '-row',
        // Looks for e.g. '.event__categories .category-row'.
        rowCount = $('.' + model + '__' + columnPluralized + ' ' + rowClass).length;

    // Add listeners to existing Remove buttons on the form.
    $('.remove-' + column).on('click', function (e) {
        e.preventDefault();
        $(this).parents(rowClass).remove();
    });

    // Listener to add a row.
    $('.add-' + column).on('click', function (e) {
        e.preventDefault();

        // Clone the template row, correct CSS classes, then insert at the end of the container.
        var $newRow = $(rowClass + '__template').clone()
            .removeClass('hidden ' + column + '-row__template')
            .appendTo('.' + model + '__' + columnPluralized);

        // Go through all the inputs and update the indexing in the name and id attributes.
        $newRow.find('input').each(function (_index, el) {
            var name = $(el).prop('name'),
                id = $(el).prop('id');

            // Bump the index in the 'id' and 'name' attributes (rowCount should be one more than there is already).
            // This has the caveat that numbers cannot be in the name of the form, model, or column, but I don't think
            // we'd ever want to do that.
            $(el).prop('name', name.replace(/\[\d+]/, '[' + rowCount + ']'))
                .prop('id', id.replace(/_\d+/, '_' + rowCount));

            // Clear out existing value, unless the input is read-only (meaning we intentionally pre-supplied a value).
            if (!$(el).prop('readonly')) {
                $(el).val('');
            }
        });

        // Remove unwanted inner elements.
        $newRow.find('.invalid-input').remove();

        // Increment count so the next added row will have the correct index in the name and id attributes.
        rowCount++;

        // Add listener to remove the row.
        $newRow.find('.remove-' + column).on('click', function () {
            $newRow.remove();
        });

        // Setup autocompletion on the relevant input in the new row (must use a fresh selector).
        if ($newRow.find('.user-input, .category-input, .page-input').length) {
            // Re-init listeners for all inputs. Existing typeaheads are destroyed, but only when the input
            // has focus, so this shouldn't pose much of a performance overhead.
            grantmetrics.application.setupAutocompletion();
        }

        if (typeof callback === 'function') {
            callback($newRow);
        }
    });
};

/**
 * Setup autocompletion of user/category inputs, etc., if such input fields are present.
 * This method uses jQuery.one() to attach listeners, so it can be called as many times as needed without worry
 * of adding duplicate listeners or typeahead instances.
 *
 * One requirement is each input in the view must have a wrapper element with the CSS class '.type-row',
 * where 'type' is one of 'user', 'category' or 'page'.
 */
grantmetrics.application.setupAutocompletion = function () {
    // Loop through each type of input that needs autocompletion, and attach a typeahead instance to each.
    ['user', 'category', 'page'].forEach(function (type) {
        var $input = $('.' + type + '-input');

        $input.one('focus.autocompletion', function () {
            // Associated wiki input. This doesn't exist for user inputs (they always query meta.wikimedia).
            var $wikiInput = $(this).parents('.' + type + '-row').find('.wiki-input');

            // Destroy any existing typeahead instances, as a safeguard.
            if ($(this).data('typeahead')) {
                $(this).data('typeahead').destroy();
            }

            // Default domain (what's used for username autocompletion).
            var domain = 'meta.wikimedia';

            // Look for an associated wiki input.
            // @fixme AJAX calls are redundantly made for invalid wikis. These are silent errors, but still...
            if (['category', 'page'].includes(type)) {
                domain = $wikiInput.val();
                if (!domain) {
                    // Invalid wiki, don't attempt autocompletion.
                    return;
                }

                // Add a new autocompletion listener when the associated wiki input changes. This should be safe to do
                // here, because setupAutocompletion() should be called whenever new rows are created in the view.
                // The listener does do a 1-level recursion, but the .one() will ensure only one listener is added.
                $wikiInput.one('change.autocompletion', function () {
                    // Turn off the listener for the input, and re-add it.
                    $(this).off('focus.autocompletion');
                    grantmetrics.application.setupAutocompletion();
                }.bind(this));
            }

            setupTypeahead($(this), domain, type);

            // Needed because of https://github.com/bassjobsen/Bootstrap-3-Typeahead/issues/150
            $(this).trigger('focus');
        });
    });
};

/**
 * Attach a typeahead instance to the given input. This method is called from setupAutocompletion()
 * and intentionally private (Webpack ensures this isn't the global scope).
 * @param {jQuery} $input
 * @param {string} domain Such as en.wikipedia
 * @param {string} type Type of autocompletion. One of 'user', 'category' or 'page'.
 */
function setupTypeahead($input, domain, type)
{
    var apiParams = function (query) {
        return {
            'user': {
                list: 'allusers',
                aufrom: query
            },
            'category': {
                list: 'prefixsearch',
                pssearch: query,
                psnamespace: 14
            },
            'page': {
                list: 'prefixsearch',
                pssearch: query
            }
        };
    };

    // Defaults for typeahead options. preDispatch and preProcess will be
    // set accordingly for each typeahead instance.
    var typeaheadOpts = {
        url: 'https://' + domain + '.org/w/api.php',
        timeout: 200,
        triggerLength: 1,
        method: 'get'
    };

    $input.typeahead({
        ajax: Object.assign(typeaheadOpts, {
            preDispatch: function (query) {
                query = query.charAt(0).toUpperCase() + query.slice(1);

                return Object.assign({
                    action: 'query',
                    format: 'json',
                    origin: '*'
                }, apiParams(query)[type]);
            },
            preProcess: function (data) {
                return data.query[apiParams()[type].list].map(function (elem) {
                    var result = elem[type === 'user' ? 'name' : 'title'];

                    // Strip out namespace from result.
                    if (type === 'category' && result.includes(':')) {
                        result = result.match(/.*?:(.*)/)[1];
                    }

                    return result;
                });
            }
        })
    });
}

/**
 * Adds column sorting to table. To use, applicable <th> elements in the <thead> must follow this structure:
 *     <th>
 *         <div class="sort-link sort-link--name" data-column="name">
 *             {{ msg('name') }}
 *         </div>
 *     </th>
 * Then in the <tbody>, the applicable <td> elements should be structured like:
 *     <td class="sort-entry--name" data-value="{{ name }}">
 *         {{ name }}
 *     </td>
 * See participants/show.html.twig for an example.
 */
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
 * Makes an API request to the sitematrix API, returning a promise
 * that resolves with the shortened domain names of all the Wikipedias.
 * @return {Deferred}
 */
grantmetrics.application.populateValidWikis = function () {
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

        // Commons & Wikidata.
        validWikis.push('commons.wikimedia', 'www.wikidata');

        dfd.resolve(validWikis);
    });

    return dfd;
};

/**
 * Prevent the insertion of emojis etc.
 * See also TitleUserTrait::setTitle() for the same replacement being made server-side.
 * @fixme This does not get applied to inputs that are dynamically added (add/remove lists).
 */
grantmetrics.application.preventHighUtf8Strings = function () {
    function replaceHighUtf8Strings()
    {
        var newVal = $(this).val().replace(/[\u{010000}-\u{10FFFF}]/gu, String.fromCodePoint(0xFFFD));
        $(this).val(newVal);
    }
    $('input, textarea').on({ keydown: replaceHighUtf8Strings, change: replaceHighUtf8Strings });
};
