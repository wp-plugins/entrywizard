<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

class Ewz_Followup_Input extends Ewz_Input
{
    protected $field;       // the followup field, should have the same properties for all webforms
                            

    function __construct( $form_data, $in_files, $in_field ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        assert( is_array( $in_files ) );
        assert( is_object( $in_field ) );

        $this->field = $in_field;

        // Set the rdata value for the image upload fields
        // Needs to be done before validation
        if ( count( $in_files ) > 0 ) {
            foreach ( $in_files['rdata']['name'] as $row => $uploaded_fileinfo ) {
                foreach ( $uploaded_fileinfo as $field_id => $filename ) {
                    if ( $filename ) {
                        $this->input_data['rdata'][$row][$field_id] = 'ewz_img_upload';
                    }
                }
            }
        }
        $this->rules = array(
                             'ewzuploadnonce'   => array( 'type' => 'unonce', 'req' =>  true,  'val' => '' ),
                             '_wp_http_referer' => array( 'type' => 'string', 'req' =>  false, 'val' => '' ),
                             'rdata'            => array( 'type' => 'v_rdata', 'req' => true,  'val' => '' ),
                             );
        $this->validate();
    }

    protected function v_rdata( &$value, $arg ){
        assert( is_array( $value ) );
        assert( $arg == '' );
        $optcounts = array();
        foreach ( $value as $rownum => $input_row ) {
            foreach ( $input_row as $item_id => $val ) {
                if ( !$val && $this->field->required ) {
                    $rownum1 = $rownum + 1;
                    throw new EWZ_Exception( "Row $rownum1: " . $this->field->field_header . ' is required.' );
                }
                $field_id = $this->field->field_id;
                switch ( $this->field->field_type ) {
                case 'str':
                        self::validate_str_data( $this->field->fdata, $val );  // may change 2nd arg
                        break;
                case 'opt':
                        if( !isset( $optcounts[$field_id][$val] ) ){
                            $optcounts[$field_id][$val] = 1;
                        } else {
                            ++$optcounts[$field_id][$val];
                        }
                        self::validate_opt_data( $this->field, $val, $optcounts[$field_id][$val] );
                        break;
                default:
                    throw new EWZ_Exception( "Invalid field type " . $this->field->field_type );
                }
            }
            $bad_data = '';
            if ( $bad_data ) {
                throw new EWZ_Exception( "Restrictions not satisfied: $bad_data" );
            }
        }
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
        if( !self::string( $val, '' ) ){               // also encodes the string
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
    private static function validate_opt_data( $field, $val, $optcount )
    {
        assert( is_string( $val ) );
        assert( is_object( $field ) );
        assert( Ewz_Base::is_pos_int( $optcount ) );
        assert( Ewz_Base::is_pos_int( $field->field_id ) );

        // if the item was required, it would be caught earlier, so allow blank here
        if( !$val ){
            return true;
        }
        if( !is_string( $val ) ){
            throw new EWZ_Exception( "Invalid format for option input" );
        }

        if ( array_key_exists( $val, $field->Xmaxnums ) && $field->Xmaxnums[$val] ) {
            if ( intval($field->Xmaxnums[$val]) < $optcount ) {
                throw new EWZ_Exception( "Too many '$val' values for $field->field_header"  );
            }
        }
        foreach ( $field->fdata['options'] as $option ) {
            if ( $val == $option['value'] ) {
                return true;
            }
        }
        throw new EWZ_Exception( "Invalid $field->field_header value $val");
    }
}