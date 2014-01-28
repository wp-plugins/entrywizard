<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

/* Validation for the main form in the Item List page */

class Ewz_Item_List_Input extends Ewz_Input
{
    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $act = '';
        if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ){
            $act = $_REQUEST['action'];
        } else {
            if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ){
                $act = $_REQUEST['action2'];
            }
        }

        $is_del = ( 'ewz_admin_del_items' ==  $act );
        $is_att = ( 'ewz_attach_imgs' == $act );
        $this->rules 
            = array(
                    'webform_id'   => array( 'type' => 'seq',       'req' => true,    'val' => ''            ),
                    'ewznonce'     => array( 'type' => 'anonce',    'req' => true,   'val' => ''            ), 
                    'fopt'         => array( 'type' => 'v_fopts',   'req' => false,   'val' => array()       ),
                    '_wpnonce'     => array( 'type' => 'string',    'req' => false,   'val' => ''            ),  // for WP_List_Table
                    'action'       => array( 'type' => 'limited',   'req' => false,   'val' => array('ewz_attach_imgs',
                                                                                                     'ewz_set_ipp',
                                                                                                     'ewz_batch_delete', 
                                                                                                     '-1' ) ),
                    'action2'      => array( 'type' => 'limited',   'req' => false,   'val' => array('ewz_attach_imgs',
                                                                                                     'ewz_set_ipp',
                                                                                                     'ewz_batch_delete', 
                                                                                                     '-1' ) ),
                    'ewz_check'    => array( 'type' => 'int_arr',   'req' => $is_del, 'val' => array( 1, 1000 ) ), // selected rows
                    'ewz_ipp'      => array( 'type' => 'seq',        'req' => false,   'val' => '0'              ),
                    'ewzmode'      => array( 'type' => 'limited',   'req' => false,   'val' => array('list')    ),  // needed to override other modes in webform
                    'ewz_page_sel' => array( 'type' => 'seq',       'req' => $is_att, 'val' => ''               ),  // page for attaching
                    'ifield'       => array( 'type' => 'seq',       'req' => false,   'val' => ''               ),  // img col for attach, if more than one
                    'img_comment'  => array( 'type' => 'bool',      'req' => false,   'val' => ''               ),  // comments allowed on imgs being attached?
                    'img_size'     => array( 'type' => 'limited',   'req' => $is_att, 'val' => ewz_get_img_sizes() ), // copy image when attaching
                    'page'         => array( 'type' => 'fixed',     'req' => false,    'val' => 'entrywizlist' ), 
                    'paged'        => array( 'type' => 'seq',       'req' => false,   'val' => ''               ),  // pagination
                    'uploaddays'   => array( 'type' => 'seq',       'req' => false,   'val' => '0'              ),
                    '_wp_http_referer' => array( 'type' => 'string', 'req' => false,  'val' => ''               ),
                    );
        $this->validate();          
    }

    function v_fopts( $value, $arg ){
         assert( is_array( $value ) || empty( $value ) );
         assert( isset( $arg ) );
        return Ewz_Webform_Data_Input::v_fopts( $value, $arg );
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
