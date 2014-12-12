'use strict';
jQuery(document).ready(function() {
    init_ewz_layouts();
});
var ewzG;
/************************ The OnLoad Function  ****************************************/
/* called on load                                   */
/* generates the whole page from the ewzG structure */
function init_ewz_layouts() {
    fixConsole();
    var i, restr;
    // ewzG is null if not logged in
    if (null !== ewzG1) {
        ewzG = ewzG1.gvar;
        for (i = 0; i < ewzG.layouts.length; ++i) {
            // error message, page titles, fields
            jQuery('#ewz_layouts').append(layout_str(i, ewzG.layouts[i]));

            // restrictions (one blank for each existing - will fill out later
            for (restr = 0; restr < ewzG.layouts[i].restrictions.length; ++restr) {
                jQuery('#restricts_f' + i + '_').append(new_restriction_str(i, jQuery('#add_restr_f' + i + '_').get()));
            }
            // add some functionality
            setup_layout(i, i);
        }
        if (ewzG.message) {
            alert(ewzG.message);
        }
        // add the "add layout" button if user has right permissions
        if (ewzG.can_do) {
            jQuery('#ewz_layouts').append(add_layout_button_str());
        }
        // add expand_all, close_all
        add_expander();
    }
}


/************************ Functions Returning an HTML String ****************************************/

/* Returns the html string for a postbox containing a single layout */
function layout_str(lnum, fObj) {

    var i, key, str, kvalue, khead, korigin;
    /*********** Postbox *************/
    str = '<div id="ewz_admin_layouts_f' + lnum + '_" class="metabox-holder">';

    str += '    <div id="ewz_postbox-layout_f' + lnum + '_" class="postbox closed" style="display: block;" >';
    str += '       <div class="handlediv" onClick="toggle_postbox(this)" title="Click to toggle"><br /></div>';
    str += '       <h3 id="tpg_header_f' + lnum + '_"  class="hndle" onClick="toggle_postbox(this)">' + fObj.layout_name + '</h3>';

    /*********** General *************/
    str += '       <div class="inside">';
    str += '          <form method="POST" action="" id="cfg_form_f' + lnum + '_" ';
    str += '                onSubmit="return ewz_check_layout_input(this, ' + ewzG.jsvalid + ')">';
    str += '          <div class="ewzform">';
    str += '             <input type="hidden" name="ewzmode" value="layout">';
    str += '             <input type="hidden" name="layout_id" id="layout_id' + lnum + '_"value="' + fObj.layout_id + '">';
    str += '             <div class="ewz_data ewz_95">';
    if (fObj.n_webforms > 0) {
        str += '      <div class="ewz_warn">Warning: This layout is in use by ' + fObj.n_webforms + ' webforms.<br /> ';
        if (fObj.n_items > 0) {
            str += fObj.n_items + ' items have already been uploaded. <br />';
            str += 'Making changes now could cause serious problems.';
        } else {
            str += 'Changes made now will affect them, <br />but no items have yet  been uploaded.';
        }
        str += '      </div>';
    }
    str += '                <p class="ewz_sect_title"><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'layout\')">&nbsp;General Information</p>';
    str += '                <table>';
    str += '                    <tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'name\')">&nbsp;Name for this layout</td>';
    str += '                        <td>' + textinput_str('f' + lnum + '_layout_name_', 'layout_name', 50, fObj.layout_name) + '</td>';
    str += '                    </tr>';
    str += '                    <tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'maxnum\')">&nbsp;Maximum number of items &nbsp;</td>';
    str += '                        <td>' + numinput_str('f' + lnum + '_max_num_items_', 'max_num_items', '', 1, ewzG.maxNumitems, Number(fObj.max_num_items));
    str += '                        &nbsp; &nbsp; Overrideable by webforms: &nbsp; ' + checkboxinput_str('f' + lnum + '_override_', 'override', fObj.override ) + '</td>';
    str += '                    </tr>';
    str += '                </table>';
    str += '             </div>';

    /*********** Fields *************/
    str += '             <div class="ewz_data ewz_95">';
    str += '                <span class="ewz_sect_title"><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'field\')">';
    str += '                   Fields to be entered/uploaded for each item</span> &nbsp; &nbsp; &nbsp; ';
    str += '                      ( <i>Items affected by restrictions are outlined in red and may not be edited</i> <img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'restr\')"> &nbsp;)</p>';
    str += '                <div class="ewz_95">';
    str += '                   <div  id="ewz_sortable_f' + lnum + '_" >';
    // <br> needed at top and bottom to drag a field to the top or bottom
    str += '<br />';
    for (i = 0; i < fObj.forder.length; ++i) {
        str += field_str(lnum, fObj.forder[i], fObj.fields[fObj.forder[i]]);
    }
    str += '<br />';
    str += '                   </div>';
    str += '                   <p>';
    str += '                      <img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'ftype\')">&nbsp;Add another field: &nbsp; ';
    str += '                      <button type="button" class="button-secondary" id="addTextBtn_f' + lnum + '_" onClick="add_field( this, ' + "'str'" + ')">';
    str += '                          A Text Entry</button>';
    str += '                      <button type="button" class="button-secondary" id="addImgBtn_f' + lnum + '_" onClick="add_field( this, ' + "'img'" + ')">';
    str += '                          An Image File</button>';
    str += '                      <button type="button" class="button-secondary" id="addOptBtn_f' + lnum + '_" onClick="add_field( this, ' + "'opt'" + ')">';
    str += '                          A Drop-down Selection</button>';
    str += '                      <button type="button" class="button-secondary" id="addRadBtn_f' + lnum + '_" onClick="add_field( this, ' + "'rad'" + ')">';
    str += '                          A Radio Button</button>';
    str += '                      <button type="button" class="button-secondary" id="addChkBtn_f' + lnum + '_" onClick="add_field( this, ' + "'chk'" + ')">';
    str += '                          A Check Box</button>';
    str += '                  </p>';
    str += '                </div>';
    str += '             </div>';

    /*********** Restrictions *************/
    str += '             <div class="ewz_data ewz_95">';
    str += '                <p class="ewz_sect_title"><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'restr\')">';
    str += '                    Optional Restrictions On Allowed Field Values';
    str += '                </p>';
    str += '                <div class="ewz_95">';
    str += '                   <div id="restricts_f' + lnum + '_">';
    str += '                   </div>';
    str += '                   <button type="button" id="add_restr_f' + lnum + '_" class="button-secondary" onClick="add_restriction(this)">';
    str += '                         Add A New Restriction</button>';
    str += '                </div>';
    str += '             </div>';

    /*********** Spreadsheet *************/
    str += '             <div class="ewz_data ewz_95">';
    str += '                <p class="ewz_sect_title"><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'xtra\')">';
    str += '                    Extra Data For Display Only';
    str += '                </p>';
    str += '                <div class="ewz_95">';
    str += '                   <div id="spread_f' + lnum + '_" class="postbox closed">';
    str += '                      <div class="handlediv" onClick="toggle_postbox(this)" title="Click to toggle"><br /></div>';
    str += '                      <h3 id="hspread_f' + lnum + '_" class="hndle"  onClick="toggle_postbox(this)">Select Items</h3>';
    str += '                      <div class="inside">';
    str += '                         <table class="ewz_field">';


    for (key in ewzG.display) {
        if (ewzG.display.hasOwnProperty(key)) {
            kvalue = fObj.extra_cols[key];
            khead = ewzG.display[key].header;
            korigin = ewzG.display[key].origin;
            str += '                            <tr><td>' + khead + ' ( <i> ' + korigin + ' data )</i></td><td>';
            str += colinput_str('f' + lnum + '_extra_cols_' + key + '_', 'extra_cols[' + key + ']', kvalue, 'ssc' + lnum);
            str += '                            </td></tr>';
        }
    }

    str += '                         </table>';
    str += '                      </div>';
    str += '                   </div>';
    str += '                </div>';
    str += '             </div>';

    /*********** Save/Delete *************/
    str += '                <div class="ewz_numc"></div>';
    str += '                   <button type="submit" id="lsub_f' + lnum + '_" class="button-primary">Save Changes to <i>' + fObj.layout_name + '</i></button> &nbsp;  &nbsp;  &nbsp;  &nbsp; ';

    if (ewzG.can_do) {
        str += '               <button type="button" id="ldel_f' + lnum + '_" class="button-secondary" ';
        str += 'onClick="delete_layout(this, ' + fObj.n_webforms + ', ' + fObj.n_items + ' )">Delete <i>' + fObj.layout_name + '</i>';
        str += '               </button>';
    }
    str += '                </p>';
    str += '          <div id="waitmessage"></div></div>';
    str += '          </form>';
    str += '       </div>';
    str += '    </div>';
//    str +=   '  </div>';
    str += '</div>';
    return str;
}

