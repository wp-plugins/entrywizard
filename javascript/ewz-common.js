


/* Disables the selected spreadsheet columns in any other element of the same class */
/* Called on change of any element of class uniq_class                              */
function disable_ss_options(uniq_class) {
   'use strict';
   var nowselected = [];
   // for each ss select list
   jQuery(uniq_class).each(function(index) {
      // enable all items
      var jthis = jQuery(this);
      jthis.children().removeAttr("disabled");
      // save the non-blank selected ones
      if (jthis.attr("value") !== "-1") {
         nowselected[jthis.attr("value")] = jthis.prop("selectedIndex");
      }
   });

   // for each ss select list
   jQuery(uniq_class).each(function(index) {
      // for each selected item
      var sel;
      for (sel in nowselected ) {
         // disable the same item in all the other select lists
         if (sel !== this.value) {
            jQuery(this.options[nowselected[sel]]).attr("disabled", "disabled");
         }
      }
   });
}

/* returns html for a select element with positive integer options start .. end */
/* and a blank option with value "0" */
/**
 *  Returns html for a select element with positive integer options start .. end
 *
 * @param  idstring: string   id of select element
 * @param  namestring: string  name of  select element
 * @param  nosel:     label for "no selection" item
 * @param  start:    first selectable integer
 * @param  end:    last  selectable integer
 * @param  vval:   selected value
 * @return string
 */
function numinput_str(idstring, namestring, nosel, start, end, vval)
{
   'use strict';
   var qidstring, qnmstring, code, sel, i;

   qidstring = "'" + idstring + "'";
   qnmstring = "'" + namestring + "'";
   code = '<select id=' + qidstring + 'name=' + qnmstring + '>';
   sel = '';
   if( nosel ){
        code = code + '<option value="0">' + nosel + '</option>';
    }
   for ( i = start; i <= end; ++i) {
      sel = (i == vval) ? 'selected="selected"' : '';
      code = code + '<option value="' + i + '" ' + sel + ">" + i + "</option>";
   }
   code = code + "</select>";
   return code;
}

/* returns html for a text input element,  max size mw, value vval  with optional onChange function */
function textinput_str(idstring, namestring, mw, vval, func) {
   'use strict';
   var qqidstring, qqnmstring, qqmw, retstr;

   qqidstring = '"' + idstring + '"';
   qqnmstring = '"' + namestring + '"';
   qqmw = '"' + mw + '"';
   retstr = '<input type="text" ';
   if ( typeof  func  !== 'undefined' ){
      retstr += ' ' + func + ' ';
   }
    if( typeof  vval  === 'undefined' ){
        vval = '';
    }
   retstr += ' id=' + qqidstring + ' name=' + qqnmstring + ' maxlength=' + qqmw + ' value="' + vval + '">';
   return retstr;
}

// returns html for a checkbox input element with optional onChange function
function checkboxinput_str(idstring, namestring, checked, func) {
   'use strict';
   var qqidstring, qqnmstring, chkd, retstr, disabled;

   qqidstring = '"' + idstring + '"';
   qqnmstring = '"' + namestring + '"';

   disabled = '';
   if( checked == 'disabled' ){
       disabled = ' disabled="disabled" ';
   }
   chkd = ((true == checked) ? ' checked="checked"' : '');
   retstr = '<input type="checkbox" ';
   if ( typeof func !== 'undefined') {
      retstr += ' ' + func + ' ';
   }
   retstr += ' value="1" id=' + qqidstring + ' name=' + qqnmstring + chkd + disabled + '>';
   return retstr;
}

function isblank(string)
{
   'use strict';
   if (typeof string === 'undefined') {
      return true;
   }
   if (null == string) {
      return true;
   }
   var blankre = /^\s*$/;
   return (blankre.test(string));
}

/* show a help dialog */
function ewz_help(help_id) {
   'use strict';
   jQuery('#' + help_id + '_help').dialog({
      'title': 'EntryWizard Help',
      'dialogClass': 'wp-dialog',
      'autoOpen': false,
      'width': 650,
      'closeOnEscape': true,
       'modal'         : true
          }).dialog('open');
}

function ewz_info(infostring) {
   'use strict';

   jQuery("#info-text").html(infostring);
   jQuery("#info-text").dialog({
      'title': 'Extra Data Uploaded by Administrator',
      dialogClass: 'wp-dialog',
      'autoOpen': false,
      'width': 500,
      'closeOnEscape': true
   }).dialog('open');
}

function field_values(fieldtype, isreq, select_name, select_id, options) {
   'use strict';
   var txt = '';
   switch (fieldtype) {
      case 'str':
      case 'img':
         if (isreq === 0) {
            txt += '      <select name="' + select_name + '" id="' + select_id + '">';
            txt += '         <option value="~*~">Any </option>';
            txt += '         <option value="~-~">Blank </option>';
            txt += '         <option value="~+~">Not Blank </option>';
            txt += '      </select>';
         } else {
            txt += ' --- <input type="hidden" name="' + select_name + '" id="' + select_id + '" value="~*~">';
         }
         break;
      case 'opt':
         txt += '          <select name="' + select_name + '" id="' + select_id + '">';
         txt += '             <option value="~*~">Any </option>';
         if (isreq === 0) {
            txt += '         <option value="~-~">Blank </option>';
            txt += '         <option value="~+~">Not Blank </option>';
         }
         txt += options;
         txt += '          </select>';
         break;
       case 'rad':
            txt += '      <select name="' + select_name + '" id="' + select_id + '">';
            txt += '         <option value="~*~">Any </option>';
            txt += '         <option value="~+~">Checked </option>';
            txt += '         <option value="~-~">Not Checked </option>';
            txt += '      </select>';
         break;
       case 'chk':
            txt += '      <select name="' + select_name + '" id="' + select_id + '">';
            txt += '         <option value="~*~">Any </option>';
            txt += '         <option value="~+~">Checked </option>';
            txt += '         <option value="~-~">Not Checked </option>';
            txt += '      </select>';
         break;
      case undefined:
         break;
   }
   return txt;
}



