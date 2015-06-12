/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    local_elisprogram
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2013 Onwards Remote Learner.net Inc (http://www.remote-learner.net)
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

(function($) {

/**
 * DeepSight Filter Bar
 * Wrapper function to allow interaction between active filters and a filter generator - when filters are deleted they
 * are added to the generator.
 *
 * Required Options:
 *     object datatable       The datatable object this filterbar is for.
 *     object filters         An object of objects defining all available filters.
 *                                Member object params:
 *                                string type The type of filter - i.e. "searchselect", "textsearch", etc.
 *                                object opts The options object to pass to the filter.
 *                                                Note: datatable will be populated automatically, unless specifically overridden.
 * Optional Options:
 *     array starting_filters An array of keys from the opts.filters object defining which filters should be present
 *                            initially.
 *     boolean updatetable True if bulk table should be updated once filter bar is loaded.
 *
 * @param object options  Options object (See Options section above for description)
 */
$.fn.deepsight_filterbar = function(options) {
    this.default_opts = {
        datatable: null,
        filters: {},
        starting_filters: [],
        lang_add: 'Add',
        lang_addtitle: 'Add A Filter',
        updatetable: true,
    };
    var opts = $.extend({}, this.default_opts, options);

    var main = this;
    this.generator = null;

    /**
     * Get options.
     *
     * @return object Options for filter bar.
     */
    this.get_options = function() {
        return opts;
    };

    /**
     * Add tools button.
     */
    this.add_tools_button = function() {
        if (typeof main.tools === "undefined") {
            main.tools = $('<button id="'+opts.datatable.name+'_deepsight_searchtools" class="" title="'+opts.lang_search_toolstitle+'">'+opts.lang_search_tools+'</button>');
            main.tools.addClass('elisicon-configuration');
            main.tools.addClass('deepsight_filter_generator');
            main.tools.addClass('deepsight_dropdown_activator');
            main.searchesbar.append(main.tools);
        }

        main.tools.deepsight_tools_button({
            datatable: opts.datatable,
            lang_search_setdefault: opts.lang_search_setdefault,
            lang_search_clearsearch: opts.lang_search_clearsearch,
            lang_search_delete: opts.lang_search_delete,
            lang_search_form_saving: opts.lang_search_form_saving,
            lang_search_form_saved: opts.lang_search_form_saved,
            lang_search_deleted: opts.lang_search_deleted,
            lang_search_setdefault_saved: opts.lang_search_setdefault_saved,
            lang_search_form_name_error: opts.lang_search_form_name_error,
            lang_search_for: opts.lang_search_for,
            lang_search_delete_confirm: opts.lang_search_delete_confirm,
            filterbar: main
        });

        if (typeof opts.datatable.starting_searches !== 'undefined' && opts.datatable.starting_searches.length < 1) {
            main.tools.hide();
            main.searchestitle.hide();
        } else {
            main.tools.show();
            main.searchestitle.show();
        }
    };

    /**
     * Add load search button.
     */
    this.add_loadsearch_button = function() {
        var activate = false;
        if (opts.datatable.can_load_searches) {
            if (typeof (main.loadsearch) === "undefined") {
                main.loadsearch = $('<button id="'+opts.datatable.name+'_deepsight_searchload"  title="'+opts.lang_search_loadtitle+'">'+opts.lang_search_load+'</button>');
                main.loadsearch.addClass('elisicon-sortable');
                main.loadsearch.addClass('deepsight_filter_generator');
                main.loadsearch.addClass('deepsight_dropdown_activator');
                main.searchesbar.append(main.loadsearch);
            }

            if ($('#'+opts.datatable.name+'_deepsight_searchload[class*="active"]').length) {
                activate = true;
            }

            main.loadsearch.deepsight_loadsearch_button({
                datatable: opts.datatable,
                lang_search_for: opts.lang_search_for,
                lang_search: opts.lang_search,
                lang_search_noresults: opts.lang_search_noresults,
                filterbar: main
            });

            if (opts.datatable.starting_searches.length < 1) {
                main.loadsearch.hide();
            } else {
                main.loadsearch.show();
            }

            if (activate) {
                $.deactivate_all_filters();
                $('#'+opts.datatable.name+'_deepsight_searchload').mousedown();
                $('#'+opts.datatable.name+'_deepsight_searchload').click();
            }
        }
    };

    /**
     * Add save search button.
     */
    this.add_savesearch_button = function() {
        if (opts.datatable.can_load_searches && opts.datatable.can_save_searches) {
            if (typeof (main.searchsave) === 'undefined') {
                var button = '<button id="'+opts.datatable.name+'_deepsight_searchsave" ';
                button += opts.lang_search_savetitle+'">'+opts.lang_search_save+'</button>';
                main.searchsave = $(button);
                main.searchsave.addClass('elisicon-more');
                main.searchsave.addClass('deepsight_filter_generator');
                main.searchsave.addClass('deepsight_dropdown_activator');
                main.searchesbar.append(main.searchsave);
            }

            main.searchsave.deepsight_savesearch_button({
                datatable: opts.datatable,
                lang_search_form_name: opts.lang_search_form_name,
                lang_search_form_default: opts.lang_search_form_default,
                lang_search_form_save: opts.lang_search_form_save,
                lang_search_form_save_copy: opts.lang_search_form_save_copy,
                lang_search_form_save_title: opts.lang_search_form_save_title,
                lang_search_form_saving: opts.lang_search_form_saving,
                lang_search_form_saved: opts.lang_search_form_saved,
                lang_search_form_name_error: opts.lang_search_form_name_error,
                filterbar: main
            });
        }
    };

    /**
     * Load filters into filter bar and create add button.
     *
     * @param boolean cleardropdowns Optional param if set to true will not hide drop downs after rendering
     */
    this.loadfilters = function(cleardropdowns) {
        var available = {}, loadfilters = [];

        if (typeof cleardropdowns === "undefined") {
            cleardropdowns = true;
        }

        main.html('');
        var filtergenid = opts.datatable.name+'filter_generator_'+Math.random().toString(36).substring(5); // ELIS-9178.
        if (main.generator === null) {
            main.generator = $('<button id="'+filtergenid+'" class="elisicon-more" title="'+opts.lang_addtitle+'">'+opts.lang_add+'</button>');
        }

        if (typeof opts.datatable.current_search !== "undefined" &&
            typeof opts.datatable.current_search.data !== "undefined") {
            for (var key in opts.datatable.current_search.data) {
                loadfilters.push(key);
            }
        } else {
            for (var key in opts.starting_filters) {
                loadfilters.push(key);
            }
        }

        for (var i in opts.filters) {
            if ($.inArray(i, loadfilters) >= 0) {
                // This is a starting filter.
                var filter = $('<span></span>').prop('id', 'filter_'+opts.filters[i].opts.name);
                main.append(filter);

                var default_filter_opts = {
                    datatable: opts.datatable,
                    filterbar: main
                };
                if (typeof(opts.starting_filters[i]) != 'undefined') {
                    default_filter_opts.initialvalue = opts.starting_filters[i];
                }
                var filter_opts = $.extend({}, default_filter_opts, opts.filters[i].opts);
                var filterfunc = 'deepsight_filter_'+opts.filters[i].type;
                if (typeof opts.datatable.current_search !== "undefined" &&
                    typeof opts.datatable.current_search.data !== "undefined" &&
                    typeof opts.datatable.current_search.data[i] !== "undefined") {
                    filter_opts.initialvalue = opts.datatable.current_search.data[i];
                }

                // Create filter.
                filter = filter[filterfunc](filter_opts);
                if (typeof(filter.removebutton) != 'undefined') {
                    filter.removebutton.click(opts.filters[i], function(e) {
                        main.generator.available_filters[e.data.opts.name] = e.data;
                        main.generator.render_available_filters();
                    });
                }
                filter.register_with_datatable();
            } else {
                // this is a filter to add to the "add more" dropdown
                available[opts.filters[i].opts.name] = opts.filters[i];
            }
        }

        main.append(main.generator);
        main.generator.deepsight_filter_generator({
            available_filters: available,
            datatable: opts.datatable,
        });
        main.generator.render_available_filters(cleardropdowns);
    };

    /**
     * Validate search to save.
     *
     * @return boolean true if search is valid.
     */
    this.validatesearch = function() {
        if (typeof opts.datatable.current_search === "undefined") {
            opts.datatable.current_search = {};
        }

        if (typeof (opts.datatable.current_search.cansave) == "undefined") {
            opts.datatable.current_search.cansave = opts.datatable.can_save_searches;
        }

        if (opts.datatable.current_search.id !== "undefined") {
            if (!opts.datatable.current_search.cansave) {
                // Save a copy of the search to the current page context id.
                if (opts.datatable.current_search.contextid != opts.datatable.contextid) {
                    // Saving a copy so delete the id and a insert will take place.
                    opts.datatable.current_search = { cansave: true };
                }
                opts.datatable.current_search.contextid = opts.datatable.contextid;
            }
        }

        opts.datatable.current_search.name = $('#'+opts.datatable.name+'_deepsight_search_name').val();
        opts.datatable.current_search.isdefault = $('#'+opts.datatable.name+'_deepsight_search_default:checked').val() ? true : false;
        if (typeof opts.datatable.current_search.contextid == "undefined") {
            opts.datatable.current_search.contextid = opts.datatable.contextid;
        }

        // Is is required for search to have a name.
        if (opts.datatable.current_search.name === '') {
            return false;
        }

        return true;
    };

    /**
     * Initializer.
     *
     * Performs the following actions:
     *     - Either renders filters or adds them to the generator, depending on whether the keys in starting_filters.
     *     - Adds a filter generator containing all filters not defined to be starting filters.
     */
    this.initialize = function() {
        var cleanform = [
                opts.datatable.name+'_filter_generator',
                opts.datatable.name+'_deepsight_searchload',
                opts.datatable.name+'_deepsight_searchsave',
                opts.datatable.name+'_deepsight_searchtools'
        ];
        $.remove_dynamic_elements(cleanform);
        main.loadfilters();

        // Empty searches bar and title.
        main.searchesbar = $('#'+opts.datatable.name+'_searchesbar');
        main.searchesbar.html('');
        main.searchestitle = $('#'+opts.datatable.name+'_searchestitle');
        main.searchestitle.html('');

        // Add buttons to search bar.
        main.add_loadsearch_button();
        main.add_savesearch_button();
        main.add_tools_button();

        if (opts.updatetable) {
            opts.datatable.doupdatetable();
        }
    };

    main.initialize();
    return main;
}

})(jQuery);
