<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

class Ewz_Upload_Input extends Ewz_Input
{
    protected $layout;

    function __construct( $form_data, $in_files, $in_layout ) {
        parent::__construct( $form_data );
        assert( is_array( $form_data ) );
        assert( is_array( $in_files ) );
        assert( is_object( $in_layout ) );

        $this->layout = $in_layout;

        // Set the rdata value for the image upload fields
        // Needs to be done before validation
        if ( $in_files ) {
            foreach ( $in_files['rdata']['name'] as $row => $uploaded_fileinfo ) {
                foreach ( $uploaded_fileinfo as $field_id => $filename ) {
                    if ( $filename ) {
                        $this->input_data['rdata'][$row][$field_id] = 'ewz_img_upload';
                    }
                }
            }
        }
        $this->rules = array(
                             'layout_id'        => array( 'type' => 'seq',    'req' =>  true,  'val' => '' ),
                             'webform_id'       => array( 'type' => 'seq',    'req' =>  true,  'val' => '' ),
                             'ewzuploadnonce'   => array( 'type' => 'unonce', 'req' =>  true,  'val' => '' ),
                             '_wp_http_referer' => array( 'type' => 'string', 'req' =>  false, 'val' => '' ),
                             'rdata'            => array( 'type' => 'v_rdata', 'req' => true,  'val' => '' ),
                             'item_id'          => array( 'type' => 'v_items', 'req' => false, 'val' => '' ),
                             'identifier'       => array( 'type' => 'ident',   'req' => true,  'val' => '' ),
                             );
        $this->validate();
    }


    public function is_valid_ident( $identifier ){
        assert( is_string(  $identifier ) );
        return self::ident( $identifier );
    }

  //****** All v_.... functions must return a boolean **************/

/**
 * Validate "item_id" input 
 * 
 * @param   $value  input value, may be changed by function
 * @param   $arg    '' -- unused here, needed for generic code in ewz-input.php
 * @return  boolean  
 */
  protected function v_items( &$value, $arg ){
      assert( is_array( $value ) );
      assert( $arg == '' );
      foreach ( array_keys( $value ) as $id ){
          return self::seq( $value[$id], $arg  );      // seq potentially changes first arg
      }
  }

/**
 * Validate "rdata" input 
 * 
 * @param   $value  input value, may be changed by function
 * @param   $arg    '' -- unused here, needed for generic code in ewz-input.php
 * @return  true  ( exception raised if not valid )
 */
  protected function v_rdata( &$value, $arg ){
        assert( is_array( $value ) );
        assert( $arg == '' );
        $fields = $this->layout->fields;
        $optcounts = array();
        $chkcount = array();
        foreach ( $value as $rownum => $input_row ) {
            foreach ( $this->layout->restrictions as $restr ) {
                $row_matches_restr[$restr['msg']] = true;   // may set to false during check below
            }
            foreach ( $input_row as $field_id => $val ) {
                if ( !isset( $fields[$field_id] ) ){
                    throw new EWZ_Exception( "Row $rownum: invalid data"  );
                }
                if ( !$val && $fields[$field_id]->required ) {
                    throw new EWZ_Exception( "Row $rownum: " . $fields[$field_id]->field_header . ' is required.' );
                }

                switch ( $fields[$field_id]->field_type ) {
                case 'str':
                        self::validate_str_data( $fields[$field_id]->fdata, $value[$rownum][$field_id] );  // may change 2nd arg
                        break;
                case 'opt':
                        if( !isset( $optcounts[$field_id][$val] ) ){
                            $optcounts[$field_id][$val] = 1;
                        } else {
                            ++$optcounts[$field_id][$val];
                        }
                        self::validate_opt_data( $fields[$field_id], $val, $optcounts[$field_id][$val] );
                        break;
                case 'img':
                        self::validate_img_data( $val );
                        break;
                case 'rad':
                    if( !isset( $chkcount[$field_id] ) ){
                        $chkcount[$field_id] = 0;
                    }
                    if( $val ){
                        ++$chkcount[$field_id];                            
                    }
                    self::validate_rad_data( $value[$rownum][$field_id], $chkcount[$field_id] );  // may change it's input
                    break;
                case 'chk':
                    if( !isset( $chkcount[$field_id] ) ){
                        $chkcount[$field_id] = 0;
                    }
                    if( $val == 'on' ){
                        ++$chkcount[$field_id];
                    }
                    self::validate_chk_data(  $fields[$field_id], $value[$rownum][$field_id], $chkcount[$field_id] );  // may change it's input
                    break;
                default:
                    throw new EWZ_Exception( "Invalid field type " . $fields[$field_id]->field_type );
                }
                foreach ( $this->layout->restrictions as $restr ) {
                    $ismatch = self::field_matches_restr( $field_id, $restr, $val );
                    $row_matches_restr[$restr['msg']] = $row_matches_restr[$restr['msg']] && $ismatch;
                }
            }
            $bad_data = '';
            foreach ( $this->layout->restrictions as $restr ) {
                if ( $row_matches_restr[$restr['msg']] ) {
                    $user_row = $rownum + 1;
                    $bad_data .= " row $user_row: " . $restr['msg'];
                }
            }
            if ( $bad_data ) {
                throw new EWZ_Exception( "Restrictions not satisfied: $bad_data" );
            }
        }
        return true;
    }

