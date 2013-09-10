
jQuery(document).ready(function($) {
    init_ewz_webforms();
});
var ewzG;
/* toggle visibility of hideable controls in the same form */
function toggle_hidden_controls(checkbox){
    'use strict';
    fixConsole();
    var jcheckbox = jQuery(checkbox),
         jtable = jcheckbox.closest('form');
    if(jcheckbox.prop("checked")){
        jtable.find('[class="ewz_hideable"]').hide();
    } else {
        jtable.find('[class="ewz_hideable"]').show();
    }
}

/************************ The OnLoad Function  ****************************************/
/* called on load                                   */
/* generates the whole page from the ewzG structure */
function init_ewz_webforms(){
    'use strict';
    var i, jthis;

    // ewzG is null if not logged in
    if( null !== ewzG1 ){
        ewzG = ewzG1.gvar;
        for( i = 0; i < ewzG.webforms.length; ++i){
            jQuery('#ewz_management').append(ewz_management(i, ewzG.webforms[i]));
            jQuery('#webform_title_ev' + i + '_').change(function(){
                jthis = jQuery(this);
                jthis.closest('div[id^="ewz-postbox-webform_ev"]').find('span[id^="wf_title_"]').text(jthis.val());
            });
            jQuery('#o_user_' + i+ '_').val(ewzG.webforms[i].open_for);
            jQuery('#upload_open' + i + '_').change( function() {
                toggle_hidden_controls(this);
            } );
            toggle_hidden_controls('#upload_open' + i + '_');

            // add the nonce
            jQuery('.ewz_numc').html(ewzG.nonce_string);
            jQuery('input[name="ewznonce"]').each(function(index){
                jQuery(this).attr('id', 'ewznonce'+index);
            });
        }
        if( ewzG.message ){
            alert(  ewzG.message.replace(/~/g,"\n")  );
        }
        if( ewzG.can_edit_all ){
            // insert the "add webform" button
            jQuery('#ewz_management').after(webform_button_str());
        }
        add_expander();
        //console.log(ewzG);
   }
}


/************************ Functions Returning an HTML String ****************************************/

