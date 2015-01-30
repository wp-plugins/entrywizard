"use strict";

jQuery(document).ready(function() {
    init_ewz_webforms();
});
var ewzG;
/* toggle visibility of hideable controls in the same form */
function toggle_hidden_controls(checkbox){
    fixConsole();
    var jcheckbox = jQuery(checkbox),
           jtable = jcheckbox.closest('form');
    if(jcheckbox.prop("checked")){
        jtable.find('[class="ewz_hideable"]').hide();
        jtable.find('[class="ewz_showable"]').show();
    } else {
        jtable.find('[class="ewz_hideable"]').show();
        jtable.find('[class="ewz_showable"]').hide();
    }
}

/************************ The OnLoad Function  ****************************************/
/* called on load                                   */
/* generates the whole page from the ewzG structure */
function init_ewz_webforms(){
    var i, jthis;
    // ewzG is null if not logged in
    if( null !== ewzG1 ){
        ewzG = ewzG1.gvar;
        // make the webform postboxes sortable
        jQuery('#ewz_management').sortable({
            containment: 'parent',
            items: "> div",
            distance: 5
        });
        for( i = 0; i < ewzG.webforms.length; ++i){ 
            var wf_id = ewzG.webforms[i].webform_id;
            jQuery('#ewz_management').append(ewz_management(i, wf_id, ewzG.webforms[i]));
            jQuery('#webform_title_ev' + wf_id + '_').change(function(){
                jthis = jQuery(this);
                jthis.closest('div[id^="ewz-postbox-webform_ev"]').find('span[id^="wf_title_"]').text(jthis.val());
            });
            jQuery('#o_user_' + wf_id + '_').val(ewzG.webforms[i].open_for);
            jQuery('#upload_open' + wf_id + '_').change( function() {
                toggle_hidden_controls(this);
            } );
            toggle_hidden_controls('#upload_open' + wf_id + '_');

            // add the nonce
            jQuery('.ewz_numc').html(ewzG.nonce_string);
            jQuery('input[name="ewznonce"]').each(function(index){
                jQuery(this).attr('id', 'ewznonce'+index);
            });

            jQuery( "#auto_date_" + wf_id  ).datepicker( {showOtherMonths: true,
                                                      selectOtherMonths: true,
                                                      dateFormat:  ewzG.dateFormat,
                                                      constrainInput: true,
                                                      minDate: 0,
                                                      maxDate: "+1y" 
                                                     });
        }
        jQuery('#ewz_management').append('<br>');
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
function ewz_management(i, evnum, eObj){
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
    var str='';
    str +=   '<h4>Data Management</h4>';
    if(eObj.can_manage_webform){
        str +=  '<table class="ewz_csv_upload"><tr>';
        str +=  xtra_data_upload_str(  evnum, eObj );
        str +=  '</tr><tr>';        
        str +=  itmcsv_data_upload_str(  evnum, eObj );
        str +=  '</tr></table>';
    }
    /**** NB: action is item_list, not webform here, and requires 'get' method for pagination ********/
    str +=   '<form method="GET" action="' + ewzG.list_page  + '" id="data_form_ev' + evnum + '_">';
    str +=   '    <div class="ewzform">';
    str +=   '    <input type="hidden" name="page" value="entrywizlist">';
    str +=   '    <input type="hidden" name="ewzmode" value="list">';
    str +=   '    <input type="hidden" name="webform_id" value="' + eObj.webform_id + '">';
    str +=   '    <br>&nbsp;<img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'datasel\')">&nbsp; With selected items:';

    if( eObj.hasOwnProperty('field_options' )){

        str += field_options_str( eObj );
    }
    str += upload_date_str( evnum );

    str +=   '    <TABLE class="ewz_buttonrow">';
    var renameOnDownload = eObj.prefix.length && !eObj.apply_prefix;
    if( eObj.itemcount > 0 && eObj.can_manage_webform ){
        str +=   '  <TR> </TD><TD><button type="button" onClick="set_mode(this,\'list\', ' + evnum + ',' + renameOnDownload  + ')"  id="list_' + evnum + '" class="button-secondary">Manage Items</button></TD>';
        str +=   '     <TD></TD>';
        str +=   '  </TR>';
    }
    if(eObj.can_download){
        str +=   '  <TR>';
        str +=   '    <TD><button type="button" onClick="set_mode(this,\'spread\', ' + evnum + ', ' + renameOnDownload  + ')" id="spread_' + evnum + '" class="button-secondary">Download Spreadsheet</button></TD>';
        str +=   '    <TD><button type="button" onClick="set_mode(this,\'images\', ' + evnum + ', ' + renameOnDownload  + ')" id="images_' + evnum + '" class="button-secondary">Download Images</button></TD>';
        str +=   '    <TD><button type="button" onClick="set_mode(this,\'download\',' + evnum + ', ' + renameOnDownload  + ')" id="download_' + evnum + '" class="button-secondary">Download Images and Spreadsheet</button></TD>';
        str +=   '  </TR>';
    }
    str +=   '  </TABLE>';
    str +=   ' </div>';
    str +=   ' <div class="ewz_numc"></div>';
    str +=   '</form>';

    return str;
}

function set_mode(button, mode, evnum, renameOnDownload ){
    var jform = jQuery( button ).closest('form');
    var jmode = jform.find('input[name="ewzmode"]');
    var jwebform = jQuery( '#cfg_form_ev' + evnum + '_');
    var jbutton = jQuery(button);
    var doRenameOnDownload;
    if( jwebform.find('input[name="prefix"]').length ){
        doRenameOnDownload = jwebform.find('input[name="prefix"]').first().val().trim().length && 
        !jwebform.find('input[name="apply_prefix"]').is(':checked');  // have a prefix not applied on upload
    } else {
        doRenameOnDownload = renameOnDownload;
    }
    switch( mode ){
        case 'spread':
        case 'list':
           jform.find('input[name="ewzmode"]').val(mode);
        jform.submit();
           break;
        case 'images':
           if( ewzG.canzip  && !doRenameOnDownload ){
               jmode.val('zimages');
               jform.submit();
           } else {
               jmode.val('images');
               gen_zipfile( jform, jbutton, 'images' );
           }
           break;
        case 'download':
           if( ewzG.canzip && !doRenameOnDownload ){
               jmode.val('zdownload');
               jform.submit();
           } else { 
               jmode.val('download');
               gen_zipfile( jform, jbutton, 'download' );
           }
           break;
    }
}

function gen_zipfile( jform, jbutton, mode ){
               
    jbutton.after('<span id="temp_gen" style="text-align:left"> Processing, please wait ... <img alt="Please Wait" src="' + ewzG.load_gif + '"/></span>');

    jform.append('<input type="hidden" id="ajax_action" name="action" value="ewz_gen_zipfile" />');
    var jqxhr = jQuery.post( ajaxurl,
                         jform.serialize(),
                         function(response) {
                             jQuery("#temp_gen").remove();
                             if ( response.match(/^ewz_.*/) ) {
                                 jQuery('#ajax_action').remove();
                                 jform.find('input[name="ewzmode"]').val(mode);
                                 download_zipfile( jform, response );
                             } else {
                                 jQuery('#ajax_action').remove();
                                 alert( response );
                             }
                         }
                       );
}
        

function download_zipfile( jform, response ){
    jform.find('input[name="ewzmode"]').val('stored');
    jform.append( '<input type="hidden" name="archive_id" value="' + response + '">');
    jform.submit();
}

/* Return the html string for the editable form of the webform data */
function webform_data_str(evnum, eObj) {
    var divid = 'usel' + evnum + '_',
        clickstr = 'user_select(' + "'" + divid + "'" + ')',
        str = '';
    str += '<form method="post" action="" id="cfg_form_ev' + evnum + '_" onSubmit="return ewz_check_webform_input(this,' +  "'" + evnum +  "'"  + ', ewzG.jsvalid)">';
    str += '  <div class="ewzform">';
    str +=   '    <input type="hidden" name="webform_id" id="edit_wfid_' + evnum + '" value="' + eObj.webform_id + '">';
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

    if(eObj.canOverride){
    str +=   '        <tr id="override' + evnum + '" ><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'numitems\')">&nbsp;Maximum number of items:</td> ';
    str +=   '            <td>' + numinput_str("num_items_" + evnum, "num_items", '', 1, 30, eObj.num_items ) + '</td>';
    str +=   '        </tr>';
    }  

    str +=   '        <tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'prefix' + eObj.webform_id + '\')">&nbsp;Optional prefix:</td> ';
    str +=   '            <td><input type="text" name="prefix" id="prefix_' + evnum + '" value="' + eObj.prefix + '"  maxlength="25"></td>';
    str +=   '            <td>Apply prefix on upload &nbsp; ';
    str +=                  checkboxinput_str("apply_prefix_" + evnum, "apply_prefix", eObj.apply_prefix );
    str +=   '            </td>';
    str +=   '            <td>Generate the entire filename from the prefix &nbsp; ';
    str +=                  checkboxinput_str("gen_fname_" + evnum, "gen_fname", eObj.gen_fname );
    str +=   '            </td>';
    str +=   '        </tr>';

    str +=   '        <tr><td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'open\')">&nbsp;Open for Uploads:</td>';
    str +=   '            <td>' + checkboxinput_str('upload_open' + evnum + '_', 'upload_open', eObj.upload_open ) + '</td>';
    str +=   '            <td>  <div id="open_for_' + evnum + '_" class="ewz_hideable"><i>' + eObj.open_for_string + '</i></div> </td>';
    str +=   '        </tr>';

    str +=   '        <tr class="ewz_showable"><td> <img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'autoclose\')">&nbsp;Close Automatically:</td>';
    str +=   '             <td>' + checkboxinput_str("auto_close_" + evnum, "auto_close", eObj.auto_close );         
    str +=   '            &nbsp;  &nbsp;  Date: ' + textinput_str("auto_date_" + evnum, "auto_date", 15, eObj.auto_date);
    str +=   '           </td><td>Time: <select name="auto_time" id="auto_time_' + evnum + '">' + eObj.close_time_opts + '</select>';
    str +=   '                 </td><td>( Timezone ' + ewzG.tz + ' )  &nbsp; <br><i>Current date-time is ' + ewzG.now + '</i></td>';
    str +=   '        </tr>';


if(eObj.user_options){
    str +=   '        <tr class="ewz_hideable">';
    str +=   '            <td><img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'openfor\')">&nbsp;Show user selection list</td>';
    str +=   '            <td><input type="checkbox"  id="show_uselect_' + evnum + '" onChange="' + clickstr + '"></td>';
    str +=   '            <td><div id="' + divid + '" style="display:none">';
    str +=   '                  Open for selected users only:<br><select  multiple="multiple" size="8" name="o_user[]" id="o_user_' + evnum + '_">';
    str +=                           eObj.user_options + '</select>';
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
    var str='';
    str +=   '             <table>';
    str +=   '                 <tr><td>Name for this webform:</td>';
    str +=   '                     <td>' + eObj.webform_title + '</td>';
    str +=   '                 </tr>';
    str +=   '                 <tr><td>Identifier for use in file names: &nbsp;</td>';
    str +=   '                     <td>' +  eObj.webform_ident + '</td>';
    str +=   '                 </tr>';
    str +=   '                 <tr><td>Webform layout: </td>';
    str +=   '                     <td>' +  eObj.layout_name + '</td></tr>';
    str +=   '                 <tr><td>';
    var when;
    if( eObj.apply_prefix ){
        when = ' on upload ';
    } else {
        when =  ' on download ';
    }
    if( eObj.gen_fname ){
        str += 'Filename generated <br> &nbsp;  &nbsp; ' + when + ' from formula: ';
    } else {
        str += 'Prefix formula applied <br> &nbsp;  &nbsp; to filename ' + when;
    }
    str +=   '</td>';
    str +=   '                     <td>' +  eObj.prefix + '</td>';
        
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
    var str = '',
        inputid = 'csv_data_' + evnum + '_';
    str +=   '<td><form method="post" enctype="multipart/form-data" action="" id="csv_form_ev' + evnum + '_"  onSubmit="return ewz_check_csv_input("csv",' + evnum + ', \'' + inputid + '\' ) ">';
    str += '  <div class="ewzform">';
    str +=   '    <input type="hidden" name="webform_id" value="' + eObj.webform_id + '">';
    str +=   '    <input type="hidden" name="ewzmode" value="csv">';
    str +=   '    <div class="ewz_numc"></div>';

    str +=   '    <img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'csv\')">&nbsp; Upload extra per-<u>image</u> data for the webform: &nbsp; ';
    str +=   '    <input  id="' + inputid + '" name="csv_data" type="file" >';
    str +=   '    <button type="submit" id="csv_btn_' + evnum + '" name="csv_btn" class="button-secondary">Upload <b>Image</b> Data</button>';
    str +=   ' </div>';
    str +=   '</form></td>';
    return str;
}
/* Return the html string for the Admin Data Upload form */
function itmcsv_data_upload_str(  evnum, eObj ){
    var str = '',
        inputid = 'itmcsv_data_' + evnum + '_';
    str +=   '<td><form method="post" enctype="multipart/form-data" action="" id="itmcsv_form_ev' + evnum + '_"  onSubmit="return ewz_check_csv_input("itmcsv",' + evnum + ', \'' + inputid + '\' ) ">';
    str += '  <div class="ewzform">';
    str +=   '    <input type="hidden" name="webform_id" value="' + eObj.webform_id + '">';
    str +=   '    <input type="hidden" name="ewzmode" value="itmcsvdata">';
    str +=   '    <div class="ewz_numc"></div>';

    str +=   '    <img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'itmcsv\')">&nbsp; Upload extra per-<u>item</u> &nbsp; data for the webform: &nbsp;';
    str +=   '    <input  id="' + inputid + '" name="itmcsv_data" type="file"  >';
    str +=   '    <button type="submit" id="itmcsv_btn_' + evnum + '" name="csv_btn" class="button-secondary">Upload <b>Item</b> Data</button>';
    str +=   ' </div>';
    str +=   '</form></td>';
    return str;
}

/* Return the html string for the field options select boxes */
function field_options_str( eObj ){
    var field_id1,
        field_id2,
        data1,
        data2,
        fid,
        num  = 0,
        str  = '';
    for( fid in eObj.field_options ){
        if(eObj.field_options.hasOwnProperty(fid) && eObj.field_options[fid]){
            ++num;
        }
    }
    if( num > 0 ){   
        str +=   '    <TABLE class="ewz_field_opts"><TBODY><TR>';
        for( field_id1 in eObj.field_options ){
           if(eObj.field_options.hasOwnProperty(field_id1)){
               str +=   '<TH>';
               if( eObj.field_options[field_id1] ){
                   str +=   eObj.field_names[field_id1];
               }
               str +=   '</TH>';
           }
        }
        for ( data1 in eObj.custom_header ){
           if(eObj.custom_header.hasOwnProperty(data1)){
               str +=   '<TH>';
               str +=   eObj.custom_header[data1];
               str +=   '</TH>';
           }
        }

        str +=   '</TR>';
        str +=   '<TR>';
        for( field_id2 in eObj.field_options ){
           if(eObj.field_options.hasOwnProperty(field_id2)){
               str +=   '      <TD>';
               if( eObj.field_options[field_id2] ){
                   str +=   '      <select name="fopt[' + field_id2 +']"  id="l'+ eObj.webform_id + 'f' + field_id2 + '_opt_">';
                   str +=              eObj.field_options[field_id2];
                   str +=   '      </select>';
               }
               str +=   '</TD>';
           }
        }
        for( data2 in eObj.custom_options ){
           if(eObj.custom_options.hasOwnProperty(data2)){
               str +=   '      <TD>';
                   str +=   '      <select name="copt[' + data2 +']"  id="l'+ eObj.webform_id + 'c' + data2 + '_opt_">';
                   str +=              eObj.custom_options[data2];
                   str +=   '      </select>';
               str +=   '</TD>';
           }
        }
        
        str +=   '</TR></TBODY></TABLE>';
    }
    return str;
}

/* Return the html string for the upload date selection */
function upload_date_str( evnum ){
    var day,
        str  = '';

        str +=   '<TABLE class="ewz_field_opts">';
        str +=   '       <TR>';
        str +=   '          <TD>Initial upload occurred during the last &nbsp;';
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


/* Return the html string for the "add webform" and "save order" buttons */
function webform_button_str(){
    var str  = '<div class="clear alignleft">';
    str +=   '    <img alt="" class="ewz_ihelp" src="' +  ewzG.helpIcon + '" onClick="ewz_help(\'wfsort\')">&nbsp;';
    str += '         <button  type="button" class="button-secondary" id="webform_add_" onClick="add_new_webform()">Add a New Web Form</button> ';
    str += '         &nbsp; <button  type="button" class="button-secondary" id="webforms_save2_" onClick="save_webform_order()">Save Order of Webforms</button> ';
    str += '    </div> ';
    return str;
}

/* Return the html string for the layout selection */
function select_layout_str( evnum, eObj ){
    var str = '';
    str +=   '                 <td>';
    str +=   '                    <select id="layout_id' + evnum + '" name="layout_id" onChange=disable_override(' + evnum + ')>';
    str +=                           eObj.layouts_options;
    str += '                      </select>';
    str +=   '                 </td>';
    if(eObj.itemcount > 0){
        str +=   '             <td colspan="2"><div class="ewz_warn">WARNING: This form has uploaded data. ';
        str +=   '                             <br>Changing the layout or the prefix options now could cause problems.</div></td>';
    }
    return str;
}
/************************ Functions That Change The Page  ****************************************/

function disable_override(evnum){
    jQuery("#override" + evnum).hide();
}

function user_select(id){
   if(jQuery('#' + id ).is(':visible')){
        jQuery('#' + id ).hide();
    } else {
        jQuery('#' + id ).show();
    }
}



/* Actually delete the form on the server via ajax.  If successful, delete it from the page. */
function delete_webform(button, itemcount){
    var jbutton = jQuery(button),
        lname = jbutton.closest('div[id^="ewz-postbox-webform_ev"]').find('span[id^="wf_title_"]').text(),
        confirmstring = '',
        thediv,id,del_nonce,jqxhr;

    thediv = jbutton.closest('div[id^="ewz_admin_webforms_ev"]');
    id = thediv.find('input[name="webform_id"]').first().attr("value");
    if( '' === id || null === id || undefined ===  id ){
        thediv.remove();
        return;
    }
    if(itemcount > 0){
        confirmstring +=  ewzG.errmsg.warn + "\n"  + ewzG.errmsg.hasitems + "\n\n";
    }
    confirmstring += ewzG.errmsg.reallydelete;
    confirmstring += "\n" + ewzG.errmsg.noundo;

    if( confirm( confirmstring ) ){
            jbutton.after('<span id="temp_del" style="text-align:left"> Processing, please wait ... <img alt="Please Wait" src="' + ewzG.load_gif + '"/></span>');
            del_nonce = thediv.find('input[name="ewznonce"]').val();
            jqxhr = jQuery.post( ajaxurl,
                                     {
                                         action: 'ewz_del_webform',
                                         webform_id: id,
                                         webform_title: lname,
                                         ewznonce: del_nonce
                                     },
                                     function (response) {
                                         jQuery("#temp_del").remove();
                                         if( '1' == response ){
                                             thediv.remove();
                                         } else {
                                             alert( response );
                                         }
                                     }
                                   );       
    }
}


function add_new_webform(){
    var jthis,
        num = ewzG.webforms.length,
        newform = {},
        jQnew, newid;
    newform.can_manage_webform = true;
    newform.can_edit_webform = true;
    newform.can_download = true;
    newform.can_override = false;
    newform.itemcount = 0;
    newform.upload_open = false;
    newform.open_for_string = "";
    newform.layouts_options = ewzG.base_options;
    newform.webform_title = '--- New Web Form ---';
    newform.webform_id = '';
    newform.webform_ident = '';
    newform.prefix = '';
    newform.apply_prefix = true;
    newform.gen_fname = false;
    newid = 'X'+num;
    jQnew = jQuery(ewz_management(num, newid, newform));
    jQuery('#ewz_management').append(jQnew);
    jQnew.find('span[id^="tpg_header"]').first().html("New Web Form: <i>To make it permanent, set the options and save</i>");
    jQnew.find('.ewz_numc').html(ewzG.nonce_string);
    jQnew.find('input[name="ewznonce"]').each(function(index){
        jQuery(this).attr('id', 'ewznonce' + newid + index);
    });

    jQnew.find('input[name="webform_id"]').val('');

    jQnew.find('input[id^="webform_title_ev"]').change(function(){
        jthis = jQuery(this);
        jthis.closest('div[id^="ewz-postbox-webform_ev"]').find('span[id^="wf_title_"]').text(jthis.val());
    });

    jQuery('#o_user_' + newid + '_').html(ewzG.webforms[0].user_options);
    jQuery('#o_user_' + newid + '_').val([]);
    jQuery('#upload_open' + newid + '_').change( function() {
        toggle_hidden_controls(this);
    } );
    toggle_hidden_controls('#upload_open' + newid + '_');
}

function save_webform_order(){
    var wf_nonce = jQuery('input[name="ewznonce"]').val();
    var data = {
        action: 'ewz_save_webform_order',
        ewznonce:   wf_nonce,
        ewzmode:  'wf_set',
        wforder: new Object()
    };
    if( jQuery('input[id^="edit_wfid"][value=""]').length > 0 ){
        alert("Please save your unsaved Webform before trying to rearrange them");
        return;
    }
        
    jQuery('input[id^="edit_wfid"]').each(function(index){
        data['wforder'][jQuery(this).val()] = index;
    });
    data['action'] = 'ewz_save_webform_order';
    data['ewznonce'] = wf_nonce;
    var jqxhr = jQuery.post( ajaxurl,
                             data,
                             function (response) {
                                 jQuery("#temp_del").remove();
                                     alert( response );
                             }
                           );       
}

/* Validation */
function ewz_check_csv_input(ftype, evnum, file_input_id){
    var  theFile = document.getElementById(file_input_id).files[0],
          mb;
    jQuery('#' + ftype+'_btn_' + evnum ).prop("disabled", true);
    if(theFile !== null){

        // get selected file element
        mb =  Math.floor( theFile.size / 1048576 );
        if( mb > ewzG.maxUploadMb ){
            jQuery('#'+ftype+'_btn_' + evnum ).prop("disabled", false);
            alert( 'Sorry, your file size is ' + mb + 'M, which is bigger than the allowed maximum of ' + ewzG.maxUploadMb + 'M' );
            return false;
        }
        var theType = theFile.type;
        var theName = theFile.name;
        if( ( theType.length > 0 && theType != "text/csv" ) ||
            ( theName.length > 0 && !/\.csv$/.test(theName)  ) ){
            jQuery('#'+ftype+'_btn_' + evnum ).prop("disabled", false);
            alert( theFile.name + ': Found filename: ' +  theName + ', detected type: ' + theType + '.  Sorry, the file must be of type "text/csv"' );
            return false;
        }
    }
    return true;
}

function ewz_check_webform_input(form, evnum, do_js_check){
    var jform,
    pref;
    jform = jQuery(form);
    if( jform.find('input[id^="apply_prefix"]').is(':checked') &&
        !jform.find('input[id^="prefix_"]').val().trim( ).length ){
        jform.find('input[id^="apply_prefix"]').prop('checked', false );
    }
    if( jform.find('input[id^="gen_fname"]').is(':checked') &&
        !jform.find('input[id^="prefix_"]').val().trim( ).length ){
        jform.find('input[id^="gen_fname"]').prop('checked', false );
    }

    if( do_js_check) {
        jQuery('#cfg_form_wf' +  evnum + '_').prop("disabled", true);
        try{
            if(!jform.find('input[id^="webform_title_ev"]').val().trim( )){
                err_alert(evnum, ewzG.errmsg.formTitle);
                return false;
            }
            if(!jform.find('input[id^="webform_ident_ev"]').val()){
                err_alert(evnum, ewzG.errmsg.formIdent);
                return false;
            }
            if(!jform.find('input[id^="webform_ident_ev"]').val().match(/^[a-z0-9_\-]+$/i)){
                err_alert(evnum, ewzG.errmsg.formIdent);
                return false;
            }
            pref = jform.find('input[id^="prefix_"]').val();
            if(!pref.match(/^[\[\]A-Z0-9~\-_]*$/i)){
                err_alert(evnum, ewzG.errmsg.formPrefix);
                return false;
            }
            if( pref.match(/\[~1\]/) && !(jform.find('input[id^="gen_fname"]').is(':checked'))){
                err_alert(evnum, ewzG.errmsg.numPrefix);
                return false;
            } 
            return true;
        } catch(except) {
            jQuery('#cfg_form_wf' +  evnum + '_').prop("disabled", false);
            err_alert( evnum, "Sorry, there was an unexpected error: " + except.message);
            return false;
        }
    } else {
        return true;
    }
}

function err_alert(evnum, msg){
    jQuery('#cfg_form_wf' +  evnum + '_').prop("disabled", false);
    alert(msg);
}
