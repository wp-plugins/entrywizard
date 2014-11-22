<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-item-list-input.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-item-list-IPP-input.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-item-list.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-field.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-webform.php' );
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-common.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-exception.php' );
require_once( EWZ_CUSTOM_DIR . 'ewz-custom-data.php' );

/**
 * Set the items-per-page user option
 *
 * Called via ajax from item list page
 * @return: blank or error message
 */
function ewz_set_ipp(){
    try{
        $input = new Ewz_Item_List_IPP_Input( $_POST );
        $requestdata  = $input->get_input_data();
    } catch( Exception $e ){
        return "Invalid input for items-per-page: " .$e->getMessage();
    }
    update_user_meta(
                     get_current_user_id(),
                     'ewz_itemsperpage',
                     $requestdata['ewz_ipp'] );
    return '';
}


/**
 * Attach images to selected page
 *
 * Called via ajax from item list page
 * Generate new copies of the images in the regular uploads directory, so the page is not
 * in any way tied to entrywizard.
 *
 * @return: status message
 */
function ewz_check_and_attach_imgs()
{
    // validate the input
    try{
        $input = new Ewz_Item_List_Input( $_POST );
        $requestdata  = $input->get_input_data();
    } catch (Exception $e) {
        return $e->getMessage();
    }

    if( !Ewz_Permission::can_manage_webform( $requestdata['webform_id'] ) ) {
        throw new EWZ_Exception( 'Attempt to attach items without permission to manage webform' );
    }

    // save the options for attaching, because may need to use the same ones for several pages
    $save_prefs = array(
                        'ewz_page_sel' => $requestdata['ewz_page_sel'],
                        'img_comment'  => $requestdata['img_comment'],
                        'img_size'     => $requestdata['img_size'],
                        'ifield'       => $requestdata['ifield'],
                        );
    $webform = new Ewz_webform( $requestdata['webform_id'] );
    $webform->save_attach_prefs( $save_prefs );

    $count = 0;
    $n = 0;
    if( isset( $requestdata['ewz_check'] ) ){
        $n = count( $requestdata['ewz_check'] );
    }
    $errmsg = '';

    // allow extra time for processing - this can be slow
    $timelimit = ini_get('max_execution_time');
    if( 5 * $n > $timelimit ){
        set_time_limit ( 5 * $n );
    }
    if( isset( $requestdata['ewz_check'] ) ){
        foreach ( $requestdata['ewz_check'] as $item_id ) {
            $msg = ewz_attach_item( $item_id, $requestdata );
            if( $msg ){
                $errmsg .= $msg;
            } else {
                ++$count;
            }
        }
    }
    $errmsg .= "  $count items attached";
    return $errmsg;
}

/**
 * Attach a single item to a page
 *
 *
 * @param  $item_id          item to be attached
 * @param  $requestdata      parameters for attachment: destination page, image size, coments allowed
 * @return error message or empty string
 */
function ewz_attach_item( $item_id, $requestdata ){
    assert( Ewz_Base::is_pos_int( $item_id ) );
    assert( is_array( $requestdata ) );
    $msg = '';
    try{
        $item = new Ewz_Item( $item_id );
        if( !count( $item->item_files ) || !array_key_exists( $requestdata['ifield'], $item->item_files ) ){
            $msg .= "No image file for item with id $item->item_id.\n";
        }
        foreach ( $item->item_files as $field_id => $file ) {
            if( ( $requestdata['ifield'] == $field_id ) ) {
                if( !isset( $file['fname'] ) ){
                    throw new EWZ_Exception( "No image file found" );
                }   
                $orig_file = wp_check_filetype_and_ext( $file['fname'],  basename( $file['fname'] ) );
                $filename = ewz_create_attachment_file( $file['fname'], $requestdata['img_size'] );
                $attachment = array(
                                    'post_mime_type' => $orig_file['type'],
                                    'comment_status' => $requestdata['img_comment'] ? 'open' : 'closed',
                                    'post_name'      => basename($filename),
                                    'post_status'    => 'inherit',
                                    'post_author'    => $item->user_id,
                                    );
                if( isset( $item->item_data[$field_id]['pexcerpt'] ) ){
                    $attachment['post_excerpt'] = $item->item_data[$field_id]['pexcerpt'];
                }
                if( isset( $item->item_data[$field_id]['pcontent'] ) ){
                    $attachment['post_content']  = $item->item_data[$field_id]['pcontent'];
                }
                if( isset( $item->item_data[$field_id]['ptitle'] ) ){
                    $attachment['post_title'] = $item->item_data[$field_id]['ptitle'];
                }

                // insert an attachment into the media library
                $attach_id = wp_insert_attachment( $attachment, $filename, $requestdata['ewz_page_sel'] );

                // must first include the image.php file for the function wp_generate_attachment_metadata() to work
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                // This function generates metadata for an image attachment. It also creates a thumbnail and other intermediate
                // sizes of the image attachment based on the sizes defined on the Settings_Media_Screen.
                $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );

                wp_update_attachment_metadata( $attach_id, $attach_data );
                $item->record_attachment( $requestdata['ewz_page_sel'] );
            }
        }
    } catch( Exception $e ) {
        $msg .= "\nItem $item_id: " . $e->getMessage();
    }
    return $msg;
}


