'use strict';
var ewzF;

jQuery(document).ready(function() {
    if( ewzF ){
        init_ewz_followup();
    }
});


/* To stop IE from generating errors if a console.log call was left in */
function f_fixConsole()
{
    if ( console === undefined)
    {
        console = {}; // define it if it doesn't exist already
    }
    if ( console.log === undefined)
    {
        console.log = function() {
        };
    }
    if ( console.dir === undefined)
    {
        console.dir = function() {
        };
    }
}

// the OnLoad function
function init_ewz_followup() {
    f_fixConsole();
    // show the user any message coming from the server
    if (ewzF.errmsg) {
        alert(ewzF.errmsg.replace(/;;.*?$/gm,'').replace(/~/g,"\n"));
    }
    jQuery('input[name="ewzuploadnonce"]').each(function(index) {
        jQuery(this).attr('id', 'ewzuploadnonce' + index);
    });
    var tablew = 0;
    jQuery('#scrollablediv').find('table[class="ewz_upload_table"]').each(function(){
               if(jQuery(this).outerWidth() > tablew ){ tablew = jQuery(this).outerWidth(); }
    });
    var scrollw = jQuery('#scrollablediv').innerWidth();
    if ( scrollw <= tablew ){
        var sub = 'at the bottom of the page';
        if(ewzF.f_field){
            sub = "just above the submit button";
        }
        jQuery('#scrollablediv').prepend("<i>The available space is too narrow to display all of this form.<br>You should see <b>a scrollbar " +
                                          sub + ".</i></b>");
    }
}

function f_fix_radios(rad_name){
    jQuery("#foll_form").find( 'input[type="radio"][name="' + rad_name + '"]' ).each( function(){
        var jrad = jQuery(this);
        if( jrad.prop( "checked" ) ){
            jrad.closest('form').append('<input type="hidden" name="' + jrad.val() + '" value="1" >');
        } else {
            jrad.closest('form').append('<input type="hidden" name="' + jrad.val() + '" value="0" >');
        }
        jrad.prop("disabled", true);
    });
}


function f_fix_cbs(){
    jQuery("#foll_form").find( 'input[type="checkbox"][id^="rdata_"]' ).each( function(){
        var jchk = jQuery(this);
        if( !jchk.prop( "checked" ) ){
            jchk.closest('form').append('<input type="hidden" name="' + jchk.attr("name") + '" value="off" >');
            jchk.prop("disabled", true);
        }
    });

}


/* For validation   Return an array of values entered by the user              */
/* Each value is an array whose first entry is the entered value,               */
/* and whose second entry contains additional data depending on the input type */
function f_get_value( jitem ){
    var fieldval;
    if (jitem.length > 0) {
        switch(ewzF.f_field.field_type){
        case 'str':
            fieldval = [ jitem.val(), jitem.val() ];
            break;
        case 'img':
            if( jitem.prop("src") ) {
                fieldval = [ jitem.attr("src"), 'old' ];
            } else {
                fieldval = [ jitem.val(), 'new' ];
            }
            break;
        case 'opt':
            var sel = jitem.find(":selected");
            fieldval = [ sel.val(), sel.text() ];   // need value for code and text for message
            break;
        case 'rad':
            if( jitem.prop("checked") ){
               fieldval  = [ "checked", 'checked' ];
            } else {
                fieldval = [ '', 'checked' ];
            }
            break;
        case 'chk':
            if( jitem.prop("checked") ){
                fieldval = [  "checked", 'checked' ];
            } else {
                fieldval = [  "", 'checked'  ];
            }
            break;
        }

    } else {
        fieldval = [ '', '' ];
    }
    return fieldval;
}


/* Check any max rules on drop-down option selections */
function f_options_check( field){
    var msg = '', status = true, maxn, key, text;
    if (  field.field_type === 'opt' || field.field_type === 'chk'  ) {
        var optcount = {};
        var textvals = {};
        jQuery('#foll_form').find('[id^="rdata_"]').each(function() {
            var sel = f_get_value(jQuery(this));
            var val = sel[0];
            if ( val ) {
                textvals[ val ] =  sel[1]; // for display to user
                if ( optcount[ val ] === undefined) {
                    optcount[ val ] = 1;
                } else {
                    ++optcount[ val ];
                }
            }
        });
        for ( key in optcount ) {
            if(!optcount.hasOwnProperty(key)){ continue; }
            if( field.field_type === 'opt' ){
                maxn = field.Xmaxnums[key];
                text = 'equal to ';
            } else {
                maxn = field.fdata.chkmax;
                text = ' ';
            }
            if ( ( maxn > 0 ) && ( optcount[key] > maxn ) ) {
                if( msg ){
                    msg += "\n";
                }
                msg += "no more than " + maxn +  " items may have " +  field.field_header + " " + text + textvals[key];
                status = false;
            }               
        }
    }
    if( !status ){
        alert( "** Sorry, " + msg );
    }
    return status;
}


/* validation                                           */
function f_check_missing( ) {
    var status = true, except;
    jQuery('#foll_form').find('[id^="rdata_"]').each(function() {
        try {
            var fval = f_get_value( jQuery(this) );
            if( ewzF.f_field.required && ( 1 > fval[0].length ) ){
                status = false;
                alert( "Field " + ewzF.f_field.field_header + " is required" );
            }

        } catch (except) {
            alert("** Sorry, there was an unexpected error: " + except.message);
            status = false;
        }
    });
    return status;
}


function f_validate(){
    jQuery('#f_submit').prop("disabled", true);

    if( !ewzF.jsvalid ){
        f_fix_radios('radioFollowup');
        f_fix_cbs();
        return true;
    } else {
        var no_errs = true;
        no_errs  = no_errs && f_check_missing( );
        no_errs  = no_errs && f_options_check( ewzF.f_field );
        if( !no_errs ){
            // ie found some errors, cancel submit and allow further changes
            jQuery('#f_submit').prop("disabled", false);
            return false;
        }
        f_fix_radios('radioFollowup');
        f_fix_cbs();
        return true;
    }
}
