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
        if ( isset( $form_data['action'] ) && -1 != $form_data['action'] ){
            $act = $form_data['action'];
        } else {
            if ( isset( $form_data['action2'] ) && -1 != $form_data['action2'] ){
                $act = $form_data['action2'];
            }
        }

        $is_del = ( 'ewz_admin_del_items' ==  $act );
        $is_att = ( 'ewz_attach_imgs' == $act );
        $this->rules 
            = array(
                    'webform_id'   => array( 'type' => 'to_seq',    'req' => true,    'val' => ''            ),
                    'ewznonce'     => array( 'type' => 'anonce',    'req' => true,   'val' => ''            ), 
                    'fopt'         => array( 'type' => 'v_fopts',   'req' => false,   'val' => array()       ),
                    'copt'         => array( 'type' => 'v_copts',   'req' => false,   'val' => array()       ),
                    '_wpnonce'     => array( 'type' => 'to_string', 'req' => false,   'val' => ''            ),  // for WP_List_Table
                    'action'       => array( 'type' => 'limited',   'req' => false,   'val' => array('ewz_attach_imgs',
                                                                                                     'ewz_set_ipp',
                                                                                                     'ewz_batch_delete', 
                                                                                                     '-1' ) ),
                    'action2'      => array( 'type' => 'limited',   'req' => false,   'val' => array('ewz_attach_imgs',
                                                                                                     'ewz_set_ipp',
                                                                                                     'ewz_batch_delete', 
                                                                                                     '-1' ) ),
                    'ewz_check'    => array( 'type' => 'int_arr',   'req' => $is_del, 'val' => array( 1, 1000 ) ), // selected rows
                    'ewz_ipp'      => array( 'type' => 'to_seq',    'req' => false,   'val' => '0'              ),
                    'ewzmode'      => array( 'type' => 'limited',   'req' => false,   'val' => array('list')    ),  // needed to override other modes in webform
                    'ewz_page_sel' => array( 'type' => 'to_seq',    'req' => $is_att, 'val' => ''               ),  // page for attaching
                    'ifield'       => array( 'type' => 'to_seq',    'req' => false,   'val' => ''               ),  // img col for attach, if more than one
                    'dups_ok'      => array( 'type' => 'to_bool',   'req' => false,   'val' => ''               ),  // allow images to be attached twice
                    'img_comment'  => array( 'type' => 'to_bool',   'req' => false,   'val' => ''               ),  // comments allowed on imgs being attached?
                    'img_size'     => array( 'type' => 'limited',   'req' => $is_att, 'val' => ewz_get_img_sizes() ), // copy image when attaching
                    'orderby'      => array( 'type' => 'to_seq',    'req' => false,   'val' => ''               ),  // column to order by
                    'order'        => array( 'type' => 'limited',   'req' => false,   'val' => array( 'asc','desc' ) ),
                    'page'         => array( 'type' => 'fixed',     'req' => false,    'val' => 'entrywizlist' ), 
                    'paged'        => array( 'type' => 'to_seq',    'req' => false,   'val' => ''               ),  // pagination
                    'uploaddays'   => array( 'type' => 'to_seq',     'req' => false,   'val' => '0'              ),
                    '_wp_http_referer' => array( 'type' => 'to_string', 'req' => false,  'val' => ''               ),
                    );
        $this->validate();          
    }

   function validate(){

        parent::validate();
        // an unchecked checkbox does not create any matching value in $_POST
        if ( !array_key_exists( 'img_comment', $this->input_data ) ) {
            $this->input_data['img_comment'] = false;
        }
        if ( !array_key_exists( 'dups_ok', $this->input_data ) ) {
            $this->input_data['dups_ok'] = false;
        }
        if ( !array_key_exists( 'fopt', $this->input_data ) ) {
            $this->fopt = array();
        }
        if ( !array_key_exists( 'copt', $this->input_data ) ) {
            $this->copt = array();
        }
        return true;
   }

    function v_fopts( $value, $arg ){
         assert( is_array( $value ) || empty( $value ) );
         assert( isset( $arg ) );
        return Ewz_Webform_Data_Input::v_fopts( $value, $arg );
    }
    function v_copts( $value, $arg ){
         assert( is_array( $value ) || empty( $value ) );
         assert( isset( $arg ) );
        return Ewz_Webform_Data_Input::v_copts( $value, $arg );
    }

}
