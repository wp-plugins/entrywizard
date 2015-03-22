<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

/* Validation for the forms on Layouts page */

class Ewz_Layout_Set_Input extends Ewz_Input
{

     function __construct( $form_data ) {
         parent::__construct( $form_data );
         assert( is_array( $form_data ) );
       
         $this->rules = array(

                  '_wp_http_referer' => array( 'type' => 'to_string', 'req' => false, 'val' => '' ),
                  'ewzmode'        =>  array( 'type' => 'fixed',      'req' => true,  'val' => 'lset' ),
                  'action'         => array( 'type' => 'fixed',       'req' => true, 'val' => 'ewz_save_layout_order' ),
                  'ewznonce'       =>  array( 'type' => 'anonce',     'req' => true,  'val' => '' ),
                  'lorder'        =>  array( 'type' => 'v_order',   'req' => true,  'val' => '' ),
                  );
        $this->validate();
     }


    function v_order( $value, $arg ) {
        assert( is_array( $value ) );
        assert( isset( $arg ) );
        if ( is_array( $value ) ) {
            $seen = array();
            $count = count($value);
            foreach ( $value as $key => $nm ) {
                if ( !preg_match( '/^\d+$/', $key ) ) {
                    throw new EWZ_Exception( "Invalid key $key" );
                }
                if ( !preg_match( '/^\d+$/', $nm ) ) {
                    throw new EWZ_Exception( "Invalid order $nm" );
                }
                if( $nm < 0 || $nm >= $count ){
                    throw new EWZ_Exception( "Invalid value for order $nm" );
                } 
                if( isset($seen[$nm]) ){
                    throw new EWZ_Exception( "Duplicate value for order $nm" );
                }
                $seen[$nm] = true;
            }   
        } else {
            throw new EWZ_Exception( "Bad input data for layout order" );
        }
        return true;
     }
}
