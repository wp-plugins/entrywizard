/*************** Functions for the List page ************************************/

function processForm(formId) {
    'use strict';
    var jform = jQuery("#"+formId);
    if( ( jform.find('select[name="action"]').val() === 'ewz_batch_delete') ||
        ( jform.find('select[name="action2"]').val() === 'ewz_batch_delete') ){

        jQuery('#message').html('<img alt="" src="' + ewzG.load_gif + '"/>');
        jQuery.post( ajaxurl,
                     jform.serialize(),
                     function (response) {
                         alert(response);
                         jQuery('#message').html('');
                         document.location.reload(true);
                     });
        return false;

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




