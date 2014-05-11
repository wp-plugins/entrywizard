<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

/* Validation for the main form in the Item List page */

class Ewz_Show_List_Input extends Ewz_Input
{
    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $this->rules 
            = array(

                    '_wp_http_referer' => array( 'type' => 'to_string',  'req' => false, 'val' => '' ),
                    '_wpnonce'         => array( 'type' => 'to_string',  'req' => false, 'val' => '' ),  // for WP_List_Table
                    'ewzmode'          => array( 'type' => 'fixed',      'req' => true,  'val' => 'list' ),
                    'ewznonce'         => array( 'type' => 'anonce',     'req' => true,  'val' => '' ),
                    'page'             => array( 'type' => 'fixed',      'req' => true,  'val' => 'entrywizlist' ), 
                    'webform_id'       => array( 'type' => 'to_seq',     'req' => true,  'val' => '' ),
                    'fopt'             => array( 'type' => 'v_fopts',    'req' => false, 'val' => ''  ),
                    );

        $this->validate();          
    }
}
