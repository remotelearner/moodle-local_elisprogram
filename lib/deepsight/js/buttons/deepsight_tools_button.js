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
     * DeepSight tools button
     * Generates additional filters on-demand
     *
     * Usage:
     *     $('#element').deepsight_tools_button();
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
     *     object filterbar          Filter bar object.
     * Optional Options:
     *     string css_class          A CSS class to add to the button. Not recommended to change this.
     *     string css_dropdown_class A CSS class to add to the dropdown. Not recommended to change this.
     *     string css_choice_class   A CSS class to add to each option in the dropdown.
     *
     * Note: All elements this is run on must have an "id" attribute.
     *
     * @param object options Options for the class.
     */
    $.fn.deepsight_tools_button = function (options) {
        this.default_opts = {
            datatable: null,
            filterbar: {},
            css_dropdown_class: 'deepsight_filter_generator_dropdown',
            css_choice_class: 'deepsight_filter_generator_choice',
        };

        var opts = $.extend({}, this.default_opts, options);
        var main = this;

        /**
         * Confirm deletion of search.
         */
        this.confirmdeletesearch = function () {
            var actionpanelbody = '<div class="body">'+opts.lang_search_delete_confirm+'</div>\n\
                                <div class="actions"><i class="elisicon-confirm deepsight_search_confirm"></i><i class="elisicon-cancel deepsight_search_cancel"></i></div>',
            actionpanel = $('#'+opts.datatable.name+'_deepsight_tools_saved');
            actionpanel.html(actionpanelbody);
            actionpanel.find('i.elisicon-confirm').bind('click', main.deletesearch);
            actionpanel.find('i.elisicon-cancel').click(main.reset);
            $('#'+opts.datatable.name+'_deepsight_tools_saved').addClass('deepsight_search_confirm_body').show();
            $('#'+opts.datatable.name+'_deepsight_tools_body').hide();
        };

        /**
         * Add set as default option to the tools button.
         * @return void
         */
        this.add_setdefault = function () {
            if (!(opts.datatable.cansave() && opts.datatable.cansavecurrent())) {
                return;
            }

            var id = main.prop('id')+'_choice_search_setdefault';
            var choice = $('<div></div>')
                .prop('id', id)
                .addClass(opts.css_choice_class)
                .html(opts.lang_search_setdefault);

            choice.click(function (e) {
                console.log('setdefault');
                $('#'+opts.datatable.name+'_deepsight_tools_saved').html(opts.lang_search_setdefault_saved);
                $('#'+opts.datatable.name+'_deepsight_search_default').prop('checked', true);

                if (!opts.filterbar.validatesearch()) {
                    // Show the save form to add name to filter.
                    $('#'+opts.datatable.name+'_deepsight_tools_saving').removeClass('deepsight_search_saving').html('');
                    $('#'+opts.datatable.name+'_deepsight_tools_saved').css('display', 'none');
                    $('#'+opts.datatable.name+'_deepsight_tools_body').show();
                    main.reset();
                    opts.datatable.render_save_error(opts.lang_search_form_name_error, 'search');
                    $('#'+opts.datatable.name+'_deepsight_searchsave').mousedown();
                    $('#'+opts.datatable.name+'_deepsight_searchsave').click();
                    $('#'+opts.datatable.name+'_deepsight_search_name').focus();
                } else {
                    main.showsaving();
                    opts.datatable.dosavesearch('save', 'tools');
                }
            });

            main.dropdownbody.append(choice);
        };

        /**
         * Add clear search option to the tools button.
         */
        this.add_clearsearch = function () {
            var id = main.prop('id')+'_choice_search_clearsearch';
            var choice = $('<div></div>')
                .prop('id', id)
                .addClass(opts.css_choice_class)
                .html(opts.lang_search_clearsearch);

            choice.click(function (e) {
                main.clearsearch();
            });

            main.dropdownbody.append(choice);
        };

        /**
         * Delete current search.
         */
        this.deletesearch = function () {
            $('#'+opts.datatable.name+'_deepsight_tools_saved')
                .removeClass('deepsight_search_confirm_body')
                .html(opts.lang_search_deleted)
                .hide();
            if (typeof (opts.datatable.current_search.id) !== "undefined") {
                var deleteid = opts.datatable.current_search.id;
                // Load new search.
                var newid = opts.datatable.findothersearch(deleteid);
                main.showsaving(deleteid);
                if (newid) {
                    // Load new search.
                    opts.datatable.loadsearch(newid);
                    opts.filterbar.add_loadsearch_button();
                    main.dropdownbody.html('');
                    main.add_clearsearch();
                    if (typeof opts.datatable.current_search === "object" &&
                            typeof opts.datatable.current_search.id !== "undefined") {
                        main.add_setdefault();
                        main.add_delete();
                    }
                    opts.filterbar.add_savesearch_button();
                    opts.filterbar.loadfilters(false);
                    if (opts.datatable.current_search.name !== '') {
                        $('#'+opts.datatable.name+'_searchestitle').html('<b>'+opts.lang_search_for+'</b>: '+opts.datatable.current_search.name).show();
                    }
                    opts.datatable.updatetable();
                }
                opts.datatable.deletesearch(deleteid, 'tools');
            }
        };

        /**
         * Clear current search and start fresh.
         */
        this.clearsearch = function () {
            var startingopts = opts.filterbar.get_options();
            main.reset();
            opts.datatable.clearsearch();

            var filterbardiv = $('#'+opts.datatable.name+'_filterbar');
            filterbardiv.deepsight_filterbar(startingopts);
        };

        /**
         * Add delete option to the tools button.
         * @return void
         */
        this.add_delete = function () {
            var id, choice;
            if (!(opts.datatable.cansave() && opts.datatable.cansavecurrent())) {
                return;
            }

            id = main.prop('id')+'_choice_search_delete';
            choice = $('<div></div>')
                .prop('id', id)
                .addClass(opts.css_choice_class)
                .html(opts.lang_search_delete);

            choice.click(main.confirmdeletesearch);

            main.dropdownbody.append(choice);
        };

        /**
         * Show saving spinner.
         *
         * @param int id Id of deleted search.
         */
        this.showsaving = function (id) {
            // Show saving spinner and saved message.
            $('#'+opts.datatable.name+'_deepsight_tools_saving')
                .addClass('deepsight_search_saving')
                .css('display', 'block')
                .html(opts.lang_search_form_saving);
            // Hide tools list.
            $('#'+opts.datatable.name+'_deepsight_tools_body').hide();
            $('#'+opts.datatable.name+'_deepsight_tools_saved').hide();

            $('#'+opts.datatable.name+'_deepsight_tools_saving').bind('searchsaved', function (e) {
                // Hide saving message.
                $(this).removeClass('deepsight_search_saving').css('display', 'none');
                // Show saved message.
                $('#'+opts.datatable.name+'_deepsight_tools_saved').css('display', 'block');
                // Hide saved message after 1500 ms.
                setTimeout(function () {
                    if (id && !opts.datatable.findothersearch(id)) {
                        // No other search found, start a new search.
                        main.clearsearch();
                    } else {
                        main.reset();
                        opts.filterbar.add_loadsearch_button();
                    }
                }, 1500);
            });
        };

        /**
         * Reset
         */
        this.reset = function () {
            if ($('#'+opts.datatable.name+'_deepsight_searchtools[class*="active"]').length) {
                // Only hide dropdowns if searchtools is still present.
                $.deactivate_all_filters();
            }
            $('#'+opts.datatable.name+'_deepsight_tools_saved').removeClass('deepsight_search_confirm_body').css('display', 'none');
            $('#'+opts.datatable.name+'_deepsight_tools_body').show();
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
         * Initializer.
         *
         * Performs the following actions:
         *     - added the opts.css_class class to main.
         *     - attaches a dropdown + adds css class to dropdown
         *     - renders the list of available filters
         */
        this.initialize = function () {
            var cleanform = ['deepsight_tools_body', 'deepsight_tools_saved', 'deepsight_tools_saving'];

            $.remove_dynamic_elements(cleanform);

            main.dropdownbody = $('<div id="'+opts.datatable.name+'_deepsight_tools_body"></div>');
            main.dropdownsaving = $('<div id="'+opts.datatable.name+'_deepsight_tools_saved" class="deepsight_search_saved">' + opts.lang_search_tools_setdefault_saved + '</div>');
            main.dropdownsaved = $('<div id="'+opts.datatable.name+'_deepsight_tools_saving" class="deepsight_search_saving">' + opts.lang_search_form_saving + '</div>');
            main.addClass(opts.css_class);
            main.attach_dropdown();
            main.dropdown.addClass(opts.css_dropdown_class);
            main.dropdown.addClass(opts.filter_dropdown_cssclass);
            main.add_clearsearch();
            var isobject = (typeof opts.datatable.current_search === "object");
            if (isobject && typeof opts.datatable.current_search.id !== "undefined") {
                main.add_setdefault();
                main.add_delete();
            }
            main.dropdown.append(main.dropdownbody);
            main.dropdown.append(main.dropdownsaving);
            main.dropdown.append(main.dropdownsaved);
        };

        this.initialize();

        return main;
    };

})(jQuery);
