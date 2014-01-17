
var ewzF;

jQuery(document).ready(function() {
    if( ewzF ){
        init_ewz_followup();
    }
});



/* To stop IE from generating errors if a console.log call was left in */
function f_fixConsole()
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

// the OnLoad function
function init_ewz_followup() {
    'use strict';
    var name, row, ff;
    f_fixConsole();
    // show the user any message coming from the server
    if (ewzF.errmsg) {
        alert(ewzF.errmsg.replace(/;;.*?$/gm,'').replace(/~/g,"\n"));
    }
    jQuery('input[name="ewzuploadnonce"]').each(function(index) {
        jQuery(this).attr('id', 'ewzuploadnonce' + index);
    });

}

function f_fix_radios_chks(rad_name){
    jQuery("#foll_form").find( 'input[type="radio"][name="' + rad_name + '"]' ).each( function(){
        var jrad = jQuery(this);
        if( jrad.prop( "checked" ) ){
            jrad.closest('form').append('<input type="hidden" name="' + jrad.val() + '" value="1" >');
        } else {
            jrad.closest('form').append('<input type="hidden" name="' + jrad.val() + '" value="0" >');
        }
        jrad.prop("disabled", true);
    });

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
    'use strict';
    var fieldval;
    if (jitem.length > 0) {
        switch(ewzF.f_field.field_type){
        case 'str':
            fieldval = [ jitem.val(), jitem.val() ];
            break;
        case 'img':
            if( jitem.prop("src") ) {
                fieldvals[i] = [ jitem.attr("src"), 'old' ];
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
    'use strict';
    var msg = '', status = true, maxn, key, text;
    if (  field.field_type === 'opt' || field.field_type === 'chk'  ) {
        var optcount = {};
        var textvals = {};
        jQuery('#foll_form').find('[id^="rdata_"]').each(function() {
            var sel = f_get_value(jQuery(this));
            var val = sel[0];
            if ( val ) {
                textvals[ val ] =  sel[1]; // for display to user
                if (typeof optcount[ val ] === 'undefined') {
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
function f_check_data( jinput ) {
    'use strict';
    var status;
    var fval;

    try {
        status = true;
        fval = f_get_value( jinput );
        if( ewzF.f_field.required && ( 1 > fval[0].length ) ){
            status = false;
            alert( "Field " + ewzF.f_field.field_header + " is required" );
        }
        return status;

    } catch (except) {
        alert("** Sorry, there was an unexpected error: " + except.message);
        return false;
    }

}


function f_validate(){
    var no_errs = true;

    no_errs  = no_errs && f_options_check( ewzF.f_field );

    f_fix_radios_chks('radioFollowup');

    if( ewzF.jsvalid ){        
        return no_errs;
    } else {
        return true;
    } 
}