/* Returns the html string for a postbox containing a single webform */
function ewz_management(evnum, eObj){
    'use strict';
    if(!eObj){
        eObj= {};
    }
    var formstatus = '<span  style="float:right">' + (eObj.upload_open ? 'Open' : (eObj.open_for_string ? 'Open for some users' : 'Closed'))
        + '       &nbsp; ' + eObj.itemcount + ' items uploaded</span>',
        str= '<div id="ewz_admin_webforms_ev' + evnum + '_" class="metabox-holder">'
        +  '    <div id="ewz-postbox-webform_ev' + evnum + '_" ';

    if( eObj.webform_id == ewzG.openform_id ){
        str +=   '    class="postbox ">';
    } else {
        str +=   '    class="postbox closed" >';
    }
    str +=   '       <div class="handlediv"  onClick="toggle_postbox(this)" title="Click to toggle"><br/></div>';
    str +=   '       <h3  class="hndle"  onClick="toggle_postbox(this)" id="tpg_header_ev' + evnum + '_"  >';
    str +=   '          <span id="wf_title_' + evnum + '">' + eObj.webform_title + '</span>' + formstatus;
    str +=   '       </h3>';
    str +=   '       <div class="inside">';
    str +=   '          <div class="ewz_formsetup">';
    str +=   '             <div class="ewz_data">';
    str +=   '                 <h4>Webform Setup</h4>';
    if(eObj.can_manage_webform){
        // Can manage webform, present the stored info in editable form
        str += webform_data_str( evnum, eObj );
    } else {
        // Cant manage webform, present the stored info read-only
        str += read_only_info_str( eObj );
    }
    str +=   '             </div>';   // end of ewz_data

    if( (eObj.can_download || eObj.can_manage_webform) && ( eObj.webform_id > 0 ) ){
        str +=   '             <div class="ewz_data">';
        str += data_management_str( evnum, eObj );
        str +=   '             </div>';   // end of ewz_data
    }
    str +=   '          </div>';      // end of ewz_formsetup
    str +=   '       </div>';         // end of inside
    str +=   '    </div>';           // ewz-postbox-webform_ev
    str +=   '</div>';               // ewz_admin_webforms_ev
    return str;
}
/* Return the html string for the data management section */
function data_management_str( evnum, eObj ){
    'use strict';
    var str='';
    str +=   '<h4>Data Management</h4>';
    if(eObj.can_manage_webform){
        str +=  xtra_data_upload_str(  evnum, eObj );
    }
    /**** NB: action is item_list, not webform here, and requires the webform_id in the url ********/
    str +=   '<form method="post" action="' + ewzG.list_page + '&webform_id=' + eObj.webform_id + '" id="data_form_ev' + evnum + '_">';
    str += '  <div class="ewzform">';
    str +=   '    <div class="ewz_numc"></div>';
    str +=   '    <input type="hidden" name="page" value="entrywizlist">';
    str +=   '    <input type="hidden" name="webform_id" value="' + eObj.webform_id + '">';
    str +=   '    <br><br><br><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'datasel\')"> &nbsp; With selected items:';

    if( eObj.hasOwnProperty('field_options' )){

        str += field_options_str( evnum, eObj );
    }
    str += upload_date_str( evnum );

    str +=   '    <TABLE class="ewz_buttonrow">';
    if( eObj.itemcount > 0 && eObj.can_manage_webform ){
        str +=   '  <TR><TD><button type="button" onClick="set_mode(this,\'list\')"  id="list_' + evnum + '" class="button-secondary">Manage Items</button></TD>';
        str +=   '     <TD></TD><TD></TD>';
        str +=   '  </TR>';
    }
    if(eObj.can_download){
        str +=   '  <TR>';
        str +=   '    <TD><button type="button" onClick="set_mode(this,\'spread\')" id="spread_' + evnum + '" class="button-secondary">Download Spreadsheet</button></TD>';
        str +=   '    <TD><button type="button" onClick="set_mode(this,\'images\')" id="images_' + evnum + '" class="button-secondary">Download Images</button></TD>';
        str +=   '    <TD><button type="button" onClick="set_mode(this,\'download\')" id="download_' + evnum + '" class="button-secondary">Download Images and Spreadsheet</button></TD>';
        str +=   '  </TR>';
    }
    str +=   '  </TABLE>';
    str +=   ' </div>';
    str +=   '</form>';

    return str;
}

function set_mode(button, mode){
    jform = jQuery( button ).closest('form');
    jform.append('<input type="hidden" name="ewzmode" value="' + mode + '">' );
    jform.submit();
}

