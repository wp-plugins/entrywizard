<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-exception.php" );
require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php" );
require_once( EWZ_PLUGIN_DIR . "classes/ewz-field.php" );
require_once( EWZ_PLUGIN_DIR . "classes/ewz-permission.php" );
require_once( EWZ_CUSTOM_DIR . "ewz-custom-data.php" );

/********************************************************************************************
 * Interaction with the EWZ_LAYOUT table.
 *
 * Determines the appearance of a webform. Contains the title, the number of items, any
 * restrictions ( which are stored as serialized arrays ) and any member information  columns
 * ( which are for display only, and are also stored  as serialized arrays ).
 *
 * Each Ewz_Layout also "contains" several Ewz_fields, which correspond to the EWZ_FIELD table.
 * Each field contains a layout_id which specifies which layout it belongs to.
 *
 ********************************************************************************************/

class Ewz_Layout extends Ewz_Base
{

    const INCLUDE_FOLLOWUP = 1;
    const EXCLUDE_FOLLOWUP = 0;

    const DELETE_FORMS = 1;
    const FAIL_IF_FORMS = 0;

    // key
    public $layout_id;

    // data stored on db
    public $layout_name;
    public $max_num_items;
    public $override;
    public $restrictions;
    public $extra_cols;      // columns for the spreadsheet generated from WP member data and other tables
    public $layout_order;

    // other data generated
    public $fields;
    public $n_webforms;      // number of webforms using the layout - for warning on webform edit page
    public $n_items;         // number of items uploaded to webforms using the layout

    // non-key data stored on db
    public static $varlist = array(
        'layout_name'   => 'string',
        'max_num_items' => 'integer',
        'override'      => 'boolean',
        'restrictions'  => 'array',
        'extra_cols'    => 'array',
        'layout_order'  => 'integer',
    );

