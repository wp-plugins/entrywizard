<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

/* * ************************************* */
/* Generate and process the upload form */
/* * ************************************* */
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-base.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-exception.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-item.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-webform.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-layout.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-upload-input.php' );
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-common.php' );



/**
 * Display the upload form
 *
 * This is the function called by the shortcode
 *
 * @param   array   $atts   attributes passed to the shortcode
 * @return  string  $output html
 */
function ewz_show_webform( $atts )
{
    //error_log("EWZ: showing webform for " . $_SERVER["REMOTE_ADDR"]);
    assert( is_array( $atts ) );

    try{
        // need webformdata whether or not we need to process an upload
        $webformdata = ewz_get_webform_data( $atts );
    } catch( Exception $e ) {
        return $e->getMessage();
    }
    if(  array_key_exists( 'failmsg', $webformdata ) ) {
        return $webformdata['failmsg'];
    }

    $errmsg = '';

    // Now we have the webform data, process any changes  coming
    // in from old browsers that dont use ajax for the upload
    if ( $_POST && isset( $_POST['identifier'] ) && ( $_POST['identifier'] == $atts['identifier'] ) ) {
        try{
            //error_log("EWZ: uploading (old form) for " . $_SERVER["REMOTE_ADDR"]);
            // had problems with more than 10 3M images
            $n = $webformdata['webform']->num_items;
            $timelimit = ini_get('max_execution_time');
            if( 15 * $n > $timelimit ){
                set_time_limit ( 15 * $n );
            }

            $input = new Ewz_Upload_Input( stripslashes_deep( $_POST ), $_FILES, $webformdata['layout'] );

            $errmsg .= ewz_process_upload(
                                           $input->get_input_data(), $webformdata['user_id'],
                                           $webformdata['webform']->webform_id 
                                          );

        } catch( Exception $e ) {
            $errmsg .= $e->getMessage();
        }
    }

    $webform_id = $webformdata['webform']->webform_id;

    // Get the info for the display from the database
    // Boolean arg for get_items_for_webform when true restricts items to those of current user
    try{

        $stored_items = Ewz_Item::get_items_for_webform( $webform_id,  true );
    } catch( Exception $e ) {
        $errmsg .= $e->getMessage();
    }

    $ewzG = ewz_get_layout_info( $webformdata['layout'] );  // grabs all required layout data
    $ewzG['webform_id'] = $webform_id;
    $ewzG['num_items'] = $webformdata['webform']->num_items;
    $ewzG['errmsg'] = $errmsg;
    $ewzG['thumb_height'] = 100;        // TODO - make this configurable
    $ewzG['has_data'] = ( count( $stored_items ) > 0 );
    $ewzG['jsvalid'] = Ewz_Base::validate_using_javascript();     // this is set to false for testing server validation


    // this passes ewzG to Javascript
    wp_localize_script( "ewz-upload", "ewzG_$webform_id",  $ewzG );

    global $current_user;
    get_currentuserinfo();

    // Display of stored data for confirmation.  Only displayed if there is some stored data.
    $output = '<h2>' . esc_html( $webformdata['webform']->webform_title ) . '</h2>';
    $output .= ewz_current_status(
                                   $stored_items,
                                   $webformdata['layout']->fields,
                                   $current_user->display_name,
                                   $webform_id
                                  );

    // Display of upload form, hidden if there is stored data
    $output .= ewz_upload_form( $stored_items,
                                $webformdata['layout'],
                                $webformdata['webform'] );

    return $output;
}

/**
 * Return all the required information about the webform
 *
 * @param   array  $atts   attributes the shortcode was called with
 * @return  object containing user, webform and layout info
 */
function ewz_get_webform_data( $atts )
{
    assert( is_array( $atts ) );

    ewz_check_upload_atts( $atts );

    $data = array();

    /* ****************** */
    /* Check user status  */
    /* ****************** */
    get_currentuserinfo();
    if ( !is_user_logged_in() ) {
        $data['failmsg'] = 'Sorry, you must be logged in to see this.';
        return $data;
    }

    /* ******************* */
    /* Collect some  data  */
    /* ******************* */

    $webform = new Ewz_Webform( $atts['identifier'] );

    if( !$webform->open_for_current_user() ){
        $data['failmsg'] = '<div class="ewz-err"><h2>' . 
            esc_html( $webform->webform_title ) . 
            "</h2>\n\n<p>Sorry, this form is not currently open for uploads.</p></div>";
        return $data;
    }

    $data['user_id'] = get_current_user_id();
    $data['webform'] = $webform;
    $data['layout'] = new Ewz_Layout( $webform->layout_id, Ewz_Layout::EXCLUDE_FOLLOWUP );
    return $data;
}

/**
 * Return an html string to display the upload form
 *
 * The upload form is displayed if there is no stored data, or
 *   when  "Add, Change or Delete" is clicked
 ***************************************************************************************
 *                         Ewz_Item data format
 * -----------------------------------------------------------------------------------
 * item_id
 * user_id
 * webform_id
 * last_change
 * upload_date
 * item_files:
 *   ( "444" => (field_id => 444, thumb_url => ..., fname => ..., type=>jpg, width=>.., height=>.., orient=>...  )
 *     "544" => (field_id => 544, thumb_url => ..., fname => ..., type=>jpg),
 *      ....)
 * item_data:   ( "11"=> (field_id => 11, value => 'P'),
 *                "25"=> (field_id => 25, value => 'A'),
 *                "34"=> (field_id => 34, value => 'sssssssssssss')
 *              )
 *************************************************************************************
 * @param   $stored_items     Array of Ewz_Items
 * @param   $layout             Ewz_Layout object
 * @param   $webform            Ewz_Webform object
 * @return  string
 */