/* Return the html string for the editable form of the webform data */
function webform_data_str(evnum, eObj) {
    'use strict';
    var divid = 'usel' + evnum + '_',
        clickstr = 'user_select(' + "'" + divid + "'" + ')',
        str = '';
    str += '<form method="post" action="" id="cfg_form_ev' + evnum + '_" onSubmit="return ewz_check_webform_input(this, ewzG.jsvalid)">';
    str += '  <div class="ewzform">';
    str +=   '    <input type="hidden" name="webform_id" value="' + eObj.webform_id + '">';
    str +=   '    <input type="hidden" name="ewzmode" value="webform">';
    str +=   '    <table class="ewz_padded">';
    str +=   '        <tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'title\')">&nbsp;Title:</td>';
    str +=   '            <td>' + textinput_str('webform_title_ev' + evnum + '_', 'webform_title', 50, eObj.webform_title) + '</td>';
    str +=   '            <td></td>';
    str +=   '        </tr>';
    str +=   '        <tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'ident\')">&nbsp;Identifier:</td>';
    str +=   '            <td>' + textinput_str('webform_ident_ev' + evnum + '_', 'webform_ident', 20, eObj.webform_ident);
    str +=   '            </td>';
    str +=   '            <td></td>';
    str +=   '        </tr>';
    str +=   '        <tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'wlayout\')">&nbsp;Webform layout: </td>';
    if(eObj.can_edit_webform){
        str +=   select_layout_str( evnum, eObj );
    } else {
        str +=   '        <td><input type="hidden" name="layout_id" value="' + eObj.layout_id + '">' + eObj.layout_name + '</td>';
    }
    str +=   '            <td></td>';
    str +=   '        </tr>';

    str +=   '        <tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'prefix\')">&nbsp;Optional prefix:</td> ';
    str +=   '            <td> <input type="text" name="prefix" id="prefix_' + evnum + '" value="' + eObj.prefix + '" size="15" maxlength="25"></td>';
    str +=   '            <td></td>';
    str +=   '        </tr>';

    str +=   '        <tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'open\')">&nbsp;Open for Uploads:</td>';
    str +=   '            <td>' + checkboxinput_str('upload_open' + evnum + '_', 'upload_open', eObj.upload_open ) + '</td>';
    str +=   '            <td>  <div id="open_for_' + evnum + '_" class="ewz_hideable"><i>' + eObj.open_for_string + '</i></div> </td>';
    str +=   '        </tr>';
if(eObj.user_options){
    str +=   '        <tr class="ewz_hideable">';
    str +=   '            <td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'openfor\')">&nbsp;Show user selection list</td>';
    str +=   '            <td><input type="checkbox"  id="show_uselect_' + evnum + '" onChange="' + clickstr + '"></td>';
    str +=   '            <td><div id="' + divid + '" style="display:none">';
    str +=   '                  <select  multiple="multiple" size="8" name="o_user[]" id="o_user_' + evnum + '_">' +  eObj.user_options + '</select>';
    str +=   '                </div></td>';
    str +=   '        </tr>';
}
    str +=   '     </table>';
    str +=   '     <div class="ewz_numc"></div>';
    str +=   '     <p><button id="cfg_form_wf' +  evnum + '_"  type="submit" class="button-primary">Save Changes</button> &nbsp;  &nbsp;  &nbsp;  &nbsp; ';
    if(ewzG.can_edit_all){
        str +=   '    <button type="button" id="wfdel_' + evnum + '_" class="button-secondary"';
        str +=   '            onClick="delete_webform( this, ' + eObj.itemcount + ')">Delete Web Form</button>';
    }
    str +=   '     </p>';
    str +=   ' </div>';
    str +=   '</form>';
    return str;

}

/* Return the html string for the read-only form of the webform data */
function read_only_info_str( eObj ){
    'use strict';
    var str='';
    str +=   '             <table>';
    str +=   '                 <tr><td>Name for this webform</td>';
    str +=   '                     <td>' + eObj.webform_title + '</td>';
    str +=   '                 </tr>';
    str +=   '                 <tr><td>Identifier for use in file names</td>';
    str +=   '                     <td>' +  eObj.webform_ident + '</td>';
    str +=   '                 </tr>';
    str +=   '                 <tr><td>Webform layout: </td>';
    str +=   '                     <td>' +  eObj.layout_name + '</td></tr>';
    str +=   '                 <tr><td>Open for Uploads: </td>';
    if(eObj.upload_open ){
        str +=   '                 <td>Yes</td>';
    } else {
        str +=   '                 <td>No</td>';
    }
    str +=   '                 </tr>';
    str +=   '              </table>';
    return str;
}

/* Return the html string for the Extra Data Upload form */
function xtra_data_upload_str(  evnum, eObj ){
    'use strict';
    var str = '',
        inputid = 'csv_data_' + evnum + '_';
    str +=   '<form method="post" enctype="multipart/form-data" action="" id="csv_form_ev' + evnum + '_"  onSubmit="return ewz_check_csv_input( \'' + inputid + '\' ) ">';
    str += '  <div class="ewzform">';
    str +=   '    <input type="hidden" name="webform_id" value="' + eObj.webform_id + '">';
    str +=   '    <input type="hidden" name="ewzmode" value="csv">';
    str +=   '    <div class="ewz_numc"></div>';

    str +=   '    <img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'csv\')">&nbsp;Upload extra data for the webform: ';
    str +=   '    <input  id="' + inputid + '" name="csv_data" type="file" > &nbsp; ';
    str +=   '    <button type="submit" id="csv_btn_' + evnum + '" name="csv_btn" class="button-secondary">Upload</button>';
    str +=   ' </div>';
    str +=   '</form>';
    return str;
}

