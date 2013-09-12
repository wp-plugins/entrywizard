<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-item-list-input.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-webform-data-input.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-item-list-IPP-input.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-item-list.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-field.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-webform.php' );
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-common.php' );
require_once( EWZ_PLUGIN_DIR . 'ewz-custom-data.php' );


/**
 * Attach images to selected page
 *
 * Called via ajax from item list page
 * Generate new copies of the images in the regular uploads directory, so the page is not
 * in any way tied to entrywizard.
 *
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
    $destination = (int)$requestdata['ewz_page_sel'];   // should be an int - cast for safety
    $count = 0;
    $n = count( $requestdata['ewz_check'] );

    // allow extra time for processing - this can be slow
    $timelimit = ini_get('max_execution_time');
    if( 5 * $n > $timelimit ){
        set_time_limit ( 5 * $n );
    }

    foreach ( $requestdata['ewz_check'] as $item_id ) {
        $item = new Ewz_Item( $item_id );

        // should not be able to attach item unless can manage its webform
        if( !Ewz_Permission::can_manage_webform( $item->webform_id ) ) {
            throw new EWZ_Exception( 'Attempt to attach item without permission to manage webform' );
        }

        foreach ( $item->item_files as $field_id => $file ) {
            if( ( $requestdata['ifield'] == $field_id ) && isset( $file['fname'] ) ) {
                $orig_file = wp_check_filetype_and_ext( $file['fname'],  basename( $file['fname'] ) );
                $filename = ewz_create_attachment_file( $file['fname'], $requestdata['img_size'] );
                $attachment = array(
                                    'post_mime_type' => $orig_file['type'],
                                    'comment_status' => isset($requestdata['img_comment']) ? 'open' : 'closed',
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
                $attach_id = wp_insert_attachment( $attachment, $filename, $destination );

                // must first include the image.php file for the function wp_generate_attachment_metadata() to work
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                // This function generates metadata for an image attachment. It also creates a thumbnail and other intermediate
                // sizes of the image attachment based on the sizes defined on the Settings_Media_Screen.
                $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );

                wp_update_attachment_metadata( $attach_id, $attach_data );

                ++$count;
            }
        }
    }
    return "$count items attached";
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
    $attach_path = $upload_dir['path'] . "/$base";

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
function ewz_batch_delete_items()
{
    $n = 0;
    foreach ( $_POST['ewz_check'] as $item_id ) {
        $item = new Ewz_Item( $item_id );
        // should not be able to attach item unless can manage its webform
        if( Ewz_Permission::can_manage_webform( $item->webform_id ) ){
            $item->delete();
            ++$n;
        } else {
            throw new EWZ_Exception( 'Attempt to delete item without permission to manage webform' );
        }
    }
    return "$n items deleted";
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
 *                       ('dtu','iid','wft','wid','wfm','nam','fnm',
 *                        'lnm', 'mnm', 'mem','mid', 'mli', 'custom1', 'custom2' )
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
                if( isset( $item_file['fname'] ) ){
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
                    $rows[$n][$col] = ( string ) $field_value_arr['value'];
                }
                // append the uploaded info from the .csv file if it exists
                $info = '';
                if ( isset( $item->item_data[$field_id]['ptitle'] ) ) {
                    $info .= "<p><b>Title:</b> " . $item->item_data[$field_id]['ptitle'] . "</p>";
                }
                if ( isset( $item->item_data[$field_id]['pexcerpt'] ) ) {
                    $info .= "<p><b>Excerpt:</b> " . $item->item_data[$field_id]['pexcerpt'] . "</p>";
                }
                if ( isset( $item->item_data[$field_id]['pcontent'] ) ) {
                    $info .= "<p><b>Content:</b> " . $item->item_data[$field_id]['pcontent'] . "</p>";
                }
                if ( $info ) {
                    $rows[$n][$col] .= '<br>' .
                            '<a href="#" onClick="ewz_info(' . "'" . $info . "'" . ')">' .
                            'Item Info</a>';
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
    $sizes = array( 'full' );
    foreach ( get_intermediate_image_sizes() as $size ) {
        array_push( $sizes, $size );
    }
    return $sizes;
}

/**
 * Return the options string for a size selection drop-down
 *
 * @param   none
 * @return  string consisting of html options
 */
function ewz_get_img_size_options() {
    $options = '';
    foreach ( ewz_get_img_sizes() as $size ) {
        $options .= '<option value="' . esc_attr( $size ) . '">' . "$size</option>";
    }
    return $options;
}

/**
 * Call the relevant validation function depending on the mode
 *
 * @param   array  $data  $_POST + $_GET
 * @return  array  validated data
 */
