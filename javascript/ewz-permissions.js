"use strict";
var ewzG1;

/* Set the selections to reflect the  permissions stored for the selected user */
/* Show a summary of these permissions */

var ewzG;

function ewz_set_user(){
    var userid = jQuery("#ewz_have_perm").val();
    jQuery("#ewz_user_perm").val(userid);
    ewz_show_perms();
}

function ewz_show_perms(){
    jQuery("#ewz_can_edit_layout").val([]);
    jQuery("#ewz_can_assign_layout").val([]);
    jQuery("#ewz_can_edit_webform").val([]);
    jQuery("#ewz_can_manage_webform").val([]);
    jQuery("#ewz_can_manage_webform_L").val([]);
    jQuery("#ewz_can_download_webform").val([]);
    jQuery("#ewz_can_download_webform_L").val([]);

    var i, j, val, str, opt,
        permcount = 0,
        userid = jQuery("#ewz_user_perm").val(),
        pp = '<table>';

    ewzG = ewzG1.gvar;
    if( ewzG.ewz_perms ){
        for(i = 0; i < ewzG.ewz_perms.length; ++i){
            if(ewzG.ewz_perms[i].user_id == userid){
                for(j = 0; j < ewzG.ewz_perms[i].meta_value.length; ++j){
                    val = ewzG.ewz_perms[i].meta_value[j];
                    str = '#' + ewzG.ewz_perms[i].meta_key +' option[value=' + val + ']';
                    opt = jQuery(str);
                    if(opt !== null){
                        ++permcount;
                        opt.attr("selected","selected");
                        pp += "<tr><td>" +jQuery('#' + ewzG.ewz_perms[i].meta_key +'T').text() + "</td><td> " + opt.text() + "</td></tr>";
                    }
                }
            }
        }
    }
    pp += "</table>";
    if( permcount > 0 ){
        jQuery('#uperms').html('<br><i><b>All Currently Assigned Permissions for this user</b></i>: ' + pp);
    } else {
        jQuery('#uperms').html('<br><i>No EntryWizard permissions currently assigned for this user</i>');
    }
}

/* validation and removal of "none" options */
function ewz_check_perm_input(the_form){
    var except;
    jQuery('#submit').prop("disabled", true);
    try{
        var user = jQuery("#ewz_user_perm option:selected").val();
        if( !user || '0' === user ){
            jQuery('#submit').prop("disabled", false);
            alert("Please select a user for whom the permissions are to apply");
            return false;
        }
        jQuery('select[name$="[]"] option[value="0"]').remove();
        return true;
    } catch(except) {
        jQuery('#submit').prop("disabled", false);
        alert("Sorry, there was an unexpected error: " + except.message);
        return false;
    }
}