function ewz_upload_form( $stored_items, $layout, $webform )
{
    assert( is_array( $stored_items ) );
    assert( is_array( $layout->fields ) );
    assert( Ewz_Base::is_pos_int( $webform->webform_id ) );

    $output = '';
    $has_data = (count( $stored_items ) > 0);
    $fields_arr = array_values($layout->fields);
    $webform_id = $webform->webform_id;
    $hidemodify = '';
    if ( $has_data ) {
        $hidemodify = 'style="display:none"';
        $output .= '<div class="ewz_pcentre"><button type="button" id="ewz_change_' .
            esc_attr( $webform_id ) .
            '" onClick="show_modify(this, ' . esc_js( $webform_id ) .
            ')">Add, Change or Delete</button></div>';
    }

    $form_action = esc_attr( get_permalink() );
    $output .= '<div id="ewz_modify_' . esc_attr( $webform_id ) .'" ' . $hidemodify . '>';

    //  autocomplete="off"  required for FF, otherwise it saves old values and uses them
    //  after an upload
    $output .= '<form  autocomplete="off" id="ewz_form_' . esc_attr( $webform_id ) .
            '" method="POST" action="' . esc_js( $form_action ) . '" ';
    $output .= ' enctype="multipart/form-data"  encoding="multipart/form-data" >';
    $output .= '<div class="ewzform">';
    $output .= wp_nonce_field( 'ewzupload', 'ewzuploadnonce', true, false );
    $output .= "\n";
    $output .= '<input type="hidden" name="webform_id" value="' .  esc_attr( $webform_id ) . '">';
    $output .= '<input type="hidden" name="identifier" value="' . esc_attr( $webform->webform_ident ) . '">';
    $output .= '<div id="scrollablediv_'. esc_attr( $webform_id ) . '" class="ewz_overflow">';
    $output .= "\n";
    $output .= '<table class="ewz_upload_table ewz_padded">';
    $output .= "\n";

    $append = array_map ( function($v){ return $v->append == 1; },  $fields_arr );
    $append[ count($fields_arr) ] = false;

    //  header line
    $output .= '<thead>   <tr>';
    foreach ( $fields_arr as $n => $field ) {
        assert( $n < count($fields_arr) );
        $reqflag = $field->required ? '*' : ' ';
        if( $append[$n] ){
            $output .= ' / <br \>';
        } else {
            $output .= "<th>";
        }
        if( !( $append[$n] || $append[$n+1] ) ){
            $output .= $reqflag;
        }
        $output .= esc_html( $field->field_header );
        if( !$append[$n+1] ){
            $output .= '</th>';
        }
    }
    if( $has_data ){
        $output .= '     <th class="btn"></th>';
    }
    $output .= "   </tr></thead><tbody>\n";


    // item rows
    $row = 0;
    foreach ( $stored_items as $p => $item ) {
        assert( $p<count($stored_items ) );
        $output .='<tr id="row' . $row . '_' . esc_attr( $webform_id ) . '">';
        foreach ( $fields_arr as $n => $field ) {
            $reqflag = $field->required ? '*' : ' ';
            $savedval = ewz_get_saved_value( $field, $item );
            if( $append[$n] ){
                $output .= '<br \><br \>';
            } else {
                $output .= '<td>';
            }
            if( $append[$n] || $append[$n+1] ){
           
                $output .= "<label for='rdata_{$row}__{$field->field_id}__$webform_id'>" .  $reqflag . esc_html( $field->field_header ) . ':</label>';
            }
            
            // dont allow editing of data that has already been included in the filename
            if( $webform->apply_prefix && ( strpos( $webform->prefix, '[~'.$field->field_ident.']' ) !== false ) ){
                $output .=   ewz_display_webform_field( $row, $webform_id, $savedval, $field, true );       
            } else {
                $output .= ewz_display_webform_field( $row, $webform_id, $savedval, $field, false );
            }
            if( !$append[$n+1] ){
                $output .= '</td>';
            }
        }
        $output .= '<td><input type="hidden"   name="item_id[' . $row . ']" value="' .
                esc_attr( $item->item_id ) . '">';
        $output .= '<button  id="delete_row' . $row . '_' . esc_attr( $webform_id ) .
                '" type="button"  onClick="delete_item(this, ' . esc_js( $webform_id ) .
                ')">Delete</button></td>';
        $output .= "</tr>\n";
        ++$row;
    }
    // add blank rows to fill to max_num_items
    while ( $row < $webform->num_items ) {
        $output .="<tr id='row" . $row . "_" . esc_attr( $webform_id ) . "'>";

        foreach ( $fields_arr as $n => $field ) {
            $reqflag = $field->required ? '*' : ' ';
            if( $append[$n] ){
                $output .= '<br \><br \>';
            } else {
                $output .= '<td>';
            }
            if( $append[$n] || $append[$n+1] ){
                $output .= "<label for='rdata_{$row}__{$field->field_id}__$webform_id'>" .  $reqflag . esc_html( $field->field_header ) . ':</label>';
            }
            $output .=  ewz_display_webform_field( $row, $webform_id, '', $field, false );
            if( !$append[$n+1] ){
                $output .= '</td>';
            }
        }
        if( $has_data ){
             $output .= '<td class="btn"></td>';
        }
        $output .= "</tr>\n";

        ++$row;
    }

    $output .= "</table>";
    $output .= "</div>\n";

    /*     * ******************************** */
    /* Submit button and progress area */
    /*     * ******************************** */
    // progress bar above submit button so visible after submit is clicked.
    $esc_wid = esc_attr( $webform_id );
    $output .= '      <div class="ewz_progress" >';
    $output .= '            <div id="pleasewait_' . $esc_wid . '"></div>';
    $output .= '            <div id="progress_info_' . $esc_wid . '">';
    $output .= '                <div id="complete_' . $esc_wid . '">';
    $output .= '                   <div id="progress_bar_' . $esc_wid . '"></div>';
    $output .= '                </div>';
    $output .= '                <div id="progress_percent_' . $esc_wid . '"></div>';
    $output .= '                <div class="ewz_clear_both"></div>';
    $output .= '                <div>';
    $output .= '                    <div id="speed_' . $esc_wid . '"></div>';
    $output .= '                    <div id="remaining_' . $esc_wid . '"></div>';
    $output .= '                    <div id="b_transfered_' . $esc_wid . '"></div>';
    $output .= '                    <div class="ewz_clear_both"></div>';
    $output .= '                </div>';
    $output .= '                <div id="upload_response_' . $esc_wid . '"></div>';
    $output .= '            </div>';
    $output .= '      </div>';
    $output .= '      <div class="ewz_pcentre"><button type="button" disabled="disabled" id="ewz_fsubmit_' . $esc_wid;
    $output .=             '" onclick="startUploading( ' . esc_js( $webform_id ) . ')" >Submit</button>';
    $output .= '      </div>';

    $output .= '</div>';

    $output .= "</form>\n";
    $output .= "</div>\n";

    return $output;
}