/**
 * Create a (possibly resized) copy of an uploaded image
 *
 * To avoid problems with deletion when an image is attached to another page, create a (possibly resized) copy
 *
 * @param  string  $fpath        path to uploaded image
 * @param  string  $size_string  size of copy to be created
 *              ( must be one of the size names set in wordpress, either in settings/media or via the theme )
 * @return string  path to the newly created copy
 */
function ewz_create_attachment_file( $fpath, $size_string ){
    assert( ewz_is_valid_ewz_path( $fpath ) );
    assert( is_string( $size_string ) );

    $max_width  = intval( get_option( "{$size_string}_size_w" ) );
    $max_height = intval( get_option( "{$size_string}_size_h" ) );

    $upload_dir = wp_upload_dir();

    $base = basename( $fpath );

    $attach_path =  $upload_dir['path'] . '/' . wp_unique_filename( $upload_dir['path'], $base );

    $image = wp_get_image_editor( $fpath );
    if ( is_wp_error( $image ) ) {
        throw new EWZ_Exception( $fpath . ": " . $image->get_error_message() );
    } else {
        $image->resize( $max_width, $max_height, false );
        $status = $image->save( $attach_path );
        if( is_wp_error( $status ) ){
            throw new EWZ_Exception( $fpath . ": " . $status->get_error_message() );
        }
    }
    return $attach_path;
}


/**
 * Batch delete selected items
 *
 * @return string  status message
 */
function ewz_batch_delete_items(  )
{
    $data = array_merge( $_POST, $_GET );
    if ( !( isset( $data['webform_id'] ) &&
            Ewz_Permission::can_manage_webform( $data['webform_id'] ) ) ){
        throw new EWZ_Exception( 'Insufficient Permissions To Modify Data' );
    }
    try{
        $input = new Ewz_Item_List_Input( $data );
        $requestdata  = $input->get_input_data();
    } catch( Exception $e ){
        return $e->getMessage();
    }
    $items_for_deletion = array();
    if( isset( $requestdata['ewz_check'] ) ){
        $items_for_deletion = $requestdata['ewz_check'];
    }
    $n = 0;
    $msgs = '';
    foreach ( $items_for_deletion as $item_id ) {
        $item = new Ewz_Item( $item_id );
        // item should belong to input webform
        if( $item->webform_id == $data['webform_id'] ){
            try{
                $item->delete();
            } catch( Exception $e ){
                $msgs .= "\n$item_id " . $e->getMessage();
            }
            ++$n;
        } else {
            throw new EWZ_Exception( 'Attempt to delete item on different webform' );
        }
    }
    return "$n items deleted". $msgs;
}


/**
 * Get the header array for the item-list page
 *
 * @param   $fields array of EWZ_Fields
 * @param   $extra_cols  array of extra columns for display
 * @return  array of strings - headers for the item list page
 */
function ewz_get_headers( $fields, $extra_cols )
{
    assert( is_array( $fields ) );
    assert( is_array( $extra_cols ) );
    $headers = array();
    foreach ( $fields as $field ) {
        $headers[$field->ss_column + 1] = $field->field_ident;
    }
    $dheads = Ewz_Layout::get_all_display_headers();

    foreach ( $extra_cols as $xcol => $sscol ) {
        if ( $sscol >= 0 ) {
            $ssc = $sscol + 1;
            if( isset( $dheads[$xcol] ) ){
                $headers[$ssc] = $dheads[$xcol]['header'];
            }
        }
    }
    return $headers;
}

