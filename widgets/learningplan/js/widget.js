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
 * @package    eliswidget_learningplan
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

(function($) {

    /**
     * A configurable datatable that can show different types of elements.
     * Usage:
     *     $('[container selector]').eliswidget_learningplan_datatable(options);
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
    $.fn.eliswidget_learningplan_datatable = function(options) {
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
            opts.requestdata.initialized = main.filtersinit;
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
                    var tagname = main.prop('tagName');
                    var classname = '';
                    main.children().not('.tableheaderrow').remove();
                    if (tagname == 'TR') {
                        main = main.parents('table:first');
                        classname = ' class="classtablerow"';
                    }
                    if (typeof data.data.children === 'object' && data.data.children.length > 0) {
                        for (var i in data.data.children) {
                            var child = $('<'+tagname+classname+'></'+tagname+'>')[opts.childrenderer](data.data.children[i], opts.ids, data.data.fields, opts.childopts, main);
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
                    } else if (tagname == 'TR') {
                        main.parents('.program').find('.childrenlist').css('display', 'none');
                        main.parents('.program').find('.programspace').css('display', 'none');
                        main.parents('.program').find('.showhide').css('display', 'none');
                    } else {
                        main.append('<span class="empty">'+opts.lang.nonefound+'</span>');
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
     * ELIS LearningPlan Widget Class Renderer.
     *
     * Usage:
     *     $('[container selector]').eliswidget_learningplan_mdlclass(data, ids, fieldvisibility, opts);
     *
     * Required Options:
     *     string endpoint The URL to send ajax requests.
     *     object lang     An object of language strings to use throughout the widget.
     *
     * @param object data All received data from the ajax request.
     * @param object ids An object of relevant IDs. This should contain 'widgetid', 'programid', and 'courseid'.
     * @param object fieldvisibility An object listing visible and hidden fields for the element.
     * @param object opts Options object (See Options section above for description)
     * @param object datatable The datatable object
     * @return object jQuery object for each instance.
     */
    $.fn.eliswidget_learningplan_mdlclass = function(data, ids, fieldvisibility, opts, datatable) {
        return this.each(function() {
            var jqthis = $(this);
            var ajaxendpoint = opts.endpoint;

            /** @var object All received data from the ajax request. */
            this.data = data;

            /** @var int The ID of the class. */
            this.classid = data.element_id;

            /** @var int The ID of the course this class belongs to. */
            this.courseid = ids.courseid;

            /** @var int The ID of the program this class belongs to. */
            this.programid = ids.programid;

            /** @var int The ID of the widget this class belongs to. */
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
                return 'eliswidget_learningplan'+main.widgetid+'_pgm'+main.programid+'_crs'+main.courseid+'_cls'+main.classid+'_'+name;
            }

            /**
             * Render the class enrolment status, and the link to change it.
             *
             * @param string status The student's current status in the class.
             * @return object jQuery object for the status/link element.
             */
            this.renderstatus = function(status) {
                var statusele = $('<span id="'+main.generateid('status')+'" class="pmclassstatus"></span>');
                var enrolinfo = '';
                var waitinfo = '';
                if (status == 'full' || status == 'waitlist') {
                    waitinfo = ' (';
                    if (this.data.meta.waitpos) {
                        waitinfo += this.data.meta.waitpos+' '+opts.lang.of+' ';
                        enrolinfo = '';
                    }
                    waitinfo += this.data.meta.waiting+' '+opts.lang.waiting;
                    waitinfo += ')';
                }
                statusele.append('<span>'+opts.lang['status_'+status]+enrolinfo+'</span>');
                if (waitinfo != '') {
                    statusele.append(waitinfo);
                }
                return statusele;
            }

            /**
             * Render the class.
             * @param parenttr the parent tr element.
             */
            this.render = function(parenttr) {
                var status = 'notenroled';
                if (this.data.enrol_id != null) {
                    if (this.data.completestatusid == 2) {
                        status = 'passed';
                    } else if (this.data.completestatusid == 1) {
                        status = 'failed';
                    } else {
                        status = 'enroled';
                    }
                } else if (this.data.waitlist_id != null) {
                    status = 'waitlist';
                }

                var completed = (status == 'passed' || status == 'failed');
                parenttr.append('<td align="left">'+main.data.header+'</td>');
                parenttr.append('<td align="left">'+main.renderstatus(status).html()+'</td>');
                parenttr.append('<td align="center">'+this.data.completetime+'</td>');
                parenttr.append('<td align="center">'+(completed ? parseFloat(this.data.grade).toFixed(2) : '-')+'</td>');
                parenttr.addClass(completed ? 'completed' : 'notcompleted');
            }

            jqthis.attr({id: 'pmclass_'+this.classid, class: 'pmclass'});
            jqthis.data('id', this.classid);
            this.render((jqthis.prop('tagName') == 'TR') ? jqthis : jqthis.parents('tr:first'));
        });
    }

    /**
     * ELIS LearningPlan Widget Program Renderer
     *
     * Usage:
     *     $('[container selector]').eliswidget_learningplan_program(data, ids, fieldvisibility, opts, datatable)
     *
     * Required Options:
     *     string endpoint The URL to send ajax requests.
     *     object lang     An object of language strings to use throughout the widget.
     *
     * @param object data All received data from the ajax request.
     * @param object ids An object of relevant IDs. This should contain 'widgetid' and 'programid'.
     * @param object fieldvisibility An object listing visible and hidden fields for the element.
     * @param object opts Options object (See Options section above for description)
     * @param object datatable The datatable object
     * @return object jQuery object for each instance.
     */
    $.fn.eliswidget_learningplan_program = function(data, ids, fieldvisibility, opts, datatable) {
        return this.each(function() {
            var jqthis = $(this);

            /** @var object All received data from the ajax request. */
            this.data = data;

            /** @var int The ID of the program. */
            this.programid = this.data.element_id;

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
                return 'eliswidget_learningplan'+main.widgetid+'_pgm'+main.programid+'_'+name;
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
             * Generate program progress bar.
             *
             * @param string pctcomplete The percent complete.
             * @return object A jQuery object for the DOM element.
             */
            this.generateprogressbar = function(pctcomplete) {
                if (pctcomplete < 0) {
                    return '';
                }
                var decile = Math.floor(pctcomplete/10.0);
                var colorcode = 0;
                if (decile >= 8) {
                    colorcode = 3;
                } else if (decile >= 5) {
                    colorcode = 2;
                } else {
                    colorcode = 1;
                }
                // We must use string to prevent closing rect tag.
                return '<svg class="elisprogress"><rect x="0" y="0" height="100%" width="'+pctcomplete+
                        '%" class="decile'+decile+' colorcode'+colorcode+'"></svg>';
            }

            /**
             * Render the header information for this element.
             *
             * @return object jQuery object for the DOM element that contains the element header.
             */
            this.renderheader = function() {
                var header = $(this);
                var progressbar = main.generateprogressbar(main.data.pctcomplete);
                header.append(progressbar);
                header.append('<div class="header"><h5 class="header">'+main.data.header+'</h5></div>');
                header.append(main.generateitem('', main.data.description, 'description'));
                var spacing = $('<div class="programspace"></div>');
                spacing.html('<br/>&nbsp;');
                header.append(spacing);
                return header;
            }

            jqthis.attr({id: 'program_'+this.programid, class: 'program'});
            jqthis.data('id', this.programid);
            jqthis.append(this.renderheader());
            jqthis.append('<div class="childrenlist"></div>');
            var hideshow = $('<button></button>').addClass('showhide');
            hideshow.html(opts.lang.show_classes);
            jqthis.append(hideshow);
            var spacing = $('<div></div>').css('height', '1.5rem').css('display', 'block');
            jqthis.append(spacing);
            jqthis.children('.showhide').click(function() {
                var childrenlist = jqthis.children('.childrenlist');
                jqthis.toggleClass('expanded');
                jqthis.children('.programspace').toggleClass('expanded');
                var hideshow = jqthis.children('.showhide');
                if (hideshow) {
                    hideshow.html(jqthis.hasClass('expanded') ? opts.lang.hide_classes : opts.lang.show_classes);
                }
                if (childrenlist.is(':empty')) {
                    var coursewrapper = $('<div id="'+main.generateid('coursewrapper')+'"></div>');
                    var coursetable = $('<table id="'+main.generateid('courselist')+'" class="lpclasstable"></table>');
                    var courselist = $('<tr class="tableheaderrow"></tr>'); // TBD: heading
                    courselist.append('<th class="tableheaderrow">'+opts.lang.course+'</th>');
                    courselist.append('<th class="tableheaderrow">'+opts.lang.data_status+'&nbsp;&nbsp;&nbsp;&nbsp;</th>');
                    courselist.append('<th class="tableheaderrow">&nbsp;&nbsp;'+opts.lang.data_completetime+'&nbsp;&nbsp;</th>');
                    courselist.append('<th class="tableheaderrow">'+opts.lang.data_grade+'</th>');
                    coursetable.append(courselist);
                    coursewrapper.append(coursetable);
                    var coursepagination = $('<div id="'+main.generateid('coursepagination')+'" class="ds_pagelinks"></div>');
                    coursewrapper.append(coursepagination);
                    childrenlist.append(coursewrapper);

                    // Initialize course datatable.
                    main.coursedatatable = courselist.eliswidget_learningplan_datatable({
                        ids: {programid: main.programid, widgetid: main.widgetid},
                        endpoint: opts.endpoint,
                        requestmode: 'classesforprogram',
                        requestdata: {programid: main.programid},
                        childrenderer: 'eliswidget_learningplan_mdlclass',
                        childopts: opts,
                        lang: opts.lang
                    });
                    main.coursedatatable.doupdatetable();
                }
            });
            jqthis.children('.showhide').trigger('click');
        });
    }

    /**
     * ELIS LearningPlan Widget Top Renderer
     *
     * Usage:
     *     $('[container selector]').eliswidget_learningplan_top(ids, opts);
     *
     * Required Options:
     *     string endpoint The URL to send ajax requests.
     *     object lang     An object of language strings to use throughout the widget.
     *
     * @param object ids An object of relevant IDs. This should contain 'widgetid'.
     * @param object opts Options object (See Options section above for description)
     * @return object jQuery object for each instance.
     */
    $.fn.eliswidget_learningplan_top = function(ids, opts) {
        return this.each(function() {
            var jqthis = $(this);

            /** @var int The ID of the widget this program belongs to. */
            this.widgetid = ids.widgetid;

            var main = this;

            /**
             * Generate a unique ID for a given string name.
             *
             * @param string name A name for the ID.
             * @return string A unique name that contains the given ID.
             */
            this.generateid = function(name) {
                return 'eliswidget_learningplan'+main.widgetid+'_'+name;
            }

            var childrenlist = jqthis.children('.childrenlist');
            if (childrenlist.is(':empty')) {
                var programwrapper = $('<div id="'+main.generateid('programwrapper')+'"></div>');
                var programlist = $('<div id="'+main.generateid('programlist')+'"></div>');
                programwrapper.append(programlist);
                var programpagination = $('<div id="'+main.generateid('programpagination')+'" class="ds_pagelinks"></div>');
                programwrapper.append(programpagination);
                childrenlist.append(programwrapper);

                // Initialize program datatable.
                main.datatable = programlist.eliswidget_learningplan_datatable({
                    ids: {widgetid: main.widgetid},
                    endpoint: opts.endpoint,
                    requestmode: 'programsforuser',
                    requestdata: {widgetid: main.widgetid},
                    childrenderer: 'eliswidget_learningplan_program',
                    childopts: opts,
                    lang: opts.lang
                });
                main.datatable.doupdatetable();
            }
        });
    }

    /**
     * ELIS LearningPlan Widget Initializer
     *
     * Usage:
     *     $('[container selector]').eliswidget_learningplan(options);
     *
     * Required Options:
     *     string endpoint The URL to send ajax requests.
     *     object lang     An object of language strings to use throughout the widget.
     *
     * @param object options Options object (See Options section above for description)
     * @return object jQuery object for each instance.
     */
    $.fn.eliswidget_learningplan = function(options) {
        return this.each(function() {
            var jqthis = $(this);
            var main = this;
            var learningplandiv = jqthis.find('div.learningplan');
            learningplandiv.eliswidget_learningplan_top({widgetid: jqthis.data('id')}, options);
        });
    }
})(jQuery);