/**
 * Return the html for displaying a single field
 *
 * @param   int     $rownum    row number
 * @param   int     $webform_id
 * @param   string  $savedval  data currently stored for the field
 * @param   array   $field     field info
 * @return  string  $display   -- the html
 */
function ewz_display_webform_field( $rownum, $webform_id, $savedval, $field, $fixed )
{
    assert( Ewz_Base::is_nn_int( $rownum ) || $rownum == '' );
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( in_array( $field->field_type, array( 'str', 'opt', 'img', 'rad', 'chk' ) ) );
    assert( is_string( $savedval ) || is_bool( $savedval ) || $savedval === null || $savedval === 1 || $savedval === 0 );
    assert( is_bool($fixed));

    $name    = "rdata[$rownum][" . $field->field_id . "]";

    $display = '';

    switch ( $field->field_type ) {
    case 'str': $display = ewz_display_str_form_field( $name, $webform_id, $savedval, $field );
            break;
    case 'opt': $display = ewz_display_opt_form_field( $name, $webform_id, $savedval, $field, $fixed );
            break;
    case 'img': $display = ewz_display_img_form_field( $name, $webform_id, $savedval, $field );
            break;
    case 'rad': $display = ewz_display_rad_form_field( $name, $webform_id, $savedval, $field );
            break;
    case 'chk': $display = ewz_display_chk_form_field( $name, $webform_id, $savedval );
            break;
    default:
        throw new EWZ_Exception( "Invalid field type " . $field->field_type );
    }
    return $display;
}

/**
 * Return the value saved on the database for the item
 *
 * @param  $field
 * @param  $item
 * @return string
 */
function ewz_get_saved_value( $field, $item ){
    assert( is_object( $field ) );
    assert( is_object( $item ) );
    $savedval = '';
    if ( 'img' == $field->field_type ) {
        if ( array_key_exists( $field->field_id, $item->item_files ) ) {
            $savedval = $item->item_files[$field->field_id]['thumb_url'];
        }
    } else {
        if ( isset( $item->item_data[$field->field_id]['value'] ) ) {
            $savedval = $item->item_data[$field->field_id]['value'];
        }
    }
    return $savedval;
}


/**
 * Return the html for displaying a single text-input field
 *
 * @param   string  $name        js name of field
 * @param   int     $webform_id
 * @param   string  $savedval    data currently stored for the field
 * @param   array   $field       field info
 * @return  string   -- the html
 */
function ewz_display_str_form_field( $name, $webform_id, $savedval, $field )
{
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( is_string( $name ) );
    assert( is_string( $savedval ) );
    assert( Ewz_Base::is_nn_int( $field->fdata['fieldwidth'] ) );

    $ename = esc_attr( $name );
    $iname = str_replace( '[', '_', str_replace( ']', '_', $ename ) );
    return '<input type="text" name="' . $ename .
                   '" id="' . $iname . '_' . esc_attr( $webform_id ) .
                   '" size="' . esc_attr( $field->fdata['fieldwidth'] ) .
                   '" maxlength="' . esc_attr( $field->fdata['maxstringchars'] ) .
                   '" value="' . esc_attr( $savedval ) .
            '">';
}

/**
 * Return the html for displaying a single image field
 *
 * @param   string  $name        js name of field
 * @param   int     $webform_id
 * @param   string  $savedval    data currently stored for the field
 * @param   array   $field       field info
 * @return  string   -- the html
 */
