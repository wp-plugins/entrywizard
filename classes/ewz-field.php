<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-exception.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-layout.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-permission.php");


/***************************************************************/
/* Interaction with the EWZ_FIELD table.                       */
/*                                                             */
/* Fields are components of Layouts.                           */
/* A field should only be edited/created/destroyed by a Layout */
/***************************************************************/

class Ewz_Field extends Ewz_Base
{
    // key
    public $field_id;
    // database
    public $layout_id;
    public $field_type;
    public $field_header;       // header for web page
    public $field_ident;        // slug, and header for spreadsheet
    public $required;           // item is required on web form
    public $pg_column;          // column on web page
    public $ss_column;          // column in spreadsheet
    public $fdata;              // serialized data structure of parameters specific to the field type
                                //   -- TODO: make each type a sub-class of  Ewz_Field

    // keep list of db data names/types as a convenience for iteration and so we can easily add new ones.
    // Dont include field_id here
    public static $varlist = array(
        'layout_id'    => 'integer',
        'field_type'   => 'string',
        'field_header' => 'string',
        'field_ident'  => 'string',
        'required'     => 'boolean',
        'pg_column'    => 'integer',
        'ss_column'    => 'integer',
        'fdata'        => 'array',
    );

    public $Xmaxnums;           // maxnums for an option field - max allowed for each option
    public $Xlabels;            // labels  for an option field

    public static $typelist = array( "img", "opt", "str" );
    public static $col_max = 100;

    /**
     * Change made in version 0.9.6 to use min longest dimension instead of min area
     * Set min_longest_dim to square root of min_img_area, unset min_img_area
     */
    public static function change_min_area_to_dim( )
    {
        global $wpdb;
        $list = $wpdb->get_col( "SELECT field_id FROM " . EWZ_FIELD_TABLE . " WHERE field_type = 'img'" );
        foreach ( $list as $field_id ) {
            $field = new Ewz_Field( $field_id );
            if( isset( $field->fdata['min_img_area'] ) ){
                $area = $field->fdata['min_img_area'];
                unset( $field->fdata['min_img_area'] );
                $field->fdata['min_longest_dim'] = floor( sqrt( $area ) );
                $field->save();
            }
        }
    }



