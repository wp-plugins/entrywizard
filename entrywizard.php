<?php

/*
  Plugin Name: EntryWizard
  Plugin URI: http:
  Description:  Uploading by logged-in users of sets of image files and associated data. Administrators may download the images together with the data in spreadsheet form.
  Version: 1.2.9
  Author: Josie Stauffer
  Author URI:
  License: GPL2
*/
/*  Copyright 2012  Josie Stauffer  (email : )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

define( 'EWZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EWZ_CUSTOM_DIR', plugin_dir_path( __FILE__ ) );
define( 'EWZ_REQUIRED_WP_VERSION', '3.5' );
define( 'EWZ_REQUIRED_PHP_VERSION', '5.3.0' );

/*
 * INCLUDES
 */
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-setup.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-webform.php' );

if ( is_admin() ) {
    require_once( EWZ_PLUGIN_DIR . '/includes/ewz-admin.php' );
}

// this is needed for admin, too, because the ajax function runs as admin
require_once( EWZ_PLUGIN_DIR . '/includes/ewz-upload.php' );
require_once( EWZ_PLUGIN_DIR . '/includes/ewz-followup.php' );


/*
 * HOOKS
 */
register_activation_hook( __FILE__,   array( 'Ewz_Setup', 'activate_or_install_ewz' ) );
register_deactivation_hook( __FILE__, array( 'Ewz_Setup', 'deactivate_ewz' ) );
// no uninstall hook, use the uninstall.php file


/**
 * ACTIONS
 */
add_action('plugins_loaded', 'ewz_requires_version');   // first useable hook

// in the admin area we add another function after this, so make sure we know its priority
add_action( 'init', 'ewz_set_dev_env', 1 );

add_action( 'wp_enqueue_scripts', 'ewz_add_stylesheet' );

add_action( 'plugins_loaded', 'ewz_init_globals', 10 );

add_action( 'admin_init', 'ewz_check_for_db_updates' );
        
// Needed for cron job to auto-close webform
add_action( 'init', 'Ewz_Webform::schedule_close' );

// delete items when a user is deleted
add_action( 'deleted_user', 'Ewz_Item::delete_user_items' );

/*
 * SHORTCODES
 */

/* Set up the EWZShortcodes menu in tinyMCE */
function ewz_shortcode_menu() {
    // only hook up these filters if we're in the admin panel, and the current user has permission
    // to edit posts and pages, and has some entrywizard permissions
    if ( current_user_can( 'edit_posts' ) && current_user_can( 'edit_pages' ) && Ewz_Permission::can_see_webform_page() ) {
        add_filter( 'mce_buttons_2', 'ewz_mce_button' );
        add_filter( 'mce_external_plugins', 'ewz_mce_plugin' );

        foreach( array('post.php','post-new.php') as $hook ){
            add_action( "admin_head-$hook", 'ewz_admin_head' );
        }     
    }
}
add_action( 'admin_init',  'ewz_shortcode_menu' );

/* pass the data to javascript */
function ewz_admin_head(){
    global $all_text;
    $ewzdata = array();
    $ewzdata['webforms'] = array_values(array_filter( Ewz_Webform::get_all_webforms( 'can_view_webform' )));
     ?>
<script type='text/javascript'>
     var EWZdata =  <?php echo json_encode( $ewzdata ); ?>;
</script>
    <?php
}  


/* pass the button data to mce_buttons filter */
function ewz_mce_button( $buttons ) {
    assert( is_array( $buttons ) );

    array_push( $buttons,'ewz_shortcodes');
    return $buttons;
}
 
/* pass the javascript file name to mce_external_plugins filter */
function ewz_mce_plugin( $plugins ) {
    assert( is_array( $plugins ) );

    $plugins['ewz_shortcodes'] = plugins_url() . '/entrywizard/javascript/ewz-shortcodes.js';
    return $plugins;
}


