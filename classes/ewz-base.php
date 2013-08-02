<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

/*
 *  Base for the database classes
 */
abstract class Ewz_Base
{

    abstract protected function check_errors();

    abstract public function save();

    abstract public function delete();

    public static function validate_using_javascript(){
        // normally returns true
        // return false to force all validation to be done on server,
        // with none on the client
        return true;
    }


    /**
     * Sets the variables to the keys of "data" based on the type in the values
     *
     * Convenience function that sets boolean and integer types to themselves,
     * but unserializes objects and arrays
     *
     * @param    array  $variables:  array of object variables
     * @param    array  $data:  array whose keys are the variable names and values are their types

     * @return   none
     */
    protected function base_set_data( $variables, $data )
    {
        assert( is_array( $variables ) );
        assert( is_array( $data ) );

	foreach ( $variables as $key => $type ) {
	    if ( array_key_exists( $key, $data ) ) {
		switch ( $type ) {
		    case 'object':
			if ( is_string( $data[$key] ) ) {
			    $this->$key = unserialize( $data[$key] );
			} else {
			    $this->$key = (object) $data[$key];
			}
			break;
		    case 'array':
                        if ( !$data[$key] ){
                            $this->$key = array();
                        } else {
                            if ( is_string( $data[$key] ) ) {
                                $this->$key = unserialize( $data[$key] );
                            } else {
                                $this->$key = (array) $data[$key];
                            }
                        }
			break;
		    case 'boolean':
			$this->$key = (boolean) $data[$key];
			break;
		    case 'integer':
			$this->$key = (integer) $data[$key];
			break;
		    case 'string':
			$this->$key = (string) $data[$key];
			break;
		    default:
                        error_log("EWZ: warning - invalid data type $type passed to base class");
			$this->$key = NULL;
		}
	    } else {
		$this->$key = NULL;
	    }
	}
    }

    /**
     * Is the arg a non-negative integer, or a string representation of same
     *
     * @param   mixed   String or Integer
     * @return  boolean
     */
    public function is_nn_int( $val ){
        // no assert
        if( is_string( $val )){
            return preg_match( '/^[0-9]+$/', $val );
        } else {
            return is_int( $val );
        }
    }

    /**
     * Is the arg a positive integer, or a string representation of same
     *
     * @param   mixed   String or Integer
     * @return  boolean
     */
    public function is_pos_int( $val ){
        // no assert
        if( is_string( $val )){
            return preg_match( '/^[1-9][0-9]*$/', $val );
        } else {
            return ( is_int( $val ) && $val > 0 );
        }
    }

}

