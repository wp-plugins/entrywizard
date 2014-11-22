<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . 'classes/ewz-base.php');
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-exception.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-item.php');
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-webform.php');
require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-webform-data-input.php');
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-admin-layouts.php');
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-admin-webforms.php');
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-admin-permissions.php');
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-admin-list-items.php');



// File that is included with any admin entrywizard call. Delegates all admin code.

/* * ****************   Functions to enqueue the scripts and styles ******************* */
/*  Most scripts require data stored in a variable called 'ewzG', which must first be   */
/*      generated. They are not enqueued until we know which are really needed       */


function ewz_enqueue_common_styles( ) {
    // called in ewz_admin_init
    wp_enqueue_style( 'jquery-ui-dialog' );
    wp_enqueue_style( 'ewz-admin-style' );
}

function ewz_enqueue_common_scripts( ) {
    // hooked in ewz_admin_menu
    wp_enqueue_script( 'ewz-common' );
}

function ewz_enqueue_layout_scripts() {
    // hooked in ewz_admin_menu
    wp_enqueue_script( 'ewz-admin-layouts' );
}

function ewz_enqueue_webform_scripts() {
    // hooked in ewz_admin_menu
    wp_enqueue_script( 'ewz-admin-webforms' );
}
function ewz_enqueue_permission_scripts() {
    // hooked in ewz_admin_menu
    wp_enqueue_script( 'ewz-admin-permissions' );
}

function ewz_enqueue_item_list_scripts() {
    // hooked in ewz_admin_menu
    wp_enqueue_script( 'ewz-admin-list-items' );
}


/********************************************************************************/
function ewz_admin_init() {
    wp_register_style( 'jquery-ui-dialog', includes_url() . "/css/jquery-ui-dialog.css" );
    wp_register_style( 'ewz-admin-style', plugins_url( 'styles/ewz-admin.css', dirname(__FILE__) ) );

    wp_register_script( 'ewz-common', plugins_url( 'javascript/ewz-common.js', dirname( __FILE__ ) ),
                        array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog' ) );

    if( isset( $_REQUEST['page'] ) ){
        if( $_REQUEST['page'] == 'ewzlayouts' ){
            wp_register_script(
                               'ewz-admin-layouts',
                               plugins_url( 'javascript/ewz-layouts.js', dirname(__FILE__) ),
                               array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-dialog',
                                      'jquery-ui-position','ewz-common', 'jquery-ui-sortable' ),
                               false,
                               true         // in footer, so $ewzG has been defined
                               );
        } elseif( $_REQUEST['page'] == 'ewzperms' ){
            wp_register_script(
                               'ewz-admin-permissions',
                               plugins_url( 'javascript/ewz-permissions.js', dirname(__FILE__) ),
                               array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'ewz-common' ),
                               false,
                               true         // in footer, so $ewzG has been defined
                               );
        } elseif( $_REQUEST['page'] == 'entrywizard' ){
            wp_register_script(
                               'ewz-admin-webforms',
                               plugins_url( 'javascript/ewz-webforms.js', dirname(__FILE__) ),
                               array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-ui-datepicker', 'ewz-common' ),
                               false,
                               true         // in footer, so $ewzG has been defined
                               );
        } elseif( $_REQUEST['page'] == 'entrywizlist' ){
            wp_register_script(
                               'ewz-admin-list-items',
                               plugins_url( 'javascript/ewz-list-items.js', dirname(__FILE__) ),
                               array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget',
                                      'jquery-ui-dialog', 'jquery-ui-position', 'ewz-common' ),
                               false,
                               true         // in footer, so $ewzG has been defined
                               );
            ewz_enqueue_common_styles();
        }
    }
}
add_action( 'admin_init', 'ewz_admin_init' );

/**
 * Hook in the admin menu pages
 *
 * Add the EntryWizard menu section to the admin menu, with submenu items for
 *     Webforms, Layouts, Permissions
 * The "EntryWizard" header links to the webforms page, which is the one for managing
 *     open/close and downloads.
 *
 * Hooked to 'admin_menu', adds action to 'admin_print_scripts-....' which comes later
 * Calls Ewz_Permission, which does not require global constants.
 *
 */
