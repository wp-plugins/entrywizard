'use strict';
jQuery(document).ready(function() {
    init_ewz_upload(window);
});

/* To stop IE from generating errors if a console.log call was left in */
function fixConsole()
{
    if (console === undefined)
    {
        console = {}; // define it if it doesn't exist already
    }
    if (console.log === undefined)
    {
        console.log = function() {
        };
    }
    if (console.dir === undefined)
    {
        console.dir = function() {
        };
    }
}

// the OnLoad function
// allows for more than one form on a page.  Each has an ewzG object ewzG_{webform_id}
function init_ewz_upload(ewz_win) {
    var name, wkey1, wkey;
    fixConsole();
    if (ewz_win.RuntimeObject) {   // IE 8 or earlier
        wkey = RuntimeObject('ewzG*');
        for (name in wkey)
        {
            if (wkey.hasOwnProperty(name)) {
                //window.console && console.log('RuntimeObject: ', name, typeof wkey[name], ' >>> ',  wkey[name]);
                do_setup(wkey[name]);
            }
        }
    } else {
        // window.console && console.log( ewz_win );
        for (wkey1 in ewz_win) {
            if (ewz_win.hasOwnProperty(wkey1)) {
                if (wkey1.substring(0, 5) === 'ewzG_') {
                    // window.console && console.log( 'this: ', wkey1, typeof ewz_win[wkey1], ' >>> ',  ewz_win[wkey1] );
                    do_setup(ewz_win[wkey1]);
                }
            }
        }
    }
    jQuery('input[name="ewzuploadnonce"]').each(function(index) {
        jQuery(this).attr('id', 'ewzuploadnonce' + index);
    });


    // Bind the onchange event of the inputs to flag the inputs as being "ewzdirty".
    jQuery(":input").change(
        function() {
            // Add ewzdirty flag to the input in question (whose value has changed).
            jQuery(this).addClass("ewzdirty");
        }
    );
}

function do_setup(ewzG) {
    // ewzG is null if not logged in
    // make any change in any input enable the "submit" button
    jQuery("#ewz_form_" + ewzG.webform_id).on("change keydown paste input", ":input:not(:button)",
                                              function() {
                                                  do_changed( jQuery(this), ewzG.webform_id);
                                              });
    // show the user any message coming from the server
    if (ewzG.errmsg) {
        alert(ewzG.errmsg.replace(/;;.*?$/gm,'').replace(/~/g,"\n"));
    }

   // check_table_width(ewzG.webform_id);
}


/* After a change, enable Submit and create the Clear button for the row if it doesnt exist */
function do_changed( jitem, webform_id ) { 
    jitem.closest('form').find('button[id^="ewz_fsubmit"]').prop("disabled", false);
    var jrow = jitem.closest('tr');
    var jtable = jrow.closest('table');
    var jhead = jtable.find('thead');
    var jbody = jtable.find('tbody');
    // header row
    if( jhead.find('th[class="btn"]').length === 0  ){
        jhead.children().has('th').append('<th class="btn"></th>');
        jbody.children().has('td').append('<td class="btn"></td>' );
    }

    // changed row
    var    jlastcol = jrow.children().last();

    var    jrownum;
    if (jlastcol.find('button').length === 0) {
        jrownum = jrow.attr("id").replace("row", "").replace(/_\d*$/, '');
        jlastcol.append('<button  type="button" id="clear' + jrownum +
                        '"  onClick="clear_row(this, ' + webform_id + ' )">Clear</button>');
    }
}

