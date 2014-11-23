<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-field-input.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");
require_once( EWZ_CUSTOM_DIR . "ewz-custom-data.php" );
/* Validation for the Data Management area of the Webforms page ( excluding CSV upload ) */
/* Processed in admin.php */

class Ewz_Webform_Data_Input extends Ewz_Input
{

    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $this->rules = array(
                             'ewzmode'          => array( 'type' => 'limited',   'req' => true,  'val' => array( 'list', 'spread', 'download', 'images','zdownload', 'zimages' ) ),
                             'ewznonce'         => array( 'type' => 'anonce',    'req' => true,  'val' => '' ),
                             '_wp_http_referer' => array( 'type' => 'to_string', 'req' => true,  'val' => '' ),
                             'page'             => array( 'type' => 'fixed',     'req' => true,  'val' => 'entrywizlist' ),
                             'webform_id'       => array( 'type' => 'to_seq',    'req' => true,  'val' => '' ),
                             'webform_num'      => array( 'type' => 'to_seq',    'req' => false, 'val' => '0' ),
                             'fopt'             => array( 'type' => 'v_fopts',   'req' => false, 'val' => array() ),
                             'copt'             => array( 'type' => 'v_copts',   'req' => false, 'val' => array() ),
                             'uploaddays'       => array( 'type' => 'to_seq',    'req' => false, 'val' => '0' ),
                             'action'           => array( 'type' => 'limited',   'req' => false, 'val' => array('ewz_gen_zipfile') ),
                             'archive_id'       => array( 'type' => 'to_string', 'req' => false, 'val' => '' ),
                             );
        $this->validate();
    }

   function validate(){

        parent::validate();
        if ( !array_key_exists( 'fopt', $this->input_data ) ) {
            $this->input_data['fopt'] = array();
        }
        if ( !array_key_exists( 'copt', $this->input_data ) ) {
            $this->input_data['copt'] = array();
        }
        return true;
   }

    // selected values for fields
     static function v_fopts( $value, $arg ){
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
                 throw new EWZ_Exception( "Invalid key for field option: '$key' " );
             }  
             if ( !( is_string( $val ) &&
                     preg_match( '/^[_a-zA-Z0-9\-\~\+\*\-]*$/', $val ) ) ){
                 throw new EWZ_Exception( "Invalid value for field option: '$val' ");
             }
         }  
         return true;
     }

     // selected values for custom variables
     static function v_copts( $value, $arg ){
         assert( is_array( $value ) || empty( $value ) );
         assert( isset( $arg ) );
         if( !is_array( $value ) ){
             throw new EWZ_Exception( 'Invalid custom field option value' );
         }
         foreach( $value as $key => $val ){
             if( !preg_match( '/^custom[0-9]$/', $key )  ){
                 throw new EWZ_Exception( "Invalid key for custom option: '$key' " );
             } 
             if( method_exists( 'Ewz_Custom_Data', 'selection_list' ) ){
                 $allowed = Ewz_Custom_Data::selection_list( $key );
                 if( !$allowed ){
                     throw new EWZ_Exception( "Selection not implemented for custom option $key " );
                 }  
                 if( !in_array( $val, $allowed ) && ( $val != '~*~' ) ){
                     throw new EWZ_Exception( "Invalid value for custom option $key: '$val' " );
                 } 
             } else {
                 throw new EWZ_Exception( "Function selection_list not implemented by Ewz_Custom_Data ( see documentation )" );
             } 
         }  
         return true;
     }

}