/* return the html for a new blank restriction */
function new_restriction_str(lnum, button) {

    var restrs = jQuery(button).parent().find('button[id^="x' + jQuery(button).attr("id") + '"]'),
        rnum = 0,
        txt, rid, jcurr_form,
        msgnm = '';

    if (restrs.length) {
        rnum = restrs.last().attr("id").replace(/^.*R/, '').replace(/_$/, '');
        rnum = parseInt(rnum, 10 ) + 1;
    }
    txt = '<div id="restr_title_f' + lnum + '_r' + rnum + '_" class="ewz_subpost postbox closed">';
    txt += '   <div class="handlediv" onClick="toggle_postbox(this)" title="Click to toggle"><br /></div>';
    txt += '   <h3 id="new_restr_f' + lnum + '_r' + rnum + '_" class="hndle"  onClick="toggle_postbox(this)">-- New Restriction --</h3>';
    txt += '   <div class="inside">';

    rid = jQuery(button).attr("id") + "R" + rnum + '_';
    txt += '       <div class="ewz_add_restr" id="' + rid + '">';
    txt += '          <table class="ewz_field"><tr><td colspan="2">Forbidden combination:</td></tr>';
    jcurr_form = jQuery(button).closest('form[id^="cfg_form_f"]');

    jcurr_form.find('select[id$="_field_type_"]').each( function() {
        var inside, field_id, nmstr, fieldname, isreq, fieldtype, option_list;
        
        inside = jQuery(this).closest('div[class="inside"]');
        field_id = inside.find('input[name^="fields"]').filter(":hidden").val();
        if( inside.find( 'input[id$="_field_ident_"]').val() != 'followupQ' ){
            nmstr = 'restrictions[' + rnum + '][' + field_id + ']';
            fieldname = inside.find('input[id$="field_header_"]').val();
            isreq = inside.find('input[id$="_required_"]').filter(":checked").length;
            fieldtype = jQuery("option:selected", this).val();

            txt += '   <tr><td class="ewz_leftpad">' + ewz_esc(fieldname) + ":</td>";
            txt += '       <td>';

            // do this in javascript instead of getting from db so it will reflect current changes
            if ('opt' === fieldtype) {
                option_list = '';
                inside.find('table[id^="data_fields_"]').find('tr[id$="_row_"]').each( function() {
                    var label, val;
                    label = jQuery(this).find('input[id$="_label_"]').val();
                    val = jQuery(this).find('input[id$="_value_"]').val();

                    option_list += '         <option value="' + val + '">' + label + '</option>';
                });
            }

            txt += field_values(fieldtype, isreq, nmstr, 'f' + lnum + '_restrictions_' + rnum + '__' + field_id + '_', option_list);
        }
    });
   
    txt += '       </td>';
    txt += '   </tr>';
    txt += '   <tr><td ><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'rmsg\')">&nbsp;Message: ';
    msgnm = 'restrictions[' + rnum + '][msg]';
    txt += '               </td><td><input type="text" name="' + msgnm + '" id="f' + lnum + '_restrictions_' + rnum + '__msg_">';
    txt += '               </td>';
    txt += '             </tr>';
    txt += '          </table>';
    txt += '   </div>';
    txt += '   <p style="text-align:right">';
    txt += '       <button type="button" class="button-secondary" id="x' + rid + '" onClick="delete_restr(this)">Remove Restriction</button>';
    txt += '   </p>';
    txt += '</div>';

    return txt;
}

function add_layout_button_str() {
    var str = '<div class="alignleft">';
    str += '       <button  type="button" id="add_layout" class="button-secondary" onClick="add_layout_copy()">Add a New Layout</button>';
    str += '           &nbsp; with options copied from: ';
    str += '       <select id="ewz_addlayout" >';
    str += ewzG.layouts_options;
    str += '       </select> ';
    str += ' &nbsp; ( <i>Restrictions will not be copied</i> ) ';
    str += '    </div> ';
    return str;
}

/* return the html for a "field" div */
function field_str(lnum, i, fObj) {
    var fld, fid, str;

    fld = 'fields[' + i + ']';
    fid = "f" + lnum + '_fields' + i + '_';
    str = '<div id="' + fid + 'field_mbox_" class="postbox closed">';

    str += '  <div class="handlediv" onClick="toggle_postbox(this)" title="Click to toggle"><br /></div>';
    str += '  <h3 class="hndle" onClick="toggle_postbox(this)" id="field_title_' + fid + '">' + fObj.field_header + '</h3>';
    str += '  <div class="inside">';
    str += '     <input type="hidden" name="' + fld + '[field_id]' + '" value="' + fObj.field_id + '">';
    str += '     <input type="hidden" name="forder[]" value="forder_f' + lnum + '_c' + i + '_">';
    str += '     <table  class="ewz_field">';
    str += '       <tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'fhead\')">&nbsp;Column Header For Web Page: </td>';
    str += '           <td>' + textinput_str(fid + 'field_header_', fld + '[field_header]', 40, fObj.field_header, 'onChange="update_title(this)"') + '</td>';
    str += '           <td rowspan="5"  id="special_' + fid + '" > ' + type_data_field_str(lnum, fid, fld, fObj.field_type, fObj.fdata) + '</td>';
    str += '       </tr>';
    str += '       <tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'ftype\')">&nbsp;Data Type: </td>';
    str += '           <td class="ewz_shaded">' + type_opt_str(lnum, fid, fld, fObj.field_type) + '</td>';
    str += '       </tr>';
    str += '       <tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'sshead\')">&nbsp;Field Identifier: </td>';
    str += '           <td>' + textinput_str(fid + 'field_ident_', fld + '[field_ident]', 20, fObj.field_ident) + '</td>';
    str += '       </tr>';
    str += '       <tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'sscol\')">&nbsp;Spreadsheet Column: </td>';
    str += '           <td>' + colinput_str(fid + 'ss_column_', fld + '[ss_column]', fObj.ss_column, 'ssc' + lnum) + '</td>';
    str += '       </tr>';
    var rq = fObj.required;
    if( fObj.field_type == 'chk' || fObj.field_type == 'rad' ){
       rq = 'disabled';
    }
    str += '       <tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'req\')">&nbsp;Required: </td>';
    str += '           <td>' + checkboxinput_str(fid + 'required_', fld + '[required]', fObj.required, rq) + '</td>';
    str += '       </tr>';

    str += '       <tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'append\')">&nbsp;Append to Previous Column in Webform: </td>';
    str += '           <td>' + checkboxinput_str(fid + 'append_', fld + '[append]', fObj.append ) + '</td>';
    str += '       </tr>';
    
    str += '     </table>';
    str += '     <div style="text-align:right; padding:10px;"> ';
    str += '        <button type="button" class="button-secondary" id="del_' + fid + '" onClick="delete_field(this)">Delete Field</button>';
    str += '     </div>';
    str += '  </div>';
    str += '</div>';
    return str;
}