/**
 * Return the list of columns that contain images
 *
 * @param   $fields  array of EWZ_Fields
 * @return  array of field identifiers representing columns that contain images
 */
function ewz_get_img_cols( $fields )
{
    assert( is_array( $fields ) );

    $image_columns = array();
    foreach ( $fields as $field ) {
        if ( 'img' == $field->field_type  ) {
            $image_columns[$field->field_id] = $field->field_ident;
        }
    }
    return $image_columns;
}

/**
 * Generate the array of rows for the item list table
 *
 * @param $items        array of Ewz_Items, indexed on item_id
 * @param $fields       array of Ewz_Fields, indexed on field_id
 * @param $extra_cols   array of integers representing column numbers, indexed
 *                        by the extra_column abbreviations
 *                       ('att','aat','aae','aac','add','dlc','dtu','iid','wft','wid','wfm','nam','fnm',
 *                        'lnm', 'mnm', 'mem','mid', 'mli', 'custom1', 'custom2', ... )
 * @param $wform        Ewz_Webform
 *
 * @return 2-D array of strings containing the html content of the item table
 */
function ewz_get_item_rows( $items, $fields, $extra_cols, $wform )
 {
    assert( is_array( $items ) );
    assert( is_array( $fields ) );
    assert( is_array( $extra_cols ) );
    assert( is_object( $wform ) );

    $maxcol = max( array_values( $extra_cols ) );
    $n = 0;
    $rows = array( );
    foreach ( $items as $item ) {
        $rows[$n] = array_fill( 0, $maxcol + 1, '' );
        $rows[$n][0] = $item->item_id;

        // image columns from item_files
        if ( $item->item_files ) {
            foreach ( $item->item_files as $field_id => $item_file ) {
                if( isset( $item_file['fname'] ) && $item_file['fname'] ){
                    if ( array_key_exists( $field_id, $fields ) && $fields[$field_id]->ss_column >= 0 ) {
                        $col = $fields[$field_id]->ss_column + 1;
                        $basename = basename( $item_file['fname'] );
                        $clickFunc = 'window.open ' .
                            "('" . ewz_file_to_url( $item_file['fname'] ) . "');" .
                            ' return false;';

                        $rows[$n][$col] =
                            '<a href="#" onClick="' . $clickFunc . '">' .
                            '<img alt="" src="' . $item_file['thumb_url'] . '">' .
                            '</a><br>' . $basename;
                    }
                }
            }
        }
        // other columns from item_data
        foreach ( $item->item_data as $field_id => $field_value_arr ) {
            if ( array_key_exists( $field_id, $fields ) &&
                    $fields[$field_id]->ss_column >= 0 ) {
                $col = $fields[$field_id]->ss_column + 1;
                $field = new Ewz_Field( $field_id );
                if ( $field->field_type != 'img' ) {
                    // image columns have already been set
                    assert(  !isset( $rows[$n][$col] ) ||  !$rows[$n][$col] );
                    if( in_array( $field->field_type, array( 'rad', 'chk' ) ) ){
                        $rows[$n][$col] =  $field_value_arr['value'] ? 'checked' : '';
                    } else {
                        $rows[$n][$col] = ( string ) $field_value_arr['value'];
                    }
                }
                // append the uploaded info from the .csv file if it exists
                // NB: <b> tag does not work here, it is overridden by the jquery dialog css
                $info = '';
                if ( isset( $item->item_data[$field_id]['ptitle'] ) ) {
                    $info .= '<p><span class="ui-priority-primary">Title:</span> ' . $item->item_data[$field_id]['ptitle'] . "</p>";
                }
                if ( isset( $item->item_data[$field_id]['pexcerpt'] ) ) {
                    $info .= '<p><span class="ui-priority-primary">Excerpt:</span> ' .  $item->item_data[$field_id]['pexcerpt'] . "</p>";
                }
                if ( isset( $item->item_data[$field_id]['pcontent'] ) ) {
                    $info .= '<p><span class="ui-priority-primary">Content:</span> ' .  $item->item_data[$field_id]['pcontent'] . "</p>";
                }
                if ( $info ) {
                    // this gets passed through echo, so needs extra escaping for single quotes (?)
                    $info = str_replace( '&#039;', '\\&#039;', $info);
                    $info =  '&#039;'. str_replace( "'", '&#039;', $info) . '&#039;';
                    // return false to stop it going to top of page when popup is closed
                    $rows[$n][$col] .= "<br><a href='#' onClick='ewz_info( $info ); return false;'>Item Info</a>";
                }
            }
        }
        $user = get_userdata( $item->user_id );
        $display = Ewz_Layout::get_all_display_data();
        $custom = new Ewz_Custom_Data( $item->user_id );
        // could be done more succinctly using $$display[$xcol]['dobject']
        // but harder to understand, and fools the ide into warning
        foreach ( $extra_cols as $xcol => $sscol ) {
            if ( $sscol >= 0 ) {
                $ssc = $sscol + 1;
                assert( !isset( $rows[$n][$ssc]) ||  !$rows[$n][$ssc] );
                $datasource = '';
                // dont crash on undefined custom data
                if( isset( $display[$xcol] ) ){
                    switch ( $display[$xcol]['dobject'] ) {
                        case 'wform':
                            $datasource = $wform;
                            break;
                        case 'user':
                            $datasource = $user;
                            break;
                        case 'item':
                            $datasource = $item;
                            break;
                        case 'custom':
                            $datasource = $custom;
                            break;
                        default:
                            throw new EWZ_Exception( 'Invalid data source ' .  $display[$xcol]['dobject'] );
                    }
                    $rows[$n][$ssc] = Ewz_Layout::get_extra_data_item( $datasource, $display[$xcol]['value'] );
                }
            }
        }
        ++$n;
    }
    return $rows;
}

