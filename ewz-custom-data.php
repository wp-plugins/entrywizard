<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");
add_action('init', 'ewz_add_custom_class', 20);

function ewz_add_custom_class(){
    if ( !class_exists( 'Ewz_Custom_Data' ) ){

        class Ewz_Custom_Data
        {
            public static $data = array();

            public function  __construct( $user_id ){
            }
               
            public static function selection_list( $item ){
            }
        }
    }
}