/* Return an array of values entered by the user                               */
/* Each item is an array whose first entry is the entered value,               */
/* and whose second entry contains additional data depending on the input type */
function get_values(ewzG, webform_id ){
    var row, fvalues, i, jelem, sel;
    fvalues = [];
    for (row = 0; row < ewzG.num_items; ++row) {
        fvalues[row] = {};
        for (i in ewzG.layout.fields) {
            if (!ewzG.layout.fields.hasOwnProperty(i)) { continue; }
            jelem = jQuery("#rdata_" + row + "__" + ewzG.layout.fields[i].field_id + "__" + webform_id );
            if (jelem.length > 0) {
                switch(ewzG.layout.fields[i].field_type){
                case 'str':
                    fvalues[row][i] = [ jelem.val(), jelem.val() ];
                    break;
                case 'img':
                    if( jelem.prop("src") ) {
                        fvalues[row][i] = [ jelem.attr("src"), 'old' ];
                    } else {
                        fvalues[row][i] = [ jelem.val(), 'new' ];
                    }
                    break;
                case 'opt':
                    sel = jelem.find(":selected");
                    fvalues[row][i] = [ sel.val(), sel.text() ];   // need value for code and text for message
                    break;
                case 'rad':
                    if( jelem.prop("checked") ){
                        fvalues[row][i] = [ "checked", 'checked' ];
                    } else {
                        fvalues[row][i] = [ '', 'checked' ];
                    }
                    break;
                case 'chk':
                    if( jelem.prop("checked") ){
                        fvalues[row][i] = [  "checked", 'checked' ];
                    } else {
                        fvalues[row][i] = [  "", 'checked'  ];
                    }
                    break;
                }
            } else {
                fvalues[row][i] = [ '', '' ];
            }
        }
    }
    return fvalues;
}

/* return a list of all required fields that are missing */
function missing_check( ewzG, fvalues, use_row ){
    var missing, status, row, row1, i;
    missing = '';
    status = true;
    for (row = 0; row < ewzG.num_items; ++row) {
        if( !use_row[row] ){ continue; }
        row1 = row + 1;   // for user display
        for (i in ewzG.layout.fields) {
            if (ewzG.layout.fields.hasOwnProperty(i)) {
                if( ( ewzG.layout.fields[i].required ) && isblank( fvalues[row][i][0] ) ){
                    if (missing) {
                        missing += ", ";
                    }
                    missing += " row " + row1 + ": " + ewzG.layout.fields[i].field_header;
                    status = false;
                }
            }
        }
    }
    if( !status && ewzG.jsvalid ){
        alert( "** Sorry, there are some required items missing:\n" + missing );
    }
    return status;
}

/* Check any max rules on drop-down option selections */
function options_check(  ewzG, fvalues, use_row ){
    var optcount, msg, status, textvals, row, sel_val, key, maxn, field_id, ftype, text;
    msg = '';
    status = true;
    for (field_id in ewzG.layout.fields) {
        if (!ewzG.layout.fields.hasOwnProperty(field_id)) { continue; }
        ftype = ewzG.layout.fields[field_id].field_type;
        if (ftype === 'opt' || ftype === 'chk') {
            optcount = {};
            textvals = {};
            for (row = 0; row < ewzG.num_items; ++row) {
                if( !use_row[row] ){ continue; }
                sel_val = fvalues[row][field_id][0];
                if ( sel_val  ) {
                    textvals[ sel_val ] = fvalues[row][field_id][1];  // for display to user
                    if (optcount[ sel_val ] === undefined) {
                        optcount[ sel_val ] = 1;
                    } else {
                        ++optcount[ sel_val ];
                    }
                }
            }
            for ( key in optcount ) {
                if(!optcount.hasOwnProperty(key)){ continue; }
                if( ftype === 'opt' ){
                    maxn = ewzG.layout.fields[field_id].Xmaxnums[key];
                    text = 'equal to ';
                } else {
                    maxn = ewzG.layout.fields[field_id].fdata.chkmax;
                    text = ' ';
                }
                if ( ( maxn > 0 ) && ( optcount[key] > maxn ) ) {
                    if( msg ){
                        msg += "\n";
                    }
                    msg += "no more than " + maxn +  " items may have " +  ewzG.layout.fields[field_id].field_header + " " + text + textvals[key];
                    status = false;
                }                
            }
        }
    }
    if( !status && ewzG.jsvalid ){
        alert( "** Sorry, " + msg );
    }
    return status;
}