function ewz_display_img_form_field( $name, $webform_id, $savedval, $field )
{
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( is_string( $name ) );
    assert( is_string( $savedval ) || $savedval === null );
    assert( Ewz_Base::is_pos_int( $field->field_id ) );

    // for an image file, don't want it changed, so only have an input if no savedval
    $ename = esc_attr( $name );
    $iname = str_replace( '[', '_', str_replace( ']', '_', $ename ) );
    $esc_wid = esc_attr( $webform_id );
    if ( $savedval ) {
        $ret  = '<input type="hidden" name="' . $ename . '" value="ewz_img_upload" >';
        $ret .= '<img id="' . $iname . '_' . $esc_wid . '" src="' . esc_url( $savedval ) . '">';
        return $ret;
    } else {
        $qname = "'" . $iname . "_$esc_wid'";
        $fid = esc_attr( $field->field_id );
        $imginfo = '<input type="file"  name="' . $ename .  '" id="' . $iname . '_' . $esc_wid .
                           '" onchange="fileSelected(' . $fid . ', ' . $qname . ', ' . $webform_id . ' );">';
        // watch no spaces here - they put newlines between the divs
        $imginfo .= '<div id="dv_' . $iname . '_' . $esc_wid . '" style="display:none">';
        $imginfo .= '<div id="nm_' . $iname . '_' . $esc_wid . '"></div>';
        $imginfo .= '<div id="sz_' . $iname . '_' . $esc_wid . '"></div>';
        $imginfo .= '<div id="tp_' . $iname . '_' . $esc_wid . '"></div>';
        $imginfo .= '<div id="wh_' . $iname . '_' . $esc_wid . '"></div>';
        $imginfo .= '</div>';
        return $imginfo;
    }
}

/**
 * Return the html for displaying a single option field
 *
 * @param   string $name        js name of field
 * @param   int    $webform_id
 * @param   mixed  $savedval    data currently stored for the field
 * @param   array  $field       field info
 * @return  string   -- the html
 */
function ewz_display_opt_form_field( $name, $webform_id, $savedval, $field, $fixed )
{
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( is_string( $name ) );
    assert( is_string( $savedval ) );
    assert( Ewz_Base::is_pos_int( $field->field_id ) );
    assert( is_bool( $fixed ) );

    $ename = esc_attr( $name );
    $iname = str_replace( '[', '_', str_replace( ']', '_', $ename ) );
    $txt = '<select name="' . $ename . '" id="' . $iname . '_' . esc_attr( $webform_id ) .'">';
    $txt .= '  <option value="" ' .  ( $fixed ? ' disabled="disabled"' : '' ) . '></option>' ;
    foreach ( $field->fdata['options'] as $n => $dataval ) {
        $txt .= '<option value="' . esc_attr( $dataval['value'] ) . '"';
        if ( $dataval['value'] === $savedval ) {
            $txt .=         ' selected="selected" ';
        } elseif( $fixed ){
            $txt .=         ' disabled="disabled"';
        }
        $txt .= '>' . esc_attr( $dataval['label'] ) . '</option>';
    }
    $txt .= '</select>';
    return $txt;
}
/**
 * Display a radio button
 *
 * Radios in the same column must have the same name, so use the field id for that.
 * Give the button a value equal to the name we would normally have used.
 * If it is checked, when submitting, Javascript disables it and creates a new hidden
 * input with name equal to the value of the radio button and value '1'.
 *
 * @param
 * @return
 */
function ewz_display_rad_form_field( $name,  $webform_id, $savedval, $field )
{
    assert( is_string( $name ) );
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( in_array( $savedval, array( null, 1, 0 ) ) );
    assert( is_object( $field ) );

    $ename = esc_attr( $name );
    $iname = str_replace( '[', '_', str_replace( ']', '_', $ename ) );


    return '<input type="radio"  name="radio' . $field->field_id . '"' .
                   ' id="' . $iname . '_' . esc_attr( $webform_id ) . '"' .
                   ' value="' . $ename .'" ' .
                   ($savedval ? ' checked="checked" ' : '') .
            '>';
}


function ewz_display_chk_form_field( $name, $webform_id, $savedval )
{
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( is_string( $name ) );
    assert( in_array( $savedval, array( null, 1, 0 ) ) );

    $ename = esc_attr( $name );

    $iname = str_replace( '[', '_', str_replace( ']', '_', $ename ) );
      return '<input type="checkbox"  name="' . $ename .
                   '" value="1"  id="' . $iname . '_' . esc_attr( $webform_id ) . '"' .
          ($savedval ? ' checked="checked" ' : '') .
            '>';
}

/**
 * Generate a javascript array to store info needed in client
 *
 * @param   object $layout
 * @return  string  $str - html javascript string
 */
function ewz_get_layout_info( $layout )
{
    global $post;
    assert( is_object( $layout ) );
    $ewzG = array('layout' => $layout);

    // required for viewer-side ajax, automatically defined for admin
    $ewzG['ajaxurl'] = admin_url('admin-ajax.php', (is_ssl() ? 'https' : 'http'));
    $ewzG['uploadurl'] = get_permalink( $post );
    $ewzG['load_gif'] = plugins_url( 'images/loading.gif' , dirname(__FILE__) ) ;

    $ewzG['upload_err'] = "An unknown error occurred while uploading the data.";
    $ewzG['abort_err'] = "Either you cancelled the upload, or your browser dropped the connection.";
    $ewzG['ftype_err'] = "Sorry, this image will not be accepted.\nEither it's type could not be detected " .
                           "or it is not an acceptable image type for this application.";
    $ewzG['ismall_err'] = "Sorry, this image will not be accepted.\nIts longest dimension is smaller than the minimum of:" .
                           "\n    %d pixels.\n\nIt could be enlarged up to:\n";
    $ewzG['isize_err'] = "Sorry, this image will not be accepted.\nIt does not fit within the required bounds of: ";
    $ewzG['fsize_err'] = "Sorry, this file will not be accepted.\nIts size ( %d ) is greater than the limit of ";

    $ewzG['wait'] = 'PLEASE WAIT until all processing is finished and the screen refreshes.';
    $ewzG['complete'] = 'PLEASE WAIT. Upload is complete, processing may take a few moments .... ';
    $ewzG['oldform'] = 'PLEASE WAIT until all processing is finished and the screen refreshes.  Upload may take some time, depending on image size and network speed.  More feedback is available using a browser with better support for HTML5.';    
    $ewzG['iBytesUploaded'] = 0;
    $ewzG['iBytesTotal'] = 0;
    $ewzG['iPreviousBytesLoaded'] = 0;
    $ewzG['iMaxFileBytes'] = EWZ_MAX_SIZE_BYTES;
    $ewzG['timer'] = 0;
    $ewzG['inProgress'] = false;
    $ewzG['sResultFileSize'] = '';

    return $ewzG;
}