    // items selectable for display in spreadsheet
    protected static $display_data_item =
        // a value containing '>' is interpreted so that origin='object', value='property>key' becomes 'object->property[key]'
        // a value containing '|' is interpreted so that origin='object', value='property|key' where property is an array 
        //          becomes the concatenation of all object->property[key] values in the array
        //          e.g. item_data is an array of data whose keys are the field_ids
        //             item_data|pexcerpt becomes the concatenation of all uploaded excerpts for all image fields in the item
        // other values are interpreted so that origin='object', value='property' becomes 'object->property'
        array(
              'att' => array(  'header' => 'Attached To',   'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'item_data>attachedto' ),
              'aat' => array(  'header' => 'Added Title',   'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'item_data|ptitle' ),
              'aae' => array(  'header' => 'Added Excerpt', 'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'item_data|pexcerpt' ),
              'aac' => array(  'header' => 'Added Content', 'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'item_data|pcontent' ),
              'add' => array(  'header' => 'Added Item Data','dobject'=> 'item', 'origin' => 'EWZ Item',    'value' => 'item_data>admin_data' ),
              'dlc' => array(  'header' => 'Last Changed',  'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'last_change' ),
              'dtu' => array(  'header' => 'Upload Date',   'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'upload_date' ),
              'iid' => array(  'header' => 'WP Item ID',    'dobject' => 'item', 'origin' => 'EWZ Item',    'value' => 'item_id' ),
              'wft' => array(  'header' => 'Webform Title', 'dobject' => 'wform','origin' => 'EWZ Webform', 'value' => 'webform_title' ),
              'wid' => array(  'header' => 'WP Webform ID', 'dobject' => 'wform','origin' => 'EWZ Webform', 'value' => 'webform_id' ),
              'wfm' => array(  'header' => 'Webform Ident', 'dobject' => 'wform','origin' => 'EWZ Webform', 'value' => 'webform_ident' ),
              );

    protected static $display_data_user =
        array(
              'nam' => array(  'header' => 'Full Name',    'dobject' => 'user', 'origin' => 'WP User',   'value' => array('first_name',' ','last_name') ),
              'fnm' => array(  'header' => 'First Name',   'dobject' => 'user', 'origin' => 'WP User',   'value' => 'first_name' ),
              'lnm' => array(  'header' => 'Last Name',    'dobject' => 'user', 'origin' => 'WP User',   'value' => 'last_name' ),
              'mnm' => array(  'header' => 'Display Name', 'dobject' => 'user', 'origin' => 'WP User',   'value' => 'display_name' ),
              'mem' => array(  'header' => 'Email',        'dobject' => 'user', 'origin' => 'WP User',   'value' => 'user_email' ),
              'mid' => array(  'header' => 'WP User ID',   'dobject' => 'user', 'origin' => 'WP User',   'value' => 'ID' ),
              'mli' => array(  'header' => 'User Login',   'dobject' => 'user', 'origin' => 'WP User',   'value' => 'user_login' ),
              );


    /*
     * Check that a layout with the input id exists
     */
    public static function get_count($layout_id){
        assert( Ewz_Base::is_pos_int( $layout_id ) );
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM " . EWZ_LAYOUT_TABLE . 
                                               " WHERE layout_id = %d", $layout_id ) );
    }

    /*
     * Return an array of all the headers for the spreadsheet or item list
     */
    public static function get_all_display_headers(){
        $data = array_merge( self::$display_data_item, self::$display_data_user );

        foreach( Ewz_Custom_Data::$data as $customN => $header ){
            $data[$customN] = array( 'header'  => $header,
                                     'dobject' => 'custom',
                                     'origin'  => 'Custom',
                                    );
        }
        return $data;
    }

    /*
     * Return all the data for the spreadsheet or item list
     */
    public static function get_all_display_data(){
        $data = array_merge( self::$display_data_item, self::$display_data_user );

        foreach( Ewz_Custom_Data::$data as $customN => $header ){
            $data[$customN]  = array( 'header'  => $header,
                                      'dobject' => 'custom',
                                      'origin'  => 'Custom',
                                      'value'   => $customN,
                                    );
        }
        return $data;
    }

    /**
     * Get an extra data item ( user, item webform or custom info ) for display
     *
     * @param  $dobj       data source object: Ewz_Webform, Ewz_Item, Ewz_Custom or WP_User
     * @param  $keyholder  array with keys 'dtu',  'nam', ... -- extra data required for display
     *
     */
    public static function get_extra_data_item( $dobj, $keyholder ){
        assert( is_object( $dobj ) );
        assert( is_array( $keyholder ) || is_string( $keyholder ) );

        // if this is a user object, return blank if current user doesnt have permission to list
        if( isset( $dobj->user_login ) && !current_user_can( 'list_users' ) ){
            return '';
        }
        $value = '';
        if( is_array( $keyholder ) ){
            // may be used for custom data arrays
            foreach( $keyholder as $key ){
                if( isset( $dobj->$key ) ){
                    $value .= $dobj->$key;
                } else {
                    $value .= $key;
                }
            }
        } else {
            if( strpos(  $keyholder, '>' ) !== false ){
                $xx = explode( '>', $keyholder );
                if( isset( $dobj->{$xx[0]}[$xx[1]] ) ){
                    $value = $dobj->{$xx[0]}[$xx[1]];
                }  
            } elseif(  strpos(  $keyholder, '|' ) !== false ){
                $xx = explode( '|', $keyholder );
                if( is_array( $dobj->{$xx[0]} ) ){
                    $value = '';
                    foreach(  $dobj->{$xx[0]} as $field_id=>$fielddata ){
                        if( isset( $fielddata[$xx[1]] ) ){
                            $value .= $fielddata[$xx[1]];
                        }
                    }
                }
            } else {
                $value = $dobj->$keyholder;
            }
        }
        if( isset($value )){
            return $value;
        }
        return '';
      }


    /**
     * Return an array of all defined layouts
     *
     * @param   None
     * @param   callback    $class_or_obj  Class of filter function that must return true for the layout_id
     * @param   callback    $filter        Filter function that must return true for the layout_id
     * @return  array of all defined layouts visible to current user
     */
    public static function get_all_layouts( $filter = 'truefunc' )
    {
        global $wpdb;
        assert(is_string( $filter ) );

        $list = $wpdb->get_col( "SELECT layout_id  FROM " . EWZ_LAYOUT_TABLE . " ORDER BY layout_order" );
        $layouts = array();
        foreach ( $list as $layout_id ) {
            if ( call_user_func( array( 'Ewz_Permission',  $filter ), $layout_id ) ) {
                $layout = new Ewz_Layout( $layout_id );
                array_push( $layouts, $layout );
            }
        }
        return $layouts;
    }

    /**
     * Return the extra column info required for a spreadsheet download
     *
     * @param   int   $layout_id
     * @return  array of extra_cols
     */
    public static function get_extra_cols( $layout_id )
    {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $layout_id ) );
        $xcols = $wpdb->get_var( $wpdb->prepare( "SELECT extra_cols  FROM " .  EWZ_LAYOUT_TABLE . 
                                                 " WHERE layout_id = %d", $layout_id ) );
        $col_arr = unserialize( $xcols );
        if( !is_array( $col_arr ) ){
            error_log( "EWZ: failed to unserialize xcols for layout $layout_id");
            $col_arr = array();
        }
        return $col_arr;
    }


    /**
     * Return a string consisting of the html options for selecting a layout
     * NB: must return in layout_id order to match get_all_layouts
     * @param   callback    $class_or_obj  Class of filter function that must return true for the layout_id
     * @param   callback    $filter        Filter function that must return true for the layout_id
     * @param   int         $selected_id   Selected layout id, default 0
     * @return  string consisting of the html options for selecting a layout
     */
    public static function get_layout_opt_array( $filter = 'truefunc',
                                                 $selected_id = 0 )
    {
        global $wpdb;
        assert( Ewz_Base::is_nn_int( $selected_id ) );

        $options = array();
        $layouts = $wpdb->get_results( "SELECT layout_id, layout_name  FROM " . EWZ_LAYOUT_TABLE .
                                       " ORDER BY layout_order", OBJECT );
        foreach ( $layouts as $layout ) {
            if ( call_user_func( array( 'Ewz_Permission',  $filter ), $layout->layout_id ) ) {
                if ( $layout->layout_id == $selected_id ) {
                    $is_sel = true;
                } else {
                    $is_sel = false;
                }
                array_push( $options, array( 'value' => $layout->layout_id ,
                                             'display' => esc_html( $layout->layout_name ),
                                             'selected' => $is_sel ) );
            }
        }
        return $options;
    }

    /*
     * Used as a default filter when none is specified
     */
    public static function truefunc()
    {
        return true;
    }

    /**
     * Make sure the input layout_id is a valid one
     *
     * @param    int      $layout_id
     * @return   boolean  true if $layout_id is the key for a EWZ_LAYOUT_TABLE row, otherwise false
     */
    public static function is_valid_layout( $layout_id )
    {
        global $wpdb;
        assert( Ewz_Base::is_nn_int( $layout_id ) );
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_LAYOUT_TABLE .
                                                 " WHERE  layout_id = %d", $layout_id ) );
        return ( 1 == $count );
    }

