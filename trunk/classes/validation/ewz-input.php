<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");

/*
 * Base class for input validation
 * Has a set of rules specifying the type of each allowed input
 * This base class has the code for validating standard types like
 * database sequence numbers and other integers, and items with
 * only specific allowed values.
 *
 * Child classes may specify validation rules for other types.
 */
abstract class Ewz_Input {
    // alpha-numeric, underscore or dash, starting with alpha
    const REGEX_ALPHA_NUM = '/^[A-Z][A-Z0-9_\-]*$/i';

    // allowed identifier - alpha-numeric plus _ or -
    const REGEX_IDENT = '/^[A-Z0-9_\-]+$/i';

    // integer >= 0
    const REGEX_SEQ   = '/^[0-9]+$/';

    // same as above, but used in different context, so may change later
    const REGEX_INT   = '/^[0-9]+$/';

    // integer >= -1
    const REGEX_INT1   = '/^[0-9]+$|-1/';

    // integer >= 1
    const REGEX_NZ_INT   = '/^[1-9][0-9]+$/';

    // 0 or 1
    const REGEX_BOOL  = '/^[01]$/';


    protected $input_data = array( );
    protected $rules;

    function __construct( $form_data ) {
        assert( is_array( $form_data ) );
        $this->input_data = $form_data;
    }


    public function validate( ) {
        // Validate each form field
        foreach( $this->rules as $field => $specs ){
            if( $specs['req'] && !isset( $this->input_data[$field] ) ){
                throw new EWZ_Exception( get_class( $this ) . ": $field is required.");
            }
        }
        foreach ( $this->input_data as $field => $value ) {
            if( empty( $value ) || ( $value == "0" ) ){
                if( $this->rules[$field]['type'] == 'noval' ){
                    return true;
                } elseif (  $this->rules[$field]['req'] ) {
                    throw new EWZ_Exception( get_class( $this ) . ": $field required.");
                } else {
                    $this->input_data[$field] = $this->rules[$field]['val'];
                }
            } else {
                /**
                 * For each rule specified for an element,
                 * call a function with the same name, e.g. 'limited()' when
                 * checking whether a field has one of a given set of values
                 *
                 */
                if( !isset( $this->rules[$field] ) ){
                    throw new EWZ_Exception( get_class( $this ) . " found unexpected data $field: " . $value );
                }
                if( $value == 'on' ){
                    $this->input_data[$field] = '1';
                }
                if( $value == 'off' ){
                    $this->input_data[$field] = '0';
                }
                if( !call_user_func_array(
                                          array( $this, $this->rules[$field]['type'] ),   // function to call
                                          array( &$this->input_data[$field], $this->rules[$field]['val'] )    // args to pass
                                          ) ){
                    throw new EWZ_Exception( get_class( $this ) . " found invalid value for $field: " . $value );
                }
            }
        }

        return true;
    }

   public function get_input_data(){
       return $this->input_data;
   }

    /********* Validation Functions ************/

    protected function fixed( $value, $arg ) {
        assert( is_string( $value ) );
        assert( is_string( $arg ) );
        return ( is_string( $value ) && ( $value == $arg ) );
    }

    protected function limited( $value, $arg ) {
        assert( is_string( $value ) );
        assert( is_array( $arg ) );
        return ( is_string( $value ) && ( in_array( $value, $arg ) ) );
    }

    // for values passed to functions that do their own validation
    protected function arrayv( $value, $arg ) {
        assert( is_array( $value ) );
        assert( is_array( $arg ) );
        return true;
    }

    protected function anonce( $value, $arg ) {
        assert( is_string( $value ) );
        assert( isset( $arg ) );
        return check_admin_referer( 'ewzadmin', 'ewznonce' );
    }
    protected function unonce( $value, $arg ) {
        assert( is_string( $value ) );
        assert( isset( $arg ) );
        return wp_verify_nonce( $value, 'ewzupload' );
    }

    protected function ident( $value, $arg  ) {
        assert( is_string( $value ) );
        assert( isset( $arg ) );
        return ( is_string( $value ) &&
                 ( preg_match( self::REGEX_IDENT, $value ) ) );
    }

    protected function alpha_num( $value, $arg  ) {
        assert( is_string( $value ) );
        assert( isset( $arg ) );
        return ( is_string( $value ) &&
                 ( preg_match( self::REGEX_ALPHA_NUM, $value ) ) );
    }

    protected function seq( &$value, $arg  ) {
        assert( is_string( $value ) );
        assert( isset( $arg ) );
        $ok= ( is_string( $value ) &&
                 ( strlen( $value ) <= 10 ) &&
                 preg_match( self::REGEX_SEQ, $value ) );
        $value = (int)$value;
        return $ok;
    }

    protected function int1( &$value, $arg  ) {
        assert( is_string( $value ) );
        assert( isset( $arg ) );
        $ok= ( is_string( $value ) &&
                 ( strlen( $value ) <= 10 ) &&
                 preg_match( self::REGEX_INT1, $value ) );
        $value = (int)$value;
        return $ok;
    }

    protected function is_int_arr( $value, $arg, $allow1  ) {
        assert( is_array( $value ) );
        assert( is_array( $arg ) );
        assert( is_bool( $allow1 ) );
        assert( Ewz_Base::is_nn_int($arg[0] ) );
        assert( Ewz_Base::is_nn_int($arg[1] ) );

        if( !is_array( $value ) ){
            return false;
        }
        $c = count( $value );
        foreach( $value as $val ){
            if( !is_string( $val ) ){
                return false;
            }
            if( $allow1 ){
                if( !preg_match( self::REGEX_INT1, $val ) ){
                 return false;
                }
            } else {
                if( !preg_match( self::REGEX_INT, $val ) ){
                    return false;
                }
            }
        }
        if( $c > $arg[1] || $c < $arg[0] ){
            return false;
        }
        return true;
    }

    protected function int_arr( $value, $arg  ) {
        // no assert
        return $this->is_int_arr( $value, $arg, false  );
    }
    protected function int1_arr( $value, $arg  ) {
        // no assert
        return $this->is_int_arr( $value, $arg, true  );
    }

    protected function string( &$value, $arg  ) {
        assert( is_string( $value ) );
        assert( isset( $arg ) );
        if( is_string( $value ) ){
            // decode entities previously encoded for html display
            $value = html_entity_decode( $value );
            return true;
        } else {
            return false;
        }
    }

    protected function bool( &$value, $arg  ) {
        assert( is_string( $value )  );
        assert( isset( $arg ) );
        if( !is_string( $value )){
            return false;
        }
        $ok = preg_match( self::REGEX_BOOL, $value );
        $value = (bool)$value;
        return $ok;

    }

    protected function str_len( $value, $limits ) {
        assert( is_string( $value ) );
        assert( isset( $limits[0] ) );
        assert( isset( $limits[1] ) );
        assert( Ewz_Base::is_pos_int( $limits[0] ) );
        assert( Ewz_Base::is_pos_int( $limits[1] ) );
        if( !is_string( $value ) ){
            return false;
        }
        $len = strlen( $value );
        return ( ( $limits[0] <= $len ) && ( $len >= $limits[1] )  );
    }

  }