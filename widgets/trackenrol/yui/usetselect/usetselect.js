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
 * @package    eliswidget_trackenrol
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 */

YUI.add('moodle-eliswidget_trackenrol-usetselect', function(Y) {

    /**
     * The filterbase module
     * @property FILTERBASENAME
     * @type {String}
     * @default "widget-usetselect"
     */
    var FILTERBASENAME = 'widget-usetselect';

    /**
     * This method calls the base class constructor
     * @method FILTERBASE
     */
    var FILTERBASE = function() {
        FILTERBASE.superclass.constructor.apply(this, arguments);
    }

    /**
     * @class M.eliswidget_trackenrol.usetselect
     */
    Y.extend(FILTERBASE, Y.Base, {
        /**
         * The select id
         * @property id
         * @type {String}
         * @default ''
         */
        id : '',

        /**
         * Initialize the userset select module
         * @param array args function arguments: array(id)
         */
        initializer : function(args) {
            this.id = args[0];
            var selectelem = Y.one('#'+this.id);
            if (selectelem) {
                selectelem.on('change', this.updateoptions, this);
            }
        },

        /**
         * Unserialize value serialized using PHP serialize() function
         * @param string data the serialized data.
         * @return string the unserialized value.
         */
        php_unserialize : function(data) {
            //  discuss at: http://phpjs.org/functions/unserialize/
            // original by: Arpad Ray (mailto:arpad@php.net)
            // improved by: Pedro Tainha (http://www.pedrotainha.com)
            // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
            // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
            // improved by: Chris
            // improved by: James
            // improved by: Le Torbi
            // improved by: Eli Skeggs
            // bugfixed by: dptr1988
            // bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
            // bugfixed by: Brett Zamir (http://brett-zamir.me)
            //  revised by: d3x
            //    input by: Brett Zamir (http://brett-zamir.me)
            //    input by: Martin (http://www.erlenwiese.de/)
            //    input by: kilops
            //    input by: Jaroslaw Czarniak
            //        note: We feel the main purpose of this function should be to ease the transport of data between php & js
            //        note: Aiming for PHP-compatibility, we have to translate objects to arrays
            //   example 1: unserialize('a:3:{i:0;s:5:"Kevin";i:1;s:3:"van";i:2;s:9:"Zonneveld";}');
            //   returns 1: ['Kevin', 'van', 'Zonneveld']
            //   example 2: unserialize('a:3:{s:9:"firstName";s:5:"Kevin";s:7:"midName";s:3:"van";s:7:"surName";s:9:"Zonneveld";}');
            //   returns 2: {firstName: 'Kevin', midName: 'van', surName: 'Zonneveld'}
          
            var that = this,
              utf8overhead = function (chr) {
                // http://phpjs.org/functions/unserialize:571#comment_95906
                var code = chr.charCodeAt(0);
                if (code < 0x0080) {
                  return 0;
                }
                if (code < 0x0800) {
                  return 1;
                }
                return 2;
              };
            error = function (type, msg, filename, line) {
              throw new that.window[type](msg, filename, line);
            };
            read_until = function (data, offset, stopchr) {
              var i = 2,
                buf = [],
                chr = data.slice(offset, offset + 1);
          
              while (chr != stopchr) {
                if ((i + offset) > data.length) {
                  error('Error', 'Invalid');
                }
                buf.push(chr);
                chr = data.slice(offset + (i - 1), offset + i);
                i += 1;
              }
              return [buf.length, buf.join('')];
            };
            read_chrs = function (data, offset, length) {
              var i, chr, buf;
          
              buf = [];
              for (i = 0; i < length; i++) {
                chr = data.slice(offset + (i - 1), offset + i);
                buf.push(chr);
                length -= utf8overhead(chr);
              }
              return [buf.length, buf.join('')];
            };
            _unserialize = function (data, offset) {
              var dtype, dataoffset, keyandchrs, keys, contig,
                length, array, readdata, readdata, ccount,
                stringlength, i, key, kprops, kchrs, vprops,
                vchrs, value, chrs = 0,
                typeconvert = function (x) {
                  return x;
                };

              if (!offset) {
                offset = 0;
              }
              dtype = (data.slice(offset, offset + 1))
                .toLowerCase();

              dataoffset = offset + 2;

              switch (dtype) {
              case 'i':
                typeconvert = function (x) {
                  return parseInt(x, 10);
                };
                readdata = read_until(data, dataoffset, ';');
                chrs = readdata[0];
                readdata = readdata[1];
                dataoffset += chrs + 1;
                break;
              case 'b':
                typeconvert = function (x) {
                  return parseInt(x, 10) !== 0;
                };
                readdata = read_until(data, dataoffset, ';');
                chrs = readdata[0];
                readdata = readdata[1];
                dataoffset += chrs + 1;
                break;
              case 'd':
                typeconvert = function (x) {
                  return parseFloat(x);
                };
                readdata = read_until(data, dataoffset, ';');
                chrs = readdata[0];
                readdata = readdata[1];
                dataoffset += chrs + 1;
                break;
              case 'n':
                readdata = null;
                break;
              case 's':
                ccount = read_until(data, dataoffset, ':');
                chrs = ccount[0];
                stringlength = ccount[1];
                dataoffset += chrs + 2;

                readdata = read_chrs(data, dataoffset + 1, parseInt(stringlength, 10));
                chrs = readdata[0];
                readdata = readdata[1];
                dataoffset += chrs + 2;
                if (chrs != parseInt(stringlength, 10) && chrs != readdata.length) {
                    error('SyntaxError', 'String length mismatch');
                }
                break;
              case 'a':
                readdata = {};

                keyandchrs = read_until(data, dataoffset, ':');
                chrs = keyandchrs[0];
                keys = keyandchrs[1];
                dataoffset += chrs + 2;

                length = parseInt(keys, 10);
                contig = true;

                for (i = 0; i < length; i++) {
                  kprops = _unserialize(data, dataoffset);
                  kchrs = kprops[1];
                  key = kprops[2];
                  dataoffset += kchrs;

                  vprops = _unserialize(data, dataoffset);
                  vchrs = vprops[1];
                  value = vprops[2];
                  dataoffset += vchrs;

                  if (key !== i) {
                    contig = false;
                  }
                  readdata[key] = value;
                }

                if (contig) {
                  array = new Array(length);
                  for (i = 0; i < length; i++)
                    array[i] = readdata[i];
                  readdata = array;
                }

                dataoffset += 1;
                break;
              default:
                error('SyntaxError', 'Unknown / Unhandled data type(s): ' + dtype);
                break;
              }
              return [dtype, dataoffset - offset, typeconvert(readdata)];
            };

            return _unserialize((data + ''), 0)[2];
        },

        /**
         * Update options on child pulldown for userset select
         * @param object ev the event that triggered call.
         * @return boolean true on success, false otherwise
         */
        updateoptions : function(ev) {
            var elem = Y.one('#'+this.id);
            if (!elem) {
                return false;
            }
            // console.debug(ev);
            var target = ev._event.explicitOriginalTarget;
            // console.debug(target);
            // console.debug(ev.target);
            // console.debug(ev.currentTarget);
            var val = target.value;
            var selected = target.selected;
            var update_children = function(ctx, baseelem, sval, isselected) {
                if (sval.indexOf('a:') != 0) {
                    return;
                }
                var uslist = ctx.php_unserialize(sval);
                // console.debug(uslist);
                if (uslist.length < 2) {
                    return;
                }
                for (var i = 1; i < uslist.length; ++i) {
                    var childmatch = '{i:0;i:'+uslist[i]+';';
                    baseelem.get('options').each( function() {
                        if (this.get('value').indexOf(childmatch) != -1) {
                            this.set('selected', isselected ? 'selected' : '');
                            update_children(ctx, baseelem, this.get('value'), isselected);
                        }
                    });
                }
            };
            update_children(this, elem, val, selected);
            return true;
        }
    },
    {
        NAME : FILTERBASENAME,
        ATTRS : { id: ''}
    }
    );

    // Ensure that M.eliswidget_trackenrol exists and that filterbase is initialised correctly
    M.eliswidget_trackenrol = M.eliswidget_trackenrol || {};

    /**
     * Entry point for userset select module
     * @param string pid parent pulldown field id
     * @param int    id pulldown field id
     * @param string path web path to report instance callback
     * @return object the userset select object
     */
    M.eliswidget_trackenrol.init_usetselect = function(id) {
        var args = [id];
        return new FILTERBASE(args);
    }

}, '@VERSION@', { requires : ['base', 'event', 'json', 'node'] }
);
