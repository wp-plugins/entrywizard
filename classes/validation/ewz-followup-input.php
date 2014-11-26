<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

class Ewz_Followup_Input extends Ewz_Input
{
    protected $fdata;         
                                                           
    function __construct( $form_data, $followup_data ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        assert( is_array( $followup_data ) );

        $this->fdata = $followup_data['fdata'];
        $this->required = $followup_data['required'];
        $this->field_type = $followup_data['field_type'];
        $this->field_header = $followup_data['field_header'];
        $this->Xmaxnums = $followup_data['Xmaxnums'];
        $this->chkmax = isset( $followup_data['fdata']['chkmax'] ) ?  $followup_data['fdata']['chkmax'] : 0;
        
        $this->rules = array(
                             'ewzuploadnonce'   => array( 'type' => 'unonce',    'req' =>  true,  'val' => '' ),
                             '_wp_http_referer' => array( 'type' => 'to_string', 'req' =>  false, 'val' => '' ),
                             'rdata'            => array( 'type' => 'v_rdata',   'req' => true,  'val' => '' ),
                             );
        $this->validate();
    }

    /**
     * Validate "rdata" input 
     * 
     * @param   $value  input value, may be changed by function
     * @return  true  ( exception raised if not valid )
     */
    protected function v_rdata( &$value, $arg ){
        assert( is_array( $value ) );
        assert( $arg == '' );
        $optcounts = array();
        $chkcount = array();
        foreach ( $value as $rownum => &$input_row ) {
            foreach ( $input_row as $item_id => &$val ) {
                if ( ( !isset($val) || $val === '' ) && $this->required ) {
                    $rownum1 = $rownum + 1;
                    throw new EWZ_Exception( "Row $rownum1: " . $this->field_header . ' is required.' );
                }
                switch ( $this->field_type ) {
                case 'str':
                        self::validate_str_data( $this->fdata, $val );  // may change 2nd arg
                        break;
                case 'opt':
                        if( !isset( $optcounts[$val] ) ){
                            $optcounts[$val] = 1;
                        } else {
                            ++$optcounts[$val];
                        }
                        $this->validate_opt_data( $this->Xmaxnums, $val, $optcounts[$val] );
                        break;
                case 'rad':
                    if( !isset( $chkcount[$val] ) ){
                        $chkcount[$val] = 0;
                    }
                    if( $val ){
                        ++$chkcount[$val];                            
                    }
                    self::validate_rad_data( $value[$rownum][$item_id], $chkcount[$val] );  // may change 1st arg
                    break;
                case 'chk':
                    if( !isset( $chkcount[$val] ) ){
                        $chkcount[$val] = 0;
                    }
                    if( $val == 'on' ){
                        ++$chkcount[$val];
                    }
                    self::validate_chk_data(  $this->chkmax, $value[$rownum][$item_id], $chkcount[$val] );  // may change it's input
                    break;
                default:
                    throw new EWZ_Exception( "Invalid field type " . $this->field_type );
                }
           }
            unset( $val );
            $bad_data = '';
            if ( $bad_data ) {
                throw new EWZ_Exception( "Restrictions not satisfied: $bad_data" );
            }
        }
        unset( $input_row );
        return true;
    }


    /**
     * Validate  string input
     *
     * Check length constraints in $fdata. If not satisfied, return a message.
     *
     * @param   array   $fdata  input field data
     * @param   string  $val    input value
     * @return  string  $msg  Error message, empty if no error.
     */
    private static function validate_str_data( $fdata, &$val )
    {
        assert( is_string( $val ) );
        assert( is_array( $fdata ) );
        if( !self::to_string( $val, '' ) ){               // also encodes the string
            throw new EWZ_Exception( "Invalid format for text input" );
        }
        $val = str_replace('\\', '', $val );  // messes up stripslashes and serialize. Its hard
                                              // to see a legit use for this, so easier to just remove

        if ( isset( $fdata['maxstringchars'] )
             && $fdata['maxstringchars']
             && ( strlen( $val ) > $fdata['maxstringchars'] )
             ) {
            $val = substr( $val, 0, $fdata['maxstringchars'] );
            throw new EWZ_Exception( "Text starting '$val' is too long. Limit is " . $fdata['maxstringchars'] );
        }
        return true;
    }

   /**
     * Validate option input
     *
     * Check $val is one of the options in $fdata, and there are not too many items with that value
     * already selected.
     *
     * @param   string  $val  input from a select list
     * @return  $val
     */
    private function validate_opt_data( $Xmaxnums, $val, $optcount )
    {
        assert( is_string( $val ) );
        assert( Ewz_Base::is_pos_int( $optcount ) );
        assert( is_array( $Xmaxnums ) );

        // if the item was required, it would be caught earlier, so allow blank here
        if( !$val ){
            return true;
        }
        if( !is_string( $val ) ){
            throw new EWZ_Exception( "Invalid format for option input" );
        }

        if ( array_key_exists( $val, $Xmaxnums ) && $Xmaxnums[$val] ) {
            if ( intval($Xmaxnums[$val]) < $optcount ) {
                throw new EWZ_Exception( "Too many '$val' values for $this->field_header"  );
            }
        }
        foreach ( $this->fdata['options'] as $option ) {
            if ( $val == $option['value'] ) {
                return true;
            }
        }
        throw new EWZ_Exception( "Invalid $this->field_header value $val");
    }

    /**
     * Validate radio button data
     * 
     * @param    $val   input data, string convertible to boolean, gets changed to  true or false
     * @param    $count  integer  current count of items checked via this button
     * @return   boolean true,  exception if data not valid
     */
    private static function validate_rad_data(  &$val, $count ){
        assert( is_string( $val ) );
        assert( Ewz_Base::is_nn_int( $count ) );
        if( !self::to_bool( $val, '' ) ){               // changes $val to int 0 or 1
            throw new EWZ_Exception( "Invalid radio button input" );
        }
        if ( 1 < $count ) {
            throw new EWZ_Exception( "More than one item checked"  );
        }
        return true;       
    }

    /**
     * Validate checkbox data
     * 
     * @param    $chkmax max number of checked items allowed ( ignored if 0 )
     * @param    $val   input data, string convertible to boolean, gets changed to true or false
     * @param    $count  current count of items with this value for this checkbox
     * @return   boolean true,  exception if data not valid
     */
    private static function validate_chk_data( $chkmax, &$val, $count ){
        assert( is_string( $val ) );
        assert( Ewz_Base::is_nn_int( $chkmax ) );
        assert( Ewz_Base::is_nn_int( $count ) );
        if( !self::to_bool( $val, '' ) ){               // changes $val to true or false
            throw new EWZ_Exception( "Invalid checkbox input" );
        }
        if ( isset( $chkmax ) && $chkmax  ) {
            if ( intval( $chkmax ) < $count ) {
                throw new EWZ_Exception( "Too many items checked"  );
            }
        }     
        return true;       
    }
}
