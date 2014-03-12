/*************** Functions for the List page ************************************/

function processForm(formId) {
    'use strict';
    var jform = jQuery("#"+formId);
    jQuery('#doaction').prop("disabled", true );
    jQuery('#doaction2').prop("disabled", true );
    if(( jform.find('select[name="action"]').val() === 'ewz_attach_imgs') ||
        ( jform.find('select[name="action2"]').val() === 'ewz_attach_imgs') ){
        if( !(jform.find('input[type="hidden"][name="ifield"]').length > 0) &&
            !(jform.find('input[type="radio"][name="ifield"]:checked').length > 0) ){
            jQuery('#doaction').prop("disabled", false );
            jQuery('#doaction2').prop("disabled", false );
            alert( "Please select an image column");
            return false;
        }
    }      
           

    if( ( jform.find('select[name="action"]').val() === 'ewz_batch_delete') ||
        ( jform.find('select[name="action2"]').val() === 'ewz_batch_delete')||
        ( jform.find('select[name="action"]').val() === 'ewz_attach_imgs') ||
        ( jform.find('select[name="action2"]').val() === 'ewz_attach_imgs') ){

        jQuery('#message').html('<img alt="" src="' + ewzG.load_gif + '"/>');
        var overlay = jQuery('<div></div>').prependTo('body').attr('id', 'overlay');
        return true;
    } else {
        jQuery('#doaction').prop("disabled", false );
        jQuery('#doaction2').prop("disabled", false );
        alert( "Please select an action");
        return false;
    }        
}

function processIPP(formId) {
    'use strict';
    var jform = jQuery("#"+formId).serialize();  
    jQuery('#ippmsg').html('<img alt="" src="' + ewzG.load_gif + '"/>');
    // NB: reload only after response is received
    jQuery.post( ajaxurl,
                 jform,
                 function (response) {
                     jQuery('#ippmsg').html('');
                     document.location.href = document.location.href + '&paged=1';
                     document.location.load(document.location.href);
                 });    
    return false;
}

jQuery(document).ready(function(){
    fixConsole();
    if(ewzG.message){
        alert(ewzG.message);
    }
});