/**
 * Return the array of defined wordpress size names
 *
 * @param   none
 * @return  array of strings
 */
function ewz_get_img_sizes() {
    $sizes = array();
    foreach ( get_intermediate_image_sizes() as $size ) {
        array_push( $sizes, $size );
    }
     array_push( $sizes, 'full' );
    return $sizes;
}

/**
 * Return the options string for a size selection drop-down
 *
 * @param   $selected  item that is to be shown as selected
 * @return  string consisting of html options
 */
function ewz_get_img_size_options( $selected ) {
    assert( is_string( $selected )|| $selected == null );
    $options = '';
    foreach ( ewz_get_img_sizes() as $size ) {
        $sel = '';
        if( $size == $selected ){
            $sel = ' selected="selected"';
        }
        $options .= '<option value="' . $size . '"' . $sel . ">$size</option>";
    }
    return $options;
}

/**
 * Return a function to be used to sort the array
 *
 * @param   $sort_col  column to sort on (numeric, 0-based)
 * @param   $sort_order  string 'asc' or 'desc'
 * @return  the function
 */
function ewz_item_list_sort( $sort_col, $sort_order ){
    assert( Ewz_Base::is_nn_int( $sort_col ) );
    assert( ( $sort_order == 'asc' ) || ( $sort_order == 'desc' ) );
            
    return function($a, $b) use ( $sort_col, $sort_order ) {
        if( $sort_order == 'asc' ){
            return strcmp( $a[$sort_col], $b[$sort_col] );
        } else {
            return  strcmp( $b[$sort_col], $a[$sort_col] );
        }   
    };
}

/* * ************************** Main Item List Function ********************************
 * Generates the management page that lists uploaded items
 *
 * Checks for input data, validates, sanitizes and processes it
 * Then outputs the page
 *
 * @param  none
 * @return none
 */

function ewz_list_items() {

    $data = array_merge( $_POST, $_GET );    // this is normally accessed via GET
    if ( !( isset( $data['webform_id'] ) &&
            Ewz_Permission::can_manage_webform( $data['webform_id'] ) ) ){
        wp_die( "Insufficient Permissions To View Page" );
    }
    // if errors here stop processing
    try{
        $input = new Ewz_Item_List_Input( $data );
        $requestdata  = $input->get_input_data();
    } catch( Exception $e ){
        wp_die( $e->getMessage() );
    }

    // Do any bulk actions requested
    $the_action = -1;
    if( isset( $requestdata['action'] ) ){
        $the_action = $requestdata['action'];
        if( $the_action == -1 ){
            $the_action = $requestdata['action2'];
        }
    }
    $message = '';
    // Messages from exceptions generated here are  are shown to user
    if( $the_action == 'ewz_attach_imgs' ){
        $message = ewz_check_and_attach_imgs();
    } else if(  $the_action == 'ewz_batch_delete' ){
        $message = ewz_batch_delete_items();
    }

    // if errors here stop processing
    try{
        ewz_display_list_page( $message, $requestdata );
    } catch( Exception $e ){
        wp_die( $e->getMessage() );
    }
}