    /**
     * Return true if all the conditions specified in the restriction are met
     *
     * @param   $field_id
     * @param   $restr     the restriction
     * @param   $fval    the field value uploaded
     * @return  Boolean
     */
    private static function field_matches_restr( $field_id, $restr, $fval )
    {
        assert( Ewz_Base::is_pos_int( $field_id ) );
        assert( is_array( $restr ) );
        assert( is_string( $fval ) );
        $ismatch = true;
        if ( array_key_exists( $field_id, $restr ) ) {
            $rval = $restr[$field_id];
            switch ( $rval ) {
                case '~*~': break;
                case '~-~': if ( $fval ) { $ismatch = false; }
                    break;
                case '~+~': if ( !$fval ) { $ismatch = false; }
                    break;
                default: if ( $rval != $fval )  { $ismatch = false; }
                    break;
            }
        } else {
            error_log( "EWZ: warning - field_matches_restr $field_id not a key in restriction array " );
        }
        return $ismatch;
    }

    /**
     * Validate and sanitize  input that is not string or option
     *
     * There should be no data here - return an error message and set the data to null.
     * If reached, this function should always return an error.
     *
     * @param   string  $val  input value
     * @return  $val
     */
    private static function validate_img_data( $val )
    {
        assert( is_string( $val ) );

        if ( !(  is_string( $val ) &&
                 preg_match( '/^ewz_img/', $val ) ) ) {
            throw new EWZ_Exception( 'Image type should upload a file, not data' );
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

    /**
     * Validate checkbox input
     *
     * @param   array   $fdata  input field data
     * @param   string  $val    input value
     * @param   int     $count  number of items with this field checked
     * @return  string  $msg  Error message, empty if no error.
     */
    private static function validate_chk_data( $field, &$val, $count )
    {
        assert( is_object( $field ) );
        assert( is_string( $val ) );
        assert( Ewz_Base::is_nn_int( $count ) );
        if( !self::bool( $val, '' ) ){
            throw new EWZ_Exception( "Invalid value <$val> for checkbox input" );
        }   
        if ( isset( $field->fdata['chkmax'] ) && $field->fdata['chkmax']  ) {
            if ( intval($field->fdata['chkmax'] ) < $count ) {
                throw new EWZ_Exception( "Too many items checked for $field->field_header"  );
            }
        }     
        return true;
    }

    /**
     * Validate radiobutton input
     *
     * @param   array   $fdata  input field data
     * @param   string  $val    input value
     * @return  string  $msg  Error message, empty if no error.
     */
    private static function validate_rad_data(  &$val, $count )
    {
        assert( is_string( $val ) );
        assert( Ewz_Base::is_nn_int( $count ) );
 
        if( !self::bool( $val, '' ) ){
            throw new EWZ_Exception( "Invalid value <$val> for radiobutton input" );
        }
        if( 1 < $count ){
            throw new EWZ_Exception( "More than one radiobutton checked" );
        } 
        return true;
    }
 
}