/**
 * Return the html for the "current status" display, plus an array of the data
 *
 * @param   array   $stored_items  --  contents of an item table row
 * @param   array   $fields
 * @param   string  $name -- current user name
 * @param   string  $webform_id -- id of webform being used for upload
 * @return  string  $output is the html
 */
function ewz_current_status( $stored_items, $fields, $name, $webform_id ) {
    assert( is_array( $stored_items ) );
    assert( is_array( $fields ) );
    assert( is_string( $name ) );
    assert( Ewz_Base::is_pos_int( $webform_id ) );

    $labels = array( );
    $fields_arr = array_values($fields);

    foreach( $fields_arr as $field ) {
        if ( 'opt' == $field->field_type ) {
            foreach ( $field->fdata['options'] as $data ) {
                $labels[$field->field_header][$data['value']] = $data['label'];
            }
        }
    }

    $output = '<div id="ewz_stored_' . esc_attr( $webform_id ) . '">';
    if ( count( $stored_items ) > 0 ) {
        $output .= "Currently stored on the server for $name: <br \><br \>";

        $output .= '<table id="datatable_' . esc_attr( $webform_id ) . '"  class="ewz_upload_table ewz_padded"><thead>';

        $append = array_map ( function($v){ return $v->append == 1; },  $fields_arr );
        $append[ count($fields_arr) ] = false;


        $output .= '   <tr>';
        foreach ( $fields_arr as $n => $field ) {
            assert( $n < count($fields));
            if( $append[$n] ){
                $output .= ' / <br \>';
            } else {
                $output .= "<th>";
            }
            $output .=  esc_html( $field->field_header );
            if( !$append[$n+1] ){
                $output .= '</th>';
            }
        }
        $output .=    '</tr></thead><tbody>';
        foreach ( $stored_items as $m => $item ) {
            assert( $m < count($stored_items));
            $output .= '<tr>';
            foreach ( $fields_arr as $n => $field ) {
                if( $append[$n] ){
                    $output .= '<br \><br \>';
                } else {
                    $output .= '<td>';
                }
                if( $append[$n] || $append[$n+1] ){
           
                    $output .='<b>' . esc_html( $field->field_header ) . ":</b> ";
                }

                if ( 'img' == $field->field_type ) {
                    // an image field
                    if ( array_key_exists( $field->field_id, $item->item_files ) ) {
                        if ( is_array( $item->item_files[$field->field_id] ) ) {
                            $output .= '<img src="' . esc_attr( $item->item_files[$field->field_id]['thumb_url'] ) . '">';
                            $output .= '<br \>' . esc_html( basename( $item->item_files[$field->field_id]['fname'] ) );
                        } else {
                            $output .= esc_html( $item->item_files[$field->field_id] );
                        }
                    }
                } else {
                    // a data field
                    if ( isset( $item->item_data[$field->field_id] ) ) {
                        $output .= esc_html( ewz_display_item( $field, $item->item_data[$field->field_id]['value'] ) );
                    }
                }
                if( !$append[$n+1] ){
                    $output .= '</td>';
                }
            }
            $output .= '</tr>';
        }
        $output .= "</tbody></table>";
    }
    $output .= '</div>';       // <div id="stored">
    return $output;
}

/**
 * Process the uploaded data
 *
 *
 *
 * @param  array  $postdata    sanitized version of $_POST
 * @param  int    $user_id     id of current user
 * @param  int    $webform_id  id of the webform
 * @return blank or error message  -- a hack to allow server checking of image dimensions, etc
 *                                    without blocking subsequent items.  Only needed for older IE.
 */
function ewz_process_upload( $postdata, $user_id, $webform_id )
{
    assert( is_array( $postdata ) );
    assert( Ewz_Base::is_pos_int( $user_id ) );
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    /*     * ************************** */
    /* Get the field information */
    /*     * ************************** */
    $webform = new Ewz_Webform( $webform_id );
    $layout = new Ewz_Layout( $webform->layout_id, Ewz_Layout::EXCLUDE_FOLLOWUP );

    // Reformat the post data to a more usable form,  upload any files,
    // and add the uploaded file data to the item data

    $post_arr = ewz_to_upload_arr( $webform_id, $postdata, $layout->fields );
    /* ********************************************************* */
    /* Process the items                                         */
    /* If there is matching data stored, update the database     */
    /* Otherwise, create a new item entry                        */
    /* ********************************************************* */
    $errs = '';
    foreach ( $post_arr as $row => $item ) {
        // $row index may not be 0,1,... if rows are missing
        assert( is_numeric($row));
        $data = array(
            'user_id' => $user_id,
            'webform_id' => $webform_id,
            'item_data' => $item['data'],
        );

        if ( array_key_exists( 'item_id', $item ) ) {
            $data['item_id'] = $item['item_id'];
        }
        // only create item_files if we are uploading.  $item['files'] is created from $_FILES in ewz_to_upload_arr
        if ( array_key_exists( 'files', $item ) ) {
            foreach( $item['files'] as $field_id => $uploaded_file ){
                // error is stored here instead of being raised as an exception because older IE's dont allow
                // checking dimensions on client.  We don't want to ignore the rest of the upload if one
                // has a dimension error.
                if( preg_match('/^___/', $uploaded_file['fname'] ) ){
                    // i.e there was an error picked up by ewz_handle_img_upload in ewz_to_upload_arr
                    $errs .= "\n" . preg_replace('/^___/', '', $uploaded_file['fname'] );
                    $data = NULL;
                } else {
                    $data['item_files'][$field_id] = $uploaded_file;
                }
            }
        }
        if( $data ){
            try {
                $item_obj = new Ewz_Item( $data );  
                $item_obj->save();
            } catch( Exception $e ) {
                $errs .= $e->getMessage();
            }
        }
    }
    return $errs;
}

