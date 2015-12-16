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
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

/** @var array The global list of expanded elements. */
var eliswidgetenrolmentexpandedlist = [];

(function($) {

    /**
     * A configurable datatable that can show different types of elements.
     * Usage:
     *     $('[container selector]').eliswidget_enrolment_datatable(options);
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
    $.fn.eliswidget_enrolment_datatable = function(options) {
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
            var allexpanded = $.merge(main.find('.expanded'), window.eliswidgetenrolmentexpandedlist);
            window.eliswidgetenrolmentexpandedlist = $.unique(allexpanded);
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
                    main.children().remove();
                    main.newfilters = data.data.filters;
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

                        // ELIS-9082: Re-expanded expanded entities.
                        if (window.eliswidgetenrolmentexpandedlist.length) {
                            window.eliswidgetenrolmentexpandedlist.each(function() {
                                var jqthis = $(this);
                                var elem = $('.eliswidget_enrolment').find('#'+jqthis.prop('id'));
                                if (typeof(elem[0]) != 'undefined') {
                                    elem.children('.header').trigger('click');
                                    var index = $.inArray(this, window.eliswidgetenrolmentexpandedlist);
                                    if (index >= 0) {
                                        window.eliswidgetenrolmentexpandedlist.splice(index, 1);
                                    }
                                }
                            });
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
     * ELIS Enrolment Widget Class Renderer.
     *
     * Usage:
     *     $('[container selector]').eliswidget_enrolment_pmclass(data, ids, fieldvisibility, opts);
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
    $.fn.eliswidget_enrolment_pmclass = function(data, ids, fieldvisibility, opts, datatable) {
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
                return 'eliswidget_enrolment'+main.widgetid+'_pgm'+main.programid+'_crs'+main.courseid+'_cls'+main.classid+'_'+name;
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
             * Render the class enrolment status, and the link to change it.
             *
             * @param string status The student's current status in the class.
             * @return object jQuery object for the status/link element.
             */
            this.renderstatus = function(status) {
                var status2action = {
                    enroled: 'unenrol',
                    passed: '',
                    failed: '',
                    waitlist: 'leavewaitlist',
                    available: 'enrol',
                    full: 'enterwaitlist',
                    unavailable: ''
                };
                var action = status2action[status];
                if (action == 'enrol' && opts.enrolallowed != '1') {
                    action = '';
                }
                if (action == 'enterwaitlist' && opts.enrolallowed != '1') {
                    action = '';
                }
                if (action == 'unenrol' && opts.unenrolallowed != '1') {
                    action = '';
                }
                var statusele = $('<span id="'+main.generateid('status')+'" class="pmclassstatus"></span>');
                var enrolinfo = '';
                var waitinfo = '';
                if (action != '') {
                    if (status != 'enroled' && this.data.meta.limit > 0) {
                        enrolinfo = ' (';
                        enrolinfo += this.data.meta.total+' '+opts.lang.enrolled+'/'+this.data.meta.limit+' '+opts.lang.max;
                        enrolinfo += ')';
                    }
                }
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
                if (action != '') {
                    statusele.append('<a href="javascript:;">'+opts.lang['action_'+action]+'</a>'+waitinfo);
                } else if (waitinfo != '') {
                    statusele.append(waitinfo);
                }
                statusele.find('a').click(function(e) {
                    main.changestatus(e, action);
                });
                return statusele;
            }

            /**
             * Change the student's status within the class.
             *
             * @param object e Click event from clicking the change status link.
             * @param string action The action to perform.
             */
            this.changestatus = function(e, action) {
                e.preventDefault();
                e.stopPropagation();
                // Check if a dialog is already present.
                if ($('.modaldialog').length) {
                    return false;
                }
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
                        dialogClass: 'modaldialog',
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
                                        m: 'changeclassstatus',
                                        data: {action: action, classid: main.classid},
                                    };
                                    $('#'+main.generateid('status')).find('a').replaceWith('<span class="smloader">'+opts.lang.working+'</span>');
                                    $.ajax({
                                        url: ajaxendpoint,
                                        data: data,
                                        dataType: 'json',
                                        type: 'POST',
                                        success: function(data, textStatus, jqXHR) {
                                            var coursedt = jqthis.closest('.program, .courseset');
                                            if (coursedt) {
                                                // Update the parent table so Course shows updated enrolment status.
                                                // console.debug(coursedt);
                                                if (typeof(coursedt[0].coursedatatable) !== 'undefined') {
                                                    coursedt[0].coursedatatable.doupdatetable();
                                                } else {
                                                    coursedt[0].datatable.doupdatetable();
                                                }
                                            }
                                            main.datatable.doupdatetable();
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
                var status = 'unavailable';
                if (this.data.enrol_id != null) {
                    if (this.data.enrol_completestatusid == 2) {
                        status = 'passed';
                    } else if (this.data.enrol_completestatusid == 1) {
                        status = 'failed';
                    } else {
                        status = 'enroled';
                    }
                } else if (this.data.waitlist_id != null) {
                    status = 'waitlist';
                } else if (this.data.meta.enrolallowed) {
                    status = (this.data.meta.limit > 0 && this.data.meta.total >= this.data.meta.limit) ? 'full' : 'available';
                }

                var details = $('<div class="details"></div>');

                // Visible details.
                for (var fieldalias in fieldvisibility.visible) {
                    var label = fieldvisibility.visible[fieldalias];
                    var value = main.data[fieldalias];
                    details.append(main.generateitem(label, value, fieldalias));
                }

                // Instructors.
                if (this.data.instructors.length > 0) {
                    var instructorshtml = '';
                    for (var i in this.data.instructors) {
                        var href = 'mailto:'+this.data.instructors[i].email;
                        var name = this.data.instructors[i].firstname+' '+this.data.instructors[i].lastname;
                        instructorshtml += '<span><a href="'+href+'">'+name+'</a></span>';
                    }
                    details.append(this.generateitem(opts.lang.data_instructors, instructorshtml, 'instructors'));
                }

                // Class status.
                if ((typeof(main.datatable.newfilters) != 'undefined' && typeof(main.datatable.newfilters["classstatus"]) != 'undefined') ||
                        opts.enrolallowed == '1' || opts.unenrolallowed == '1') {
                    details.append(this.generateitem(opts.lang.data_status, main.renderstatus(status)));
                }
                if (status == 'passed' || status == 'failed') {
                    details.append(this.generateitem(opts.lang.data_grade, this.data.enrol_grade));
                }

                // Hidden details.
                if (Object.keys(fieldvisibility.hidden).length > 0) {
                    var detailshidden = $('<div class="detailshidden" style="display:none;"></div>');
                    for (var fieldalias in fieldvisibility.hidden) {
                        if (typeof main.data[fieldalias] != 'undefined') {
                            var label = fieldvisibility.hidden[fieldalias];
                            var value = main.data[fieldalias];
                            detailshidden.append(main.generateitem(label, value, fieldalias));
                        }
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
                }
                return details;
            }

            jqthis.attr({id: 'pmclass_'+this.classid, class: 'pmclass'});
            jqthis.data('id', this.classid);
            jqthis.append(this.render());
        });
    }

    /**
     * ELIS Enrolment Widget Course Renderer.
     *
     * Usage:
     *     $('[container selector]').eliswidget_enrolment_course(data, ids, fieldvisibility, opts);
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
    $.fn.eliswidget_enrolment_course = function(data, ids, fieldvisibility, opts, datatable) {
        return this.each(function() {
            var jqthis = $(this);

            /** @var object All received data from the ajax request. */
            this.data = data;

            /** @var int The ID of the course. */
            this.courseid = data.element_id;

            /** @var int The ID of the program this course belongs to. */
            this.programid = ids.programid;

            /** @var int The ID of the widget this course belongs to. */
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
                return 'eliswidget_enrolment'+this.widgetid+'_pgm'+main.programid+'_crs'+this.courseid+'_'+name;
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
             * Render the header information for this element.
             *
             * @return object jQuery object for the DOM element that contains the element header.
             */
            this.renderheader = function() {
                var header = $('<div class="header"></div>');
                header.append('<h6 class="title">'+main.data.header+'</h6>');

                if (typeof(main.datatable.newfilters) != 'undefined' && typeof(main.datatable.newfilters["coursestatus"]) != 'undefined') {
                    // Determine status.
                    var status;
                    if (main.data.numenrol > 0) {
                        if (main.data.higheststatus == 2) {
                            status = opts.lang.status_passed;
                        } else if (main.data.higheststatus == 1) {
                            status = opts.lang.status_failed;
                        } else {
                            status = opts.lang.status_enroled;
                        }
                    } else if (main.data.numwaitlist > 0) {
                        status = opts.lang.status_waitlist;
                    } else if (main.data.numnoncompleteprereq > 0) {
                        status = opts.lang.status_prereqnotmet;
                    } else {
                        status = opts.lang.status_notenroled;
                    }
                    header.append('<small>'+status+'</small>');
                }
                // Build and add details.
                var details = $('<div class="details"></div>');
                for (var fieldalias in fieldvisibility.visible) {
                    var label = fieldvisibility.visible[fieldalias];
                    var value = main.data[fieldalias];
                    details.append(main.generateitem(label, value, fieldalias));
                }

                if (Object.keys(fieldvisibility.hidden).length > 0) {
                    var detailshidden = $('<div class="detailshidden" style="display:none;"></div>');
                    for (var fieldalias in fieldvisibility.hidden) {
                        if (typeof main.data[fieldalias] != 'undefined') {
                            var label = fieldvisibility.hidden[fieldalias];
                            var value = main.data[fieldalias];
                            detailshidden.append(main.generateitem(label, value, fieldalias));
                        }
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
                }
                header.append(details);
                return header;
            }

            // Determine if the user can choose classes.
            if (main.data.numenrol == 0 && main.data.numwaitlist == 0 && main.data.numnoncompleteprereq > 0) {
                var canchooseclasses = false;
            } else {
                var canchooseclasses = true;
            }

            jqthis.attr({id: 'course_'+this.courseid, class: 'course'});
            jqthis.data('id', this.courseid);
            jqthis.append(this.renderheader());
            jqthis.append('<div class="childrenlist"></div>');
            jqthis.children('.header').click(function() {
                var childrenlist = jqthis.children('.childrenlist');
                jqthis.toggleClass('expanded');
                if (childrenlist.is(':empty') === true) {
                    if (canchooseclasses === true) {
                        // Add filterbar and classlist elements.
                        var pmclassheading = $('<div class="childrenlistheader"></div>');
                        pmclassheading.append('<h6>'+opts.lang.classes+'</h6>');
                        pmclassheading.append('<span id="'+main.generateid('filterbar')+'" class="filterbar"></span>');
                        childrenlist.append(pmclassheading);
                        var classlist = $('<div id="'+main.generateid('classlist')+'"></div>');
                        childrenlist.append(classlist);
                        var pagination = $('<div id="'+main.generateid('pagination')+'" class="ds_pagelinks"></div>');
                        childrenlist.append(pagination);

                        // Initialize datatable.
                        main.datatable = classlist.eliswidget_enrolment_datatable({
                            ids: {courseid: main.courseid, programid: main.programid, widgetid: main.widgetid},
                            endpoint: opts.endpoint,
                            requestmode: 'classesforcourse',
                            requestdata: {courseid: main.courseid, programid: main.programid},
                            childrenderer: 'eliswidget_enrolment_pmclass',
                            childopts: opts,
                            lang: opts.lang
                        });
                        main.datatable.doupdatetable();
                    }
                }
            });
        });
    }

    /**
     * ELIS Enrolment Widget Courseset Renderer.
     *
     * Usage:
     *     $('[container selector]').eliswidget_enrolment_courseset(data, ids, fieldvisibility, opts);
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
    $.fn.eliswidget_enrolment_courseset = function(data, ids, fieldvisibility, opts, datatable) {
        return this.each(function() {
            var jqthis = $(this);

            /** @var object All received data from the ajax request. */
            this.data = data;

            /** @var int The ID of the courseset. */
            this.coursesetid = data.element_id;

            /** @var int The ID of the program this courseset belongs to. */
            this.programid = ids.programid;

            /** @var int The ID of the widget this courseset belongs to. */
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
                return 'eliswidget_enrolment'+this.widgetid+'_pgm'+main.programid+'_crsset'+this.coursesetid+'_'+name;
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
             * Render the header information for this element.
             *
             * @return object jQuery object for the DOM element that contains the element header.
             */
            this.renderheader = function() {
                var header = $('<div class="header"></div>');
                header.append('<h6 class="title">'+main.data.header+'</h6>');

                // Build and add details.
                var details = $('<div class="details"></div>');
                for (var fieldalias in fieldvisibility.visible) {
                    var label = fieldvisibility.visible[fieldalias];
                    var value = main.data[fieldalias];
                    details.append(main.generateitem(label, value, fieldalias));
                }

                if (Object.keys(fieldvisibility.hidden).length > 0) {
                    var detailshidden = $('<div class="detailshidden" style="display:none;"></div>');
                    for (var fieldalias in fieldvisibility.hidden) {
                        if (typeof main.data[fieldalias] != 'undefined') {
                            var label = fieldvisibility.hidden[fieldalias];
                            var value = main.data[fieldalias];
                            detailshidden.append(main.generateitem(label, value, fieldalias));
                        }
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
                }

                header.append(details);
                return header;
            }

            jqthis.attr({id: 'courseset_'+this.coursesetid, class: 'courseset'});
            jqthis.data('id', this.coursesetid);
            jqthis.append(this.renderheader());
            jqthis.append('<div class="childrenlist"></div>');
            jqthis.children('.header').click(function() {
                var childrenlist = jqthis.children('.childrenlist');
                jqthis.toggleClass('expanded');
                if (childrenlist.is(':empty') === true) {
                    var courseheading = $('<div class="childrenlistheader"></div>');
                    courseheading.append('<h6>'+opts.lang.courses+'</h6>');
                    courseheading.append('<span id="'+main.generateid('coursefilterbar')+'" class="filterbar"></span>');
                    childrenlist.append(courseheading);
                    var courselist = $('<div id="'+main.generateid('courselist')+'"></div>');
                    childrenlist.append(courselist);
                    var coursepagination = $('<div id="'+main.generateid('coursepagination')+'" class="ds_pagelinks"></div>');
                    childrenlist.append(coursepagination);

                    // Initialize datatable.
                    main.datatable = courselist.eliswidget_enrolment_datatable({
                        ids: {coursesetid: main.coursesetid, programid: main.programid, widgetid: main.widgetid},
                        endpoint: opts.endpoint,
                        requestmode: 'coursesforcourseset',
                        requestdata: {coursesetid: main.coursesetid},
                        childrenderer: 'eliswidget_enrolment_course',
                        childopts: opts,
                        lang: opts.lang
                    });
                    main.datatable.doupdatetable();
                }
            });
        });
    }

    /**
     * ELIS Enrolment Widget Program Renderer
     *
     * Usage:
     *     $('[container selector]').eliswidget_enrolment_program(data, ids, fieldvisibility, opts, datatable)
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
    $.fn.eliswidget_enrolment_program = function(data, ids, fieldvisibility, opts, datatable) {
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
                return 'eliswidget_enrolment'+main.widgetid+'_pgm'+main.programid+'_'+name;
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

                // Build and add details.
                var details = $('<div class="details"></div>');
                for (var fieldalias in fieldvisibility.visible) {
                    if (typeof main.data[fieldalias] != 'undefined') {
                        var label = fieldvisibility.visible[fieldalias];
                        var value = main.data[fieldalias];
                        details.append(main.generateitem(label, value, fieldalias));
                    }
                }
                if (Object.keys(fieldvisibility.hidden).length > 0) {
                    var detailshidden = $('<div class="detailshidden" style="display:none;"></div>');
                    for (var fieldalias in fieldvisibility.hidden) {
                        if (typeof main.data[fieldalias] != 'undefined') {
                            var label = fieldvisibility.hidden[fieldalias];
                            var value = main.data[fieldalias];
                            detailshidden.append(main.generateitem(label, value, fieldalias));
                        }
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
                }
                header.append(details);
                return header;
            }

            jqthis.attr({id: 'program_'+this.programid, class: 'program'});
            jqthis.data('id', this.programid);
            jqthis.append(this.renderheader());
            jqthis.append('<div class="childrenlist"></div>');
            jqthis.children('.header').click(function() {
                var childrenlist = jqthis.children('.childrenlist');
                jqthis.toggleClass('expanded');
                if (childrenlist.is(':empty')) {
                    if (main.programid != 'none' && main.data.numcrssets > 0) {
                        var coursesetwrapper = $('<div id="'+main.generateid('coursesetwrapper')+'"></div>');
                        var coursesetheading = $('<div class="childrenlistheader"></div>');
                        coursesetheading.append('<h6>'+opts.lang.coursesets+'</h6>');
                        coursesetheading.append('<span id="'+main.generateid('coursesetfilterbar')+'" class="filterbar"></span>');
                        coursesetwrapper.append(coursesetheading);
                        var coursesetlist = $('<div id="'+main.generateid('coursesetlist')+'"></div>');
                        coursesetwrapper.append(coursesetlist);
                        var coursesetpagination = $('<div id="'+main.generateid('coursesetpagination')+'" class="ds_pagelinks"></div>');
                        coursesetwrapper.append(coursesetpagination);
                        childrenlist.append(coursesetwrapper);

                        // Initialize datatable.
                        main.coursesetdatatable = coursesetlist.eliswidget_enrolment_datatable({
                            ids: {programid: main.programid, widgetid: main.widgetid},
                            endpoint: opts.endpoint,
                            requestmode: 'coursesetsforprogram',
                            requestdata: {programid: main.programid},
                            childrenderer: 'eliswidget_enrolment_courseset',
                            childopts: opts,
                            lang: opts.lang
                        });
                        main.coursesetdatatable.doupdatetable();
                    }

                    var coursewrapper = $('<div id="'+main.generateid('coursewrapper')+'"></div>');
                    var courseheading = $('<div class="childrenlistheader"></div>');
                    courseheading.append('<h6>'+opts.lang.courses+'</h6>');
                    courseheading.append('<span id="'+main.generateid('coursefilterbar')+'" class="filterbar"></span>');
                    coursewrapper.append(courseheading);
                    var courselist = $('<div id="'+main.generateid('courselist')+'"></div>');
                    coursewrapper.append(courselist);
                    var coursepagination = $('<div id="'+main.generateid('coursepagination')+'" class="ds_pagelinks"></div>');
                    coursewrapper.append(coursepagination);
                    childrenlist.append(coursewrapper);

                    // Initialize course datatable.
                    main.coursedatatable = courselist.eliswidget_enrolment_datatable({
                        ids: {programid: main.programid, widgetid: main.widgetid},
                        endpoint: opts.endpoint,
                        requestmode: 'coursesforprogram',
                        requestdata: {programid: main.programid},
                        childrenderer: 'eliswidget_enrolment_course',
                        childopts: opts,
                        lang: opts.lang
                    });
                    main.coursedatatable.doupdatetable();
                }
            });
        });
    }

    /**
     * ELIS Enrolment Widget Top Renderer
     *
     * Usage:
     *     $('[container selector]').eliswidget_enrolment_top(ids, opts);
     *
     * Required Options:
     *     string endpoint The URL to send ajax requests.
     *     object lang     An object of language strings to use throughout the widget.
     *
     * @param object ids An object of relevant IDs. This should contain 'widgetid'.
     * @param object opts Options object (See Options section above for description)
     * @return object jQuery object for each instance.
     */
    $.fn.eliswidget_enrolment_top = function(ids, opts) {
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
                return 'eliswidget_enrolment'+main.widgetid+'_'+name;
            }

            var childrenlist = jqthis.children('.childrenlist');
            if (childrenlist.is(':empty')) {
                var programwrapper = $('<div id="'+main.generateid('programwrapper')+'"></div>');
                var programheading = $('<div class="childrenlistheader"></div>');
                programheading.append('<h6>'+opts.lang.programs+'</h6>');
                // New line for program filters
                programheading.append('<span id="'+main.generateid('programfilterbar')+'" class="filterbar"></span>');
                programwrapper.append(programheading);
                var programlist = $('<div id="'+main.generateid('programlist')+'"></div>');
                programwrapper.append(programlist);
                var programpagination = $('<div id="'+main.generateid('programpagination')+'" class="ds_pagelinks"></div>');
                programwrapper.append(programpagination);
                childrenlist.append(programwrapper);

                // Initialize program datatable.
                main.datatable = programlist.eliswidget_enrolment_datatable({
                    ids: {widgetid: main.widgetid},
                    endpoint: opts.endpoint,
                    requestmode: 'programsforuser',
                    requestdata: {widgetid: main.widgetid},
                    childrenderer: 'eliswidget_enrolment_program',
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
     *     $('[container selector]').eliswidget_enrolment(options);
     *
     * Required Options:
     *     string endpoint The URL to send ajax requests.
     *     object lang     An object of language strings to use throughout the widget.
     *
     * @param object options Options object (See Options section above for description)
     * @return object jQuery object for each instance.
     */
    $.fn.eliswidget_enrolment = function(options) {
        return this.each(function() {
            var jqthis = $(this);
            var main = this;
            var programdiv = jqthis.find('div.program');
            programdiv.eliswidget_enrolment_top({widgetid: jqthis.data('id')}, options);
        });
    }
})(jQuery);