     /**
      * Get a count of the layouts
      *
      * @return   int count
      */
     public static function count_layouts( ) {
         global $wpdb;
         $count = $wpdb->get_var(  "SELECT count(*) FROM " . EWZ_LAYOUT_TABLE  );
         return $count;
     }

     /* Save the order of the layouts
      *
      * @return  number of rows updated
      */
     public static function save_layout_order( $l_orders ) {
         global $wpdb;
         assert( is_array($l_orders['lorder']) );
         $n = 0;
         foreach( $l_orders['lorder'] as $layout_id => $order ){
             $n = $n + $wpdb->query($wpdb->prepare("UPDATE " . EWZ_LAYOUT_TABLE . " wf " .
                                                   "   SET layout_order = %d WHERE layout_id = %d ", $order, $layout_id ));  
         }
         return $n;
     }
 
     /**
      * Renumber the subsequent layouts when one is deleted
      */
     private static function renumber_layouts( $order ) {
         global $wpdb;
         assert( Ewz_Base::is_nn_int( $order ) );
         $wpdb->query($wpdb->prepare("UPDATE " . EWZ_LAYOUT_TABLE . " wf " .
                                     "   SET layout_order = layout_order - 1 WHERE  layout_order > %d " , $order ));  
     }
 
 
     /**
      * Deal with upgrade -- set layout_order field in layouts 
      * 
      */
     public static function set_layout_order(){
         global $wpdb;
         $list = $wpdb->get_col( "SELECT layout_id  FROM " . EWZ_LAYOUT_TABLE . " ORDER BY layout_id" );
         $n = 0;
         foreach ( $list as $layout_id ) {                      
             $wpdb->query( $wpdb->prepare( "UPDATE " . EWZ_LAYOUT_TABLE .
                                           "   SET layout_order = %d WHERE layout_id = %d ", $n, $layout_id ));
             ++$n;
         }
     }

    /*     * ****************** Object Functions ************************* */