if ( ! is_admin() ) { 
    // adding this for all admin pages triggers warnings for some themes and plugins
    // just add it specifically for the ajax calls when they are run in ewz-admin.php
    add_shortcode( 'ewz_show_webform', 'ewz_show_webform' );
    add_shortcode( 'ewz_followup', 'ewz_followup' );
}

/*
 * UPDATES
 */
function ewz_check_for_db_updates(){
    $data             = get_plugin_data( __FILE__, false, false );
    $this_version     = $data['Version'];
    $ewz_data_version = get_option( 'ewz_data_version', '0.9.1' );
    if ( version_compare( $ewz_data_version, $this_version,  '<' ) ){
        // 0.9.6 added new apply_prefix column to webforms table 
        // and changed 'min_img_area' to 'min_longest_dim' in fields/fdata
        if ( version_compare( $ewz_data_version, '0.9.6', '<' ) ){
            error_log( "EWZ: updating $ewz_data_version to 0.9.6" );
            Ewz_Setup::activate_or_install_ewz();
            delete_option( 'ewz_db_version' );
            Ewz_Field::change_min_area_to_dim();    
        }
        // 0.9.8 added attach_prefs to webform table
        if ( version_compare( $ewz_data_version, '0.9.8', '<' ) ){
            error_log( "EWZ: updating $ewz_data_version to 0.9.8" );
            Ewz_Setup::activate_or_install_ewz();
        }
        // 1.0.0 added upload_date to item table
        if ( version_compare( $ewz_data_version, '1.0.0', '<' ) ){
            error_log( "EWZ: updating $ewz_data_version to 1.0.0" );
            Ewz_Setup::activate_or_install_ewz();
            Ewz_Item::set_upload_date();
        }
        // 1.1.0 added num_items to webform table and override to layout table
        if ( version_compare( $ewz_data_version, '1.1.0', '<' ) ){
            error_log( "EWZ: updating $ewz_data_version to 1.1.0" );
            Ewz_Setup::activate_or_install_ewz();
            Ewz_Webform::set_num_items();
        }
        // 1.2.0 added append to fields table
        if ( version_compare( $ewz_data_version, '1.2.0', '<' ) ){
            error_log( "EWZ: updating $ewz_data_version to 1.2.0" );
            Ewz_Setup::activate_or_install_ewz();
        }
        // 1.2.3 added gen_fname to webforms table
        if ( version_compare( $ewz_data_version, '1.2.3', '<' ) ){
            error_log( "EWZ: updating $ewz_data_version to 1.2.3" );
            Ewz_Setup::activate_or_install_ewz();
        }
        // 1.2.4 htaccess and index file in EWZ_IMG_UPLOAD_DIR
        if ( version_compare( $ewz_data_version, '1.2.4', '<' ) ){
            error_log( "EWZ: updating $ewz_data_version to 1.2.4" );
            Ewz_Setup::protect_uploads();
        }       
        update_option( 'ewz_data_version', $this_version );
    }
}


function ewz_add_stylesheet() {
    wp_register_style( 'ewz-style', plugins_url( 'styles/entrywizard.css', __FILE__ ) );
    wp_enqueue_style( 'ewz-style' );

    wp_enqueue_script(
                       'ewz-upload',
                       plugins_url( 'javascript/ewz-upload.js', __FILE__ ),
                       array( 'jquery', 'jquery-form' ),
                       false,
                       true      // in footer, so $ewzG has been defined
                      );
    wp_enqueue_script(
                       'ewz-followup',
                       plugins_url( 'javascript/ewz-followup.js', __FILE__ ),
                       array( 'jquery', 'jquery-form' ),
                       false,
                       true      // in footer, so $ewzG has been defined
                      );
 }

