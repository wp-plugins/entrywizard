<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-field-input.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

/* Validation for the Data Management area of the Webforms page ( excluding CSV upload ) */
/* Processed in admin.php */

class Ewz_Webform_Data_Input extends Ewz_Input
{

    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $this->rules = array(
                             'ewzmode'          => array( 'type' => 'limited', 'req' => true,  'val' => array( 'list', 'spread', 'download', 'images' ) ),
                             'ewznonce'         => array( 'type' => 'anonce',  'req' => true,  'val' => '' ),
                             '_wp_http_referer' => array( 'type' => 'string',  'req' => true,  'val' => '' ),
                             'page'             => array( 'type' => 'fixed',   'req' => true,  'val' => 'entrywizlist' ),
                             'webform_id'       => array( 'type' => 'seq',     'req' => true,  'val' => '' ),
                             'webform_num'      => array( 'type' => 'seq',     'req' => false, 'val' => '0' ),
                             'fopt'             => array( 'type' => 'v_fopts', 'req' => false, 'val' => '' ),
                             'uploaddays'       => array( 'type' => 'seq',     'req' => false, 'val' => '0' ),
                             );
        $this->validate();
    }


     function v_fopts( $value, $arg ){
         assert( is_array( $value ) || empty( $value ) );
         assert( isset( $arg ) );
         if( !is_array( $value ) ){
             throw new EWZ_Exception( 'Invalid fopt value' );
         }
         if( count( $value ) > 50 ){
             throw new EWZ_Exception( 'Invalid fopt value' );
         }
         foreach( $value as $key => $val ){
             if( !preg_match( self::REGEX_SEQ, $key )  ){
                 throw new EWZ_Exception( "Invalid key '$key' for field option" );
             }  
             if ( !( is_string( $val ) &&
                     preg_match( '/^[_a-zA-Z0-9\-\~\+\*\-]*$/', $val ) ) ){
                 throw new EWZ_Exception( "Invalid value '$val' for field option");
             }
         }  
         return true;
     }

}