/* return the html string for the data specific to the field type - image size limits, options, text input length, etc */
/* appears to the right of the header, datatype, etc items that are common to all field types                          */
function type_data_field_str(lnum, fid, fld, field_type, fdata) {
    var str, fdid, fdld;
    // hide the table borders for a radio button, which has no options
    var hidetable = ( field_type == 'rad' ) ?  ' style="border: #EEEEEE";' : '';
    str = '<table ' + hidetable + ' id="data_fields_' + fid + '"  class="widefat ewz_shaded ewz_field">';
    fdid = fid + 'fdata_';
    fdld = fld + '[fdata]';
    switch (field_type) {
        case "str":
            str += text_fields_str(lnum, fdid, fdld, fdata);
            break;
        case "img":
            str += img_fields_str(lnum, fdid, fdld, fdata);
            break;
        case "opt":
            str += opt_fields_str(fdid, fdld, fdata);
            break;
        case "rad":
            str += rad_fields_str(fdid, fdld, fdata);
            break;
        case "chk":
            str += chk_fields_str(fdid, fdld, fdata);
            break;
    }
    str += '</table>';
    if ('opt' === field_type) {
        str += '<br /><button type="button" id="' + fid + 'add_" class="button-secondary" onClick="add_option(this)">Add an Option</button>';
    }
    return str;
}



/* return the html for setting the specific text field data - number of chars visible and max number of chars */
/* called by type_data_field_str */
function text_fields_str(lnum, fdid, fdld, tObj)
{
    var str = '';
    if (tObj) {
        str += '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'maxchar\')">&nbsp;Maximum number of characters: </td><td>' + numinput_str(fdid + 'maxstringchars_', fdld + '[maxstringchars]', '', 1, 200, Number(tObj.maxstringchars)) + '</td></tr>';
        str += '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'maxvis\')">&nbsp;Number of characters visible in input: </td><td>' + numinput_str(fdid + 'fieldwidth_', fdld + '[fieldwidth]', '', 1, 100, Number(tObj.fieldwidth)) + '</td></tr>';
        str += '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'frmtstr\')">&nbsp;Spreadsheet Column for Formatted Text:</td><td>' + colinput_str(fdid + 'ss_col_fmt_', fdld + '[ss_col_fmt]', tObj.ss_col_fmt, 'ssc' + lnum) + '</td></tr>';
    }
    return str;
}

/* return the html for setting the specific select-option data - options, labels and max of each allowed */
/* called by type_data_field_str */
function opt_fields_str(fdid, fdld, oObj)
{
    var str = '',
        len = 0,
        rownum;
    if (oObj.options) {
        len = oObj.options.length;
    }
    str += "<tr><th>Label for Web Page</th><th>Value Stored</th><th colspan='2'>Maximum number allowed</th>";
    str += '    <th><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'opt\')"></th></tr>';
    for (rownum = 0; rownum < len; ++rownum) {
        str += opt_row_str(fdid, fdld, rownum, oObj.options[rownum], len );
    }
    return str;
}
function opt_row_str(fdid, fdld, rownum, opts, numrows) {
    var label, value, maxnum, rowfid, rowfld, str;

    label = opts ? opts.label : '';
    value = opts ? opts.value : '';
    maxnum = opts ? Number(opts.maxnum) : '';
    rowfid = fdid + rownum + '_';
    rowfld = fdld + '[options][' + rownum + ']';
    str = '<tr id="' + rowfid + 'row_">';
    str += '   <td>' + textinput_str(rowfid + "label_", rowfld + "[label]", 20, label) + '</td>';
    str += '   <td>' + textinput_str(rowfid + "value_", rowfld + "[value]", 20, value) + '</td>';
    str += '   <td>' + numinput_str(rowfid + "maxnum_", rowfld + "[maxnum]", 'no max', 1, 100, maxnum) + '</td>';
    str += '   <td><input type="radio" name="' + fdid + '_optrow_" id="' + rowfid + 'optrow_"></td>';
    if( rownum == 0 ){ 
        str += '   <td rowspan = "' + numrows + '">';
        str += '       <button type="button" class="button-secondary" id="' + fdid + 'up_" onClick="option_up(this)">^</button><br>';
        str += '       <button type="button" class="button-secondary" id="' + fdid + 'dn_" onClick="option_down(this)">V</button>';
        str += '   </td>';
    }
    str += '   <td><button type="button" class="button-secondary" name="' + rowfld + '[del]" ';
    str += '        id="' + rowfid + 'del_" onClick="delete_option(this)">X</button></td>';

    str += '</tr>';
    return str;
}
/* return the html for setting the specific radio button data -- ie a dummy because fdata is required */
/* called by type_data_field_str */
function rad_fields_str(fdid, fdld, oObj)
{
    return '<tr><td><input type="hidden" id="' + fdid + '" name="' +  fdld + '[radio]"></td></tr>';
}
/* return the html for setting the specific checkbutton data -- ie max num allowed */
/* called by type_data_field_str */
function chk_fields_str(fdid, fdld, oObj)
{
    var chk_maxnum = oObj.chkmax ? Number(oObj.chkmax) : '';
    
    var str = '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'chkmax\')"> &nbsp; (optional) Maximum number that may be checked</td>';
    str += '    <td>' + numinput_str(fdid + "chkmax_", fdld + "[chkmax]", 'no max', 1, 100, chk_maxnum) + '</td></tr>';
    return str;
     
}