function ewz_set_dev_env(){
    if ( is_file( plugin_dir_path( __FILE__ ). 'DEVE_ENV' )   // only true in development environment
        && ! ( isset( $_POST['action'] ) && ( 'heartbeat' == $_POST['action'] )  )
        && ( '/rhcc_site/wp-cron.php' !== $_SERVER['REQUEST_URI'] ) 
      ){   
        /*
         * ASSERT OPTIONS
         */
        assert_options( ASSERT_ACTIVE, 1 );
        assert_options( ASSERT_BAIL, false );
        assert_options( ASSERT_WARNING, false );
        function ewz_assert_handler( $file, $line, $code )
        {
            // no assert
            error_log( "EWZ: Assertion Failed at $file: $line: $code" );
        }
        assert_options( ASSERT_CALLBACK, 'ewz_assert_handler' );
        define( 'EWZ_DBG', true );
        $is_admin = is_admin() ? 'ADMIN' : '';
        error_log( "EWZ: ~~~~~~~~ Starting entrywizard.php  $is_admin ~~~~~~~ \n"
                   . 'URI:' . $_SERVER['REQUEST_URI'] . "\n" 
                   . 'GET:   ' . print_r( $_GET, true )
                   . 'POST:  ' . print_r( $_POST, true )
                   . 'FILES: ' . print_r( $_FILES, true )
                  );
    } else {
        define( 'EWZ_DBG', false );
        assert_options( ASSERT_ACTIVE, 0 );
    }
}


function ewz_init_globals(){
    global $wpdb;

    load_plugin_textdomain(
                            'entrywiz', false,
                            basename( dirname( __FILE__ ) ) . '/languages'
                           );

    define( 'EWZ_PREFIX', $wpdb->prefix );

    define( 'EWZ_LAYOUT_TABLE',  EWZ_PREFIX . 'ewz_layout' );
    define( 'EWZ_FIELD_TABLE',   EWZ_PREFIX . 'ewz_field' );
    define( 'EWZ_WEBFORM_TABLE', EWZ_PREFIX . 'ewz_webform' );
    define( 'EWZ_ITEM_TABLE',    EWZ_PREFIX . 'ewz_item' );

    $uploads_dir = wp_upload_dir();
    define( 'EWZ_IMG_UPLOAD_DIR', $uploads_dir['basedir'] . '/ewz_img_uploads' );
    define( 'EWZ_IMG_UPLOAD_URL', $uploads_dir['baseurl'] . '/ewz_img_uploads' );
    define( 'EWZ_IMG_UPLOAD_SUBDIR', 'ewz_img_uploads' );


    define( 'EWZ_DEFAULT_DIM',  1280 );           // default max image dimension in pixels
    define( 'EWZ_DEFAULT_MIN_LONGEST',  100 );    // default minimum longest image dimension in pixels
    define( 'EWZ_MAX_STRING_LEN', 50 );           // default max length of text input field
    define( 'EWZ_MAX_FIELD_WIDTH', 10 );          // default max field width of text input field

    define( 'EWZ_FILE_DOWNLOAD_TIME', 30 );       // number of seconds to add to max time allowance for every 25 files
    define( 'UPLOAD_ERR_EMPTY',   5 );            // error if uploaded file is empty -- see includes/ewz-upload.php


    $maxnumitems = ini_get( 'max_file_uploads' );
    if ( ! $maxnumitems ){
        $maxnumitems = 50;
    }
    $maxfilesize = ini_get( 'upload_max_filesize' );
    if ( ! $maxfilesize ){
        $maxfilesize = '5M';
    }
    $postmaxsize = ini_get( 'post_max_size' );
    if ( ! $postmaxsize ){
        $postmaxsize = '100M';
    }


    define( 'EWZ_MAX_NUMITEMS', (int) $maxnumitems );
    define( 'EWZ_MAX_TOTAL_MB', ewz_to_mb( $postmaxsize ) );      // default max total post size in MB
    define( 'EWZ_MAX_SIZE_MB',  ewz_to_mb( $maxfilesize ) );      // default max upload image size in MB
    define( 'EWZ_MAX_SIZE_BYTES', ewz_to_bytes( $maxfilesize ) ); // default max upload image size in bytes

}


