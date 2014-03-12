<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");
 
class Ewz_Item_List_IPP_Input extends Ewz_Input
{
    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $this->rules =  array(
                    'ewznonce'            => array( 'type' => 'anonce',      'req' => true,  'val' => '' ),
                    '_wp_http_referer'    => array( 'type' => 'string',      'req' => false, 'val' => '' ),
                    'ewz_ipp'             => array( 'type' => 'seq',         'req' => true,  'val' => '' ), 
                    'action'              => array( 'type' => 'fixed',       'req' => true,  'val' => 'ewz_set_ipp' ), 
                              );        
        $this->validate();
    }       
}