/* return the html for setting the specific image field data - dimensions, type, etc */
/* the various ...input_str functions are in ewz-common.js */
/* called by type_data_field_str */
function img_fields_str(lnum, fdid, fdld, iObj) {
    if (!iObj) {
        iObj = ewzG.empty_img;
    }
    var str = '';
    str = '<tr><td width="60%"><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'imgdim\')">&nbsp;Maximum Image Width ( or max longest side if rotation is allowed )</td>';
    str += '    <td>' + textinput_str(fdid + 'max_img_w_', fdld + '[max_img_w]', '5', iObj.max_img_w) + 'Pixels</td>';
    str += '</tr>';
    str += '<tr><td>Spreadsheet Column for Image Width </td>';
    str += '    <td>' + colinput_str(fdid + 'ss_col_w_', fdld + '[ss_col_w]', iObj.ss_col_w, 'ssc' + lnum) + '</td>';
    str += '</tr>';
    str += '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'imgdim\')">&nbsp;Maximum Image Height( or max shorter side if rotation is allowed )</td>';
    str += '    <td>' + textinput_str(fdid + 'max_img_h_', fdld + '[max_img_h]', '5', iObj.max_img_h) + 'Pixels</td>';
    str += '</tr>';
    str += '<tr><td>Spreadsheet Column for Image Height </td>';
    str += '    <td>' + colinput_str(fdid + 'ss_col_h_', fdld + '[ss_col_h]', iObj.ss_col_h, 'ssc' + lnum) + '</td>';
    str += '</tr>';
    str += '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'rot\')">&nbsp;Can rotate to fit maxima</td>';
    str += '    <td>' + checkboxinput_str(fdid + 'canrotate_', fdld + '[canrotate]', iObj.canrotate) + '</td>';
    str += '</tr>';
    str += '<tr><td>Spreadsheet Column for Image Orientation </td>';
    str += '    <td>' + colinput_str(fdid + 'ss_col_o_', fdld + '[ss_col_o]', iObj.ss_col_o, 'ssc' + lnum) + '</td>';
    str += '</tr>';
    str += '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'imgsize\')">&nbsp;Maximum image size (cannot be over ' + ewzG.maxUploadMb + 'M)</td>';
    str += '    <td>' + textinput_str(fdid + 'max_img_size_', fdld + '[max_img_size]', 10, iObj.max_img_size) + 'Megabytes</td>';
    str += '</tr>';
    str += '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'longestdim\')">&nbsp;Minimum Longest Dimension</td>';
    str += '    <td>' + textinput_str(fdid + 'min_longest_dim_', fdld + '[min_longest_dim]', 10, iObj.min_longest_dim) + 'Pixels</td>';
    str += '</tr>';
    str += '<tr><td><img alt="" class="ewz_ihelp" src="' + ewzG.helpIcon + '" onClick="ewz_help(\'imgtype\')">&nbsp;Allowed image types<br />(control-click to select more than one)</td>';
    str += '    <td>' + imgformat_input_str(fdid + 'allowed_image_types_', fdld + '[allowed_image_types][]', iObj.allowed_image_types) + '</td>';
    str += '</tr>';
    return str;
}


/* Returns the html string for the field-type selection box */
/*    which on change, calls insert_blank_data_field       */
function type_opt_str(lnum, fid, fld, field_type) {
    var sel = 'selected="selected"',
        str = '<select id="' + fid + 'field_type_" name="' + fld + '[field_type]" ';

    str += '       onFocus="prev_val=this.options[this.selectedIndex].value" ';
    str += '       onChange="insert_blank_data_field(' + lnum + ', this, this.options[this.selectedIndex].value)">';
    str += '   <option value="str" ' + (('str' === field_type) ? sel : '') + '>Text &nbsp; </option>';
    str += '   <option value="opt" ' + (('opt' === field_type) ? sel : '') + '>Option List &nbsp; </option>';
    str += '   <option value="img" ' + (('img' === field_type) ? sel : '') + '>Image File  &nbsp; </option>';
    str += '   <option value="rad" ' + (('rad' === field_type) ? sel : '') + '>Radio Button  &nbsp; </option>';
    str += '   <option value="chk" ' + (('chk' === field_type) ? sel : '') + '>Check Box  &nbsp; </option>';
    str += '</select>';
    return str;
}

/* Returns the html string for a drop-down to select a spreadsheet column */
/* including onClick call to disable_ss_options                              */
function colinput_str(idstring, namestring, vval, uniq_class) {
    var qidstring = "'" + idstring + "'",
        qnmstring = "'" + namestring + "'",
        qclass = "'." + uniq_class + "'",
        i, i1, j, j1,
        sel = '',
        display = '',
        str;
    str = '<select id=' + qidstring + 'name=' + qnmstring + ' class="' + uniq_class + '" onChange="disable_ss_options(' + qclass + ')">';
    str += '      <option value="-1">None</option>';
    for (i = 0; i <= 25; ++i) {
        sel = (i == vval) ? 'selected="selected"' : '';
        i1 = i + 1;
        display = i1 + '(' + String.fromCharCode(i + 65) + ')';
        str += '  <option value="' + i + '" ' + sel + '>' + display + '</option>';
    }
    for (i = 0; i <= 25; ++i) {
        j = 26 + i;
        sel = (j == vval) ? 'selected="selected"' : '';
        j1 = j + 1;
        display = j1 + '(A' + String.fromCharCode(i + 65) + ')';
        str += '  <option value="' + j + '" ' + sel + '>' + display + '</option>';
    }
    str += '</select>';
    return str;
}

/************************ Functions That Actually Do Something  ****************************************/

/* disable editing of fields appearing in restrictions */
function disable_restricted_fields(lnum) {
    jQuery("#restricts_f" + lnum + '_').find('option:selected').each(function() {
        if (jQuery(this).val() !== '~*~') {
            var field_id, optval, jTxt;

            field_id = jQuery(this).parent().attr('id').replace(/^.*__/, '').replace('_', '');
            disable_and_flag(jQuery('#f' + lnum + '_fields' + field_id + '_field_type_'));
            disable_and_flag(jQuery('#del_f' + lnum + '_fields' + field_id + '_'));
            optval = jQuery(this).val();
            switch (optval) {
                case "~+~":
                case "~-~":
                    disable_and_flag(jQuery('#f' + lnum + '_fields' + field_id + '_required_'));
                    break;

                default:
                    jTxt = jQuery("input[id^='f" + lnum + "_fields" + field_id + "_fdata_'][id$='_value_'][value='" + optval + "']");
                    disable_and_flag(jTxt.parent().siblings().last().find("button"));
                    disable_and_flag(jTxt);
                    break;
            }
        }
    });
}

/* disable "required" checkbox if the field type is radio or checkbox */
function disable_required_for_radio_chk(lnum){
    var jfield = jQuery("#cfg_form_f" + lnum + "_").find('input[id$="_field_ident_"]').filter(function() {
        return (this.value == 'followupQ' );}).closest('table[class="ewz_field"]');
    var type = jfield.find( 'select[id$="_field_type_"]').val();
    if( type == 'chk' || type == 'rad' ){
        jfield.find('input[id$="_required_"]').prop("disabled", true);
    }
}

function enable_restricted_fields(lnum) {
    var content;
    jQuery("#cfg_form_f" + lnum + "_").find(".ewz_disabled").each(function() {
        jQuery(this).find('input,button').prop("disabled", false);
        content = jQuery(this).html();
        jQuery(this).replaceWith(content);
    });
}

function disable_and_flag(jElement) {
    var w, h;
    jElement.prop("disabled", true);
    if (!jElement.parent().hasClass("ewz_disabled")) {
        w = jElement.outerWidth(true) + 12;
        h = jElement.outerHeight(true) + 12;
        jElement.wrap('<span  style="width: ' + w + 'px; height: ' + h + 'px;" class="ewz_disabled" >');
    }
}


