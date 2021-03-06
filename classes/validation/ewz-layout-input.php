<?php

defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php" );
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-field-input.php" );
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php" );

/* Validation for the Layouts page */

class Ewz_Layout_Input extends Ewz_Input {

    function __construct( $form_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $customvars = array_keys( Ewz_Custom_Data::$data );
        $xcols = array_merge( array( 'att','aat','aae','aac', 'add','dlc', 'dtu', 'iid', 'wft', 
                                     'wid', 'wfm', 'nam', 'fnm', 'lnm', 'mnm', 'mem', 'mid', 'mli' ),
                             $customvars );
        
        $this->rules = array(
            'fields'        => array( 'type' => 'v_fields',       'req' => true,  'val' => '' ),
            'forder'        => array( 'type' => 'v_forder',       'req' => true,  'val' => '' ),
            'layout_id'     => array( 'type' => 'to_seq',         'req' => false, 'val' => '' ),
            'layout_name'   => array( 'type' => 'to_string',      'req' => true,  'val' => '' ),
            'max_num_items' => array( 'type' => 'to_seq',         'req' => true,  'val' => '' ),
            'override'      => array( 'type' => 'to_bool',        'req' => false, 'val' => '' ),
            'ewzmode'       => array( 'type' => 'fixed',          'req' => true,  'val' => 'layout' ),
            'ewznonce'      => array( 'type' => 'anonce',         'req' => true,  'val' => '' ),
            'restrictions'  => array( 'type' => 'v_restrictions', 'req' => false, 'val' => $form_data['fields'] ),
            'action'        => array( 'type' => 'fixed',          'req' => false, 'val' => 'ewz_layout_changes' ),
            '_wp_http_referer' => array( 'type' => 'to_string',   'req' => false, 'val' => '' ),
            'extra_cols'    => array( 'type' => 'v_extra_cols',   'req' => false, 'val' => $xcols ),
        );

        $this->validate();
    }

   function validate(){

        parent::validate();
        if ( !array_key_exists( 'override', $this->input_data ) ) {
            $this->input_data['override'] = 0;
        }
        return true;
   }

    //****** All v_.... functions must return true or raise an exception **************/
    function v_extra_cols( &$value, $arg ) {
        assert( is_array( $value ) );
        assert( is_array( $arg ) );

        if ( !is_array( $value ) ) {
            throw new EWZ_Exception( "Invalid format for extra columns" );
        }
        foreach ( array_keys( $value ) as $key ) {
            if ( !in_array( $key, $arg ) ) {
                throw new EWZ_Exception( "Invalid spreadsheet column type '$key'" );
            }
        }
        $used_cols = array();
        foreach ( $arg as $key ) {
            if ( isset( $value[$key] ) ) {
                if ( !( is_string( $value[$key] ) &&
                        preg_match( '/^\-?\d+$/', $value[$key] ) &&
                        ( int ) $value[$key] >= -1 &&
                        ( int ) $value[$key] <= 1000 ) ) {
                    throw new EWZ_Exception( 'Invalid spreadsheet column ' . $value[$key] );
                }
                $value[$key] = intval( $value[$key] );

                // make sure each ss column ( except -1 )  only assigned once
                if( $value[$key] >= 0  && isset( $used_cols[$value[$key]] ) ){
                    throw new EWZ_Exception( 'Spreadsheet column used twice: ' . ( $value[$key] + 1 ) );
                } 
            }
            $used_cols[$value[$key]] = 1;
        }
        return true;
    }

    function v_forder( $value, $arg ) {
        assert( is_array( $value ) );
        assert( isset( $arg ) );
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $nm ) {
                if ( !preg_match( '/^\d+$/', $key ) ) {
                    throw new EWZ_Exception( "Invalid key $key" );
                }
                if ( !( is_string( $nm ) &&
                        preg_match( '/^forder_f(\d)+_cX?(\d)+/', $nm ) ) ) {
                    throw new EWZ_Exception( "Invalid field sort order '$key' for $nm" );
                }
            }
        } else {
            throw new EWZ_Exception( "Bad input data for 'forder'" );
        }
        return true;
    }

    function v_fields( &$value, $arg ) {
        assert( is_array( $value ) );
        assert( isset( $arg ) );
        if ( is_array( $value ) ) {
            if( count( $value ) == 0 ){
                throw new EWZ_Exception( 'A Layout must have at least one field' );
            }    
            foreach ( $value as $key => $fld ) {
                $f = new Ewz_Field_Input( $fld );
                $value[$key] = $f->get_input_data();  // changing, cant use $fld on left
            }
        } else {
            throw new EWZ_Exception( 'Invalid value for field array' );
        }
        return true;
    }

    function v_restrictions( &$restrictions, $arg ) {
        assert( is_array( $restrictions ) );
        assert( is_array( $arg ) );
        foreach ( $restrictions as &$restr ) {
            foreach ( $restr as $key => $value ) {
                if ( $key == 'msg' ) {
                    if ( $value ) {
                        if ( !self::to_string( $restr[$key], '' ) ) {      // also html_entity_decodes the string
                            throw new EWZ_Exception( 'Invalid message for restriction' );
                        }
                    } else {
                        throw new EWZ_Exception( 'Missing message for restriction' );
                    }
                } else {
                    $okkey = 0;
                    $okval = in_array( "$value", array( '~*~', '~-~', '~+~' ) ) ? 1 : 0;
                    foreach ( $arg as $field_id => $field ) {
                        if ( $key == $field_id ) {
                            if( $field['field_ident'] == 'followupQ' ){
                                throw new EWZ_Exception( "Restrictions are not allowed on 'followup' fields");
                            } else {
                                $okkey = 1;
                            }
                            if ( !$okval && $field['field_type'] == 'opt' ) {
                                foreach ( $field['fdata']['options'] as $options ) {
                                    if ( $value == $options['value'] ) {
                                        $okval = 1;
                                    }
                                }
                            }
                        }
                    }
                    if ( !$okval || !$okkey ) {
                        throw new EWZ_Exception( "Invalid value for restriction on field '" . $field['field_header'] . "'" );
                    }
                }
            }
        }
        unset($restr);
        return true;
    }

}