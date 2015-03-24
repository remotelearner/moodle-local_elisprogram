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
 * @package    eliswidget_enrolment
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

(function($) {

    /**
     * A configurable datatable that can show different types of elements.
     * Usage:
     *     $('[container selector]').eliswidget_trackenrol_datatable(options);
     *
     * Required Options:
     *     object ids           IDs to pass to child objects.
     *     string endpoint      The URL to make requests to.
     *     string requestmode   The method called to get/update the list.
     *     object requestdata   Parameters to send with the request.
     *     string childrenderer The name of a jQuery plugin that will render the child elements.
     *     object childopts     Options to pass directly to child objects.
     *     object lang          Language strings.
     *
     * @param object options Options object (See Options section above for description)
     * @return object Main object
     */
    $.fn.eliswidget_trackenrol_datatable = function(options) {
        this.default_opts = {
            ids: {},
            endpoint: '',
            requestmode: '',
            requestdata: {},
            childrenderer: '',
            childopts: {},
            lang: {}
        }
        var opts = $.extend({}, this.default_opts, options);
        var main = this;

        /** @var bool Whether the filters for this list have been initialized. */
        this.filtersinit = false;

        /** @var int The current page. */
        this.page = 1;

        /** @var object A quere storing timeouts and ajax references for delay ajax requests. */
        this.updatetable_queue = {timeout: null, ajax: null};

        /** @var object The current filterdata. Object with filter name as key, and array of values to filter on for each value. */
        this.filters = {};

        /**
         * Does a delayed table update.
         *
         * Will update the table in 500ms unless somethings calls this again, in which case the timer will start over. This is to
         * prevent firing off many updates in rapid succession in, for example, the textsearch filter, where this is called after
         * every keystroke.
         */
        this.updatetable = function() {
            if (main.updatetable_queue.timeout != null) {
                clearTimeout(main.updatetable_queue.timeout);
            }
            main.updatetable_queue.timeout = setTimeout(main.doupdatetable, 500);
        }

        /**
         * Abort a previous ajax call to update the table.
         *
         * This is fired every time doupdatetable is fired, but will only abort a request if there is another request current in
         * process.
         */
        this.abortupdatetable = function() {
            if (main.updatetable_queue.ajax && main.updatetable_queue.ajax.readyState != 4) {
                main.updatetable_queue.ajax.abort();
            }
        }

        /**
         * Updates the table.
         *
         * Makes an asynchronous request to opts.dataurl with the current page, sortdata, fields, and filters.
         * Receives data and sends to renderers.
         */
        this.doupdatetable = function() {
            main.abortupdatetable();
            main.addClass('loading');
            var data = {
                m: opts.requestmode,
                data: opts.requestdata,
            };
            data.data.filters = JSON.stringify(main.filters);
            data.data.page = main.page;
            $.ajax({
                url: opts.endpoint,
                data: data,
                dataType: 'json',
                type: 'GET',
                success: function(data, textStatus, jqXHR) {
                    main.removeClass('loading');
                    main.children().remove();
                    if (typeof data.data.children === 'object' && data.data.children.length > 0) {
                        for (var i in data.data.children) {
                            var child = $('<div></div>')[opts.childrenderer](data.data.children[i], opts.ids, data.data.fields, opts.childopts, main);
                            main.append(child);
                        }

                        // Initialize Pagination.
                        var pagination = main.siblings('.ds_pagelinks');
                        if (data.data.totalresults > data.data.perpage) {
                            pagination.show();
                            var paginationlang = {lang_showing: '', lang_result: '', lang_results: ''};
                            pagination.deepsight_pagination(main.page, data.data.totalresults, data.data.perpage, paginationlang);
                            pagination.unbind('pagechange').bind('pagechange', function(e, data) {
                                main.page = data.page;
                                main.updatetable();
                            });
                        } else {
                            pagination.hide();
                        }
                    } else {
                        main.append('<span class="empty">'+opts.lang.nonefound+'</span>');
                    }
                    if (main.filtersinit === false) {
                        // Initialize filterbar.
                        var filterbar = main.siblings('.childrenlistheader').find('.filterbar');

                        filterbar.show().deepsight_filterbar({
                            datatable: main,
                            filters: data.data.filters,
                            starting_filters: data.data.initialfilters,
                            lang_add: '',
                            lang_addtitle: opts.lang.generatortitle,
                        });
                        main.filtersinit = true;
                    }
                }
            });
        }

        /**
         * Adds filter data to the table.
         *
         * @param string filtername The name of the filter
         * @param mixed val The value to filter on.
         */
        this.filter_add = function(filtername, val) {
            if (typeof(main.filters[filtername]) == 'undefined') {
                main.filters[filtername] = [];
            }
            main.filters[filtername].push(val);
            main.page = 1;
        }

        /**
         * Removes filter data.
         *
         * @param string filtername The name of the filter
         * @param mixed val The value to remove. If not defined, ALL values for the filter will be removed.
         */
        this.filter_remove = function(filtername, val) {
            if (typeof(val) != 'undefined') {
                var index = $.inArray(val, main.filters[filtername]);
                if (index >= 0) {
                    main.filters[filtername].splice(index,1);
                }
                if (main.filters[filtername].length == 0) {
                    delete main.filters[filtername];
                }
            } else {
                delete main.filters[filtername];
            }
        }

        /**
         * Registers that a filter with the datatable.
         *
         * Registers that a filter has been added to the datatable, without adding an actual filtering value. This is used to add more
         * columns to the table when new filters are added.
         *
         * @param string filtername The name of the filter we're adding.
         */
        this.filter_register = function(filtername) {
            if (typeof(main.filters[filtername]) == 'undefined') {
                main.filters[filtername] = [];
            }
        }

        return main;
    }

    /**
     * ELIS Enrolment Widget Track Renderer
     *
     * Usage:
     *     $('[container selector]').eliswidget_trackenrol_track(data, ids, fieldvisibility, opts, datatable)
     *
     * Required Options:
     *     string endpoint The URL to send ajax requests.
     *     object lang     An object of language strings to use throughout the widget.
     *
     * @param object data All received data from the ajax request.
     * @param object ids An object of relevant IDs. This should contain 'widgetid' and 'trackid'.
     * @param object fieldvisibility An object listing visible and hidden fields for the element.
     * @param object opts Options object (See Options section above for description)
     * @param object datatable The datatable object
     * @return object jQuery object for each instance.
     */
    $.fn.eliswidget_trackenrol_track = function(data, ids, fieldvisibility, opts, datatable) {
        return this.each(function() {
            var jqthis = $(this);
            var ajaxendpoint = opts.endpoint;

            /** @var object All received data from the ajax request. */
            this.data = data;

            /** @var int The ID of the track. */
            this.trackid = this.data.element_id;

            /** @var int The ID of the widget this program belongs to. */
            this.widgetid = ids.widgetid;

            /** @var object The datatable object */
            this.datatable = datatable;

            var main = this;

            /**
             * Generate a unique ID for a given string name.
             *
             * @param string name A name for the ID.
             * @return string A unique name that contains the given ID.
             */
            this.generateid = function(name) {
                return 'eliswidget_trackenrol'+main.widgetid+'_trk'+main.trackid+'_'+name;
            }

            /**
             * Generate display elements for a piece of element information.
             *
             * @param string name The label of the information.
             * @param string val The value of the information.
             * @param string id An ID for the information (added to CSS classes)
             * @return object A jQuery object for the DOM element.
             */
            this.generateitem = function(name, val, id) {
                var itemclass = 'item';
                if (id != null) {
                    itemclass += ' '+id;
                }
                var item = $('<span class="'+itemclass+'"></span>');
                item.append('<span class="key">'+name+'</span>');
                var value = $('<span class="val"></span>').append(val);
                item.append(value);
                return item;
            }

            /**
             * Render the track enrolment status, and the link to change it.
             *
             * @param string status The student's current status in the track.
             * @return object jQuery object for the status/link element.
             */
            this.renderstatus = function(status) {
                var status2action = {
                    enroled: 'unenrol',
                    available: 'enrol'
                };
                var action = status2action[status];
                if (action == 'enrol' && opts.enrolallowed != '1') {
                    action = '';
                }
                if (action == 'unenrol' && opts.unenrolallowed != '1') {
                    action = '';
                }
                var statusele = $('<span id="'+main.generateid('status')+'" class="pmclassstatus"></span>');
                statusele.append('<span>'+opts.lang['status_'+status]+'</span>');
                if (action != '') {
                    statusele.append('<a href="javascript:;">'+opts.lang['action_'+action]+'</a>');
                }
                statusele.find('a').click(function(e) {
                    main.changestatus(e, action);
                });
                return statusele;
            }

            /**
             * Change the student's status within the track.
             *
             * @param object e Click event from clicking the change status link.
             * @param string action The action to perform.
             */
            this.changestatus = function(e, action) {
                e.preventDefault();
                e.stopPropagation();
                // Add confirm dialog.
                var height = 175;
                var prompt = '<b>'+opts.lang['enrol_confirm_'+action]+'</b><br/>&nbsp;&nbsp;'+opts.lang.idnumber+': '+main.data.element_idnumber;
                if (action == 'enrol' || action == 'unenrol') {
                    if (Date.parse(main.data.element_startdate)) {
                        height += 25;
                        prompt += '<br/>&nbsp;&nbsp;'+opts.lang.startdate+': '+main.data.element_startdate;
                    }
                    if (Date.parse(main.data.element_enddate)) {
                        height += 25;
                        prompt += '<br/>&nbsp;&nbsp;'+opts.lang.enddate+': '+main.data.element_enddate;
                    }
                }
                $('<div></div>').appendTo('body')
                    .html(prompt)
                    .dialog({
                        modal: true,
                        resizable: true,
                        height: height,
                        width: 500,
                        title: opts.lang.enrol_confirm_title,
                        buttons: [{
                            text: opts.lang.yes,
                            click: function() {
                                    $(this).dialog("close");
                                    var data = {
                                        m: 'changetrackstatus',
                                        data: {action: action, trackid: main.trackid},
                                    };
                                    $('#'+main.generateid('status')).find('a').replaceWith('<span class="smloader">'+opts.lang.working+'</span>');
                                    $.ajax({
                                        url: ajaxendpoint,
                                        data: data,
                                        dataType: 'json',
                                        type: 'POST',
                                        success: function(data, textStatus, jqXHR) {
                                            var newstatus = main.renderstatus(data.data.newstatus);
                                            $('#'+main.generateid('status')).replaceWith(newstatus);
                                        }
                                    });
                            }
                        }, {
                            text: opts.lang.cancel,
                            click: function() {
                                    $(this).dialog("close");
                            }
                        }],
                        close: function(event, ui) { $(this).remove(); },
                });
            }

            /**
             * Render the class.
             *
             * @return object jQuery object for the class.
             */
            this.render = function() {
                var details = $('<div class="details"></div>');
                details.append('<h5>'+main.data.header+'</h5>');
                // Visible details.
                for (var fieldalias in fieldvisibility.visible) {
                    var label = fieldvisibility.visible[fieldalias];
                    var value = main.data[fieldalias];
                    details.append(main.generateitem(label, value, fieldalias));
                }

                // Track status.
                var status = '';
                if (this.data.usertrack_id != null) {
                    status = 'enroled';
                } else {
                    status = 'available';
                }
                details.append(this.generateitem(opts.lang.data_status, main.renderstatus(status)));

                // Hidden details.
                var detailshidden = $('<div class="detailshidden" style="display:none;"></div>');
                for (var fieldalias in fieldvisibility.hidden) {
                    var label = fieldvisibility.hidden[fieldalias];
                    var value = main.data[fieldalias];
                    detailshidden.append(main.generateitem(label, value, fieldalias));
                }
                details.append(detailshidden);

                var morelesslink = $('<a class="morelesslink" href="javascript:;">'+opts.lang.more+'</a>');
                morelesslink.click(function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).siblings('.detailshidden').toggle();
                    $(this).html(($(this).html() === opts.lang.more) ? opts.lang.less : opts.lang.more);
                });
                details.append(morelesslink);
                return details;
            }

            jqthis.attr({id: 'track_'+this.trackid, class: 'track'});
            jqthis.data('id', this.trackid);
            jqthis.append(this.render());
        });
    }

    /**
     * ELIS Enrolment Widget Top Renderer
     *
     * Usage:
     *     $('[container selector]').eliswidget_trackenrol_top(ids, opts);
     *
     * Required Options:
     *     string endpoint The URL to send ajax requests.
     *     object lang     An object of language strings to use throughout the widget.
     *
     * @param object ids An object of relevant IDs. This should contain 'widgetid'.
     * @param object opts Options object (See Options section above for description)
     * @return object jQuery object for each instance.
     */
    $.fn.eliswidget_trackenrol_top = function(ids, opts) {
        return this.each(function() {
            var jqthis = $(this);

            /** @var int The ID of the widget this track belongs to. */
            this.widgetid = ids.widgetid;

            var main = this;

            /**
             * Generate a unique ID for a given string name.
             *
             * @param string name A name for the ID.
             * @return string A unique name that contains the given ID.
             */
            this.generateid = function(name) {
                return 'eliswidget_trackenrol'+main.widgetid+'_'+name;
            }

            var childrenlist = jqthis.children('.childrenlist');
            if (childrenlist.is(':empty')) {
                var trackwrapper = $('<div id="'+main.generateid('trackwrapper')+'"></div>');
                var trackheading = $('<div class="childrenlistheader"></div>');
                trackheading.append('<h6>'+opts.lang.tracks+'</h6>');
                // New line for track filters
                trackheading.append('<span id="'+main.generateid('trackfilterbar')+'" class="filterbar"></span>');
                trackwrapper.append(trackheading);
                var tracklist = $('<div id="'+main.generateid('tracklist')+'"></div>');
                trackwrapper.append(tracklist);
                var trackpagination = $('<div id="'+main.generateid('trackpagination')+'" class="ds_pagelinks"></div>');
                trackwrapper.append(trackpagination);
                childrenlist.append(trackwrapper);

                // Initialize track datatable.
                main.datatable = tracklist.eliswidget_trackenrol_datatable({
                    ids: {widgetid: main.widgetid},
                    endpoint: opts.endpoint,
                    requestmode: 'tracksforuser',
                    requestdata: {widgetid: main.widgetid},
                    childrenderer: 'eliswidget_trackenrol_track',
                    childopts: opts,
                    lang: opts.lang
                });
                main.datatable.doupdatetable();
            }
        });
    }

    /**
     * ELIS Enrolment Widget Initializer
     *
     * Usage:
     *     $('[container selector]').eliswidget_trackenrol(options);
     *
     * Required Options:
     *     string endpoint The URL to send ajax requests.
     *     object lang     An object of language strings to use throughout the widget.
     *
     * @param object options Options object (See Options section above for description)
     * @return object jQuery object for each instance.
     */
    $.fn.eliswidget_trackenrol = function(options) {
        return this.each(function() {
            var jqthis = $(this);
            var main = this;
            var trackdiv = jqthis.find('div.track');
            trackdiv.eliswidget_trackenrol_top({widgetid: jqthis.data('id')}, options);
        });
    }
})(jQuery);