/* Set up some onChange functions for a layout                              */
function setup_layout(for_lnum, from_lnum) {
    set_restr_selections(for_lnum, ewzG.layouts[from_lnum]);

    var jLayout = jQuery('#ewz_admin_layouts_f' + for_lnum + '_');

    // set postbox header from title
    jQuery('#f' + for_lnum + '_layout_name_').keyup(function() {
        update_layout_name(this);
    });
    jQuery('#f' + for_lnum + '_layout_name_').change(function() {
        update_layout_name(this);
    });

    // make sure individual option max nums are less than overall max
    jQuery('#f' + for_lnum + '_max_num_items_').change(function() {
        disable_max_vals(jLayout);
    });
    jLayout.find('select[id$="_maxnum_"]').change(function() {
        disable_max_vals(jLayout);
    });

    disable_ss_options('.ssc' + for_lnum);
    jQuery("#f" + for_lnum + "_max_num_items_").change();

    // make the field postboxes sortable
    jQuery('#ewz_sortable_f' + for_lnum + '_').sortable({
        containment: 'parent'
    });

    // add the nonce
    jLayout.find('.ewz_numc').html(ewzG.nonce_string);
    jLayout.find('input[name="ewznonce"]').each(function(index) {
        jQuery(this).attr('id', 'ewznonce' + for_lnum + index);
    });

    // disable fields appearing in restrictions
    disable_restricted_fields(for_lnum);

    // disable required fields for checkboxes and radio buttons
    disable_required_for_radio_chk(for_lnum);
}

/* For the maxnum dropdown box in an option field, disable any maxnum >= the overall max num items */
/*    ( unless the field has identifier 'followupQ' )
/* Also disable numbers less than this in the max_num_items drop-down */
function disable_max_vals(jLayout) {
    var jMaxNum, opt_max_limit, mni_min;
    jMaxNum = jLayout.find('select[name="max_num_items"]');
    opt_max_limit = jMaxNum.val();
    mni_min = 0;

    jLayout.find("select[id$='_maxnum_']").each(function() {
        var jselect, optval;
        jselect = jQuery(this);
        if( !(jselect.closest('table[class="ewz_field"]').find('input[id$="_field_ident_"]').val() == 'followupQ') ){
            optval = jselect.val();
            if (optval > mni_min) {
                mni_min = optval;
            }
            jselect.find('option').prop("disabled", false);
            jselect.find('option').filter(function(){
                return  parseInt(jQuery(this).val(), 10) > opt_max_limit; } ).prop("disabled", true);
        }
    });
    jMaxNum.find('option').prop("disabled", false);
    jMaxNum.find('option').filter(function(){
        return  parseInt(jQuery(this).val(), 10) < mni_min; }).prop("disabled", true);
}

/* Restrictions are initially created blank. This fills in the details */
function set_restr_selections(lnum, layout) {
    var restrict, field, field_id, jmsg, junescaped, jpbox;
    for (restrict in layout.restrictions) {
        if (layout.restrictions.hasOwnProperty(restrict)) {
            for (field in layout.fields) {
                if (layout.fields.hasOwnProperty(field)) {
                    field_id = layout.fields[field].field_id;
                    jQuery('#f' + lnum + '_restrictions_' + restrict + '__' + field_id + '_').val(layout.restrictions[restrict][field_id]);
                }
            }
            jmsg = jQuery('#f' + lnum + '_restrictions_' + restrict + '__msg_');
            junescaped = jQuery(document.createElement('div')).html(layout.restrictions[restrict].msg);
            jmsg.val(junescaped.text());
            jpbox = jmsg.closest('div[id^="restr_title"]');
            jpbox.find('h3[class="hndle"]').html(layout.restrictions[restrict].msg);
        }
    }
}

function add_restriction(button) {
    var jRestrictDiv = jQuery(button).parent().find('div[id^="restricts_f"]'),
        lnum = get_layout_num(button);

    jRestrictDiv.append(new_restriction_str(lnum, button));
}

function delete_restr(button) {
    var lnum = get_layout_num(button);
    // delete the restriction
    jQuery(button).closest('.postbox').remove();
    enable_restricted_fields(lnum);
    // there may be other restrictions, enable clears everything
    disable_restricted_fields(lnum);
}

/* Called on change of layout name, to change the postbox title */
function update_layout_name(textfield) {
    var jtext, jlayout;

    jtext = jQuery(textfield);
    jlayout = jtext.closest(jQuery('div[id^="ewz_postbox-layout_f"]'));
    jlayout.find('h3[id^="tpg_header_f"]').text(jtext.val());
    jlayout.find('button[id^="lsub_f"] i').text(jtext.val());
    jlayout.find('button[id^="ldel_f"] i').text(jtext.val());
}

/* Called on change of field column header to update postbox */
function update_title(textfield) {
    var jtext = jQuery(textfield);
    jtext.closest(jQuery('div[id$="field_mbox_"]')).find('h3[id^="field_title_"]').text(jtext.val());
}

/* Called when the type of a field is changed.  Changes the right-hand side of the field area   */
/*    to a new, blank area of the relevant type, and enables/disables the "required" field      */
function insert_blank_data_field(lnum, type_sel, type) {
    var sel, field_id, parenttable, datafield, tid, fdata;

    sel = jQuery(type_sel);
    field_id = sel.attr("name").replace('fields[', '').replace('][field_type]', '');
    parenttable = sel.closest(jQuery('table[class="ewz_field"]'));
    datafield = jQuery(parenttable).find('td[id^="special_"]');
    tid = datafield.attr("id").replace('special_', '');

    sel.blur();
    // radio buttons and checkboxes cannot be required
    switch (type) {
        case "str":
            fdata = ewzG.empty_str;
            parenttable.find('input[id$="_required_"]').prop("disabled", false);
           break;
        case "img":
            fdata = ewzG.empty_img;
            parenttable.find('input[id$="_required_"]').prop("disabled", false);
            break;
        case "opt":
            fdata = ewzG.empty_opt;
            parenttable.find('input[id$="_required_"]').prop("disabled", false);
            break;
        case "rad":
            fdata = ewzG.empty_rad;
            parenttable.find('input[id$="_required_"]').prop("value",'');
            parenttable.find('input[id$="_required_"]').prop("disabled", true  );
            break;
        case "chk":
            fdata = ewzG.empty_chk;
            parenttable.find('input[id$="_required_"]').prop("value",'');
            parenttable.find('input[id$="_required_"]').prop("disabled", true );
            break;
    }
    datafield.html(type_data_field_str(lnum, tid, 'fields[' + field_id + ']', type, fdata));
}


/* Adds a new option to the option list  */
function add_option(add_opt_btn) {
    var jQtable, tid, jQrows, row, re, fieldnum, fieldid, str, jLayout, nrows;

    jQtable = jQuery(add_opt_btn).parent().find('table[id^="data_fields_"]'); //data_fields_f0_fields1_
    tid = jQtable.attr("id");
    jQrows = jQtable.children().first().children();
    row = jQrows.length - 1;
    nrows = row + 1;
    re = new RegExp('^.*_fields');
    fieldnum = tid.replace(re, '').replace('_', ''); //1
    fieldid = tid.replace("data_fields_", '');
    str = opt_row_str(fieldid + 'fdata_', 'fields[' + fieldnum + '][fdata]', row, null, nrows );
    
    jQuery(str).insertAfter(jQrows.last());
    jQtable.find('td[rowspan]').attr('rowspan', nrows);
    jQtable.find('input[id$="_label_"]').change(function() {
        var jthis, the_label, jvalue;
        jthis = jQuery(this);
        the_label = jthis.val();
        jvalue = jQtable.find('#' + jthis.prop("id").replace("_label_", "_value_"));

        if (isblank(jvalue.val())) {
            jvalue.val(the_label);
        }
    });
    jLayout = jQtable.closest(jQuery('div[id^="ewz_postbox-layout_f"]'));
    jQtable.find('select[id$="_maxnum_"]').change(function() {
        disable_max_vals(jLayout);
    });

}