    /*     * ******************** Construction *************************** */
    /**
     * Assign the object variables from an array
     *
     * Calls parent::base_set_data with the list of variables and the data structure
     *
     * @param  array   $data input data array.
     * @return none
     */
    protected function set_data( $data )
    {
        assert( is_array( $data ) );
        parent::base_set_data( array_merge( array('layout_id' => 'integer'),
                                            self::$varlist ),
                               $data );
    }

    /**
     * Constructor
     *
     * @param  mixed  $init  layout_id or array of data
     * @param  int    $inc_followup = self::INCLUDE_FOLLOWUP or self::EXCLUDE_FOLLOWUP
     * @return none
     */
    public function __construct( $init, $inc_followup = self::INCLUDE_FOLLOWUP )
    {
        // no assert
        if ( is_numeric( $init ) ) {
            $this->create_from_id( $init, $inc_followup );
        } elseif ( is_array( $init ) ) {
            if ( array_key_exists( 'layout_id', $init ) && $init['layout_id'] ) {
                $this->update_from_data( $init );
            } else {
                $this->create_from_data( $init );
            }
        }
    }

    /**
     * Create a new layout from the layout_id by getting the data from the database
     *
     * Creates the fields array from the database
     *
     * @param  int  $id  the layout id
     * @param  int  $inc_followup = self::INCLUDE_FOLLOWUP or self::EXCLUDE_FOLLOWUP
     * @return none
     */
    protected function create_from_id( $id, $inc_followup = self::INCLUDE_FOLLOWUP )
    {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $id ) );
        assert( $inc_followup == self::EXCLUDE_FOLLOWUP || $inc_followup == self::INCLUDE_FOLLOWUP );
        $dblayout = $wpdb->get_row( $wpdb->prepare(
                "SELECT layout_id, " . implode( ',', array_keys( self::$varlist ) ) .
                " FROM " . EWZ_LAYOUT_TABLE .
                " WHERE layout_id=%d", $id ), ARRAY_A );
        if ( !$dblayout ) {
            throw new EWZ_Exception( 'Unable to find layout', $id );
        }
        $this->set_data( $dblayout );
        $this->fields = Ewz_Field::get_fields_for_layout( $this->layout_id, 'pg_column', $inc_followup );

        $this->update_usage_counts();
    }

    /**
     * Create a layout object from $data, which contains a "layout_id" key
     *
     * Error if  $data['layout_id'] does not exist on database
     *
     * @param  array  $data
     * @return none
     */
    protected function update_from_data( $data )
    {
        global $wpdb;
        assert( is_array( $data ) );
        $ok = $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_LAYOUT_TABLE .
                        " WHERE layout_id = %d", $data['layout_id'] ) );
        if ( $ok != 1 ) {
            throw new EWZ_Exception( 'Unable to find layout', $data['layout_id'] );
        }

        $this->set_data( $data );
        $this->set_field_data( $data );
        $this->update_usage_counts();
    }

    /**
     * Create a new  layout object from $data, which has no "layout_id" key
     *
     * Set the new object's layout_id to 0 and it's usage counts to 0
     *
     * @param array $data
     * @return none
     */
    protected function create_from_data( $data )
    {
        assert( is_array( $data ) );
        $this->layout_id = 0;
        $this->n_webforms = 0;
        $this->n_items = 0;

        $this->set_data( $data );
        $this->set_field_data( $data );

        $this->check_errors();
    }

    /**
     * Set the "fields" array from $data
     *
     * Creates a new Ewz_Field object from each element of $data['fields']
     *
     * @param  array  $data
     * @return none
     */
    protected function set_field_data( $data )
    {
        assert( is_array( $data ) );
        $this->fields = array();
        if ( !array_key_exists( 'fields', $data ) ) {
            throw new EWZ_Exception( 'At least one field is required for a layout' );
        }
        foreach ( $data['fields'] as $num => $field_data ) {
            $field_data['layout_id'] = $this->layout_id;
            $this->fields[$num] = new Ewz_Field( $field_data );
        }
    }

    /**
     * Set "n_webforms" and "n_items" to the counts of matching webforms/items from the database
     * @return  none
     */
    protected function update_usage_counts()
    {
       
        $this->n_webforms = Ewz_Webform::get_count_for_layout( $this->layout_id );

        $this->n_items = Ewz_Webform::get_item_count_for_layout( $this->layout_id );
        
    }


    /*     * ******************  Object Functions  *************** */
    public function contains_followup(){
        foreach( $this->fields as $field ){
            if( $field->field_ident == 'followupQ' ){
                return $field->field_id;
            }
        }
        return 0;
    }

    /*     * ******************  Validation  *************** */

    /**
     * Check for various error conditions
     *
     * @param  none
     * @return none
     */
    protected function check_errors()
    {
        foreach ( self::$varlist as $key => $type ) {
            settype( $this->$key, $type );
        }

        global $wpdb;

        // check for duplicate keys
        $used = $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_LAYOUT_TABLE .
                        " WHERE layout_name = %s AND layout_id != %d", $this->layout_name,
                                         $this->layout_id ) );
        if ( $used > 0 ) {
            throw new EWZ_Exception( "Name '$this->layout_name' already in use for this layout"  );
        }

        // make sure restrictions apply to fields belonging to the layout
        foreach ( $this->restrictions as $restr ) {
            foreach ( array_keys( $restr ) as $key ) {
                if ( is_numeric( $key ) && !in_array( $key, array_keys( $this->fields ) ) ) {
                    throw new EWZ_Exception( "Invalid Restriction Field $key in restriction " .
                            $restr['msg'] );
                }
            }
        }
        $seen = array();
        // make sure pg_column is not the same in two different fields
        foreach ( $this->fields as $key => $field ) {
                if ( array_key_exists( $field->pg_column, $seen ) ) {
                    throw new EWZ_Exception( 'Two or more fields have the same column ' . $field->pg_column );
                } else {
                    $seen[$field->pg_column] = true;
                }
        }

        // make sure ss_column is not the same in two different fields
        $seen2 = array();
        foreach ( $this->fields as $key => $field ) {
            if ( isset( $field->ss_column ) && ( $field->ss_column >= 0 ) ) {
                    if ( array_key_exists( $field->ss_column, $seen2 ) ) {
                    throw new EWZ_Exception( 'Two or more fields have the same spreadsheet column ' .
                            $field->ss_column );
                    } else {
                        $seen2[$field->ss_column] = true;
                    }
            }
        }
        // if override has been changed from on to off, change the webforms
        if( $this->layout_id && !$this->override ) {
                $old_override = $wpdb->get_var( $wpdb->prepare( "SELECT override  FROM " . EWZ_LAYOUT_TABLE .
                                                                " WHERE layout_id = %d",  $this->layout_id ) );
                if( $old_override ){
                    $webforms = Ewz_Webform::get_webforms_for_layout( $this->layout_id );
                    foreach( $webforms as $webform ){
                        $webform->update_num_items( $this->max_num_items );
                    }
                }
        }
        return true;
    }

    /*     * ******************  Database Updates ********************* */

    /**
     * Save the layout to the database
     *
     * Check for permissions, then update or insert the layout data
     *                             update or insert the fields
     *
     * @param none
     * @return none
     */
    public function save()
    {
        global $wpdb;
        if( $this->layout_id ){
            if ( !Ewz_Permission::can_edit_layout( $this->layout_id ) ) {
                    throw new EWZ_Exception( 'Insufficient permissions to edit layout',
                            "$this->layout_id" );
            }
        } else {
            if ( !Ewz_Permission::can_edit_all_layouts() ) {
                throw new EWZ_Exception( 'Insufficient permissions to create a new layout' );
            }
        }
        $this->check_errors();
        // NB: layout_order is not saved here
        $data = stripslashes_deep( array(
                                         'layout_name' => $this->layout_name,          // %s
                                         'max_num_items' => $this->max_num_items,      // %d
                                         'override' => $this->override ? 1 : 0,        // %d
                                         'restrictions' => serialize( stripslashes_deep( $this->restrictions ) ),   // %s
                                         'extra_cols' => serialize( stripslashes_deep( $this->extra_cols ) ),       // %s
                                         ) );
        $datatypes = array( '%s', // = layout_name
                            '%d', // = max_num_items 
                            '%d', // = override
                            '%s', // = restrictions 
                            '%s', // = extra_cols 
                            );

        // update or insert the layout itself
        if ( $this->layout_id ) {
            $rows = $wpdb->update( EWZ_LAYOUT_TABLE, 
                                   $data,        array('layout_id' => $this->layout_id), 
                                   $datatypes,   array('%d') 
                                 );
            if ( $rows > 1 ) {
                throw new EWZ_Exception( 'Problem with update of layout ' . $this->layout_name );
            }
        } else {
            $n_layouts = self::count_layouts();
            $wpdb->insert( EWZ_LAYOUT_TABLE, $data, $datatypes );
            $this->layout_id = $wpdb->insert_id;
            if ( !$this->layout_id ) {
                throw new EWZ_Exception( 'Problem with creation of layout ' . $this->layout_name );
            }
            $wpdb->query($wpdb->prepare( "UPDATE " . EWZ_LAYOUT_TABLE . 
                                         "   SET layout_order = %d WHERE  layout_id = %d ", $n_layouts, $this->layout_id ) );  
        }

        // save the field data and fix up any restrictions ( need field id to do that )
        $havenewfield = false;
        foreach ( $this->fields as $field ) {
            $field->layout_id = $this->layout_id;
            $fid = $field->save();
            if( $fid ){
                $havenewfield = true;
                foreach( $this->restrictions as $n => $restr ){
                    $this->restrictions[$n][$fid] = '~*~';
                }
            }
        }
        $restrs = serialize( stripslashes_deep( $this->restrictions ) ); 
        // save the restrictions again
        if(  $havenewfield && $this->restrictions ){
            $rows = $wpdb->update( EWZ_LAYOUT_TABLE, 
                                   array( 'restrictions' => $restrs ),  array( 'layout_id' => $this->layout_id ),
                                   array( '%s' ),                       array( '%d' ) );
            if ( $rows != 1 ) {
                throw new EWZ_Exception( "$rows: Problem with update of restrictions for new fields  " . $this->layout_name );
            }
        }      
        return true;
    }

    /**
     * Delete a specified field from the database
     *
     * @param  int   $field_id
     * @return none
     */
    public function delete_field( $field_id )
    {
        assert( Ewz_Base::is_pos_int( $field_id ) );
        if ( !Ewz_Permission::can_edit_layout( $this->layout_id ) ) {
            throw new EWZ_Exception( 'Insufficient permissions to edit the layout', $this->layout_id );
        }

        // make sure this is a valid field for this layout
        $field = null;
        foreach ( $this->fields as $test_field ) {
            if ( $test_field->field_id == $field_id ) {
                $field = $test_field;
            }
        }
        if ( $field !== null ) {
            foreach( $this->restrictions as $n => $restr ){
                unset( $this->restrictions[$n][$field_id] );
            }
            return $field->delete();
        } else {
            throw new EWZ_Exception( 'Failed to find field to delete', $field_id );
        }
    }

    /**
     * Delete the layout and all its fields from the database
     * Fails if any webforms use the layout
     *
     * @param  int   $delete_forms = self::FAIL_IF_FORMS ( fail if any forms use this layout )
     *                               or self::DELETE_FORMS (delete all forms using this layout so long as they have no items)
     * @return none
     */
    public function delete( $delete_forms = self::FAIL_IF_FORMS )
    {
        assert( $delete_forms == self::DELETE_FORMS || $delete_forms == self::FAIL_IF_FORMS );
        global $wpdb;

        if ( !Ewz_Permission::can_edit_all_layouts() ) {
            throw new EWZ_Exception( 'Insufficient permissions to delete the layout',
                    $this->layout_id );
        }

        $all = self::get_all_layouts();
        if( count( $all ) < 2 ){
            throw new EWZ_Exception( "Attempt to delete the only layout ( id=$this->layout_id )" );
        }

        $webforms = Ewz_Webform::get_webforms_for_layout( $this->layout_id );
        if( $delete_forms ==  self::DELETE_FORMS ){
            foreach( $webforms as $wform ){
                // never delete forms containing items using this function
                // - force each form to be deleted separately
                $wform->delete( Ewz_Webform::FAIL_IF_ITEMS );
            }
        } else {
            $n = count( $webforms );
            if( ( $n > 0 ) ){
                throw new EWZ_Exception( "Attempt to delete layout with $n associated webforms." );
            }
        }

        do_action( 'ewz_before_delete_layout', $this->layout_id );

        foreach ( $this->fields as $field ) {
            $field->delete();
        }

        // now delete the layout and renumber the layout_order for the remaining ones
        $rowsaffected = $wpdb->query( $wpdb->prepare( "DELETE FROM " . EWZ_LAYOUT_TABLE .
                " WHERE layout_id = %d", $this->layout_id ) );
        if ( 1 == $rowsaffected ) {
            self::renumber_layouts($this->layout_order);
        } else {
            throw new EWZ_Exception( "Problem deleting layout '$this->layout_name '" );
        }
    }

}