/* Return the html string for the field options select boxes */
function field_options_str( evnum, eObj ){
    'use strict';
    var field_id1,
        field_id2,
        str  = '';

        str +=   '    <TABLE class="ewz_field_opts">';
        str +=   '       <TR>';
        for( field_id1 in eObj.field_options ){
           if(eObj.field_options.hasOwnProperty(field_id1)){
               str +=   '      <TH>';
               if( eObj.field_options[field_id1] ){
                   str +=   eObj.field_names[field_id1];
               }
               str +=   '      </TH>';
           }
        }
        str +=   '       </TR>';
        str +=   '       <TR>';
        for( field_id2 in eObj.field_options ){
           if(eObj.field_options.hasOwnProperty(field_id2)){
               str +=   '      <TD>';
               if( eObj.field_options[field_id2] ){
                   str +=   '      <select name="fopt[' + field_id2 +']"  id="l'+ eObj.webform_id + 'f' + field_id2 + '_opt_">';
                   str +=              eObj.field_options[field_id2];
                   str +=   '      </select>';
               }
               str +=   '  </TD>';
           }
        }
        str +=   '       </TR>';
        str +=   '    </TABLE>';
    return str;
}

/* Return the html string for the upload date selection */
function upload_date_str( evnum ){
    'use strict';
    var day,
        str  = '';

        str +=   '<BR><TABLE class="ewz_field_opts">';
        str +=   '       <TR>';
        str +=   '          <TD>Uploaded during the last ';
        str +=   '             <SELECT id="uploaddays' + evnum  + '" name="uploaddays">';
        str +=   '                 <OPTION value="">  </OPTION>';
        for( day=1; day<=100; ++day ){
             str +=   '            <OPTION value="' + day + '">' + day + '</OPTION>';
        }
        str +=   '             </SELECT> days';
        str +=   '          </TD>';
        str +=   '       </TR>';
        str +=   '    </TABLE>';
    return str;
}


/* Return the html string for the "add webform" button */
function webform_button_str(){
    'use strict';
    var str  = '<div class="clear alignleft">';
    str += '         <button  type="button" class="button-secondary" id="webform_add_" onClick="add_new_webform()">Add a New Web Form</button> ';
    str += '    </div> ';
    return str;
}

/* Return the html string for the layout selection */
function select_layout_str( evnum, eObj ){
    'use strict';
    var str = '';
    str +=   '                 <td>';
    str +=   '                    <select id="layout_id' + evnum + '" name="layout_id" >';
    str +=                           eObj.layouts_options;
    str += '                      </select>';
    str +=   '                 </td>';
    if(eObj.itemcount > 0){
        str +=   '             <td><div class="ewz_warn">Warning: This form has uploaded data. <br>Changing the layout now could cause problems.</div></td>';
    }
    return str;
}
/************************ Functions That Change The Page  ****************************************/

function user_select(id){
    'use strict';
   if(jQuery('#' + id ).is(':visible')){
        jQuery('#' + id ).hide();
    } else {
        jQuery('#' + id ).show();
    }
}



/* Actually delete the form on the server via ajax.  If successful, delete it from the page. */
function delete_webform(button, itemcount){
    'use strict';
    var jbutton = jQuery(button),
        lname = jbutton.closest('div[id^="ewz-postbox-webform_ev"]').find('span[id^="wf_title_"]').text(),
        confirmstring = '',
        thediv,id,ok,del_nonce,jqxhr;

    if(itemcount > 0){
        confirmstring +=  ewzG.errmsg.warn + "\n"  + ewzG.errmsg.hasitems + "\n\n";
    }
    confirmstring += ewzG.errmsg.reallydelete;
    confirmstring += "\n" + ewzG.errmsg.noundo;

    if( confirm( confirmstring ) ){
        thediv = jbutton.closest('div[id^="ewz_admin_webforms_ev"]');
        id = thediv.find('input[name="webform_id"]').first().attr("value");
        if( '' === id || null === id || 'undefined' === typeof(id) ){
            thediv.remove();
        } else {
            ok = 'no';
            jbutton.after('<span id="temp_load" style="text-align:left"> &nbsp; <img alt="" src="' + ewzG.load_gif + '"/></span>');
            del_nonce = thediv.find('input[name="ewznonce"]').val();
            jqxhr = jQuery.post( ajaxurl,
                                     {
                                         action: 'ewz_del_webform',
                                         webform_id: id,
                                         webform_title: lname,
                                         ewznonce: del_nonce
                                     },
                                     function (response) {
                                         jQuery("#temp_load").remove();
                                         if( '1' == response ){
                                             thediv.remove();
                                         } else {
                                             alert( response );
                                         }
                                     }
                                   );
        }
    }
}