/* Moves the option up by one */
function option_up( up_opt_btn ) {
    var jradio, jrow, jprev, ind, ind1;

    jradio =  jQuery(up_opt_btn).closest('table').find( 'input[id$="optrow_"]:checked' );
    if( !jradio ){
        return;
    }
    jrow = jradio.closest('tr');
    ind = jrow.prevAll().length - 1; // -1 because of header row
    if( ind < 1 ){
        alert( 'cant move first up' );
        return;
    }
    ind1 =  ind - 1;
    jprev = jrow.prev();
    
    renumber( jrow, ind, ind1 );
    renumber( jprev, ind1, ind );

    jrow.insertBefore(jprev);
    reset_colspan(jrow);   
}

/* Moves the option down by one */
function option_down(down_opt_btn) {
    var jradio, jrow, jnext, ind, ind1;

    jradio =  jQuery(down_opt_btn).closest('table').find( 'input[id$="optrow_"]:checked' );
    if( !jradio ){
        return;
    }
    jrow = jradio.closest('tr');
    ind =  jrow.prevAll().length - 1; // -1 because of header row
    if( ind >= jrow.siblings().length - 1 ){
        alert( 'cant move last down' );
        return;
    }
    ind1 =  ind + 1;
    jnext = jrow.next();
    
    jrow = renumber( jrow, ind, ind1 );
    jnext = renumber( jnext, ind1, ind );

    jrow.insertAfter(jnext);
    reset_colspan(jrow);   
}

function reset_colspan(jrow){
    var numrows =  jrow.siblings().length;
    var rowid = jrow.attr("id");
    var re = new RegExp('_[0-9][[0-9]*_row_$');

    var prefix = jrow.attr("id").replace( re, '' ); 
    jrow.find('td[rowspan]').remove();
    jrow.siblings().each( function() {
        jQuery(this).find('td[rowspan]').remove();
        });

    var str = '';
        str += '   <td rowspan = "' + numrows + '">';
        str += '       <button type="button" class="button-secondary" id="' + prefix + '_up_" onClick="option_up(this)">^</button><br>';
        str += '       <button type="button" class="button-secondary" id="' + prefix + '_dn_" onClick="option_down(this)">V</button>';
        str += '   </td>';

     jQuery(str).insertBefore(jrow.parent().children().eq(1).children().last());
}
   


function renumber( jrow, oldn, newn ){
        var oldid = jrow.attr("id");
        jrow.attr("id", oldid.replace('fdata_' + oldn + '_', 'fdata_' + newn + '_'));
        jrow.find('[id]').each(function() {
            jQuery(this).attr("id", jQuery(this).attr("id").replace('fdata_' + oldn + '_', 'fdata_' + newn + '_'));
        });
        jrow.find('[name]').each(function() {
            jQuery(this).attr("name", jQuery(this).attr("name").replace('[options][' + oldn + ']', '[options][' + newn + ']'));
        });

    return jrow;
}

/* Deletes the option        */
function delete_option(del_opt_btn) {
    var row, optnum, table, jtable, trows, jQrow, oldid, i, i1, i2;

    row = del_opt_btn.parentNode.parentNode;
    optnum = row.rowIndex;
    table = row.parentNode.parentNode;
    jtable = jQuery(table);
    trows = jtable.children().first().children();
    for (i = optnum + 1; i < trows.length; ++i) {
        jQrow = jQuery(trows[i]);
        i1 = i - 1;
        i2 = i - 2;
        oldid = jQrow.attr("id");

        jQrow.attr("id", oldid.replace('fdata_' + i1 + '_', 'fdata_' + i2 + '_'));
        jQrow.find('[id]').each(function() {
            jQuery(this).attr("id", jQuery(this).attr("id").replace('fdata_' + i1 + '_', 'fdata_' + i2 + '_'));
        });
        jQrow.find('[name]').each(function() {
            jQuery(this).attr("name", jQuery(this).attr("name").replace('[options][' + i1 + ']', '[options][' + i2 + ']'));
        });
    }
    table.deleteRow(optnum);

    disable_max_vals(jtable.closest('div[id^="ewz_admin_layouts_f"]'));
}

/* Adds a new field to the current layout, of type defined by field_type */
function add_field(add_field_btn, field_type) {
    var form, cnum, newid, lnum, fdata, data;

    form = jQuery(add_field_btn).closest('form[id^="cfg_form"]');
    cnum = form.find('div[id$="field_mbox_"]').length;
    newid = form.attr("id").replace('cfg_form_', '') + 'fieldsX' + cnum + '_';
    lnum = form.attr("id").replace('cfg_form_f', '').replace('_', '');
    fdata = {};

    data = {};
    switch (field_type) {
        case "str":
            fdata = ewzG.empty_str;
            break;
        case "img":
            fdata = ewzG.empty_img;
            break;
        case "opt":
            fdata = ewzG.empty_opt;
            break;
        case "rad":
            fdata = ewzG.empty_rad;
            break;
        case "chk":
            fdata = ewzG.empty_chk;
            break;
    }

    data.fdata = fdata;
    data.field_ident = '';
    data.field_header = '';
    data.field_id = '';
    data.field_type = field_type;
    data.required = 0;
    data.ss_column = '-1';
    form.find('div[id^="ewz_sortable"]').append(jQuery(field_str(lnum, 'X' + cnum, data)));
    jQuery('h3[id="field_title_' + newid + '"]').html("-- New Field --");

    // fire a change event on the spreadsheet column select boxes to disable used columns
    jQuery('#' + newid + 'field_mbox_').find('select[onchange^="disable_ss_options"]').change();
}

/* Creates a new layout as a copy of the one selected */
function add_layout_copy() {
    var fromid, layouts, to_num, fromstringid, tostringid, jQnew, sscstr, newhtml;

    fromid = jQuery('#ewz_addlayout').prop("selectedIndex");
    layouts = jQuery('div[id^="ewz_admin_layouts_f"]');
    to_num = layouts.length;
    fromstringid = 'ewz_admin_layouts_f' + fromid + '_';
    tostringid = 'ewz_admin_layouts_f' + to_num + '_';
    jQnew = jQuery('#' + fromstringid).clone();
    sscstr = new RegExp('ssc' + fromid, "g");
    newhtml = jQnew.html().replace(sscstr, 'ssc' + to_num);

    jQnew.html(newhtml);
    jQnew.attr("id", tostringid);
    jQnew.find('[id]').each(function() {
        jQuery(this).attr("id", jQuery(this).attr("id").replace('f' + fromid + '_', 'f' + to_num + '_'));
    });

    // remove restrictions  -- TODO: save the layout, grab the new field id's, and copy restrictions, too
    jQnew.find('#restricts_f' + to_num + '_').empty();

    jQnew.find('input[name="layout_id"]').attr("value", "");
    jQnew.find('input[name$="[field_id]"]').attr("value", "");
    jQnew.find('select:disabled,input:disabled,button:disabled').prop("disabled", false);

    jQnew.find('input[name="forder[]"]').each(function() {
        jQuery(this).attr("value", jQuery(this).attr("value").replace('_f' + fromid + '_', '_f' + to_num + '_'));
    });
    jQnew.find('div[class="ewz_warn"]').html('');
    jQnew.find('h3[id^="tpg_header"]').first().html("New Layout: <i>To make it permanent, set the options and save</i>");
    jQnew.find('[id$="layout_name_"]').first().attr("value", "");
    jQnew.find('.ewz_numc').html(ewzG.nonce_string);
    jQnew.find('span[id^="lname_f"]').text('');
    jQnew.find('#add_restr_f' + to_num + '_').after(" &nbsp; <i>Restrictions may not be added until the layout has been saved.</i>");
    jQnew.find('#add_restr_f' + to_num + '_').prop("disabled", true);
    jQnew.find('button[id^="lsub_f"]').text("Save Changes to New Layout");
    var jdelbtn = jQnew.find('button[id^="ldel_f"]');
    jdelbtn.text("Delete New Layout");
    jdelbtn.attr('onclick', null);
    jdelbtn.click(function() {
        delete_layout(this, to_num, 0);
    } );
    jQnew.insertAfter(layouts.last());

    enable_restricted_fields(to_num);

    setup_layout(to_num, fromid);
}