/**
 * Return the html for the display of the criteria used to select the items
 *
 * @param    $field_opts   array of fields and their selected values
 * @param    $extra_opts   show only items uploaded within the last $uploaddays
 * @return   html
 */
function  ewz_get_opt_display_string( $field_opts, $custom_opts, $extra_opts ){
    assert( is_array(  $field_opts ) ) || empty( $field_opts );
    assert( is_array(  $custom_opts ) ) || empty( $custom_opts );
    assert( is_array(  $extra_opts ) ) || empty( $extra_opts );

    $optname = array();
    $optval = array();
    if ( isset( $field_opts ) ) {
        foreach( $field_opts as $field_id=>$fval ){
            $field = new Ewz_field($field_id);
            $optname[$field_id] = $field->field_header;
            $optval[$field_id] = str_replace( '~*~', 'Any', str_replace( '~+~', 'Not Blank', str_replace( '~-~', 'Blank', $fval ) ) );
        }
    }
    if ( isset( $custom_opts ) ) {
        foreach( $custom_opts as $custom_name=>$cval ){
            $optname[$custom_name] = Ewz_Custom_Data::$data[$custom_name];
            $optval[$custom_name] = str_replace( '~*~', 'Any', str_replace( '~+~', 'Not Blank', str_replace( '~-~', 'Blank', $cval ) ) );
        }
    }

    $str ="<ul>\n";
    foreach ( $optname as $f => $name ) {
        $str .= "<li>$name = $optval[$f]</li>\n";
    }
    foreach ( $extra_opts as $nm => $val ) {
        if( $nm == 'uploaddays' && $val > 0 ){
            $str .=  "<li>Uploaded in the last $val days</li>\n";
        }
    }
    $str .= "</ul>\n";
    return $str;
}

/**
 * Return the html for the items-per-page form
 *
 * @param   $ipp   value set for "items per page"
 * @return  html
 */
function ewz_get_ipp_form( $url, $ipp ){
    assert( is_string ( $url ) );
    assert( Ewz_Base::is_nn_int( $ipp ) || empty( $ipp ) );

    $str = '<form id="ipp_form" class="ewz_shaded" action="$url" method="POST" >';
    $str .= 'Show on screen ';
    $str .= wp_nonce_field( 'ewzadmin', 'ewznonce', true, false );
    $str .= '    <input type="hidden" name="action" value="ewz_set_ipp">';
    $str .= '    <input id="ewz_ipp" class="screen-per-page" type="number"  value="' .  esc_attr( $ipp ) . '"';
    $str .= '          " maxlength="3" name="ewz_ipp" max="999" min="1" step="1">';
    $str .= '    <label for="ewz_ipp"> Items </label>';

    // processIPP does an ajax post call followed by a document reload
    $str .= '    <button  type="submit" id="ewz-ipp-apply"  onClick="return processIPP(\'ipp_form\')" class="button action">Apply</button>';
    $str .= "</form>\n";

    return $str;
}

