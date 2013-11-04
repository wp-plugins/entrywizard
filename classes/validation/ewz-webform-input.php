<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-webform.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

/* Validation for the forms on Webforms page */

class Ewz_Webform_Input extends Ewz_Input
{

     function __construct( $form_data ) {
         parent::__construct( $form_data );
         assert( is_array( $form_data ) );
         $this->rules = array(

                  '_wp_http_referer' => array( 'type' => 'string',     'req' => false, 'val' => '' ),
                  'ewzmode'        =>  array( 'type' => 'fixed',  'req' => true,  'val' => 'webform' ),
                  'ewznonce'       =>  array( 'type' => 'anonce', 'req' => true,  'val' => '' ),
                  'layout_id'      =>  array( 'type' => 'seq',    'req' => true,  'val' => '' ),
                  'page'           =>  array( 'type' => 'fixed',  'req' => true,  'val' => 'entrywizard' ),
                  'prefix'         =>  array( 'type' => 'v_prefix', 'req' => false,  'val' => '' ),
                  'apply_prefix'   =>  array( 'type' => 'bool',   'req' => false,  'val' => '' ),
                  'upload_open'    =>  array( 'type' => 'bool',   'req' => false,  'val' => '' ),
                  'o_user'         =>  array( 'type' => 'v_users', 'req' => false,  'val' => '' ),
                  'webform_id'     =>  array( 'type' => 'seq',    'req' => false,  'val' => '' ),
                  'webform_ident'  =>  array( 'type' => 'ident',  'req' => true,  'val' => '' ),
                  'webform_title'  =>  array( 'type' => 'string', 'req' => true,  'val' => '' ),
                  'openwebform'    =>  array( 'type' => 'seq',    'req' => false,  'val' => '' ),
                  );
        $this->validate();
     }

     function validate( ){
         parent::validate();

        // an unchecked checkbox does not create any matching value in $_POST
        if ( !array_key_exists( 'upload_open', $this->input_data ) ) {
            $this->input_data['upload_open'] = false;
        }
        if ( !array_key_exists( 'apply_prefix', $this->input_data ) ) {
            $this->input_data['apply_prefix'] = false;
        }
        return true;
     }

     //****** All v_.... functions must return a boolean or raise an exception **************/

     function v_prefix( $value, $arg ){
         assert( is_string( $value ) || empty( $value ) );
         assert( $arg == '' );
         if( !is_string( $value ) && preg_match( '/^[\[\]A-Z0-9~\-_]*$/i', $value ) ){
             throw new EWZ_Exception( 'Bad input for prefix' );
         }

         return true;
     }

     function v_users( $value, $arg ){
         assert( is_array( $value ) || empty( $value ) );
         assert( $arg == '' );
         if( !is_array( $value ) ){
             throw new EWZ_Exception( 'Bad input for user' );
         }
         foreach( $value as $key => $uid ){
             if( !$this->seq( $value[$key], $arg ) ){   // seq potentially changes first arg
                 throw new EWZ_Exception( "Bad value '$uid' for user" );
             }
         }
         return true;
     }
}