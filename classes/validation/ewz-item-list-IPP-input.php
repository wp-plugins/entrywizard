<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");
 
class Ewz_Item_List_IPP_Input extends Ewz_Input
{

    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $this->rules =  array(
                    'ewzmode'             => array( 'type' => 'fixed',       'req' => true,  'val' => 'ipp'),
                    'ewznonce'            => array( 'type' => 'anonce',      'req' => true,  'val' => '' ),
                    '_wp_http_referer'    => array( 'type' => 'string',      'req' => false, 'val' => '' ),
                    'ewz_items_per_page'  => array( 'type' => 'seq',         'req' => true,  'val' => '' ), 
                    'ewz-ipp-apply'       => array( 'type' => 'fixed',       'req' => false,  'val' => '' ),
                    'page'                => array( 'type' => 'fixed',       'req' => true,  'val' => 'entrywizlist' ),
                    'webform_id'          => array( 'type' => 'seq',         'req' => true,  'val' => '' ), 
                              );
        
        $this->validate();
    }       
}