/* Just deletes the item on the current page, does not change what is stored on the server */
function delete_js_layout(id, thediv, lname) {
    var re, index;

    thediv.remove();
    re = new RegExp('<option value="' + id + '">.*' + lname + '.*?<\\/option>');
    ewzG.layouts_options = ewzG.layouts_options.replace(re, '');
    index = js_find_by_key(ewzG.layouts, 'layout_id', id);
    ewzG.layouts.splice(index, 1);
    jQuery('#ewz_addlayout').html(ewzG.layouts_options);
}

/* Just deletes the item on the current page, does not change what is stored on the server */
function delete_js_field(layout_id, field_id, jdiv) {
    var index = js_find_by_key(ewzG.layouts, 'layout_id', layout_id);
    jdiv.find('select[onchange^="disable_ss_options"]').prop("selectedIndex", 0);
    delete ewzG.layouts[index].fields[field_id];
    jdiv.remove();
    jQuery('#ewz_addlayout').html(ewzG.layouts_options);
    jQuery('select[id*="restrictions"][id$="__' + field_id + '_"]').remove();
}

/* First checks for attached webforms or items */
/* Actually deletes the layout on the server via ajax. If successful, calls delete_js_layout to delete it on the current page. */
function delete_layout(button, nwebforms, nitems) {
    var jbutton, lname, confirmstring, thediv, id, del_nonce, jqxhr;

    jbutton = jQuery(button);
    lname = jbutton.closest('div[id^="ewz_postbox-layout_f"]').find('h3[id^="tpg_header_f"]').text();
    lname = lname.replace(/To make it permanent.*$/, '');

    thediv = jbutton.closest('div[id^="ewz_admin_layouts_f"]');
    id = thediv.find('input[name="layout_id"]').first().attr("value");

    if (jQuery('div[id^="ewz_postbox-layout_f"]').length < 2) {
        alert(ewzG.errmsg.onlylayout);
        return;
    }
    if (nitems > 0) {
        alert(ewzG.errmsg.deletehasitems);
        return;
    }
    if (id === undefined || '' == id ) {
        delete_js_layout(id, thediv, lname);
    } else {

        confirmstring = '';
        if (nwebforms > 0) {
            confirmstring = ewzG.errmsg.deletehaswebforms + "\n\n";
        }
        confirmstring = confirmstring + ewzG.errmsg.deleteconfirm + "'" + lname + "'?";

        if (confirm(confirmstring)) {
            del_nonce = thediv.find('input[name="ewznonce"]').val();
            jbutton.after('<span id="temp_load" style="text-align:left"> &nbsp; <img alt="" src="' + ewzG.load_gif + '"/></span>');
            jqxhr = jQuery.post(ajaxurl,
                    {
                        action: 'ewz_del_layout',
                        layout_id: id,
                        ewznonce: del_nonce
                    },
            function(response) {
                jQuery("#temp_load").remove();
                if ('1' == response) {
                    delete_js_layout(id, thediv, lname);
                } else {
                    alert(response);
                }
            }
            );
        }
    }
}


/* Actually deletes the field on the server via ajax. If successful, deletes it on the current page. */
function delete_field(del_field_btn) {
    var jbutton, fname, jfield_div, jform_div, field_id, layout_id, del_nonce, jqxhr;

    jbutton = jQuery(del_field_btn);
    jfield_div = jbutton.closest('div[id$="field_mbox_"]');
    field_id = jfield_div.find('input[name^="fields"]').filter(":hidden").val();
    if (field_id === undefined || '' == field_id ) {
        jfield_div.find('select[id$="_maxnum_"]').prop("selectedIndex", 0);
        jfield_div.find('select[id$="_maxnum_"]').change();
        jfield_div.find('select[onchange^="disable_ss_options"]').prop("selectedIndex", 0);
        jfield_div.find('select[onchange^="disable_ss_options"]').change();
        jfield_div.remove();
    } else {
        fname = jbutton.closest('div[id$="field_mbox_"]').find('h3[id^="field_title"]').text();
        if (confirm("Really delete the '" + fname + "' field?")) {
            jform_div = jbutton.closest('form[id^="cfg_form_f"]');
            layout_id = jform_div.find('input[name="layout_id"]').first().attr("value");

            del_nonce = jform_div.find('input[name="ewznonce"]').val();
            jbutton.after('<span id="temp_load" style="text-align:left"> &nbsp; <img alt="" src="' + ewzG.load_gif + '"/></span>');
            jqxhr = jQuery.post(ajaxurl,
                    {
                        action: 'ewz_del_field',
                        field_id: field_id,
                        layout_id: layout_id,
                        ewznonce: del_nonce
                    },
            function(response) {
                jQuery("#temp_load").remove();
                if ('1' == response) {
                    delete_js_field(layout_id, field_id, jfield_div);
                } else {
                    alert(response);
                }
            }
            );
        }
    }
}