function add_new_webform(){
    'use strict';
    var jthis,
        num = ewzG.webforms.length,
        newform = {},
        jQnew;
    newform.can_manage_webform = true;
    newform.can_edit_webform = true;
    newform.can_download = true;
    newform.itemcount = 0;
    newform.upload_open = false;
    newform.open_for_string = "";
    newform.layouts_options = ewzG.base_options;
    newform.webform_title = '--- New Web Form ---';
    newform.webform_id = '';
    newform.webform_ident = '';
    newform.prefix = '';
    jQnew = jQuery(ewz_management(num, newform));
    jQuery('#ewz_management').append(jQnew);
    jQnew.find('span[id^="tpg_header"]').first().html("New Web Form: <i>To make it permanent, set the options and save</i>");
    jQnew.find('.ewz_numc').html(ewzG.nonce_string);
    jQnew.find('input[name="ewznonce"]').each(function(index){
        jQuery(this).attr('id', 'ewznonce'+num+index);
    });


    jQnew.find('input[name="webform_id"]').val('');

    jQnew.find('input[id^="webform_title_ev"]').change(function(){
        jthis = jQuery(this);
        jthis.closest('div[id^="ewz-postbox-webform_ev"]').find('span[id^="wf_title_"]').text(jthis.val());
    });

    jQuery('#o_user_' + num+ '_').html(ewzG.webforms[0].user_options);
    jQuery('#o_user_' + num+ '_').val([]);
    jQuery('#upload_open' + num + '_').change( function() {
        toggle_hidden_controls(this);
    } );
    toggle_hidden_controls('#upload_open' + num + '_');
}


/* Validation */
function ewz_check_csv_input(file_input_id){
    'use strict';
    if(typeof window.FileReader !== 'undefined'){

        var reader = new FileReader(),
            files = document.getElementById(file_input_id).files,
            mb,
            theFile;
        if(files !== null){
            // get selected file element
            theFile = files[0];
            mb =  Math.floor( theFile.size / 1048576 );
            if( mb > ewzG.maxUploadMb ){

                alert( 'Sorry, your file size is ' + mb + 'M, which is bigger than the allowed maximum of ' + ewzG.maxUploadMb + 'M' );
                return false;
            }
            if( theFile.type !== 'text/csv' ){
                alert( 'Sorry, the file must be of type "text/csv"' );
                return false;
            }
        }
    }
}

function ewz_check_webform_input(form, do_js_check){
    'use strict';
    var jform,
        pref;
    if( do_js_check) {
        try{
            jform = jQuery(form);
            if(!jform.find('input[id^="webform_title_ev"]').val()){
                alert(ewzG.errmsg.formTitle);
                return false;
            }
            if(!jform.find('input[id^="webform_ident_ev"]').val()){
                alert(ewzG.errmsg.formIdent);
                return false;
            }
            if(!jform.find('input[id^="webform_ident_ev"]').val().match(/^[a-z0-9_\-]+$/i)){
                alert(ewzG.errmsg.formIdent);
                return false;
            }
            pref = jform.find('input[id^="prefix_"]').val();
            if(!pref.match(/^[\[\]A-Z0-9~\-_]*$/i)){
                alert(ewzG.errmsg.formPrefix);
                return false;
            }
            return true;
        } catch(except) {
            alert("Sorry, there was an unexpected error: " + except.message);
            return false;
        }
    } else {
        return true;
    }
}

function set_list_url( evnum, webform_id ){
    'use strict';
    var qstring = '&ewzmode=list',
        jform = jQuery('#data_form_ev' + evnum + '_'),
        act = jform.attr('action');
    jform.attr('action', act + qstring);
    return true;
}