function ewz_attach_options_string( $help_icon, $attach_prefs, $fields ){
    assert( is_string ( $help_icon ) );
    assert( is_array( $attach_prefs ) || empty( $attach_prefs ) );
    assert( is_array( $fields ) );

    $dropdown = wp_dropdown_pages( array( 'name'=>'ewz_page_sel', 'selected'=>$attach_prefs['ewz_page_sel'], 'echo'=>0 ) );
    $size_args = ewz_get_img_size_options( $attach_prefs['img_size'] );
    $image_columns = ewz_get_img_cols( $fields );
    $commentChecked = $attach_prefs['img_comment'] ? 'checked' : '';

    $str =<<<EOF
    <table class="ewz_buttonrow ewz_shaded" style="float:left; margin:20px;">
        <tr><td><img alt="" class="ewz_ihelp" src="$help_icon" onClick="ewz_help('dest');">&nbsp;
                        Destination page when attaching images:</td>
            <td>$dropdown</td></tr>
        <tr><td><img alt="" class="ewz_ihelp" src="$help_icon" onClick="ewz_help('imgcomm');">&nbsp;
                        Allow comments on attached images: </td>
            <td><input type="checkbox" id="img_comment" value="1" name="img_comment" $commentChecked ></td></tr>
        <tr><td><img alt="" class="ewz_ihelp" src="$help_icon" onClick="ewz_help('imgsize');">&nbsp;
                        Size of attached image: </td>
            <td><select id="img_size" name="img_size" >$size_args</select></td></tr>
EOF;
     if ( count( $image_columns ) > 1 ) {
	foreach ( $image_columns as $fld_id => $fld_head ) {
            $eschead = esc_html( $fld_head );
            $str .= "<tr><td>Attach images from column $eschead</td>";
	    $str .= '    <td><input type="radio" name="ifield" value="' . $fld_id . '"></td>';
	    $str .= '</tr>';
        }
        $str .= '</table>';

     } else {
         $str .= '</table>';
         foreach ( $image_columns as $fld_id => $fld_head ) {
             $eschead = esc_html( $fld_head );
	     $str .= '<input type="hidden" name="ifield" value="' . esc_attr( $fld_id ) . '">';
         }
     }
     return $str;
}

/**
 * Generate the required data and then print the html for the item list page
 *
 * @param   string  $message       Message for user - validation errors or feedback on actions taken
 * @param   array   $requestdata   $_GET plus $_POST, validated
 */