function ewz_do_listpage_validation( $data ){
    $requestdata = array();
    switch( $_POST['ewzmode'] ){

        case 'list':       // list page from webforms page, validate as Ewz_Webform_Data_Input
           $input = new Ewz_Webform_Data_Input( $data );
           $requestdata = $input->get_input_data();
           break;

        case 'listpage':   // return after delete or items-per-page, validate as Ewz_Item_List_Input
           // Only Delete and Items-per-page calls actually reload the page.
           // Attach  calls are handled via ajax
           // Note there are two buttons and two actions that should be treated the same
           $input = new Ewz_Item_List_Input( $data );
           $requestdata = $input->get_input_data();
           break;

        case 'ipp': // setting items-per-page, validate as  Ewz_Item_List_IPP_Input
            if ( array_key_exists( 'ewz-ipp-apply', $data ) ) {
               $input = new Ewz_Item_List_IPP_Input( $data );
               $requestdata = $input->get_input_data();
           }
           break;
      default: break;
    }
    return $requestdata;
}

/* * ************************** Main Item List Function ********************************
 * Generates the management page that lists uploaded items
 *
 * Checks for input data, validates, sanitizes and processes it
 * Then outputs the page
 *
 * @param  $input  validated Ewz_Webform_Data_Input
 * @return none
 */

function ewz_list_items() {

    $requestdata = array_merge( $_POST, $_GET );    // page and webform_id are in GET, rest in POST

    if ( !( isset( $requestdata['webform_id'] ) &&
            Ewz_Permission::can_manage_webform( $requestdata['webform_id'] ) ) ){
        wp_die( "Insufficient Permissions To View Page" );
    }

    $message = '';
    if( isset( $_POST['ewzmode'] ) ){
        // Messages from exceptions generated here are  are shown to user
        try{
            $requestdata = ewz_do_listpage_validation( $requestdata );
            switch( $_POST['ewzmode'] ){
                case 'listpage':
                   if ( ( array_key_exists( 'action', $requestdata ) &&
                          'ewz_admin_del_items' == $requestdata['action'] )
                        ||
                        ( array_key_exists( 'action2', $requestdata ) &&
                          'ewz_admin_del_items' == $requestdata['action2'] )
                      ){
                       $message .= ewz_batch_delete_items();  // returns number of items deleted
                   }
                break;

                case 'ipp':
                   if ( array_key_exists( 'ewz_items_per_page',  $requestdata ) ){
                       update_user_meta(
                                        get_current_user_id(),
                                        'ewz_itemsperpage',
                                        $requestdata['ewz_items_per_page'] );
                   }
                break;
              default: break;
            }
         } catch ( Exception $e ){
            $message .= $e->getMessage();
            $message .= "\n";
        }

   }  // end isset $_POST['ewzmode']

    // errors here stop processing
    try{
        ewz_display_list_page( $message, $requestdata );
    } catch( Exception $e ){
        wp_die( $e->getMessage() );
    }
}


/**
 * Generate the required data and then print the html for the item list page
 *
 * @param   string  $message       Message for user - validation errors or feedback on actions taken
 * @param   array   $requestdata   $_GET plus $_POST, validated
 */
