<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-permission.php" );
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php" );

/* Validation for the Permissions page */

class Ewz_Permission_Input extends Ewz_Input
{

    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $this->rules = array(
                             'ewznonce'              => array( 'type' => 'anonce', 'req' => true,  'val' => '' ),
                             'ewzmode'               => array( 'type' => 'fixed',  'req' => true,  'val' => 'permission' ),
                             '_wp_http_referer'      => array( 'type' => 'to_string', 'req' => false, 'val' => '' ),
                             'ewz_user_perm'         => array( 'type' => 'to_seq',   'req' => false, 'val' => '' ), 
                             'ewz_have_perm'         => array( 'type' => 'to_seq',   'req' => false, 'val' => '' ), 
                             'ewz_can_edit_layout'   => array( 'type' => 'int1_arr',   'req' => false, 'val' =>  array(1, 500 )), 
                             'ewz_can_assign_layout' => array( 'type' => 'int1_arr',   'req' => false, 'val' =>  array(1, 500 )),  
                             'ewz_can_edit_webform'  => array( 'type' => 'int1_arr',   'req' => false, 'val' => array(1, 500 ) ), 
                             'ewz_can_manage_webform'     => array( 'type' => 'int1_arr',   'req' => false, 'val' => array(1, 500 ) ), 
                             'ewz_can_manage_webform_L'   => array( 'type' => 'int1_arr',   'req' => false, 'val' => array(1, 500 ) ), 
                             'ewz_can_download_webform'   => array( 'type' => 'int1_arr',   'req' => false, 'val' => array(1, 500 ) ), 
                             'ewz_can_download_webform_L' => array( 'type' => 'int1_arr',   'req' => false, 'val' => array(1, 500 ) ), 
                             );
        $this->validate();
    }
}