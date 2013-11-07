/*************** Functions for the List page ************************************/

function processForm(formId) {
    'use strict';
    var jform = jQuery("#"+formId);
    if( ( jform.find('select[name="action"]').val() === 'ewz_admin_del_items') ||
        ( jform.find('select[name="action2"]').val() === 'ewz_admin_del_items') ){

        return true;

    } else if( ( jform.find('select[name="action"]').val() === 'ewz_attach_imgs') ||
              ( jform.find('select[name="action2"]').val() === 'ewz_attach_imgs') ){
        
        jQuery('#message').html('<img alt="" src="' + ewzG.load_gif + '"/>');
        jQuery.post( ajaxurl,
                     jform.serialize(),
                     function (response) {
                         alert(response);
                         jQuery('#message').html('');
                     });
        return false;
    } else {
        alert( "Please select an action");
        return false;
    }        
}

jQuery(document).ready(function(){
    fixConsole();
    if(ewzG.message){
        alert(ewzG.message);
    }
});