function ewz_admin_menu() {
    /* NB: all slugs must include "entrywiz" to force load of translated strings */
    if ( Ewz_Permission::has_ewz_cap() ) {

       // MENU ITEMS
        // $page_title, $menu_title, $capability,
        // $menu_slug, $function,
        // menu icon
        $menu_hook = add_menu_page(
                                   'EntryWizard',
                                   'EntryWizard',
                                   'read',
                                   'entrywizard',
                                   'ewz_webforms_menu',
                                   plugin_dir_url( __FILE__ ) . '../images/ewz_icon.png'
                                   );

        add_action( 'admin_print_scripts-' . $menu_hook, 'ewz_enqueue_common_scripts' );
        add_action( 'admin_print_styles-' . $menu_hook, 'ewz_enqueue_common_styles' );

        if ( Ewz_Permission::can_see_webform_page() ) {
            // NB: list page is really part of webform page.
            // $parent_slug, $page_title, $menu_title,
            // $capability, $menu_slug, $function
            $webform_hook_suffix = add_submenu_page(
                                                    'entrywizard', 'EntryWizard Web Forms', 'WebForms', 'read',
                                                    'entrywizard', 'ewz_webforms_menu' );
            add_action( 'admin_print_scripts-' . $webform_hook_suffix, 'ewz_enqueue_webform_scripts' );

        }
        if ( isset( $_REQUEST['webform_id'] ) &&
             ( Ewz_Base::is_nn_int( $_REQUEST['webform_id'] ) ) &&
             Ewz_Permission::can_manage_webform( intval( $_REQUEST['webform_id'] ) ) ) {

            $list_hook_suffix  = add_submenu_page(
                                                  'NULL', 'EntryWizard Item List', 'ItemList',
                                                  'read', 'entrywizlist', 'ewz_list_items' );

            add_action( 'admin_print_scripts-' . $list_hook_suffix, 'ewz_enqueue_item_list_scripts' );
        }

        if ( Ewz_Permission::can_see_layout_page() ) {
            $layout_hook_suffix = add_submenu_page(
                                                   'entrywizard', 'EntryWizard Layouts', 'Layouts',
                                                   'read', 'ewzlayouts', 'ewz_layout_menu' );

            add_action( 'admin_print_styles-' . $layout_hook_suffix, 'ewz_enqueue_common_styles' );
            add_action( 'admin_print_scripts-' . $layout_hook_suffix,  'ewz_enqueue_layout_scripts' );
        }

        // no Permissions required here - using WP 'manage_options'
        $perm_hook_suffix = add_submenu_page(
                                             'entrywizard', 'EntryWizard Permissions', 'Permissions',
                                             'manage_options', 'ewzperms', 'ewz_permissions_menu' );

        add_action( 'admin_print_scripts-' . $perm_hook_suffix,  'ewz_enqueue_common_styles' );
        add_action( 'admin_print_scripts-' . $perm_hook_suffix,  'ewz_enqueue_permission_scripts' );
    }
}

add_action( 'admin_menu', 'ewz_admin_menu' );


/* * ************************  DOWNLOAD ACTIONS *************************** */

/**
 * Output to stdout a .csv summary of the webform data and/or a tar file of the uploaded images
 * Called via the "Download ...." buttons on the Webforms page.
 * Has to be hooked earlier than other stuff to avoid any output before it.
 * Echoes a header followed by the data to stdout, which forces a download dialog
 *
 * @return int 0 if bad data or permissions, otherwise 1
 */
function ewz_echo_data() {
    // Rest is only run if we are in the right mode
    if ( isset( $_GET["ewzmode"] ) &&
             ( in_array( $_GET["ewzmode"], array( 'spread', 'download', 'images','zdownload', 'zimages' ) ) ) ) {
        if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
            try {
                 ewz_wipe_buffers();
                // validate
                $input = new Ewz_Webform_Data_Input( array_merge( $_POST, $_GET ) );
                // 'page' is in GET
                $data = $input->get_input_data();
                $webform = new Ewz_Webform( $data['webform_id'] );

                $items = Ewz_Item::filter_items(
                           $data['fopt'],
                           $data['copt'],
                           array( 'uploaddays' => $data['uploaddays'] ),
                           Ewz_Item::get_items_for_webform( $data['webform_id'], false )
                        );
                switch ( $data['ewzmode'] ) {
                    case 'images':
                        $webform->echo_stored_archive( $data['archive_id'] );    
                        break;
                    case 'zimages':
                        $webform->gen_and_echo_archive( $items, Ewz_Webform::IMAGES );    
                        break;
                    case 'download':
                        $webform->echo_stored_archive( $data['archive_id'] );
                        break;
                    case 'zdownload':
                        $webform->gen_and_echo_archive( $items, Ewz_Webform::BOTH );
                        break;
                    case 'spread':
                        $webform->download_spreadsheet( $items, Ewz_Webform::SPREADSHEET );
                        break;
                    default:
                        throw new EWZ_Exception( "Invalid mode " . $data['ewzmode'] );
                }
            } catch (Exception $e) {
                error_log( "EWZ:  ewz_output_csv " . $e->getMessage() );
                die( $e->getMessage() );
            }
        } else {
            echo "Insufficient permissions - may have expired";
            error_log( "EWZ: ewz_echo_data, check_admin_referer failed" );
        }
    }
   
    return 1;
}
// need to make sure global constants are defined first
add_action( 'init', 'ewz_echo_data', 30 );