function ewz_esc(instring) {
    return instring.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function get_layout_num(element) {
    var dv = jQuery(element).closest('div[id^="ewz_admin_layouts_f"]');

    return dv.attr("id").replace('ewz_admin_layouts_f', '').replace('_', '');
}

/* called on submit, err_alert disables submit */
function ewz_check_layout_input(form, do_check) {
    var ok, jform, lnum, maxnumitems, maxs;
    ok = true;
    jform = jQuery(form);
    lnum = jform.attr("id").replace('cfg_form_f', '').replace('_', '');
    jQuery('#lsub_f' + lnum + '_').prop("disabled", true);
    if (do_check) {
        try {
            // remove leading and trailing spaces from all inputs
            // NB: need index arg so value is correctly assigned
            jform.find('input').val(function(index, value) {
                return value.replace(/ +$/, '').replace(/^ +/, '');
            });
            // no layout name
            if (!jform.find('input[id$="_layout_name_"]').val()) {
                err_alert( lnum, ewzG.errmsg.layoutname);
                return false;
            }
            // no max num items
            maxnumitems = jform.find('select[id$="_max_num_items_"]').val();
            if (!maxnumitems) {
                err_alert( lnum, ewzG.errmsg.maxnumitems);
                return false;
            }
            // no fields
            if (!jform.find('div[id^="field_mbox_"]')) {
                err_alert( lnum, ewzG.errmsg.nofields);
                return false;
            }
            /* cant return from function from inside filter, so use 'ok' flag */
            // missing column header
            jform.find('input[id$="_field_header_"]').filter(function() {
                return  ('' == jQuery(this).val().replace(/^\s+|\s+$/g, ''));
            }).each(function() {
                err_alert( lnum, ewzG.errmsg.colhead);
                ok = false;
            });
            // missing identifier
            jform.find('input[id$="field_ident_"]').filter(function() {
                return !jQuery(this).val().replace(/^\s+|\s+$/g, '').match(/^[a-z][a-z0-9_\-]+$/i);
            }).each(function() {
                err_alert( lnum, ewzG.errmsg.ident);
                ok = false;
            });
            // followup cannot be image type
            jform.find('input[id$="field_ident_"]').filter(function() {
                return( 'followupQ' == jQuery(this).val() );
            }).filter(function() {
                return ( 'img' == jQuery(this).closest('tbody').find('select[id$="_field_type_"]').val());
            }).each(function() {
                err_alert( lnum,  ewzG.errmsg.followimg);
                ok = false;
            });
            // invalid max_img_w
            jform.find('input[id$="_max_img_w_"]').each(function() {
                if (!jQuery(this).val().match(/^[1-9][0-9]*$/)) {
                    err_alert( lnum, ewzG.errmsg.maximgw);
                    ok = false;
                }
            });
            // invalid max_img_h
            jform.find('input[id$="_max_img_h_"]').each(function() {
                if (!jQuery(this).val().match(/^[1-9][0-9]*$/)) {
                    err_alert( lnum, ewzG.errmsg.maximgh);
                    ok = false;
                }
            });
            // invalid min_longest_dim
            jform.find('input[id$="min_longest_dim_"]').each(function() {
                if( jQuery(this).val().match(/^ *$/)) {
                     jQuery(this).val(0);
                } else if (!jQuery(this).val().match(/^[0-9][0-9]*$/)) {
                    err_alert( lnum, ewzG.errmsg.minlongestdim);
                    ok = false;
                }
            });
            // invalid or missing max_img_size, maxUploadMb
            jform.find('input[id$="_max_img_size_"]').each(function() {
                jQuery(this).val(jQuery(this).val().replace(/[Mm]$/, ''));
                maxs = jQuery(this).val();
                if (!maxs) {
                    err_alert( lnum, ewzG.errmsg.nomaximgsz);
                    ok = false;
                    return;
                }
                if (!maxs.match(/^[0-9]?[0-9]?\.?[0-9]+$/)) {
                    err_alert( lnum, ewzG.errmsg.maximgsz);
                    ok = false;
                    return;
                }
                if (parseFloat(maxs) > parseFloat(ewzG.maxUploadMb)) {
                    err_alert( lnum, ewzG.errmsg.sysmaxsz);
                    ok = false;
                    return;
                }
                // Warn about potential problems but allow if user okays
                if (parseFloat(maxs) * maxnumitems >= parseFloat(ewzG.maxTotalMb)) {
                    if (!confirm(ewzG.errmsg.sysmaxup)) {
                        jQuery('#lsub_f' + lnum + '_').prop("disabled", false);
                        ok = false;
                    }
                }
            });
            // missing option label or value
            jform.find('input[id$="_label_"]').each(function() {
                var lab = jQuery(this).val();
                if (!lab) {
                    err_alert( lnum, ewzG.errmsg.optlabel);
                    ok = false;
                    return;
                }
                if (lab.match(/[^A-Za-z0-9_\- ]/)) {
                    err_alert( lnum, ewzG.errmsg.option);
                    ok = false;
                }
            });
            // option list must contain a valid option
            jform.find('select[id$="_field_type_"]').each(function(){
                if( jQuery(this).val() === 'opt'){
                    var jfield_table = jQuery(this).closest('table[class="ewz_field"]');
                    var header = jfield_table.find('input[id$="_field_header_"]').val();
                    if(jfield_table.find('table[id^="data_fields_"]').find('tr[id$="_row_"]').size() < 1 ){
                        err_alert( lnum,  header + ': ' +  ewzG.errmsg.optioncount );
                        ok = false;
                    }
                }
            });
            // option must have a valid value
            jform.find('input[id$="_value_"]').each(function() {
                var oval = jQuery(this).val();
                if (!oval) {
                    err_alert( lnum, ewzG.errmsg.optvalue);
                    ok = false;
                    return;
                }
                if (oval.match(/[^A-Za-z0-9_\-]/)) {
                    err_alert( lnum, ewzG.errmsg.option);
                    ok = false;
                }
            });
            // restriction must have a message
            jform.find('input[id$="__msg_"]').each(function() {
                if (!jQuery(this).val()) {
                    err_alert( lnum, ewzG.errmsg.restrmsg);
                    ok = false;
                }
            });
            // text input must have maxchars
            jform.find('select[id$="_maxstringchars_"]').each(function() {
                if (!jQuery(this).val().replace(/^\s+|\s+$/g, '')) {
                    err_alert( lnum, ewzG.errmsg.maxnumchar);
                    ok = false;
                }
            });
            // img must have set of allowed types
            jform.find('select[id$="_allowed_image_types_"]').each(function() {
                if (!jQuery(this).val()) {
                    err_alert( lnum, ewzG.errmsg.imgtypes);
                    ok = false;
                }
            });
            // warnings re ineffective restrictions
            jform.find('div[id^="add_restr_f"]').each(function() {
                var any = jQuery(this).find('select[name^="restrictions"]').find('option:eq(0)').not(":selected");

                if (any.length < 1) {
                    if (!confirm((ewzG.errmsg.all_any))) {
                        jQuery('#lsub_f' + lnum + '_').prop("disabled", false);
                        ok = false;
                    }
                } else if (any.length < 2) {
                    if (!confirm(ewzG.errmsg.one_any)) {
                        jQuery('#lsub_f' + lnum + '_').prop("disabled", false);   
                        ok = false;
                    }
                }
            });

        } catch (except1) {
            jQuery('#lsub_f' + lnum + '_').prop("disabled", false);
            return false;
        }
    }
    if(!ok){
        jQuery('#lsub_f' + lnum + '_').prop("disabled", false); 
    }
    if (ok || !do_check) {
        try {
            jform.find('div[class="waitmessage"]').html('Processing, please wait ... <img alt="Please Wait" src="' + ewzG.load_gif + '"/>');
 
            // enable all the disabled stuff so right data gets sent
            jform.find('select:disabled,input:disabled,button:disabled').prop("disabled", false);
            jform.find('input[id$="optrow_"]').prop("disabled", true);  // we DO want this disabled

            jform.append('<input type="hidden" name="action" value="ewz_layout_changes" />');
            jQuery.post(ajaxurl,
                    jform.serialize(),
                    function(response) {
                        if ('1' == response) {
                            location.reload();
                        } else {
                            disable_ss_options('.ssc' + lnum);
                            disable_restricted_fields(lnum);
                            alert(response);
                        }
                    });

        } catch (except2) {
            alert("Sorry, there was an unexpected error: " + except2.message);
            return true;   // should make regular submit work
        }
    }
    return false;       // must do this to prevent re-sending via regular submit
}

function err_alert( layoutnum, msg )
{
    jQuery('#lsub_f' + layoutnum + '_').prop("disabled", false);
    alert( msg);
}