/* If any restrictions are not satisfied, show an alert and return false */
/* Otherwise, return true                                                */
function restrictions_check( ewzG, fvalues, use_row ){
    var row, matches_restr, row1, field_id, restr1;
    for (row = 0; row < ewzG.num_items; ++row) {
        if ( !use_row[row] ) { continue; }
        row1 = row + 1;   // for user display

        for (restr1 in ewzG.layout.restrictions) {
            if (!ewzG.layout.restrictions.hasOwnProperty(restr1)) { continue; }

            matches_restr = true;      // initialize

            // set matches_restr for the row to false if any field does not match
            for ( field_id in ewzG.layout.fields) {
                if (!ewzG.layout.fields.hasOwnProperty(field_id)) { continue; }
                matches_restr = matches_restr &&
                    field_matches_restr( ewzG.layout.restrictions[restr1][ewzG.layout.fields[field_id].field_id], fvalues[row][field_id][0]);
            }

            // if matches_restr is still true, alert and return false
            if ( matches_restr && ewzG.jsvalid  ) {
                alert("** Sorry, there was an error in row " + row1 + ": " + ewzG.layout.restrictions[restr1].msg);
                return false;
            }
        }
    }
    return true;
}


/* validation                                           */
function check_data(ewzG, webform_id) {
    var status, fvalues, use_row, row, field_id, except;
    fvalues = get_values(ewzG, webform_id );
    use_row = {};
    for (row = 0; row < ewzG.num_items; ++row) {
        for (field_id in ewzG.layout.fields) {
            if (!ewzG.layout.fields.hasOwnProperty(field_id)) { continue; }
            if( fvalues[row][field_id][0] ){
                use_row[row] = true;
            }
        }
    }
    try {
        status = true;
        status = status && missing_check(  ewzG, fvalues, use_row );
        status = status && options_check(  ewzG, fvalues, use_row );
        status = status && restrictions_check(  ewzG, fvalues, use_row );

        if (status) {
            disable_unused_rows( webform_id, ewzG, use_row );
        }
        return status;

    } catch (except) {
        alert(except.message);
        return false;
    }
}

// handling the unused rows on the server gets too complicated
function disable_unused_rows( webform_id, ewzG, use_row ){
    var row, fld_id, idstr;
    for (row = 0; row < ewzG.num_items; ++row) {
        if (!use_row[row]) {
            for (fld_id in ewzG.layout.fields) {
                if( !ewzG.layout.fields.hasOwnProperty(fld_id) ){ continue; }
                idstr = "#rdata_" + row + "__" + ewzG.layout.fields[fld_id].field_id + "__" + webform_id;
                jQuery(idstr).prop("disabled", true);
            }
        }
    }
}

// Does the ajax call to delete an item on the server
// On success, calls clear_row
function delete_item(button, webform_id) {
    var ewzG = window['ewzG_' + webform_id],
    jbutton = jQuery(button),
    item_id = jbutton.siblings('[name^="item_id"]').val(),
    jrow = jbutton.closest('tr'),
    del_nonce = jrow.closest('form').find('[name="ewzuploadnonce"]').val(),
    jqxhr;
    if (confirm("Really delete the item? This action cannot be undone.")) {
        jbutton.after('<div id="temp_load_' + webform_id +'" class="ewz_progress"><img alt="" src="' + ewzG.load_gif + '"/></div>');
        jqxhr = jQuery.post(ewzG.ajaxurl,
                            {
                                action: 'ewz_del_item',
                                item_id: item_id,
                                ewzdelnonce: del_nonce
                            },
                            function(response) {
                                jQuery("#temp_load_" + webform_id).remove();
                                if (response == '1') {
                                    clear_row(button, webform_id);
                                } else {
                                    alert(response + "\n\nRefresh the page to see the current status.");
                                }
                            });
    }
}

// Clear input previously entered
// Remove all images, hidden inputs
// Set select, text values to '', uncheck checkboxes and radio buttons
// Remove 'ewzdirty' flag and hidden inputs
// Remove the 'clear' button
// Disable submit if no other data
function clear_row(button, webform_id) {
    var jrow = jQuery(button).closest('tr'),
    jform;
    // img input cell will contain either 1: image alone, with id ^rdata  or 2: file input with id ^rdata, alone or with a data div
    // Note must specify file type, there are other inputs ^rdata.
    jrow.find("input[id^='rdata'][type='file'], img[id^='rdata']").each(function() {
        var oldid = jQuery(this).attr("id"),
        oldname = jQuery(this).attr("name"),
        jcell = jQuery(this).closest('div[class="fdisplay"]');
        jcell.html(empty_img_info(oldid, oldname,webform_id ));
    });

    jrow.find("img").remove();
    // reset both properties and attributes for non-image items
    jrow.find("input:text").val('').attr("value", '');
    jrow.find("input:radio").prop("checked", false ).attr("checked", false );
    jrow.find("input:checkbox").prop("checked", false ).attr("checked", false );
    jrow.find("option").prop("selected", false ).attr("selected", false ).attr("disabled", false);
    jrow.find("select").val(0);
    jrow.find("input[class='ewzdirty']").removeClass("ewzdirty");
    jrow.find('input[type="hidden"]').remove();
    jrow.find("button").remove();

    jform = jrow.closest('form');
    if (jform.find('tr td:last-child').has('button').size() === 0) {
        jform.find('tr td:last-child').remove();
        jform.find('tr th:last-child').remove();
    }
    if (jform.find(":input[class='ewzdirty']").size() === 0) {
        jQuery("#ewz_fsubmit_" + webform_id).prop({
            disabled: true
        });
    }
}