/* * ************************  AJAX CALLS *************************** *
 *
 *  NOTE: The action 'wp_ajax_xxxxxx' is called when the 'xxxxxx'
 *        action is specified in javascript
 *
 *  Each function echoes a return status that is checked in
 *  the javascript and usually alerted to the viewer, then exits.
 *
 */

/**
 * Generate a zip file of images
 *
 * Called via one of the "Download" buttons on the Webforms page
 * NB: action name is generated from the jQuery post, must match.
 * Returns a string identifying the wp option that holds the url
 * of the zip archive. Used if there is no zip command, or if images
 * need to be renamed on download.
 *
 */
function ewz_gen_zipfile_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        ewz_wipe_buffers();
        if ( !(isset( $_POST['webform_id'] ) && is_numeric( $_POST['webform_id'] )) ) {
            error_log( 'EWZ: ewz_gen_zipfile_callback, no webform_id or not numeric' );
            echo 'Missing or non-numeric webform_id';
        } else {
            try {
                // validate ( 'page' is in GET )
                $input = new Ewz_Webform_Data_Input( array_merge( $_POST, $_GET ) );
                $data = $input->get_input_data();
                $webform = new Ewz_Webform( $data['webform_id'] );

                $items = Ewz_Item::filter_items(
                           $data['fopt'],
                           $data['copt'],
                           array( 'uploaddays' => $data['uploaddays'] ),
                           Ewz_Item::get_items_for_webform( $data['webform_id'], false )
                        );
                switch ( $data['ewzmode'] ) {
                    case 'images':
                        echo $webform->generate_zip_archive( $items, Ewz_Webform::IMAGES );
                        break;
                    case 'download':
                        echo $webform->generate_zip_archive( $items, Ewz_Webform::BOTH );
                        break;
                    default:
                        throw new EWZ_Exception( "Invalid mode for gen_zipfile " . $data['ewzmode'] );
                }
            } catch (Exception $e) {
                echo $e->getMessage();
                Ewz_Base::delete_ewz_options('ewz_' . $data['webform_id']);
            }
        }
    } else {
        echo "Insufficient permissions - may have expired";
        error_log( "EWZ: ewz_del_layout_callback, check_admin_referer failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_gen_zipfile', 'ewz_gen_zipfile_callback' );

/**
 * Delete a Layout - handle ajax call
 *
 * Called via one of the "Delete Layout" buttons on the Layouts page
 * NB: action name is generated from the jQuery post, must match.
 * Javascript caller alerts if response is not "1" (true)
 *
 */
function ewz_del_layout_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        ewz_wipe_buffers();
        if ( !(isset( $_POST['layout_id'] ) && is_numeric( $_POST['layout_id'] )) ) {
            error_log( 'EWZ: ewz_del_layout_callback, no layout_id or not numeric' );
            echo 'Missing or non-numeric  layout_id';
        } else {
            try {
                $layout = new Ewz_Layout( intval( $_POST['layout_id'] ) );
                $layout->delete( Ewz_Layout::DELETE_FORMS );
                echo "1";
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    } else {
        echo "Insufficient permissions - may have expired";
        error_log( "EWZ: ewz_del_layout_callback, check_admin_referer failed" );
    }
    exit();
}

add_action( 'wp_ajax_ewz_del_layout', 'ewz_del_layout_callback' );

/**
 * Delete a Field - handle ajax call
 *
 * Called via one of the "Delete Field" buttons on the Layouts page
 * NB: action name is generated from the jQuery post, must match.
 * Javascript caller alerts if response is not "1" (true)
 */
function ewz_del_field_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        ewz_wipe_buffers();
        if ( !( isset( $_POST['layout_id'] ) && is_numeric( $_POST['layout_id'] ) &&
                isset( $_POST['field_id'] ) && is_numeric( $_POST['field_id'] )) ) {
            error_log( 'EWZ: ewz_del_field_callback, ' .
                    'no layout_id or no field_id or id not numeric' );
            echo 'Missing or non-numeric layout or field ids';
        } else {        
            try {
                $layout = new Ewz_Layout( intval( $_POST['layout_id'] ) );
                $layout->delete_field( intval( $_POST['field_id'] ) );
                echo "1";
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    } else {
        echo 'Insufficient permissions - may have expired';
        error_log( "EWZ: ewz_del_field_callback, check_admin_referer failed" );
    }
    exit();
}

add_action( 'wp_ajax_ewz_del_field', 'ewz_del_field_callback' );

/**
 * User Delete an Item - handle ajax call
 *
 * Called via one of the "Delete" buttons on the UPLOAD page
 *     ( dont confuse with admin delete from the list page )
 * NB: action name is generated from the jQuery post, must match.
 *
 * if response is not '1 item deleted.', javascript caller alerts with error message.
 */
function ewz_del_item_callback() {
    if ( wp_verify_nonce( $_POST["ewzdelnonce"], 'ewzupload' ) ) {
        ewz_wipe_buffers();
        if ( !(isset( $_POST['item_id'] ) && is_numeric( $_POST['item_id'] )) ) {
            error_log( 'EWZ:  ewz_del_item_callback - ' .
                    'no item_id or not numeric' );
            echo 'Missing or non-numeric item id';
        } else {
            try {
                $status = ewz_user_delete_item( intval( $_POST['item_id'] ) );
                echo $status;
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    } else {
        echo "No changes made - authorization expired";
        error_log( "EWZ: ewz_del_item_callback, nonce failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_del_item', 'ewz_del_item_callback' );

/**
 * Delete a Webform - handle ajax call
 *
 * Called via one of the "Delete Web Form" buttons on the Webforms page
 * NB: action name is generated from the jQuery post, must match.
 *
 * if response is not '1', javascript caller alerts with error message.
 */
function ewz_del_webform_callback() {
    if ( wp_verify_nonce( $_POST["ewznonce"], 'ewzadmin' ) ) {
        ewz_wipe_buffers();
        if ( !(isset( $_POST['webform_id'] ) && is_numeric( $_POST['webform_id'] )) ) {
            throw new EWZ_Exception( 'Missing or non-numeric webform_id' );
        } else {
            try {
                $webform = new Ewz_Webform( intval( $_POST['webform_id'] ) );
                $webform->delete( Ewz_Webform::DELETE_ITEMS );
                echo "1";
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    } else {
        echo "No deletion - authorization expired";
        error_log( "EWZ: ewz_del_webform_callback verify_nonce failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_del_webform', 'ewz_del_webform_callback' );

/**
 * Upload data - handle ajax call
 *
 * Called via the Submit button on the Upload page
 * Calling page uses XMLHttpRequest, shows any text other than '1' as an alert
 */
function ewz_upload_callback() { 
    require_once( EWZ_PLUGIN_DIR . 'includes/ewz-upload.php');

    if ( wp_verify_nonce( $_POST["ewzuploadnonce"], 'ewzupload' ) ) {
        ewz_wipe_buffers();
        // shortcode not defined within admin, need it here
        try {
            add_shortcode( 'ewz_show_webform', 'ewz_show_webform' );
            $errs = ewz_validate_and_upload();
            if( $errs ){
                echo $errs;
            } else {
                echo "1";
            }
        } catch (Exception $e) {
            echo "Upload error " . $e->getMessage();
        }
    } else {
        echo "No updates - page may have expired, or upload size may have been over the limit of " . ini_get( 'post_max_size' );
        error_log( "EWZ: ewz_upload_callback verify_nonce failed" );
    }
    exit();
}
add_action( 'wp_ajax_ewz_upload', 'ewz_upload_callback' );



/**
 * Reset Items Per Page - handle ajax call
 *
 * Called via the (items)Apply button on the List page
 * Javascript ajax handler displays the returned message always
 */
function ewz_set_ipp_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        ewz_wipe_buffers();
        try {
            $done = ewz_set_ipp();
            echo $done;
        } catch (Exception $e) {
            error_log( "EWZ: ewz_set_ipp_callback " . $e->getMessage() );
            echo $e->getMessage();
        }
    } else {
        error_log( "EWZ:  ewz_set_ipp_callback check_admin_referer failed" );
        echo "Insufficient permissions - may have expired";
    }
    exit();
}
add_action( 'wp_ajax_ewz_set_ipp', 'ewz_set_ipp_callback' );



/**
 * Process layout changes
 *
 * Do it via ajax so if it fails to validate, the data is not lost
 * Javascript ajax handler displays the message if it is not "1",
 *      otherwise reloads the page so we are sure what is displayed matches the database
 */
function ewz_layout_changes_callback() {
    if ( check_admin_referer( 'ewzadmin', 'ewznonce' ) ) {
        ewz_wipe_buffers();
        try {
            ewz_check_and_process_layouts();
            echo "1";
          } catch (Exception $e) {
            error_log( "EWZ: ewz_layout_changes_callback " . $e->getMessage() );
            echo  $e->getMessage();
          }
    } else {
        error_log( "EWZ: ewz_layout_changes_callback  check_admin_referer failed" );
        echo "Insufficient permissions - may have expired";
    }
    exit();
}
add_action( 'wp_ajax_ewz_layout_changes', 'ewz_layout_changes_callback' );

// delete the output buffers and turn off output buffering
function ewz_wipe_buffers(){
      while(ob_get_level() > 0)
       @ob_end_clean();                          
}