/* Opens the associated closed postbox, or closes the open one */
function toggle_postbox(handle) {
   'use strict';
   jQuery(handle).closest('.postbox').toggleClass('closed');
}

function add_expander() {
   'use strict';
   var close_text, open_text, open_close;

   // add 'Collapse All' and 'Expand All'
   close_text = 'Collapse All';
   open_text = 'Expand All';
   open_close = jQuery('<a href="#" id="expandall" class="ewz_expand">' + open_text + '</a>');
   open_close.click(function(e) {
      e.preventDefault();
      if (jQuery(this).text() === close_text) {
         jQuery(".postbox").addClass('closed');
         jQuery(this).text(open_text);
      } else {
         jQuery(".postbox").removeClass('closed');
         jQuery(this).text(close_text);
      }
   });
   jQuery('#wpbody').prepend(open_close);
}

/* return the html for an image format selection box */
/* called by img_fields_str */
function imgformat_input_str(id, name, values) {
   'use strict';
   var str = '<select multiple="multiple" id="' + id + '" name="' + name + '">';
   str += '   <option value="image/jpeg" ' + (values.lastIndexOf('image/jpeg') >= 0 ? " selected" : '') + '>jpeg/jpg</option>';
   str += '   <option value="image/pjpeg" ' + (values.lastIndexOf('image/pjpeg') >= 0 ? " selected" : '') + '>pjpeg (older IE)</option>';
   str += '   <option value="image/gif" ' + (values.lastIndexOf('image/gif') >= 0 ? " selected" : '') + '>gif</option>';
   str += '   <option value="image/png" ' + (values.lastIndexOf('image/png') >= 0 ? " selected" : '') + '>png</option>';
   str += '</select> ';
   return str;
}

/* find and return from an array of objects the index of the first one found that has the_key = the_value */
function js_find_by_key(the_array, the_key, the_value) {
   'use strict';
   var k, len;

   if (null === the_array) {
      throw new TypeError();
   }
   len = the_array.length;
   if (len === 0) {
      return -1;
   }
   if (0 >= len) {
      return -1;
   }
   for (k = 0; k < len; k++) {
      if (typeof the_array[k] !== 'undefined' && the_array[k][the_key] == the_value) {
         return k;
      }
   }
   return -1;
}
/************** Functions to fix older IE versions  **********************/

/* To stop IE from generating errors if a console.log call was left in */
function fixConsole()
{
   if (typeof console === 'undefined')
   {
      console = {}; // define it if it doesn't exist already
   }
   if (typeof console.log === 'undefined')
   {
      console.log = function() {
      };
   }
   if (typeof console.dir === 'undefined')
   {
      console.dir = function() {
      };
   }
}

if (!Array.prototype.indexOf) {
   Array.prototype.indexOf = function(find, i /*opt*/) {
      "use strict";
      var n, t, len, k, kstart;

      n = 0;
      t = Object (this);
      len = t.length >>> 0;
      if (len === 0) {
         return -1;
      }

      if (n >= len) {
         return -1;
      }
      k;
      kstart = n >= 0 ? n : Math.max(len - Math.abs(n), 0);
      for ( k = kstart ; len > k; k++ ) {
         if ( typeof t[k] !== 'undefined' && t[k] === find) {
            return k;
         }
      }
      return -1;
   };
}

if (!Array.prototype.forEach) {
   Array.prototype.forEach = function(action, that /*opt*/) {
      'use strict';
      var i, n;

      for ( i = 0, n = this.length; i < n; i++){
         if (typeof this[i] !== 'undefined'){
            action.call(that, this[i], i, this);
         }
      }
   };
}

if (!Array.prototype.lastIndexOf)
{
   Array.prototype.lastIndexOf = function(searchElement, fromIndex/*opt*/)
   {
      "use strict";
      var t, len,n, k;

      t = Object ( this );
      len = t.length >>> 0;
      if (len === 0){
         return -1;
      }
      n = len;
      if (arguments.length > 1)
      {
         n = Number(fromIndex);
         if (n != n){
            n = 0;
         }
         else if ( n != 0 && n != ( 1 / 0 ) && n != -( 1 / 0 ) ){
            n = (n > 0 || -1) * Math.floor(Math.abs(n));
         }
      }

      k = n >= 0
              ? Math.min(n, len - 1)
              : len - Math.abs(n);

      for ( ; k >= 0 ; k-- )
      {
         if (k in t && t[k] === searchElement){
            return k;
         }
      }
      return -1;
   };
}

/***********************************************************************************/