// display the data as modifiable instead of just the summary
function show_modify(button, webform_id) {
    button.style.display = "none";
    document.getElementById("ewz_modify_" + webform_id).style.display = "block";
    document.getElementById("ewz_stored_" + webform_id).style.display = "none";
    document.getElementById("ewz_change_" + webform_id).style.display = "none";

    // disable submit until changes made, then enable - needed to indicate a deletion was actually done on server
    jQuery("#ewz_fsubmit_" + webform_id).prop({
        disabled: true
    });
    check_table_width(webform_id);
    
}

function check_table_width(webform_id){
    var tablew = jQuery('#scrollablediv_' + webform_id).find('table[class="ewz_upload_table ewz_padded"]').outerWidth();
    var scrollw = jQuery('#scrollablediv_' + webform_id).innerWidth();
    var jmod = jQuery('#ewz_modify_' + webform_id);
    if ( parseInt( scrollw, 10 ) <= parseInt( tablew, 10 ) ){

        if( jmod.text().indexOf("too narrow") < 0 ){
            var did = '<i>The available space is too narrow to display all of this form. You should see <b>a scrollbar just above the submit button.</b></i>' ;
            jmod.prepend(did);
        }
    } else {
         jQuery('#ewz_warn_' + webform_id).text('');
    }
}