/**
 * Reformat the input data and add the uploaded file data
 *
 * To aid in correlating POST data with FILES, output array is indexed by the row number,
 *      so it will have missing items if there are blank rows in the uploaded data.
 *
 * @param   int    $webform_id id of webform
 * @param   array  $postdata   sanitized version of $_POST
 * @param   array  $fields     array of Ewz_Fields indexed on field_id
 * @return  array  $upload     uploaded items
 */
function ewz_to_upload_arr( $webform_id, $postdata, $fields ) {
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( is_array( $postdata ) );
    assert( is_array( $fields ) );

    $upload = array( );
    foreach ( $postdata['rdata'] as $row => $datavalues ) {
        // there may be missing rows, so $rows may not be 0,1,2...
        foreach ( $datavalues as $field_id => $val ) {
                $upload[$row]['data'][$field_id] = array( "field_id" => $field_id, "value" => $val );
        }
    }
    if ( array_key_exists( 'item_id', $postdata ) ) {
        foreach ( $postdata['item_id'] as $row => $item_id ) {
            // there may be missing rows, so $rows may not be 0,1,2...
            $upload[$row]['item_id'] = $item_id;
        }
    }

    if ( $_FILES ) {
        $webform = new Ewz_Webform( $webform_id );

        // ensure the next uploaded items are stored in uploads/ewz_upload_dir
        add_filter( 'upload_dir', array( $webform, 'ewz_upload_dir' ) );
        $subst_data = array();
        if( $webform->apply_prefix ){
            $user_id = get_current_user_id();
            $customdata = new Ewz_Custom_Data( $user_id );
            $subst_data = array(
                                'user_id' => $user_id,
                                );
            foreach ( $customdata as $custkey => $custval ) {
                $subst_data[$custkey] = $custval;
            }
        }
        foreach ( $_FILES['rdata']['name'] as $row => $fileset ) {
            // there is a $_FILES['rdata']['name'] for each "used" row with a file input, indexed on row number
            foreach ( $fileset as $field_id => $filename ) {
                if( isset( $filename ) && $filename ){
                    $prefix = '';
                    $newfilename = $filename;

                    // if filename ends in a digit, we normally add an underscore to the end, but not 
                    // if the final digit came from a prescribed "prefix" being used as the filename                    
                    $add_underscore = !( $webform->apply_prefix && $webform->gen_fname ); 
  
                    if( $webform->apply_prefix ){
                        // do the substitutions in the prefix
                        foreach( $upload[$row]['data'] as $fid => $val ){                            
                            $subst_data[$fid] = $val;
                        }
                        $subst_data['file_field_id'] = $field_id;
                        $prefix = $webform->generated_prefix( $subst_data );

                        if( $webform->gen_fname ){
                            $ext = pathinfo( $filename, PATHINFO_EXTENSION );
                            if ( strpos( $prefix, '[~1]' ) !== false ) {
                                // make sure the filename is unique for the directory -- append 1,2,3,... until it is
                                $uniq = ewz_get_fname_num( $prefix, $ext, EWZ_IMG_UPLOAD_DIR . '/' . $webform->webform_ident );
                                $newfilename = str_replace( '[~1]', $uniq, $prefix ). '.' . $ext;
                            } else {
                                $newfilename =  $prefix . '.' . $ext;
                            }
                        } else {
                            // let WP take care of making the name unique. 
                            $newfilename = $prefix . $filename;
                        }                            
                    }
                    try{
                        // sanitize $prefix.$filename and if ok save the uploaded image
                        $upload[$row]['files'][$field_id] = ewz_handle_img_upload( $newfilename, $row, $fields[$field_id],
                                                                                   $add_underscore );
                    } catch( Exception $e ){
                        $upload[$row]['files'][$field_id]['fname'] = '___' . $e->getMessage();
                    }
                }
            }
        }
        // back to WP normal upload directory
        remove_filter( 'upload_dir', array( $webform, 'ewz_upload_dir' ) );
    }
    return $upload;
}

/**
 * Create the thumbnail for use with EntryWizard
 *
 *
 * @param  string $img_filepath  path to the image to be thumbnailed
 * @return string path to the newly-created thumbnail
 */
function ewz_create_thumbfile( $img_filepath ){
    assert( ewz_is_valid_ewz_path( $img_filepath ) );

    // create the thumbnail
    $dim =  ewz_thumbfile_dimensions();

    $thumb_filepath =  ewz_thumbfile_path( $img_filepath );

    $image = wp_get_image_editor( $img_filepath );
    if ( is_wp_error( $image ) ) {
        throw new EWZ_Exception( 'error reading image ' . basename( $img_filepath ) . ': ' . $image->get_error_message() );
    } else {
        $image->resize( $dim['w'], $dim['h'], false );
        $image->save( $thumb_filepath );
    }
    return $thumb_filepath;
}

