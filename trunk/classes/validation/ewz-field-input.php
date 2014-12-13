<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php");
require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

/* Validation for the Layouts page */

class Ewz_Field_Input extends Ewz_Input
{

    function __construct( $form_data ) { 
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        $this->rules = array(
                             'field_id'       => array( 'type' => 'to_seq',      'req' => false, 'val' => '' ),
                             'field_header'   => array( 'type' => 'to_string',   'req' => true,  'val' => '' ),
                             'field_type'     => array( 'type' => 'limited',     'req' => true,  'val' => array( 'img', 'str', 'opt', 'rad', 'chk' ) ),
                             'field_ident'    => array( 'type' => 'ident',       'req' => true,  'val' => '' ),
                             'fdata'          => array( 'type' => 'v_fdata',     'req' => true,  'val' => '' ),
                             'ss_column'      => array( 'type' => 'to_int1',     'req' => false, 'val' => '' ),
                             'required'       => array( 'type' => 'to_bool',     'req' => false, 'val' => '' ),
                             'append'         => array( 'type' => 'to_bool',     'req' => false, 'val' => '' ),
                             );
        $this->validate();
    }

    function validate(){
        parent::validate();

        if ( !array_key_exists( 'required', $this->input_data ) ) {
            $this->input_data['required'] = false;
        }
        if ( !array_key_exists( 'append', $this->input_data ) ) {
            $this->input_data['append'] = false;
        }
        if( 'img' ==  $this->input_data['field_type'] ){
            if( !array_key_exists( 'canrotate', $this->input_data['fdata'] ) || !$this->input_data['fdata']['canrotate'] ){
                $this->input_data['fdata']['canrotate'] = false;
            } 
        }
        return true;
   }

    //****** All v_.... functions must return a boolean or raise an exception **************/
    function v_fdata( &$fdata ){
        assert( is_array( $fdata ) );

        switch ( $this->input_data['field_type'] ) {
        case 'opt':
            self::valid_opt_input( $fdata );
            break;
        case 'str':
            self::valid_str_input( $fdata );
            break;
        case 'img':
            self::valid_img_input( $fdata );
            break;
        case 'rad':
            break;
        case 'chk':
            break;
        default:   throw new EWZ_Exception( 'Invalid value for field type: ' . $this->input_data['field_type'] );
        }

        return true;
    }