////////////////// Image File Info ////////////////
// an image file has been selected, display it's thumbnail and info
function fileSelected(field_id, input_id, webform_id) {
    var pathre = /([^\\\/:]+)$/,
        ewzG = window['ewzG_' + webform_id],
        freader,
        files,oFile,fields,limits,rFilter;

    jQuery('#upload_response_' + webform_id).hide();

    if (window.FileReader !== undefined) {

        freader = new FileReader();
        freader.onload = function(fileref) {
            fileref.preventDefault();  // prevent display of full image on drag-drop

            // remove any existing image thumbnail, create the new img
            jQuery('#' + input_id).closest('td').find('img').remove();

            jQuery('#dv_' + input_id).append('<img style="height: ' + ewzG.thumb_height + 'px;" alt="" id="im_' + input_id + '" class="ewz_thumb" src="' + fileref.target.result + '">');

            var theImage = document.getElementById('im_' + input_id);
            theImage.onload = function() {

                // by the time this is run, oFile has been defined as files[0]
                var errmsg = invalid_image(field_id, theImage, webform_id);
                ewzG.sResultFileSize = bytesToSize(oFile.size);

                if (errmsg && ewzG.jsvalid ) {
                    alert('** ' + oFile.name + ":\n\n" +
                          "\nSize: " + ewzG.sResultFileSize + "\nType: " + oFile.type +
                          "\nWidth: " + theImage.naturalWidth + "\nHeight: " + theImage.naturalHeight + "\n\n" +
                          errmsg);
                    clear_file_input( input_id );
                    return;
                }

                jQuery('#dv_' + input_id).show();
                jQuery('#nm_' + input_id).text('Name: ' + pathre.exec(oFile.name)[1]);
                jQuery('#sz_' + input_id).text('Size: ' + ewzG.sResultFileSize);
                jQuery('#tp_' + input_id).text('Type: ' + oFile.type);
                jQuery('#wh_' + input_id).text('Width: ' + theImage.naturalWidth + ' Height: ' + theImage.naturalHeight);
            };
        };

        files = document.getElementById(input_id).files;
        if ( ( files == null ) || ( files[0] === undefined ) || ( files[0] == null ) ) {
            jQuery('#dv_' + input_id).hide();
            jQuery('#nm_' + input_id).text('');
            jQuery('#sz_' + input_id).text('');
            jQuery('#tp_' + input_id).text('');
            jQuery('#wh_' + input_id).text('');

        } else {

            // get selected file element
            oFile = files[0];
            fields = ewzG.layout.fields[field_id];
            limits = fields.fdata;
            if (!limits.max_img_size) {
                limits.max_img_size = 1;
            }

            if( ewzG.jsvalid ){
                rFilter = new RegExp ( '^(' + limits.allowed_image_types.join('|').replace(/\//g, '\\\/') + ')$', 'i');

                if (!rFilter.test(oFile.type)) {
                    alert('** ' + oFile.name + ":\n\n" + ewzG.ftype_err);
                    clear_file_input( input_id );
                    return;
                }
                if (oFile.size > (limits.max_img_size * 1048576)) {
                    alert('** ' + oFile.name + ":\n\n" + ewzG.fsize_err.replace('%d', bytesToSize(oFile.size)) + limits.max_img_size + 'MB');
                    clear_file_input( input_id );
                    return;
                }
            }
            freader.readAsDataURL(oFile);
            if(freader.error){
                alert('** ' + oFile.name + ":\n\n Unable to read image.");
                clear_file_input( input_id );
                return;
            }
        }
    }
}

/* Get rid of an invalid selected image file */
function clear_file_input( input_id ){
    jQuery('#' + input_id).val('');
    jQuery('#im_' + input_id).remove();
    jQuery('#dv_' + input_id).hide();
}


/* return an error message if the image does not fit the dimension limits */
function invalid_image(field_id, theImage, webform_id) {
    var scale, maxw, maxh, maxHeight, maxWidth, msg,
    ewzG = window['ewzG_' + webform_id],
    limits = ewzG.layout.fields[field_id].fdata,
    iwidth = theImage.naturalWidth,
    iheight = theImage.naturalHeight;              // actual height of image

    if ((iwidth === undefined || iwidth == null || iwidth == 0)) {

        return 'Unable to determine image dimensions.  It may be of a type not accepted by this application.';

    } else {
        // Max dimensions are always set for landscape mode.
        // If rotation allowed and image is in portrait format, interchange max width and height
        if (limits.canrotate && (iheight > iwidth)) {
            maxHeight = limits.max_img_w;
            maxWidth = limits.max_img_h;
        } else {
            maxHeight = limits.max_img_h;
            maxWidth = limits.max_img_w;
        }

        if ( ( iwidth > maxWidth ) || ( iheight > maxHeight ) ) {
            msg = "\n    Width:  " + maxWidth + " pixels or less,\n" + '    Height: ' + maxHeight + ' pixels or less';
            return ewzG.isize_err + msg;
        }

        var ilongest = Math.max(iwidth, iheight);
        if ( limits.min_longest_dim && ilongest < limits.min_longest_dim ) {

            // show the user how big the image could be and still fit the criteria
            scale = Math.min( ( maxHeight / iheight ), ( maxWidth / iwidth ) );
            maxw = Math.floor(scale * iwidth);
            maxh = Math.floor(scale * iheight);

            msg = ewzG.ismall_err.replace('%d', limits.min_longest_dim);
            msg += "\n    Width:  " + maxw + " pixels,\n" + '    Height: ' + maxh + ' pixels';

            return msg;
        }
        return '';
    }
}

function fix_radios( webform_id ){
    jQuery("#ewz_form_" + webform_id).find( 'input[type="radio"]' ).each( function(){
        var jrad = jQuery(this);
        // unused rows have been disabled by now
        if( !jrad.prop("disabled") ){
           if( jrad.prop( "checked" )){
               jrad.closest('form').append('<input type="hidden" name="' + jrad.val() + '" value="1" >');
           } else {
               jrad.closest('form').append('<input type="hidden" name="' + jrad.val() + '" value="0" >');
           }
           jrad.prop("disabled", true);
        }
    });
    jQuery("#ewz_form_" + webform_id).find( 'input[type="checkbox"]' ).each( function(){
        var jchk = jQuery(this);
        if( !jchk.prop("disabled") ){
            if( !jchk.prop( "checked" ) ){
                jchk.closest('form').append('<input type="hidden" name="' + jchk.prop("name") + '" value="0" >');
            }
        }
    });
}


/////////////////  Upload and Upload Progress Info  ///////////////////////////

function startUploading(webform_id) {
    var ewzG = window['ewzG_' + webform_id],
               status, xmlRequest, jresponse, jdivComplete, jdivProgress, form_data;
    jQuery("#ewz_fsubmit_" + webform_id).prop({
        disabled: true
    });
    if( ewzG.inProgress ){
        return;
    }
    // cleanup temp states
    ewzG.iPreviousBytesLoaded = 0;
    // do client-side validation (uses alerts), only upload if check is ok
    status = check_data(ewzG, webform_id );

    if (status || !ewzG.jsvalid ) {
        fix_radios( ewzG.webform_id );
        jresponse = jQuery('#upload_response_' + webform_id);
        if (window.FormData === undefined) {
            do_submit_form(jresponse, webform_id);
        } else {
            // create XMLHttpRequest object, adding few event listeners, and POSTing our data
            xmlRequest = new XMLHttpRequest();
            if (xmlRequest.upload === undefined) {
                do_submit_form(jresponse, webform_id);
            } else {
                ewzG.inProgress = true;
                jresponse.hide();

                jQuery('#progress_percent_' + webform_id).text('');

                jdivComplete = jQuery('#complete_' + webform_id);
                jdivComplete.show();
                jdivComplete.width('500px');

                jdivProgress = jQuery('#progress_bar_' + webform_id);
                jdivProgress.show();
                jdivProgress.width( '0px' );

                // get form data for POSTing
                form_data = new FormData(document.getElementById('ewz_form_' + webform_id));

                // set up event progress listeners (must be done before "open" call)
                xmlRequest.upload.addEventListener('progress', uploadProgress, false);
                xmlRequest.addEventListener('load', uploadFinish, false);
                xmlRequest.addEventListener('error', uploadError, false);
                xmlRequest.addEventListener('abort', uploadAbort, false);
                xmlRequest.open('POST', ewzG.ajaxurl + '?action=ewz_upload');

                xmlRequest.send(form_data);

                // set doInnerUpdates to run on timer
                ewzG.timer = setInterval( doInnerUpdates, 100);
            }
        }
    } else {
        jQuery("#ewz_fsubmit_" + webform_id).prop({
            disabled: false
        });
        return;
    }

    /* NB: this is a nested function within startUploading */
    // For browsers not supporting XMLHttpRequest.upload
    function do_submit_form( jResponseDiv, webform_id ) {

        if ((window.console !== undefined) && window.console) {
            console.log("no html5 upload available  webform " + webform_id);
        }
        jResponseDiv.show();

        jResponseDiv.text( ewzG.oldform );
        document.getElementById('ewz_form_' + webform_id).submit();
    }

    /* NB: this is a nested function within startUploading */
    // display upload progress bar and percent
    function uploadProgress(event) {
        var iPercentComplete, iBytesTransfered, jUploadResponse;

        if (event.lengthComputable) {
            ewzG.iBytesUploaded = event.loaded;
            ewzG.iBytesTotal = event.total;
            iPercentComplete = Math.round(event.loaded * 100 / event.total);
            iBytesTransfered = bytesToSize(ewzG.iBytesUploaded);

            jQuery('#progress_percent_' + webform_id).text( iPercentComplete.toString() + '%');
            jQuery('#b_transfered_' + webform_id).text( iBytesTransfered );
            jQuery('#progress_bar_' + webform_id).width( ( iPercentComplete * 5).toString() + 'px' );

            jUploadResponse = jQuery('#upload_response_' + webform_id);
            // when complete, display the  ewzG.complete message in upload_response area
            if (iPercentComplete === 100) {
                jUploadResponse.text( ewzG.complete );
            } else {
                jUploadResponse.text( ewzG.wait );
            }
            jUploadResponse.show();
        } else {
            jQuery('#progress_bar_' + webform_id).text( 'unable to calculate progress' );
        }
    }

    /* NB: this is a nested function within startUploading */
    //  display speed and time remaining
    function doInnerUpdates() {

        var iCB = ewzG.iBytesUploaded,
        iDiff = iCB - ewzG.iPreviousBytesLoaded,
        iBytesRem, secondsRemaining, iSpeed, nn;

        // if nothing new loaded - exit
        if (iDiff === 0) {
            return;
        }
        ewzG.iPreviousBytesLoaded = iCB;
        iDiff = iDiff * 2;
        iBytesRem = ewzG.iBytesTotal - ewzG.iPreviousBytesLoaded;
        secondsRemaining = iBytesRem / iDiff;

        iSpeed = iDiff.toString() + 'B/s';
        nn = 1024 * 1024;
        if (iDiff > nn) {
            iSpeed = (Math.round(iDiff * 100 / nn) / 100).toString() + 'MB/s';
        } else if (iDiff > 1024) {
            iSpeed = (Math.round(iDiff * 100 / 1024) / 100).toString() + 'KB/s';
        }

        jQuery('#speed_' + webform_id).text( iSpeed );
        jQuery('#remaining_' + webform_id).text( '| ' + secondsToTime(secondsRemaining) );
    }

    /* NB: this is a nested function within startUploading */
    // upload successfully finished, reload the page
    function uploadFinish(event) {

        if (event.target.responseText === '1') {
            document.location.reload(true);
        } else {
            alert("Upload may not have succeeded:\n\n " + event.target.responseText + "\n\nRefresh the page to see the current status.");
            document.location.reload(true);  // needed to make sure blank lines not disabled
        }
        clearInterval(ewzG.timer);
        ewzG.inProgress = false;
    }

    /* NB: this is a nested function within startUploading */
    // upload error
    function uploadError(event) {

        alert("Error in upload: " + ewzG.upload_err);
        clearInterval(ewzG.timer);
        ewzG.inProgress = false;
    }

    /* NB: this is a nested function within startUploading */
    // upload abort
    function uploadAbort(event) {
        alert("Upload aborted: " + ewzG.abort_err);
        clearInterval(ewzG.timer);
        ewzG.inProgress = false;
    }
}

////////////////// Utility Functions ////////////////

function secondsToTime(secs) {
    var hr = Math.floor(secs / 3600),
    min = Math.floor((secs - (hr * 3600)) / 60),
    sec = Math.floor(secs - (hr * 3600) - (min * 60));
    if (hr < 10) {
        hr = "0" + hr;
    }
    if (min < 10) {
        min = "0" + min;
    }
    if (sec < 10) {
        sec = "0" + sec;
    }
    if (hr) {
        hr = "00";
    }
    return hr + ':' + min + ':' + sec;
}

function bytesToSize(bytes) {
    var i,
    sizes = ['Bytes', 'KB', 'MB'];
    if (bytes === 0) {
        return 'n/a';
    }
    i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)), 10);
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
}

