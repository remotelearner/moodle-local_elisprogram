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
 * @copyright  (C) 2015 onwards Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * DeepSight load search button
 * Generates additional filters on-demand
 *
 * Usage:
 *     $('#element').deepsight_loadsearch_button();
 *
 * Required Options:
 *     object datatable          The deepsight datatable to link filters to. This will be added to the options of newly
 *                               created filters if they do not override it themselves - but it's not recommended that
 *                               they to do that
 *     array available_filters   An array of objects defining the filters that can be added.
 *                                   Object params:
 *                                       string label The text listed in the dropdown, and what will appear on the filter button
 *                                       string type  The type of filter (i.e. "searchselect")
 *                                       object opts  The option object passed to the filter.
 *     object filterbar          The filter bar containing the button.
 * Optional Options:
 *     string css_class          A CSS class to add to the button. Not recommended to change this.
 *     string css_dropdown_class A CSS class to add to the dropdown. Not recommended to change this.
 *     string css_choice_class   A CSS class to add to each option in the dropdown.
 *
 * Note: All elements this is run on must have an "id" attribute.
 *
 * @param object options Options for the class.
 */

(function ($) {

    $.fn.deepsight_loadsearch_button = function (options) {
        this.default_opts = {
            datatable: null,
            available_filters: {},
            filterbar: {},
            css_dropdown_class: 'deepsight_filter_generator_dropdown',
            css_choice_class: 'deepsight_filter_generator_choice',
        };

        var opts = $.extend({}, this.default_opts, options);
        var main = this;
        this.available_filters = opts.available_filters;

        // The last ajax request made.
        this.last_req = null;
        this.searchval = '';

        /**
         * Add an option to the list of available saved searches.
         *
         * @param object object Object contain name of the search.
         * @param string name  The name of the search.
         */
        this.add_option = function (object, cssoverride) {
            var id = main.prop('id')+'_choice_savedsearch_'+object.id, choice, temp;
            temp = $('#'+main.prop('id')+'_choice_savedsearch_'+object.id);
            if (temp.length) {
                temp.remove();
            }
            choice = $('<div class="deepsight_search_choice"></div>').prop('id', id);
            if (typeof cssoverride !== "undefined") {
                choice.addClass(cssoverride);
            } else {
                choice.addClass(opts.css_choice_class);
            }

            choice.html(object.name);

            choice.click(function (e) {
                opts.datatable.loadsearchbyobject(object);
                opts.filterbar.add_loadsearch_button();
                opts.filterbar.add_savesearch_button();
                opts.filterbar.add_tools_button();
                opts.filterbar.loadfilters();
                if (object.name !== '') {
                    $('#'+opts.datatable.name+'_searchestitle').html('<b>'+opts.lang_search_for+'</b>: '+object.name).show();
                }
                opts.datatable.updatetable();
                $.deactivate_all_filters();
            });

            main.dropdown.append(choice);
        };

        /**
         * Reposition the dropdown when a filter is added.
         */
        this.reposition_dropdown = function () {
            var offset = main.offset();
            main.dropdown.offset({
                left: offset.left,
                top: offset.top + main.outerHeight() - 1
            });
        };

        /**
         * Initializes a search for potential filterable values.
         *
         * This sets a timer to actually perform the search 250ms from now and cancels any existing timers
         * This is to prevent multiple ajax requests from firing when someone is typing.
         *
         * @param string val The value to search for.
         */
        this.search = function (val) {
            if (main.last_req !== null) {
                clearTimeout(main.last_req);
            }
            main.searchval = val;
            main.last_req = setTimeout(main.dosearch, 250);
        };

        /**
         * Performs a search for potential filterable values.
         *
         * @param string val The value to search for.
         */
        this.dosearch = function () {
            if (main.searchval !== '') {
                var requestdata = {
                    action: 'search',
                    q: main.searchval,
                    pagename: opts.datatable.pagename,
                    contextid: opts.datatable.contextid
                };
                $.ajax({
                    type: 'GET',
                    url: opts.datatable.savesearchurl,
                    data: requestdata,
                    dataType: 'text',
                    success: function (data) {
                        try {
                            data = ds_parse_safe_json(data);
                        } catch (err) {
                            return false;
                        }
                        console.log(data);
                        main.set_choices(data.results);
                        //main.filterui.dropdown.children('.selections').empty().hide();
                        //main.set_choices(data);
                    }
                });
            } else {
                main.set_choices();
            }
        };

        /**
         * Set choices.
         *
         * @param array data Array of saved searches that match search.
         */
        this.set_choices = function (data) {
            var currentsearch = opts.datatable.current_search,
                id = null,
                search = main.dropdown.find('div input').val(),
                choice = null,
                total = 0;

            if (typeof currentsearch.id !== "undefined") {
                id = currentsearch.id;
            } else {
                id = null;
            }

            if (typeof data === "undefined") {
                data = opts.datatable.starting_searches;
            }

            // Remove previous results.
            main.dropdown.find('div[class~="deepsight_search_choice"]').each( function (i, el) {
                el.remove();
            });
            $.each(data, function (key, val) {
                total = total + 1;
                if (total > 10) {
                    // Limit amount of listed searches to 10.
                    return false;
                }
                if (Number(val.id) === Number(id)) {
                    main.add_option(val, 'deepsight_filter_generator_choice_bold');
                } else {
                    main.add_option(val);
                }
            });

            if (typeof search !== "undefined" && search.length > 0 && data.length === 0) {
                choice = $('<div class="deepsight_search_choice"></div>').html(opts.lang_search_noresults);
                choice.addClass(opts.css_choice_class);
                main.dropdown.append(choice);
            }
        };

        /**
         * Initializer.
         *
         * Performs the following actions:
         *     - added the opts.css_class class to main.
         *     - attaches a dropdown + adds css class to dropdown
         *     - renders the list of available filters
         */
        this.initialize = function () {
            var currentsearch = opts.datatable.current_search,
                cleanform = ['deepsight_loadsearch_search'],
                name = '',
                search = '',
                temp,
                isdefault;

            // Retrieve current search if the drop down form exists before hand.
            temp = $('#'+opts.datatable.name+'_deepsight_loadsearch_search');
            if (temp.length) {
                search = temp.val();
            }
            $.remove_dynamic_elements(cleanform);

            if (typeof currentsearch.name !== "undefined") {
                name = currentsearch.name;
            } else {
                name = '';
            }

            if (typeof currentsearch.isdefault !== "undefined") {
                isdefault = currentsearch.isdefault ? 'true' : 'false';
            } else {
                isdefault = 'false';
            }

            // Set search title.
            if (name !== '') {
                $('#'+opts.datatable.name+'_searchestitle').html('<b>'+opts.lang_search_for+'</b>: '+name).show();
            }

            if (typeof opts.datatable.starting_searches[0] === "object") {
                main.addClass(opts.css_class);
                main.attach_dropdown({
                    focusselector: '#'+opts.datatable.name+'_deepsight_loadsearch_search'
                });
                var html = '<div class="deepsight_searches_search">\n';
                html += '<span>'+opts.lang_search+'&nbsp;&nbsp;</span><input id="'+opts.datatable.name+'_deepsight_loadsearch_search" type="text" value="'+search+'"/></div>\n';
                main.dropdown.html(html);
                main.dropdown.find('input[type="text"]').keyup(function (e) {
                    main.search($(this).val());
                });

                main.dropdown.addClass(opts.css_dropdown_class);
                main.dropdown.addClass(opts.filter_dropdown_cssclass);
                main.set_choices();
                // If loadsearch drop down is being recreated than reuse old search value and load results.
                if (search !== '') {
                    main.dropdown.find('input[type="text"]').keyup();
                }
            }
        };

        this.initialize();

        return main;
    };

})(jQuery);