function  ewz_display_list_page( $message, $requestdata ){

       if( isset( $_POST['ewzmode'] ) &&
            !in_array( $_POST['ewzmode'], array( 'list', 'listpage', 'ipp' ) ) ){
            throw new EWZ_Exception( "Invalid mode " . $_POST['ewzmode'] );
        }

        $webform_id = $requestdata['webform_id'];

        $webform = new Ewz_Webform( $webform_id );
        $fields = Ewz_Field::get_fields_for_layout( $webform->layout_id, 'ss_column' );

        $field_opts = array();
        if ( isset( $requestdata['fopt'] ) ) {
            $field_opts =  $requestdata['fopt'];
        }
        $extra_opts = array();
        if( isset( $requestdata['uploaddays'] ) ){
            $extra_opts['uploaddays'] = $requestdata['uploaddays'];
        }

        $items = Ewz_Item::filter_items( $field_opts, $extra_opts,
                                         Ewz_Item::get_items_for_webform( $webform_id, false ) );
        $extra_cols = Ewz_Layout::get_extra_cols( $webform->layout_id );

        $headers = ewz_get_headers( $fields, $extra_cols );
        $rows = ewz_get_item_rows( $items, $fields, $extra_cols, $webform );
        $item_ids = array_map( create_function( '$v', 'return $v->item_id;' ),  $items );

        $ewzG = array(
                      'listurl'    => admin_url( "admin.php?page=entrywizlist&webform_id=$webform_id" ),
                      'webform_id' => $webform_id,
                      'fopt'       => $field_opts,
                      'message'    => $message,
                      'load_gif'   => plugins_url( 'images/loading.gif', dirname(__FILE__) ),
                      'helpIcon'   => plugins_url( 'images/help.png' , dirname(__FILE__) ),
                      );
        wp_localize_script( 'ewz-admin-list-items', 'ewzG',  $ewzG  );
                                               // script name set above in ewz_item_list_scripts

        $image_columns = ewz_get_img_cols( $fields );
        $ewz_item_list = new Ewz_Item_List( $item_ids, $headers, $rows );
        $webformsurl = admin_url( 'admin.php?page=entrywizard' . "&openwebform=$webform_id" );
        $formtitle = $webform->webform_title;
        $ipp = get_user_meta( get_current_user_id(), 'ewz_itemsperpage', true );


        // needed to generate html below: $webform_id; $image_columns; $ewz_item_list; $webformsurl; $formtitle; $ipp; $ewzG;
   ?>

    <div class="ewz_showlist">
        <h2>Images and Stored Information for "<?php print $formtitle; ?>"</h2>
    <?php if( Ewz_Permission::can_manage_webform( $webform_id ) ){ ?>

        <div class="ewz_expand">
           <form id="ipp_form" action="<?php print esc_url( $ewzG['listurl'] );?>" method="POST">
            Show on screen
               <input type="hidden" name="ewzmode" id="ewzmode" value="ipp">
               <?php wp_nonce_field( 'ewzadmin', 'ewznonce' ); ?>
               <input id="ewz_items_per_page" class="screen-per-page" type="number"
                      value="<?php print esc_attr( $ipp ); ?>" maxlength="3"
                      name="ewz_items_per_page" max="999" min="1" step="1">
               <label for="ewz_items_per_page"> Items </label>
               <button  type="submit" id="ewz-ipp-apply" class="button action"
                        name="ewz-ipp-apply">Apply</button>
            </form>
        </div>
     <form id="list_form" action="<?php print esc_url( $ewzG['listurl'] );?>" method="POST"
           onSubmit="return processForm('list_form');">
        <div class="ewzform">
            <?php wp_nonce_field( 'ewzadmin', 'ewznonce', false, true ); ?>
    	    <input type="hidden" name="ewzmode" id="ewzmode" value="listpage">
    	    <input type="hidden" name="page" id="page" value="entrywizlist">
    	    <input type="hidden" name="webform_id" id="webform_id" value="<?php print esc_attr( $webform_id ); ?>">

    	    <table class="ewz_buttonrow ewz_shaded">
    	        <tr><td><img alt="" class="ewz_ihelp" src="<?php print $ewzG['helpIcon'] ?>" onClick="ewz_help('dest');">&nbsp;
    		                Destination page when attaching images:</td>
                    <td><?php wp_dropdown_pages(array('name'=>'ewz_page_sel')); ?></td></tr>
                <tr><td><img alt="" class="ewz_ihelp" src="<?php print $ewzG['helpIcon'] ?>" onClick="ewz_help('imgcomm');">&nbsp;
                                Allow comments on attached images: </td>
                        <td><input type="checkbox" id="img_comment" name="img_comment" ></td></tr>
                <tr><td><img alt="" class="ewz_ihelp" src="<?php print $ewzG['helpIcon'] ?>" onClick="ewz_help('imgsize');">&nbsp;
                                Resize image before attaching: </td>
                    <td><select id="img_size" name="img_size" >
                        <?php print ewz_get_img_size_options(); ?></select></td></tr>
      <?php if ( count( $image_columns ) > 1 ) {
	       foreach ( $image_columns as $fld_id => $fld_head ) {
      ?>
                   <tr><td>Attach images from column <?php print esc_html( $fld_head ); ?></td>
	    	    <td><input type="radio" name="ifield" value="<?php print esc_attr( $fld_id ); ?>"></td>
	    	</tr>
      <?php     }  ?>
	    </table>

      <?php } else { ?>
	    </table>
      <?php     foreach ( $image_columns as $fld_id => $fld_head ) { ?>
	    <input type="hidden" name="ifield" value="<?php print esc_attr( $fld_id ); ?>">
      <?php     } ?>
      <?php } ?>
    	    <br  />
    	    <div id='message'></div>
     <?php
           }
           $ewz_item_list->display();
     ?>
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
               gallery to appear.</p>
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
                 the page.</p>
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
              instead of the full-size one.</p>
          <p>The copy is created in your normal uploads directory, so it will not be deleted if you
              should uninstall entrywizard.  Editing or deleting the copy should have no effect on
              the image stored in EntryWizard.<p>
        </div>

    </div>

<?php

}           // end ewz_display_list_page