    /**
     * Return an array of all the fields attached to the input layout_id
     *
     * @param   int     $layout_id
     * @param   string  $orderby   column to sort by - either 'ss_column' or 'pg_column'
     * @return  array   of Ewz_Fields
     */
    public static function get_fields_for_layout( $layout_id, $orderby, $inc_followup = true )
    {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $layout_id ) );
        assert( is_bool( $inc_followup )  ||  $inc_followup == 1 ||  $inc_followup == 0);
        assert( 'ss_column' == $orderby || 'pg_column' == $orderby );

        $incfollow = '';
        if( ! $inc_followup ){
            $incfollow = ' AND  field_ident != "followupQ" ';
        }
        $list = $wpdb->get_col( $wpdb->prepare( "SELECT field_id  FROM " . EWZ_FIELD_TABLE . " WHERE layout_id = %d $incfollow ORDER BY $orderby",
                                         $layout_id ) );
        $fields = array();
        foreach ( $list as $field_id ) {
            $fields[$field_id] = new Ewz_Field( $field_id );
        }
        return $fields;
    }

    /**
     * Make sure the input field_id  is a valid one for the layout_id
     *
     * @param    int      $field_id
     * @param    int      $layout_id
     * @return   boolean  true if $field_id is the key for a EWZ_FIELD_TABLE row attached to the layout, otherwise false
     */
    public static function is_valid_field( $field_id, $layout_id )
    {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $field_id ) );
        assert( Ewz_Base::is_pos_int( $layout_id ) );
        $mycount = $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_FIELD_TABLE .
                        " WHERE field_id = %d  AND layout_id = %d", (int) $field_id, (int) $layout_id ) );
        return ( 1 == $mycount );
    }


    /**
     * Get the field_id from the layout_id and field_ident by getting the data from the database
     * Needed to add uploaded data to field via .csv file, using item_id and field_ident to specify the field
     *
     * @param  array  $ident  with elements 'layout_id' and 'field_ident'
     * @return none
     */
    public static function field_id_from_ident_arr( $ident_arr )
    {
        global $wpdb;
        assert( is_array( $ident_arr ) );
        assert( Ewz_Base::is_pos_int( $ident_arr['layout_id'] ) );
        assert( is_string( $ident_arr['field_ident'] ) );

        if ( preg_match( '/[^0-9a-zA-Z_\-]/', $ident_arr['field_ident'] ) ) {
            throw new EWZ_Exception( 'Field identifier ' . $ident_arr['field_ident'] . ' may contain only letters, digits, dashes or underscores' );
        }

        $field_id = $wpdb->get_var( $wpdb->prepare( "SELECT field_id FROM " . EWZ_FIELD_TABLE .
                                                    " WHERE layout_id = %d and field_ident = %s",
                                                    $ident_arr['layout_id'], $ident_arr['field_ident'] ) );
        if ( !$field_id ) {
            throw new EWZ_Exception( 'Unable to find matching field for identifier ' . $ident_arr['field_ident'] );
        }
        return $field_id;
    }


    /*     * ****************** Object Functions ********************* */
   /**
    * Return the list of possible values for a field, including "blank", "not blank" or "any"
    *
    * The list is used for restrictions and for selecting subsets of uploaded items
    * For option fields it contains all possible options plus "any", and, if the field is not required, "blank" and "not blank"
    * For other non-required fields it just contains "blank", "not blank" or "any"
    *
    * @return $list  array
    */
    public function get_field_list_array()
    {
        $list = array();
        if ( 'opt' == $this->field_type ) {
            array_push( $list, array( 'value'=>'~*~', 'display'=> 'Any', 'selected' => true ) );
            if ( !$this->required ) {
                array_push( $list, array( 'value'=>'~-~', 'display'=> 'Blank' ) );
                array_push( $list, array( 'value'=>'~+~', 'display'=> 'Not Blank' ) );
            }
            foreach ( $this->fdata['options'] as $dat ) {
                array_push( $list, array( 'value'=>$dat['value'], 'display'=> $dat['label'] ) );
            }
        } else {
            if ( !$this->required ) {
                array_push( $list, array( 'value'=>'~*~', 'display'=> 'Any', 'selected' => true ) );
                array_push( $list, array( 'value'=>'~-~', 'display'=> 'Blank' ) );
                array_push( $list, array( 'value'=>'~+~', 'display'=> 'Not Blank' ) );
            }
        }
        return $list;
    }


    /******************** Construction **************************/

    /**
     * Assign the object variables from an array
     *
     * Calls parent::base_set_data with the list of variables and the data structure
     *
     * @param  array  $data: input data
     * @return none
     */
    public function set_data( $data )
    {
        assert( is_array( $data ) );
        // first arg is list of valid elements - all of varlist plus field_id
        parent::base_set_data( array_merge( self::$varlist,
                                            array('field_id' => 'integer') ), $data );
    }

    /**
     * Constructor
     *
     * @param  mixed    $init    field_id or array of data
     * @return none
     */
    public function __construct( $init )
    {
        assert( Ewz_Base::is_pos_int( $init )
                || ( is_array( $init )
                     && isset( $init['layout_id'] )
                     && isset( $init['field_ident'] )
                     && isset( $init['field_header'] )
                     && isset( $init['field_type'] )
                     && isset( $init['fdata'] )
                     )
                || ( is_array( $init )
                     && isset( $init['layout_id'] )
                     && isset( $init['field_ident'] )
                     && !isset( $init['field_header'] )
                     && !isset( $init['field_type'] )
                     && !isset( $init['fdata'] )
                     )
                || is_string( $init )
                );
        if ( Ewz_Base::is_pos_int( $init ) ) {
            $this->create_from_id( $init );
        } elseif ( is_array( $init ) && !isset( $init['field_type'] ) ) {
            $this->create_from_ident( $init );
        } elseif ( is_array( $init ) ) {
            $this->create_from_data( $init );
        }
        if ( 'opt' == $this->field_type ) {
            $this->Xmaxnums = array();
            $this->Xlabels = array();
            foreach ( $this->fdata['options'] as $dat ) {
                $this->Xmaxnums[$dat['value']] = $dat['maxnum'];
                $this->Xlabels[$dat['value']] = $dat['label'];
            }
        }
    }

    /**
     * Create a new field from the field_id by getting the data from the database
     *
     * @param  int  $id: the field id
     * @return none
     */
    protected function create_from_id( $id )
    {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $id ) );
        $varstring = implode( ',', array_keys( self::$varlist ) );
        $dbfield = $wpdb->get_row( $wpdb->prepare( "SELECT field_id, $varstring FROM " . EWZ_FIELD_TABLE .
                        " WHERE field_id=%d", $id ), ARRAY_A );
        if ( !$dbfield ) {
            throw new EWZ_Exception( 'Unable to find matching field', $id );
        }
        $this->set_data( $dbfield );
    }

    /**
     * Create a field object from $data
     *
     * @param  array  $data
     * @return none
     */
    protected function create_from_data( $data )
    {
        assert( is_array( $data ) );
        if ( !array_key_exists( 'field_id', $data ) || !$data['field_id'] ) {
            $data['field_id'] = 0;
        }
        $this->set_data( $data );
        $this->check_errors();
    }

    /*     * ******************  Validation  ****************************** */

    /**
     * Check for various error conditions, and raise an exception when one is found
     *
     * @param  none
     * @return none
     */
    protected function check_errors()
    {
        global $wpdb;
        if ( is_string( $this->fdata ) ) {
            $this->fdata = unserialize( $this->fdata );
        }
        foreach ( self::$varlist as $key => $type ) {
            settype( $this->$key, $type );
        }
        if ( $this->layout_id && !Ewz_Layout::is_valid_layout( $this->layout_id ) ) {
            throw new EWZ_Exception( 'Layout is not a valid one', $this->layout_id );
        }

        // check for valid field type, ident
        if ( !in_array( $this->field_type, self::$typelist ) ) {
            throw new EWZ_Exception( 'Invalid field type ' . $this->field_type );
        }
        if ( !preg_match( '/^[0-9a-zA-Z_\-]+$/', $this->field_ident ) ) {
            throw new EWZ_Exception( 'Invalid field identifier ' . $this->field_ident .
                                     ' The value may contain only letters, digits, dashes or underscores' );
       }

        // check for key duplication
        $used1 = $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_FIELD_TABLE .
                        " WHERE layout_id = %d AND field_header = %s AND field_id != %d",
                                          $this->layout_id, $this->field_header, $this->field_id ) );
        if ( $used1 > 0 ) {
            throw new EWZ_Exception( 'Field name ' . $this->field_header . ' already in use for this layout' );
        }
        $used2 = $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_FIELD_TABLE .
                        " WHERE layout_id = %d AND field_ident = %s AND field_id != %d",
                                          $this->layout_id, $this->field_ident, $this->field_id ) );
        if ( $used2 > 0 ) {
            throw new EWZ_Exception( 'Field identifier ' . $this->field_ident . ' already in use for this layout' );
        }

        // -1 is essentially null for ss_column
        if ( $this->ss_column < -1 || $this->ss_column > self::$col_max ) {
            throw new EWZ_Exception( 'Invalid value ' . $this->ss_column . ' for spreadsheet column' );
        }
        if ( $this->pg_column < 0 || $this->pg_column > self::$col_max ) {
            throw new EWZ_Exception( 'Invalid value ' . $this->pg_column  . ' for web page column' );
        }
    }

    /*     * ******************  Database Updates ***************** */

    /**
     * Save the field to the database
     *
     * Check for permissions, then update or insert the data
     *
     * @param none
     * @return none
     */
    public function save()
    {
        global $wpdb;
        if( $this->layout_id ){
            if ( !Ewz_Permission::can_edit_layout( $this ) ) {
                throw new EWZ_Exception( 'Insufficient permissions to edit layout', $this->layout_id );
            }
        } else {
	    if ( !Ewz_Permission::can_edit_all_layouts() ) {
                throw new EWZ_Exception( 'Insufficient permissions to create a new layout' );
	    }
        }
        $this->check_errors();
        $data = stripslashes_deep( array(
            'layout_id' => $this->layout_id,
            'field_type' => $this->field_type,
            'field_header' => $this->field_header,
            'field_ident' => $this->field_ident,
            'required' => $this->required ? 1 : 0,
            'pg_column' => $this->pg_column,
            'ss_column' => (  '' === $this->ss_column ) ? '-1' : $this->ss_column,
            'fdata' => serialize( $this->fdata )
                ) );
        $datatypes = array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s');

        if ( $this->field_id ) {
            $rows = $wpdb->update( EWZ_FIELD_TABLE, $data,
                            array('field_id' => $this->field_id), $datatypes, array('%d', '%d') );
            if ( $rows > 1 ) {
                throw new EWZ_Exception( 'Failed to update field', $this->field_id );
            }
        } else {
           $layout_ok = $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM " . EWZ_LAYOUT_TABLE . " WHERE layout_id = %d",
                                                  $this->layout_id ) );
            if ( $layout_ok != 1 ) {
                throw new EWZ_Exception( 'Failed to update layout', $this->layout_id );
            }

            $wpdb->insert( EWZ_FIELD_TABLE, $data, $datatypes );
            $inserted = $wpdb->insert_id;
            if ( !$inserted ) {
                throw new EWZ_Exception( 'Failed to create new field', $this->field_id );
            }
        }
    }

    /**
     * Delete the field  from the database
     *
     * @param  none
     * @return none
     */
    public function delete()
    {
        global $wpdb;
        if ( !Ewz_Permission::can_edit_layout( $this->layout_id ) ) {
            throw new EWZ_Exception( 'Insufficient permissions to edit the layout', $this->layout_id );
        }
        $order = $wpdb->get_var( $wpdb->prepare( "SELECT pg_column  FROM " . EWZ_FIELD_TABLE . " WHERE field_id = %d",
                                                  $this->field_id ) ); 
        
        $rowsaffected = $wpdb->query( $wpdb->prepare( "DELETE FROM " . EWZ_FIELD_TABLE . " where field_id = %d",
                                               $this->field_id ) );
        if ( $rowsaffected != 1 ) {
            throw new EWZ_Exception( 'Failed to delete field', $this->field_id );
        }
        $rowsaffected = $wpdb->query( $wpdb->prepare( "UPDATE  " . EWZ_FIELD_TABLE . 
                                                      " SET pg_column = pg_column - 1 " .
                                                      " WHERE layout_id = %d AND  pg_column > %d",
                                                      $this->layout_id, $order ) );
    }
}