/**
 * Transform a string like 100G or 50K to megabytes
 *
 * Works for an integer followed by 'm', 'mb', 'g','gb', 'k', 'kb'
 * or the same in upper case
 *
 * @param   $sizestring
 * @return  integer
 */
function ewz_to_mb( $sizestring )
{
    assert( is_string( $sizestring ) );
    assert( preg_match( '/^(\d+)[mMgGkK][bB]?\s*$/', $sizestring ) );

    $val = strtolower( trim( $sizestring ) );
    $mat = array();
    preg_match( '/^(\d+)(\D+)/', $val, $mat );
    assert( 3 == count( $mat ) );
    $num  = $mat[1];
    $code = $mat[2];

    if ( preg_match( '/mb?/', $code ) ) {
        return ( int )$num;
    }
    if ( preg_match( '/gb?/', $code ) ) {
        return ( int )($num * 1024);
    }
    if ( preg_match( '/kb?/', $code ) ) {
        return ( int )($num / 1024);
    }
    return 0;
}


/**
 * Transform a string like 100G or 50K to bytes
 *
 * Works for an integer followed by 'm', 'mb', 'g','gb', 'k', 'kb'
 * or the same in upper case
 *
 * @param   $sizestring
 * @return  integer
 */
function ewz_to_bytes( $sizestring )
{
    assert( is_string( $sizestring ) );
    assert( preg_match( '/^(\d+)[mMgGkK][bB]?\s*$/', $sizestring ) );

    $val = strtolower( trim( $sizestring ) );
    $mat = array();
    preg_match( '/^(\d+)(\D+)/', $val, $mat );
    assert( 3 == count( $mat ) );
    $num  = $mat[1];
    $code = $mat[2];

    if ( preg_match( '/mb?/', $code ) ) {
        return $num * 1024;
    }
    if ( preg_match( '/gb?/', $code ) ) {
        return $num * 1048576;
    }
    if ( preg_match( '/kb?/', $code ) ) {
        return $num;
    }
    return 0;
}

/**
 *
 * Warn if required versions of WP and PHP not available
 *
 */
function ewz_requires_version(){
   $plugin = plugin_basename( EWZ_PLUGIN_DIR . '/entrywizard.php' );
   $reqvers = ewz_required_versions_warning();
   if ( $reqvers ) {
       add_action( 'admin_notices', 'ewz_admin_notice' );
   }  
}
   
function ewz_required_versions_warning(){
   global $wp_version;
   $reqvers = '';

   // version_compare returns -1 if left is lower than right, 0 if they are equal, 1 if right is lower than left
   if ( version_compare( EWZ_REQUIRED_WP_VERSION, $wp_version ) == 1 ) {
      $reqvers = 'WordPress version ' . EWZ_REQUIRED_WP_VERSION;
      error_log("EWZ: wordpress version not supported");
   }
   if ( version_compare( EWZ_REQUIRED_PHP_VERSION, PHP_VERSION ) == 1 ) {
       error_log("EWZ: php version not supported");
      if( $reqvers ){
            $reqvers .= " and ";
      }
      $reqvers .= 'PHP version ' . EWZ_REQUIRED_PHP_VERSION;
   }
   return $reqvers;
}

function ewz_admin_notice() {
    $msg = ewz_required_versions_warning();
    print "<div class='error'><h2>EntryWizard Warning</h2> EntryWizard requires at least <strong>$msg </strong></h2></div>";
}


/* * **************************************
 * Utility to output data structures in debug.log
 *
 * For debugging purposes
 * @param string $str descriptive string
 * @param mixed $obj object to be dumped
 * @return void
 * */

function ewzdbg( $str, $in_obj = null )
{
    if ( defined( 'EWZ_DBG' ) && EWZ_DBG ){
        // no assert
        if ( $in_obj !== null ) {
            error_log(  "EWZ_DBG object $str: " . var_export( $in_obj, true ) );
            return;
        } else {
            error_log( 'EWZ_DBG variable: ' .  var_export( $str, true ) );
            return;
        }
    }
}

