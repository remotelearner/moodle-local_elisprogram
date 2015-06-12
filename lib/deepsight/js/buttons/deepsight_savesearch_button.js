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

(function ($) {

    /**
     * DeepSight save search button
     * Generates additional filters on-demand
     *
     * Usage:
     *     $('#element').deepsight_savesearch_button();
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
    $.fn.deepsight_savesearch_button = function (options) {
        this.default_opts = {
            datatable: null,
            available_filters: {},
            filterbar: {}
        };

        var opts = $.extend({}, this.default_opts, options);
        var main = this;
        this.available_filters = opts.available_filters;

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
         * Show saving spinner.
         */
        this.showsaving = function () {
            // Show saving spinner and saved message.
            $('#'+opts.datatable.name+'_deepsight_search_saving').addClass('deepsight_search_saving').css('display', 'block').html(opts.lang_search_form_saving);

            $('#'+opts.datatable.name+'_deepsight_search_saving').bind('searchsaved', function (e) {
                // Hide saving message.
                $(this).removeClass('deepsight_search_saving').css('display', 'none');
                // Reset save button from 'Save a copy' to 'Save'.
                $('#'+opts.datatable.name+'_deepsight_search_save').html(opts.lang_search_form_save);
                // Show saved message.
                if (opts.datatable.starting_searches.length > 1) {
                    $('#'+opts.datatable.name+'_deepsight_search_saved').css('display', 'block');
                } else {
                    $('#'+opts.datatable.name+'_deepsight_search_body').css('display', 'block');
                    if ($('#'+opts.datatable.name+'_deepsight_searchsave[class*="active"]').length) {
                        // Only hide dropdowns if searchsave is still active.
                        $.deactivate_all_filters();
                    }
                }
                // Hide saved message after 1500 ms.
                opts.filterbar.add_loadsearch_button();
                opts.filterbar.add_tools_button();
                main.reposition_dropdown();

                if (opts.datatable.starting_searches.length > 1) {
                    setTimeout(function () {
                        if ($('#'+opts.datatable.name+'_deepsight_searchsave[class*="active"]').length) {
                            // Only hide dropdowns if searchsave is still active.
                            $.deactivate_all_filters();
                        }
                        $('#'+opts.datatable.name+'_deepsight_search_saved').css('display', 'none');
                        $('#'+opts.datatable.name+'_deepsight_search_body').show();
                    }, 1500);
                }
            });

            $('#'+opts.datatable.name+'_deepsight_search_body').hide();
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
                cleanform = [
                        'deepsight_search_save',
                        'deepsight_search_name',
                        'deepsight_search_isdefault',
                        'deepsight_search_saving',
                        'deepsight_search_saved',
                        'deepsight_search_body'
                ],
                name = '',
                isdefault = false;

            $.remove_dynamic_elements(cleanform);

            if (typeof currentsearch.name !== "undefined") {
                name = currentsearch.name;
            } else {
                name = '';
            }
            if (typeof currentsearch.isdefault !== "undefined") {
                isdefault = currentsearch.isdefault ? 'checked="checked"' : '';
            } else {
                isdefault = '';
            }
            main.addClass(opts.css_class);
            main.attach_dropdown({
                focusselector: '#'+opts.datatable.name+'_deepsight_search_name'
            });
            main.dropdown.addClass(opts.css_dropdown_class);

            var cansave = false;
            if (opts.datatable.can_load_searches && opts.datatable.can_save_searches) {
                cansave = true;
            }

            if (opts.datatable.current_search !== "undefined" && typeof opts.datatable.current_search.id !== "undefined") {
                // A search is currently loaded.
                cansave = false;
                if (typeof opts.datatable.current_search.cansave !== "undefined") {
                    cansave = opts.datatable.current_search.cansave;
                }
            }

            var savebutton = opts.lang_search_form_save;
            if (!cansave) {
                savebutton = opts.lang_search_form_save_copy;
            }

            // Create drop down.
            var prefix = opts.datatable.name+'_deepsight_search_';
            var html = '<div class="filter_search deepsight_search_container" id="'+prefix+'body">\n'+
                '<label for="'+prefix+'name">'+opts.lang_search_form_name+'&nbsp;&nbsp;</label><br>\n'+
                '<input type="text" id="'+prefix+'name" value="'+name+'"/>\n'+
                '<label for="'+prefix+'default">'+opts.lang_search_form_default+'&nbsp;&nbsp;</label>\n'+
                '<input type="checkbox" id="'+prefix+'default" value="1" '+isdefault+' /><br>\n'+
                '<button id="'+prefix+'save" class="elisicon-more deepsight_filter_generator deepsight_dropdown_activator" title="'+opts.lang_search_form_save_title+'">'+
                savebutton+'</button>\n'+
                '</div>\n'+
                '<div class="deepsight_search_saving" id="'+prefix+'saving">'+opts.lang_search_form_saving+'</div>\n'+
                '<div class="deepsight_search_saved" id="'+prefix+'saved">'+opts.lang_search_form_saved+'</div>';
            main.dropdown
                .addClass(opts.filter_dropdown_cssclass).html(html);

            // Short cut, if enter key is pressed in name input save search.
            $('#'+prefix+'name').keyup(function (e) {
                if (e.keyCode === 13) {
                    opts.datatable.savesearch(opts.filterbar);
                }
            });

            // Focus on name input.
            main.dropdown.on('mouseup', function (e) {
                $('#'+prefix+'name').focus();
            });

            // Save action.
            main.dropdown.find('#'+prefix+'save').click(
                function () {
                    if (!opts.filterbar.validatesearch()) {
                        $('#'+prefix+'name').focus();
                        opts.datatable.render_save_error(opts.lang_search_form_name_error, 'search');
                    } else {
                        main.showsaving();
                        opts.datatable.dosavesearch('save', 'search');
                    }
                });
        };

        this.initialize();
        return main;
    };
})(jQuery);
