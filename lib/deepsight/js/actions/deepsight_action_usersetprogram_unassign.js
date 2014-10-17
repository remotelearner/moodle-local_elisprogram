/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

(function($) {

/**
 * DeepSight Userset-Program Unassignment
 * An action used in userset-program and program-userset unassignments with a checkbox for the "recursive" flag.
 *
 * Usage:
 *     $('[button selector]').each(function() { $(this).deepsight_action_usersetprogram_unassign(); });
 *
 * Required Options:
 *     object rowdata           An object of information for the associated row.
 *     object parent            A jquery element after which the panel will be added.
 *     string sesskey           The Moodle sesskey (sent with requests to prevent CSRF attacks)
 *     mixed  parentid          When completing the action, this ID will be passed to the actionurl to identify for which element the
 *                              action was completed. Can also be set to "bulklist" to apply the action to the entire bulklist.
 *     object datatable         The datatable object this action is used for.
 *     string actionurl         The URL to call when completing the action.
 *     string name              The name for the action instance.
 *
 * Optional Options:
 *     int    trans_speed       The number of miliseconds to perform animations in.
 *     string wrapper           A html string for a wrapper that will be placed around the action panel.
 *     string actionclass       A CSS class to attach to the action div.
 *     string actiontrclass     A Css class to attach the action's parent.
 *     string desc_single       The message for a single association.
 *     string desc_multiple     The message for a bulk association.
 *     string mode              The mode "unassign"
 *     string lang_bulk_confirm The confirmation message to display when applying the action to the bulklist.
 *     string lang_working      The language string to display while loading / completing the action.
 *     string langrecursive     The language string to display beside the recursive checkbox.
 *     string langyes           The language string for "yes"
 *     string langno            The language string for "no"
 *
 * @param object options Options object (See Options section above for description)
 */
$.fn.deepsight_action_usersetprogram_unassign = function(options) {
    this.default_opts = {
        rowdata: {},
        parent: null,
        sesskey: null,
        parentid: null,
        datatable: null,
        actionurl: null,
        name: null,
        trans_speed: 100,
        wrapper: '<div></div>',
        actionclass: 'deepsight_actionpanel',
        actiontrclass: 'deepsight_actionpanel_tr',
        desc_single: 'You are about to assign this program to this userset. Continue?',
        desc_multiple: 'You are about to assign these programs to this userset. Continue?',
        mode: 'unassign', // TBD
        lang_bulk_confirm: 'Bulk actions can take some time, are you sure you want to continue?',
        lang_working: 'Working...',
        langrecursive: 'langrecursive',
        langyes: 'Yes',
        langno: 'No'
    }

    var opts = $.extend({}, this.default_opts, options);
    var main = this;
    this.actiontr = null;
    this.name = opts.name;
    this.form = null;
    this.parent = opts.parent;

    /**
     * Renders the HTML for the action panel.
     */
    this.render_action = function() {
        var desc = (opts.parentid == 'bulklist') ? opts.desc_multiple : opts.desc_single;

        // Assemble form.
        var recursiveid = 'deepsight_action_usersetprogram_unassign_recursive'; // TBD: bulk?
        if (opts.parentid != 'bulklist') {
            recursiveid += '_'+opts.rowdata.element_id;
        }
        var form = '<form>';
        form += '<input type="checkbox" class="recursive" name="recursive" id="'+recursiveid+'"/>';
        form += '<label for="'+recursiveid+'">'+opts.langrecursive+'</label>';
        form += '</form>';

        var actionpanel = $('<div><div>')
            .addClass(opts.actionclass)
            .addClass('deepsight_action_usersetprogram_assignedit')
            .css('display', 'none');
        var actionpanelbody = '<div class="body">'+desc+form+'</div>\n\
                                <div class="actions"><i class="elisicon-confirm"></i><i class="elisicon-cancel"></i></div>';

        actionpanel.html('<div class="deepsight_actionpanel_inner">'+actionpanelbody+'</div>');
        this.form = actionpanel.find('form');
        actionpanel.find('i.elisicon-confirm').bind('click', main.precomplete_action);
        actionpanel.find('i.elisicon-cancel').click(main.hide_action);
        return actionpanel;
    }

    /**
     * Change the parent ID of the action.
     *
     * Change the element we're performing the action for.
     *
     * @param mixed newparentid Normally this would be an int representing the ID of a single element, but could be any information
     *                           you want passed to the actionurl to represent data. for example, this is an array when this action
     *                           is used with the bulk action panel.
     */
    this.update_parentid = function(newparentid) {
        opts.parentid = newparentid;
    }

    /**
     * Selectively complete action or provide bulk action warning.
     *
     * This is fired first when the user indicates they want to complete the action. If we are completeing the action for one
     * element, this will proceed directly to main.complete_action. If we are applying the action to the bulk list however, it will
     * first display a confirmation message.
     *
     * @param object e The jquery event object that initialized the completion.
     */
    this.precomplete_action = function(e) {
        var recursive = 0;
        var recursivecheckbox = main.form.find('input.recursive')
        if (recursivecheckbox.length > 0) {
            recursive = (recursivecheckbox.prop('checked') == true) ? 1 : 0;
        }

        if (opts.parentid == 'bulklist' && typeof(bulkconfirmed) == 'undefined') {
            main.actiontr.find('.body').html(opts.lang_bulk_confirm);
            main.actiontr.find('i.elisicon-confirm')
                .unbind('click', main.precomplete_action)
                .bind('click', function(e) { main.complete_action(e, recursive); });
        } else {
            main.complete_action(e, recursive);
        }
    }

    /**
     * Completes the action.
     *
     * The user has entered whatever information is required and has click the checkmark.
     *
     * @param object e The jquery event object that initialized the completion.
     * @param int recursive The value of the recursive checkbox, or 0.
     */
    this.complete_action = function(e, recursive) {
        main.actiontr.find('.deepsight_actionpanel').html('<h1>'+opts.lang_working+'</h1>').addClass('loading');
        main.trigger('action_started');

        $.ajax({
            type: 'POST',
            url: opts.actionurl,
            data: {
                uniqid: opts.datatable.uniqid,
                sesskey: opts.sesskey,
                elements: opts.parentid,
                recursive: recursive,
                actionname: main.name
            },
            dataType: 'text',
            success: function(data) {
                try {
                    data = ds_parse_safe_json(data);
                } catch(err) {
                    opts.datatable.render_error(err);
                    return false;
                }

                main.hide_action();

                if (typeof(data.result) != 'undefined' && data.result == 'success') {
                    ds_debug('[deepsight_action_confirm.complete_action] Completed action, recevied data:', data);
                    if (opts.parentid != 'bulklist') {
                        opts.parent.addClass('confirmed').delay(1000).fadeOut(250, function() {
                                opts.datatable.removefromtable('assigned', opts.parent.data('id')); });
                    }
                    main.trigger('action_complete', {opts:opts});
                } else {
                    opts.datatable.render_error(data.msg);
                }

            },
            error: function(jqXHR, textStatus, errorThrown) {
                main.hide_action();
                opts.datatable.render_error(textStatus+' :: '+errorThrown);
            }
        });
    }

    /**
     * Shows the action panel
     */
    this.show_action = function() {
        if (opts.wrapper != null) {
            var rendered = main.render_action();
            rendered.wrap(opts.wrapper);
            main.actiontr = rendered.parents().last();
        } else {
            main.actiontr = main.render_action();
        }

        main.actiontr.addClass(opts.actiontrclass);
        opts.parent.after(main.actiontr);
        main.actiontr.find('.'+opts.actionclass).slideDown(opts.trans_speed);
        opts.parent.addClass('active');
    }

    /**
     * Hides the action.
     *
     * Fired when the "X" button is clicked in the panel, and when completing the action.
     */
    this.hide_action = function() {
        if (main.actiontr != null) {
            var removeactiontr = function() {
                main.actiontr.remove();
                main.actiontr = null;
            }
            main.actiontr.find('.'+opts.actionclass).slideUp(opts.trans_speed, removeactiontr);
            opts.parent.removeClass('active');
        }
    }

    /**
     * Toggles display of the action panel.
     *
     * This is fired when the icon is clicked.
     *
     * @param object e The click event that fired this function.
     */
    this.toggle_action = function(e) {
        if (main.actiontr == null) {
            main.show_action();
        } else {
            main.hide_action();
        }
    }

    /**
     * Set up action.
     */
    this.initialize = function() {
        if (opts.parentid == 'bulklist') {
            var actionicon = $('<i title="'+opts.label+'" class="deepsight_action_'+opts.type+' '+opts.icon+'">'+opts.label+'</i>');
        } else {
            var actionicon = $('<i title="'+opts.label+'" class="deepsight_action_'+opts.type+' '+opts.icon+'"></i>');
            actionicon.fancy_tooltip();
        }

        actionicon.click(main.toggle_action);
        main.append(actionicon);
    }

    this.initialize();
    return this;
}

})(jQuery);