function isblank(string)
{
    if (string === undefined) {
        return true;
    }
    if (string === null) {
        return true;
    }
    var blankre = /^\s*$/;
    return (blankre.test(string));
}

function empty_img_info(idstr, namestr, webform_id) {
    if (namestr === undefined) {
        namestr = idstr.replace(/_\d+$/, '').replace('__', '][').replace('_', '[').replace('_', ']');
    }
    var re = /__([0-9]*)__/,
    field = idstr.match(re)[1],
    qid = "'" + idstr + "'",
    ret = '<input type="file" name="' + namestr + '" id="' + idstr + '" onchange="fileSelected(' + field + ', ' + qid + ', ' + webform_id + ')">';

    if (window.FileReader !== undefined) {
        ret += '<div id="dv_' + idstr + '" style="display:none">';
        ret += '<div id="nm_' + idstr + '"></div>';
        ret += '<div id="sz_' + idstr + '"></div>';
        ret += '<div id="tp_' + idstr + '"></div>';
        ret += '<div id="wh_' + idstr + '"></div>';
        ret += '</div>';
    }
    return ret;
}

function field_matches_restr( restriction_val, entered_val ) {
    var ismatch = true;
    switch (restriction_val) {
    case  undefined:
        break;
    case  '~*~':
        break;
    case  '~-~':
        if (!isblank(entered_val)) {
            ismatch = false;
        }
        break;
    case  '~+~':
        if (isblank(entered_val)) {
            ismatch = false;
        }
        break;
    default:
        if (restriction_val !== entered_val) {
            ismatch = false;
        }
        break;
    }
    return ismatch;
}