/**
 * Return an html string for display when the webform is closed
 *
 * @param   string  $form_name  name of webform for display to user
 * @return  string  html
 */
function ewz_upload_closed( $form_name )
{
    assert( is_string( $form_name ) );
    $html = '<h2>Sorry, ' . esc_html( $form_name ) . ' is not open for upload at the moment</h2>';

    return $html;
}

/**
 * Save the uploaded image file in the correct folder and generate the thumbnail
 *
 * Return an array containing the filename, type, width, height, orientation
 * NB:  should be called with the upload directory set to 'ewz_upload_dir' using
 *      the 'upload_dir' filter
 *
 * @param   string     $filename  location of temp uploaded file
 * @param   int        $row       row of upload form
 * @param   Ewz_Field  $field     layout field under which the image was uploaded
 * @param   boolean    $appendU   do we add an underscore to the end of a filename ending in a digit
 * @return  array  of  field_id,thumb_url,filename, type, width, height, orientation
 */
function ewz_handle_img_upload( $filename,  $row,  $field, $appendU ){
    assert( is_string( $filename ) || is_null( $filename ) );
    assert( is_int( $row ) );
    assert( is_object( $field ) );
    assert( is_bool( $appendU ) );

    if ( $filename ) {
        $filename = ewz_to_valid_fname( $filename,  $appendU );
        $field_id = $field->field_id;
        $file = array(
                      'name'     => $filename,
                      'type'     => $_FILES['rdata']['type'][$row][$field_id],
                      'tmp_name' => $_FILES['rdata']['tmp_name'][$row][$field_id],
                      'error'    => $_FILES['rdata']['error'][$row][$field_id],
                      'size'     => $_FILES['rdata']['size'][$row][$field_id]
                      );

        $errmsg = ewz_image_file_check( $file, $field );
        if ( $errmsg ) {
            throw new EWZ_Exception( 'Image ' . basename( $filename) . " not uploaded:\n$errmsg" );
        } else {
            if ( !function_exists( 'wp_handle_upload' ) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
            }

            // NB: t done with the upload directory set to 'ewz_upload_dir'
            $uploaded_file = wp_handle_upload( $file, array( 'test_form' => false ) );
            // $uploaded_file now contains array('file'=>path, 'url'=>url, 'type'=>mime)
            if ( isset( $uploaded_file['file'] ) ) {
                $size = getimagesize( $uploaded_file['file'] );

                $thumbfile = ewz_create_thumbfile( $uploaded_file['file'] );
                $thumburl = ewz_file_to_url( $thumbfile );

                if ( $thumbfile == $uploaded_file['file'] ) {
                    throw new EWZ_Exception( "Thumb filename " . basename( $thumbfile ) . ' is same as original filename.' );
                }
                return  array( 'field_id'  => $field_id,
                               'thumb_url' => $thumburl,
                               'fname'     => $uploaded_file['file'],
                               'type'      => $uploaded_file['type'],
                               'width'     => $size[0],
                               'height'    => $size[1],
                               'orient'    => ( $size[0] > $size[1] ) ? 'L' : 'P',
                               );
            } else {
                throw new EWZ_Exception( 'Error in file upload for ' . basename( $filename ) . ': ' . $uploaded_file['error'] );
            }
        }
    }
}


/**
 * Check an uploaded image file for size, dimensions, etc
 *
 * @param   array   $imgfile_data    uploaded data for the file
 * @param   object  $field_data  stored characteristics for the field the file was uploaded by
 * @return  string  error message or blank
 */
function ewz_image_file_check( $imgfile_data, $field_data ) {
    assert( is_array( $imgfile_data ) );
    assert( is_object( $field_data ) );

    $maxw = $field_data->fdata['max_img_w'];  // ['max_img_w'];
    $maxh = $field_data->fdata['max_img_h'];  // ['max_img_h'];
    $maxs = $field_data->fdata['max_img_size'] * 1048576;  // ['max_img_size'] * 1048576;
    $minld = $field_data->fdata['min_longest_dim'];  // ['min_longest_dim'];
    $canrot = $field_data->fdata['canrotate'];
    $types = $field_data->fdata['allowed_image_types'];  // ['allowed_image_types'];

    if ( ( 0 === $imgfile_data['size'] ) && ( 0 === $imgfile_data['error'] ) ) {
        $imgfile_data['error'] = UPLOAD_ERR_EMPTY;
    }

    $upload_errors = array(
        UPLOAD_ERR_OK => "No errors.",
        UPLOAD_ERR_INI_SIZE => "Larger than the maximum allowed by the server.",
        UPLOAD_ERR_FORM_SIZE => "Larger than the maximum allowed by this application.",
        UPLOAD_ERR_PARTIAL => "Partial upload.",
        UPLOAD_ERR_NO_FILE => "No file.",
        UPLOAD_ERR_NO_TMP_DIR => "No temporary directory.",
        UPLOAD_ERR_CANT_WRITE => "Can't write to disk.",
        UPLOAD_ERR_EXTENSION => "File upload stopped by extension.",
        UPLOAD_ERR_EMPTY => "File is empty."               // *** add this
    );
    // first check the things we already have the info for

    if ( $imgfile_data['error'] ) {
        return "File failed to upload: " . $upload_errors[$imgfile_data['error']];
    }
    if ( !in_array( $imgfile_data['type'], array_values( $types ) ) ) {
        return "The image file appears to be of type: '" . $imgfile_data['type'] .
                "'. It should be one of " . join( ',', array_values( $types ) ) . " .";
    }
    if ( $imgfile_data['size'] > $maxs ) {
        return "Image file is larger than the limit of " . $field_data->fdata['max_img_size'] . 'M';
    }

    // ok, passed. Now check the dimension constraints
    $imgResource = wp_get_image_editor( $imgfile_data['tmp_name'] );
    if ( is_wp_error( $imgResource ) ) {
        return "Unable to read image file: " . $imgResource->get_error_message();
    }
    $size = $imgResource->get_size();
    $w = $size['width'];
    $h = $size['height'];

    // Max dimensions are always set for landscape mode.
    // If rotation allowed and image is in portrait format, interchange max width and height
    if ( $canrot && ( $h > $w ) ) {
        $tmp = $maxw;
        $maxw = $maxh;
        $maxh = $tmp;
    }

    if ( ( $w > $maxw ) || ( $h > $maxh ) ) {
        $msg = "Image dimensions $w x $h do not fit within the allowed dimensions of $maxw pixels wide x $maxh pixels high";
        if ( $canrot ) {
            $msg .= " ( or $maxh pixels wide x $maxw  pixels high ) ";
        }
        return $msg;
    }
    $longest = ( $w > $h ) ? $w : $h;

    if ( $longest < $minld  ) {
        return "Longest image dimension is $longest pixels, which is too small for this application.\n\nIt can be enlarged up to " .
                esc_html( "$maxw pixels wide x $maxh pixels high" );
    }

    return '';
}


