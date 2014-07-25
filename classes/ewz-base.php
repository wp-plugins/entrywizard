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

    // get from database given key
    abstract protected function create_from_id( $id );

    // create from uploaded data
    abstract protected function create_from_data( $data );

    public static function validate_using_javascript(){
        // normally returns true
        // return false to force all validation to be done on server,
        // with none on the client
        return true;
    }

    public static function toDatePickerFormat( $dateFormat ){
        assert( is_string( $dateFormat ) );
        $map = array( 
             'c' => 'yy-mm-dd',         // ISO 8601 date 2004-02-12T15:19:21+00:00
             'd' => 'dd',               // Day of the month, 2 digits with leading zeros 
             'D' => 'M',                // Day, three letters
             'j' => 'd',                // Day of the month without leading zeros
             'l' => 'DD',               // Day of the week, full
             'z' => 'oo',               // The day of the year 	0 through 365
             'F' => 'MM',               // Month, full
             'm' => 'mm',               // Numeric representation of a month, with leading zeros
             'M' => 'M',                // Month, 3 letters
             'n' => 'm',                // Numeric representation of a month, without leading zeros
             'r' => 'D, dd M YYYY',     // RFC 2822 formatted date Example: Thu, 21 Dec 2000 16:01:07 +0200
             'Y' => 'yy',               // Year, 4 digits
             'y' => 'y',                // Year, 2 digits
              );

        if( preg_match('[NSwtLo]', $dateFormat) ){
            // some items cannot be represented in datepicker
            return 'yy-mm-dd';
        } else {
            return strtr((string)$dateFormat, $map);
        }
    }


public static function toStrftimeFormat( $dateFormat ) {
    assert( is_string( $dateFormat ) );   
    $caracs = array(
        // Day - no strf eq : S
        'd' => '%d', 'D' => '%a', 'j' => '%e', 'l' => '%A', 'N' => '%u', 'w' => '%w', 'z' => '%j',
        // Week - no date eq : %U, %W
        'W' => '%V', 
        // Month - no strf eq : n, t
        'F' => '%B', 'm' => '%m', 'M' => '%b',
        // Year - no strf eq : L; no date eq : %C, %g
        'o' => '%G', 'Y' => '%Y', 'y' => '%y',
        // Time - no strf eq : B, G, u; no date eq : %r, %R, %T, %X
        'a' => '%P', 'A' => '%p', 'g' => '%l', 'h' => '%I', 'H' => '%H', 'i' => '%M', 's' => '%S',
        // Timezone - no strf eq : e, I, P, Z
        'O' => '%z', 'T' => '%Z',
        // Full Date / Time - no strf eq : c, r; no date eq : %c, %D, %F, %x 
        'U' => '%s'
    );
   
    return strtr((string)$dateFormat, $caracs);
} 

    /**
     * Sets the variables to the keys of "data" based on the type in the values
     *
     * Convenience function that sets boolean and integer types to themselves,
     * but unserializes objects and arrays
     *
     * @param   $variables  array of object variables
     * @param   $data  array whose keys are the variable names and values are their types

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
    public static function is_nn_int( $val ){
        // no assert
        if( is_string( $val )){
            return preg_match( '/^[0-9]+$/', $val );
        } else {
            return ( is_int( $val ) && ( $val >= 0 ) );
        }
    }

    /**
     * Is the arg a positive integer, or a string representation of same
     *
     * @param   mixed   String or Integer
     * @return  boolean
     */
    public  static function is_pos_int( $val ){
        // no assert
        if( is_string( $val )){
            return preg_match( '/^[1-9][0-9]*$/', $val );
        } else {
            return ( is_int( $val ) && $val > 0 );
        }
    }

}

