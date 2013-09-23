<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

/* Validation for the main form in the Item List page */

class Ewz_Item_List_Input extends Ewz_Input
{
    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        if( 'ewz_admin_del_items' == $form_data['action'] || 'ewz_admin_del_items' == $form_data['action2'] ){

        $this->rules 
            = array(

                    '_wp_http_referer' => array( 'type' => 'string',     'req' => false, 'val' => '' ),
                    '_wpnonce'         => array( 'type' => 'string',     'req' => false, 'val' => '' ),  // for WP_List_Table
                    'action'           => array( 'type' => 'limited',    'req' => true,  'val' => array('ewz_admin_del_items', '-1' ) ),
                    'action2'          => array( 'type' => 'limited',    'req' => true,  'val' => array('ewz_admin_del_items', '-1' ) ),
                    'ewz_check'        => array( 'type' => 'int_arr',    'req' => true,  'val' => array( 1, 1000 ) ), // selected rows
                    'ewzmode'          => array( 'type' => 'fixed',      'req' => true,  'val' => 'listpage' ),
                    'ewz_page_sel'     => array( 'type' => 'seq',        'req' => false, 'val' => '' ),  // page for attaching
                    'ewz_ipp_apply'    => array( 'type' => 'fixed',      'req' => false, 'val' => 'Apply'),
                    'ewznonce'         => array( 'type' => 'anonce',     'req' => true,  'val' => '' ),
                    'ifield'           => array( 'type' => 'seq',        'req' => false, 'val' => '' ),  // image column for attach, if more than one
                    'img_comment'      => array( 'type' => 'bool',       'req' => false, 'val' => '' ),  // are comments allowed on images being attached?
                    'img_size'         => array( 'type' => 'limited',    'req' => false, 'val' => ewz_get_img_sizes() ), // copy image when attaching
                    'page'             => array( 'type' => 'fixed',      'req' => true,  'val' => 'entrywizlist' ), 
                    'paged'            => array( 'type' => 'seq',        'req' => true,  'val' => '' ), // pagination
                    'webform_id'       => array( 'type' => 'seq',        'req' => true,  'val' => '' ),
                    'ewz_items_per_page'=> array( 'type' => 'seq',       'req' => false, 'val' => '0' ),
                    );

        }  elseif( $form_data['action'] == 'ewz_attach_imgs' ){
        $this->rules 
            = array(

                    '_wp_http_referer' => array( 'type' => 'string',     'req' => false, 'val' => '' ),
                    '_wpnonce'         => array( 'type' => 'string',     'req' => false, 'val' => '' ),  // for WP_List_Table
                    'action'           => array( 'type' => 'limited',    'req' => true,  'val' => array( 'ewz_attach_imgs', '-1' ) ),
                    'action2'          => array( 'type' => 'limited',    'req' => true,  'val' => array( 'ewz_attach_imgs', '-1' ) ),
                    'ewz_check'        => array( 'type' => 'int_arr',    'req' => true,  'val' => array( 1, 1000 ) ), // selected rows
                    'ewzmode'          => array( 'type' => 'fixed',      'req' => true,  'val' => 'listpage' ),
                    'ewz_page_sel'     => array( 'type' => 'seq',        'req' => true,  'val' => '' ),  // page for attaching
                    'ewz_ipp_apply'    => array( 'type' => 'fixed',      'req' => false, 'val' => 'Apply'),
                    'ewznonce'         => array( 'type' => 'anonce',     'req' => true,  'val' => '' ),
                    'ifield'           => array( 'type' => 'seq',        'req' => true,  'val' => '' ),  // image column for attach, if more than one
                    'img_comment'      => array( 'type' => 'bool',       'req' => false, 'val' => '' ),  // are comments allowed on images being attached?
                    'img_size'         => array( 'type' => 'limited',    'req' => true,  'val' => ewz_get_img_sizes() ), // copy image when attaching
                    'page'             => array( 'type' => 'fixed',      'req' => true,  'val' => 'entrywizlist' ), 
                    'paged'            => array( 'type' => 'seq',        'req' => true,  'val' => '' ), // pagination
                    'webform_id'       => array( 'type' => 'seq',        'req' => true,  'val' => '' ),
                    'ewz_items_per_page'=> array( 'type' => 'seq',       'req' => false, 'val' => '0' ),
                    );

        }  else {
        $this->rules 
            = array(

                    '_wp_http_referer' => array( 'type' => 'string',     'req' => false, 'val' => '' ),
                    '_wpnonce'         => array( 'type' => 'string',     'req' => false, 'val' => '' ),  // for WP_List_Table
                    'action'           => array( 'type' => 'fixed',      'req' => true,  'val' =>  '-1' ),
                    'action2'          => array( 'type' => 'fixed',      'req' => true,  'val' =>  '-1' ),
                    'ewz_check'        => array( 'type' => 'int_arr',    'req' => false, 'val' => array( 1, 1000 ) ), // selected rows
                    'ewzmode'          => array( 'type' => 'fixed',      'req' => true,  'val' => 'listpage' ),
                    'ewz_page_sel'     => array( 'type' => 'seq',        'req' => false, 'val' => '' ),  // page for attaching
                    'ewz_ipp_apply'    => array( 'type' => 'fixed',      'req' => false, 'val' => 'Apply'),
                    'ewznonce'         => array( 'type' => 'anonce',     'req' => true,  'val' => '' ),
                    'ifield'           => array( 'type' => 'seq',        'req' => false, 'val' => '' ),  // image column for attach, if more than one
                    'img_comment'      => array( 'type' => 'bool',       'req' => false, 'val' => '' ),  // are comments allowed on images being attached?
                    'img_size'         => array( 'type' => 'limited',    'req' => false,  'val' => ewz_get_img_sizes() ), // copy image when attaching
                    'page'             => array( 'type' => 'fixed',      'req' => true,  'val' => 'entrywizlist' ), 
                    'paged'            => array( 'type' => 'seq',        'req' => true,  'val' => '' ), // pagination
                    'webform_id'       => array( 'type' => 'seq',        'req' => true,  'val' => '' ),
                    'ewz_items_per_page'=> array( 'type' => 'seq',       'req' => false, 'val' => '0' ),
                    );

        }
        $this->validate();          
    }

     function validate( ){
         parent::validate();
	// an unchecked checkbox does not create any matching value in $_POST
	if ( !array_key_exists( 'img_comment', $this->input_data ) ) {
	    $this->input_data['img_comment'] = false;
	}
        return true;
     }
}