    /**
     * Validate string field input
     *
     * @param  array  $field     input string field to check
     * @return string $bad_data  comma-separated list of bad data
     */
    private static function valid_str_input( &$field )
    {
        assert( is_array( $field ) );

        $req_field_data = array('maxstringchars');
        $all_field_data = array('maxstringchars', 'fieldwidth', 'ss_col_fmt');
        foreach ( $req_field_data as $req ) {
            if ( !array_key_exists( $req, $field )|| preg_match( '/^ *$/', $field[$req] ) ) {
                throw new EWZ_Exception( "Missing required item $req for a text input" );
            }
        }
        foreach ( $field as $name => $val ) {
            if( !is_string( $val ) ){
                 throw new EWZ_Exception( "Bad input data format for $name ");
            }
            if ( !in_array( $name, $all_field_data ) ) {
                throw new EWZ_Exception( "Bad input data for $name ");
            }

            if ( 'maxstringchars' == $name ) {
                if ( !preg_match( '/^\d*$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                if( !$val ){
                    $field[$name] = EWZ_MAX_STRING_LEN;  // changing, cant use $val on left
                }
                $field[$name] = (int)$val;      // changing, cant use $val on left

            }
            if ( ( 'fieldwidth' == $name ) ) {
                if ( !preg_match( '/^\d*$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                if( !$val ){
                    $field[$name] = EWZ_MAX_FIELD_WIDTH;  // changing, cant use $val on left
                }
                $field[$name] = ( int )$val;   // changing, cant use $val
            }
            if ( 'ss_col_fmt' == $name ){
                if ( !preg_match( '/^-1$|^\d*$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                $field[$name] = ( int )$val;    // changing, cant use $val on left
            }
        }
        return true;
    }

    /**
     * Validate image field input
     *
     * @param  array  $field  input image field to check
     */
    private static function valid_img_input( &$field )
    {
        assert( is_array( $field ) );

        $imgtypes = array( 'image/jpeg', 'image/pjpeg', 'image/gif', 'image/png' );
        $req_field_data = array( 'max_img_w', 'max_img_h', 'ss_col_w', 'ss_col_h', 'ss_col_o', 'max_img_size', 'min_longest_dim', 'allowed_image_types' );
        $opt_field_data = array( 'canrotate' );

        foreach ( $req_field_data as $req ) {
            if( is_string( $field[$req] ) ){
                if ( !array_key_exists( $req, $field ) || preg_match( '/^ *$/', $field[$req] ) ) {
                    throw new EWZ_Exception( "Missing required item $req " );
                }
            } else {
                if ( !array_key_exists( $req, $field ) || ( count( $field[$req] ) == 0 ) ) {
                    throw new EWZ_Exception( "Missing required item $req " );
                }
            }      
        }
        foreach ( $field as $name => $val ) {
            if ( !in_array( $name, $req_field_data ) &&
                 !in_array( $name, $opt_field_data ) )
                {
                    throw new EWZ_Exception( "Bad input data '$name' for image field");
                }
            if ( $name == 'max_img_w' || $name == 'max_img_h' ){
                if( !is_string( $val ) ){
                    throw new EWZ_Exception( "Bad input data format for $name ");
                }
                if ( isset( $val ) && !preg_match( '/^\d*$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                if( !$val ){
                    $field[$name] = EWZ_DEFAULT_DIM;
                }
                $field[$name] = ( int ) $val;
                
            } elseif ( $name == 'min_longest_dim' ) {
                if( !is_string( $val ) ){
                    throw new EWZ_Exception( "Bad input data format for $name ");
                }
                if ( isset( $val ) && !preg_match( '/^\d*$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                if( !$val ){
                    $val = EWZ_DEFAULT_MIN_LONGEST;
                }
                $field[$name] = ( int ) $val;
            } elseif ( $name == 'max_img_size' ) {
                if( !is_string( $val ) ){
                    throw new EWZ_Exception( "Bad input data format for $name ");
                }
                if ( isset( $val ) && !preg_match( '/^\d?\d?\.?\d+$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'"  );
                }
                if( $val > EWZ_MAX_SIZE_MB ){
                     throw new EWZ_Exception( "Maximum upload file size must be no more than " . EWZ_MAX_SIZE_MB . "MB"  );
                }
                if( !$val ){
                    $val = EWZ_MAX_SIZE_MB;
                }
                $field[$name] = (int)$val;

            } elseif ( $name == 'canrotate' ) {
                if( !is_string( $val ) ){
                    throw new EWZ_Exception( "Bad input data format for $name ");
                }
                if ( $val != "1" ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                $field[$name] = true;
               
            } elseif ( $name == 'allowed_image_types' ) {
                if ( !is_array( $val ) ) {
                    throw new EWZ_Exception( "Invalid value '$val' for allowed image type"  );
                } elseif ( !count( $val ) ) {
                    throw new EWZ_Exception( 'Missing allowed image types' );
                } else {
                    foreach ( $val as $imgtype ) {
                        if ( !(in_array( $imgtype, $imgtypes )) ) {
                            throw new EWZ_Exception( "Invalid value '$imgtype' for image type"  );
                        }
                    }
                }
            } elseif( 'ss_col_w' == $name  ||  'ss_col_h' == $name || 'ss_col_o' == $name ){
                if ( isset( $val ) && !preg_match( '/^-1$|^\d*$/', $val ) ) {
                    throw new EWZ_Exception( "Invalid value for $name: '$val'" );
                }
                $field[$name] = ( int ) $val;
            }
            if ( $name == 'max_img_size' && $val > EWZ_MAX_SIZE_MB ) {
                throw new EWZ_Exception( "Max image size '$val' too large" );
            }
        }
        return true;
    }

    /**
     * Validate option-list field input
     *
     * @param  array  $field  input option field to check
     * @return string $bad_data  comma-separated list of bad data
     */
    private static function valid_opt_input( &$fdata )
    {
        assert( is_array( $fdata ) );

        $req_field_data = array('label', 'value', 'maxnum');
        foreach ( $req_field_data as $req ) {
            foreach ( $fdata['options'] as $key => $val ) {
                if ( !array_key_exists( $req, $val ) ||  preg_match( '/^ *$/', $val[$req] )  ) {
                    throw new EWZ_Exception( "Missing required item $req for option list");
                }
            }
        }
        foreach ( $fdata as $name => $val ) {
            if ( $name != 'options' ) {
                throw new EWZ_Exception( "Bad input data $name" );
            }
            if( 'options' == $name ){
               foreach ( $val as $key => $optarr ) {
                   // key is label, value or maxnum
                   foreach ( $optarr as $optkey => $optval ) {
                       if( !is_string( $optval ) ){
                           throw new EWZ_Exception( "Bad input data format for $optkey ");
                       }
                       switch ( $optkey ) {
                           case 'maxnum':
                               if ( !preg_match( '/^\d*$/', $optval ) ) {
                                   throw new EWZ_Exception( "Invalid value for field option $optkey : $optval");
                               }
                               $fdata[$name][$key][$optkey] = ( int )$optval;  // changing, cant use optval
                               break;
                           case 'value':
                               if ( preg_match( '/[^A-Za-z0-9_\-]/', $optval ) ) {
                                   throw new EWZ_Exception( "Invalid value for field option $optkey : $optval" );
                               }
                               break;
                           case 'label':
                               if ( preg_match( '/[^A-Za-z0-9_\- ]/', $optval ) ) {
                                   throw new EWZ_Exception( "Invalid value for field option $optkey : $optval" );
                               }
                               break;
                           default:
                                   throw new EWZ_Exception( "Invalid value for field option $optkey : $optval" );
                       }
                   }
               }
            }
        }
        return true;
    }

}