function  ewz_display_list_page( $message, $requestdata ){
    assert( is_string( $message ) );
    assert( is_array( $requestdata ) );

    $webform_id = $requestdata['webform_id'];

    $webform = new Ewz_Webform( $webform_id );
    $fields = Ewz_Field::get_fields_for_layout( $webform->layout_id, 'ss_column' );

    $field_opts = array();
    if ( isset( $requestdata['fopt'] ) ) {
        $field_opts = $requestdata['fopt'];
    }
    $custom_opts = array();
    if ( isset( $requestdata['copt'] ) ) {
        $custom_opts = $requestdata['copt'];
    }

    $extra_opts = array();             // this is set up to allow for other options than just uploaddays
    if( isset( $requestdata['uploaddays'] ) ){
        $extra_opts['uploaddays'] = $requestdata['uploaddays'];
    }
    $opt_display_string = ewz_get_opt_display_string( $field_opts, $custom_opts, $extra_opts );


    $items = Ewz_Item::filter_items( $field_opts, $custom_opts, $extra_opts,
                                     Ewz_Item::get_items_for_webform( $webform_id, false ) );
    $extra_cols = Ewz_Layout::get_extra_cols( $webform->layout_id );

    $headers = ewz_get_headers( $fields, $extra_cols );
    $rows = ewz_get_item_rows( $items, $fields, $extra_cols, $webform );
    if( isset( $requestdata['orderby'] ) && isset( $requestdata['order'] ) ){
          uasort( $rows, ewz_item_list_sort( $requestdata['orderby'], $requestdata['order'] ) );
    }
    $item_ids = array_map( function($v){ return $v->item_id; },  $items );

    $ewzG = array(
                  'message'    => $message,
                  'load_gif'   => plugins_url( 'images/loading1.gif', dirname(__FILE__) ),
                  'helpIcon'   => plugins_url( 'images/help.png' , dirname(__FILE__) ),
                  );
    wp_localize_script( 'ewz-admin-list-items', 'ewzG',  $ewzG  );
                                           // script name set above in ewz_item_list_scripts

    $ewz_item_list = new Ewz_Item_List( $item_ids, $headers, $rows );
    $listurl = admin_url( "admin.php" );

    $webformsurl = admin_url( 'admin.php?page=entrywizard' . "&openwebform=$webform_id" );
    $formtitle = $webform->webform_title;
    $ipp = get_user_meta( get_current_user_id(), 'ewz_itemsperpage', true );
    $help_icon = plugins_url( 'images/help.png' , dirname(__FILE__) );

    // set the attachment preferences
    $attach_prefs = $webform->get_attach_prefs();


    // needed to generate html below: $webform_id; $image_columns; $ewz_item_list; $webformsurl; $formtitle; $ipp; $ewzG;$selectedPage
    //                                $size_args; $commentChecked; $field_opts; $extra_opts;$listurl
    // must pass *all* args needed to generate page, because of pagination links
   ?>

    <div class="ewz_showlist">
        <h2>Images and Stored Information for "<?php print $formtitle; ?>"</h2>
     <div id="info-text" class="wp-dialog"> </div>

     <div class="ewz_params">
          <br>
          <?php print ewz_get_ipp_form( $listurl, $ipp ); ?>
          <br>
          <u>Displaying:</u> <?php print $opt_display_string; ?>
     </div>
     <form id="list_form" action="" method="POST" onSubmit="return processForm('list_form')">
        <div class="ewzform">
           <?php wp_nonce_field( 'ewzadmin', 'ewznonce' ); ?>
           <input type="hidden" name="webform_id" id="webform_id" value="<?php print $webform_id; ?>">

           <?php print ewz_attach_options_string( $help_icon, $attach_prefs, $fields ); ?>

    	   <div id='message'></div>

           <?php $ewz_item_list->display(); ?>

    	   <p class="ewz_pcentre ">
    	        <button type="button" class="button-primary" id="backButton"
                        onClick="window.location='<?php print esc_url( $webformsurl ); ?>';">
                    Back to WebForms Page</button>
    	   </p>
        </div>
        </form>
    </div>

    <div id="help-text" style="display:none">
        <div id="dest_help" class="wp-dialog" >
            <p>Images that have been "attached" to a page may be displayed on the page in a regular
                wordpress gallery.
               To do this, include the shortcode <b>"[gallery]"</b> on your page where you wish the
               gallery to appear.  If your theme has not overridden the default behaviour, this will display
               in the page thumbnails of all the attached images, which link to larger versions. If you have
               uploaded a .csv file of image data, it's contents will determine the headers and captions of the
               images.</p>
            <p> <i><b>NB:</b> The Wordpress gallery functionality is in the process of changing.
               Currently there are two formats for the shortcode:
           <ul><li>[gallery]  ( with optional parameters like the number of columns ). In this form,
                   the gallery displays all images attached to the page. </li>
               <li>[gallery ids="n1, n2, n3 ...."] where n1 n2 etc are the id's of the images to
                   display. Only the specified images are displayed, and they do not need to be
                   attached to the page.</li>
            </ul>
                EntryWizard currently assumes the first format.</i>
                 As of wordpress version 3.5, once the page has been edited to change the gallery
                 content, the shortcode will
                 automatically be put in the "list of images" format, and newly attached images will
                 not be displayed in the gallery.
                 This may be changed by using the "text" tab in the page editor to change the gallery
                 shortcode back to the first format.</p>
             <p>For documentation about the gallery shortcode, see
                 http://codex.wordpress.org/Gallery_Shortcode.</p>
             <p>-------------------------------</p>
    	     <p>If you wish the gallery to display scores or other image-specific information not
                 uploaded by the member, you need first
                to upload a csv file containing this data, using the "Upload extra data" button on
                the "Webforms" page</p>
             <p>-------------------------------</p>
             <p>Images attached to pages are copied. So deleting them here will not delete them from
                 the page they are attached to.</p>
        </div>
       <div id="imgcomm_help" class="wp-dialog" >
           <p>If this is checked, anyone with permission to see the destination page will be allowed
               to add comments to the image</p>
        </div>
       <div id="imgsize_help" class="wp-dialog" >
          <p>"Attaching" creates a copy of the image which is added to the Wordpress media.  This
              allows the attached copy to be deleted or modified using the normal wordpress interface,
              without affecting the entrywizard file.<p>
          <p>You may, if you like, choose to make this copy a smaller version of the uploaded image
              instead of the full-size one.  Select "full" to copy the original uploaded image. </p>
          <p>The copy is created in your normal uploads directory, so it will not be deleted if you
              should uninstall entrywizard.  Editing or deleting the copy should have no effect on
              the image stored in EntryWizard.<p>
        </div>

    </div>

<?php

}           // end ewz_display_list_page