/**
 * User deletion of an item.
 * Called using ajax via the 'Delete' button on the Upload page.
 * Return '1' for success, message for error.
 */
function ewz_user_delete_item( $item_id ){
    assert( is_numeric( $item_id ) );
    $item = new Ewz_Item( $item_id );
    $webform = new Ewz_Webform( $item->webform_id );
    if( $webform->open_for_current_user() ){
        $item->delete();
        return '1';
    } else {
        return 'Sorry, this form is no longer open for uploads.';
    }
}

/**
 * Validate POST data and handle any uploads
 * Called using ajax via the 'Submit' button on the Upload page for newer browsers.
 * After this, the javascript controls re-load the page
 */
function ewz_validate_and_upload( )
{
    if ( $_POST && isset( $_POST['identifier'] ) && is_string( $_POST['identifier'] ) ) {
        // first grab the webform data, since layout is needed for full validation. 
        // Do a quick sanitization of $_POST['identifier'] before passing it -- later it's compared to allowed values.
        $atts = array( 'identifier' => preg_replace( '/[^a-z0-9 _-]/i', '_', $_POST['identifier'] ) );
        $webformdata = ewz_get_webform_data( $atts );

        // not logged in or form not open - display the html failmsg
        if ( array_key_exists( 'failmsg', $webformdata ) ) {
            return wp_strip_all_tags( $webformdata['failmsg'] );
        }

        // had problems with more than 10 3M images
        $n = $webformdata['webform']->num_items;
        $timelimit = ini_get('max_execution_time');
        if( 15 * $n > $timelimit ){
            set_time_limit ( 15 * $n );
        }

        $input = new Ewz_Upload_Input( stripslashes_deep( $_POST ), $_FILES, $webformdata['layout'] );
        // return error messages
        return ewz_process_upload( $input->get_input_data(), $webformdata['user_id'],
                                   $webformdata['webform']->webform_id );
    } else {
        return "Invalid upload form.";
    }
}

/**
 * Check for valid shortcode arguments
 *
 * @param    array   $atts   arguments "ewz_show_webform" was called with
 * @return:  string  message to show to users
 */
function ewz_check_upload_atts( $atts )
{
    assert( is_array( $atts ) );
    if ( !array_key_exists( 'identifier', $atts ) ) {
        throw new EWZ_Exception( 'Missing identifier attribute for shortcode' );
    }
    $idents = Ewz_Webform::get_all_idents();
    if ( !in_array( $atts['identifier'], $idents ) ) {
        throw new EWZ_Exception(  'Invalid identifier "' . $atts['identifier'] . '" in shortcode' );
    }
}

/**
 * Return a string used to display a stored item field in read-only fashion
 *
 * For text fields, just return the text.  For drop-down items, return the option display label.
 *
 * @param  Ewz_Field   $field
 * @param  string      $value    stored value for this field
 * @return string used to display the value
 */
function ewz_display_item( $field, $value ) {
    assert( is_object( $field ) );
    assert( is_string( $value ) || is_bool( $value ) || in_array( $value, array( null, 1, 0 ), true ) );
    if(  'opt' == $field->field_type ){
        foreach( $field->fdata['options'] as $n => $opt ){
            if( $opt['value'] == $value ){
                return $opt['label'];
            }
        }
    } elseif( 'rad' == $field->field_type || 'chk' == $field->field_type ){
        if ( $value  ) {
            return 'checked';
        } else {
            return '';
        }
    } else {
       return $value;
    }
}

function ewz_get_fname_num( $in_fname, $ext, $dir ){
    assert( is_string( $in_fname ) );
    assert( is_string( $ext ) );
    assert( is_string( $dir ) );

    // some belt-and-braces checks before a potentially long loop
    if( strlen( $in_fname ) < 5 ){
        return '';
    }        
    if( strpos( $in_fname, '[~1]' ) === false ){
        return '';
    }
    if( !is_dir( $dir ) ){
        // first image, no dups
        return "1";
    }
       
    $num = 1;
    while( $num < 999 ){
        $testfname = str_replace( '[~1]', "$num", $in_fname );
        if( !file_exists( "{$dir}/{$testfname}.{$ext}" ) ){
            return "$num";
        }
        ++$num;
    }
    return "$num";
}


