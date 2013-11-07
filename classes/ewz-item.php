<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "includes/ewz-common.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");

/* * ******************************************************************** */
/* Interaction with the EWZ_ITEM table.                                   */
/* An item corresponds to a single row of uploaded data from a webform    */
/* * ******************************************************************** */

class Ewz_Item extends Ewz_Base {

    // key
    public $item_id;
    // database
    public $user_id;
    public $webform_id;
    public $last_change;
    public $item_files;
    public $item_data;

    // Keep list of db data names/types as a convenience for iteration and so
    // we can easily add new ones. Dont include item_id here for safety
    public static $varlist = array(
        'user_id' => 'integer',
        'webform_id' => 'integer',
        'last_change' => 'string',
        'item_files' => 'array',
        'item_data' => 'array',
    );

    public static function get_itemcount_for_webform( $webform_id ) {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $webform_id ) );
        $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT count(*)  FROM " . EWZ_ITEM_TABLE .
                " WHERE webform_id = %d", $webform_id ) );
        return (int)$count;
    }

    /**
     * Return an array of items attached to the input webform_id
     *
     * @param   int $webform_id
     * @param   boolean $user_only
     * @return  array of Ewz_Items
     */
    public static function get_items_for_webform( $webform_id, $user_only ) {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $webform_id ) );
        assert( is_bool( $user_only ) );
        $clause = "";
        if ( $user_only ) {
            $clause = " AND user_id = " . get_current_user_id();
        }
        $list = $wpdb->get_results( $wpdb->prepare(
               "SELECT item_id  FROM " .
                EWZ_ITEM_TABLE . " WHERE webform_id = %d" . $clause . " ORDER BY item_id",
               $webform_id ) );
        $items = array( );
        foreach ( $list as $itm ) {
            $newItem = new Ewz_Item( $itm->item_id );
            array_push( $items, $newItem );
        }
        return $items;
    }

    /**
     * Return an array of the filepaths of all the image files attached
     * to any item on the database
     *
     * @return  array of pathnames
     */
    public static function get_all_item_files() {
        global $wpdb;
        $file_list = $wpdb->get_col( "SELECT item_files FROM " . EWZ_ITEM_TABLE );
        $fname_list = array( );
        foreach ( $file_list as $item_file ) {
            $item_file_arr = unserialize( $item_file );
            foreach ( $item_file_arr as $filedata ) {
                array_push( $fname_list, $filedata['fname'] );
            }
        }
        return $fname_list;
    }

    /**
     * Return those members of the input items list that match the $field_opts list of values.
     *
     * @param array   $field_opts   validated $_POST array from webforms page
     * @param array   $in_items     list of items to be filtered
     * @return array  those members of the input items list that match all the options
     */
    public static function filter_items( $field_opts, $extra_opts, $in_items ) {
        assert( is_array( $field_opts ) || empty( $field_opts ) );
        assert( is_array( $extra_opts ) || empty( $extra_opts ) );
        assert( is_array( $in_items ) );

        $out_items = array( );
        foreach ( $in_items as $item ) {
            // go through all the item's fields. If all match, add to $out_items
            foreach ( $field_opts as $field_id => $optval ) {
                if ( isset( $item->item_data[$field_id] ) && isset( $item->item_data[$field_id]['value'] ) ) {
                    $itemval = $item->item_data[$field_id]['value'];
                } else {
                    $itemval = NULL;
                }
                switch ( $optval ) {
                    case "~*~":           // anything
                        break;
                    case "~+~":           // not empty
                        if ( !$itemval ) {
                            continue 3;   // need continue3 because switch statement counts as a loop
                        }
                        break;
                    case "~-~":           // empty
                        if ( $itemval ) {
                            continue 3;
                        }
                        break;
                    default:             // exact match
                        if ( $itemval != $optval ) {
                            continue 3;
                        }
                }
            }
            array_push( $out_items, $item );
        }

        // only want those modified in the last 'uploaddays' days
        if ( isset( $extra_opts['uploaddays'] ) && ( $extra_opts['uploaddays'] > 0 ) ) {
            $days = $extra_opts['uploaddays'];
            $out_items = self::only_recent( $out_items, $days );
        }
        return $out_items;
    }

    /* Return only those members of the $items list that were changed within
     * the last $days days
     */
    static private function only_recent( $items, $days ) {
        assert( is_array( $items ) );
        assert( Ewz_Base::is_pos_int( $days ) );

        $filtered = array( );
        $seconds = 3600 * 24 * $days;
        foreach ( $items as $item ) {
            $changed = date_parse( $item->last_change );
            $changedtime = mktime( 0, 0, 0, $changed['month'], $changed['day'], $changed['year'] );
            if ( ( time() - $changedtime ) < $seconds ) {
                array_push( $filtered, $item );
            }
        }
        return $filtered;
    }

    /******************** Construction **************************/

    /**
     * Assign the object variables from an array
     *
     * Calls parent::base_set_data with the list of variables and the data structure
     *
     * @param  array $data: input data array.
     * @return none
     */
    public function set_data( $data ) {
        assert( is_array( $data ) );
        parent::base_set_data( array_merge( self::$varlist, array( 'item_id' => 'integer' ) ), $data );
    }

    /**
     * Constructor
     *
     * @param  mixed $init  item_id or array of data
     * @return none
     */
    public function __construct( $init ) {
        assert( Ewz_Base::is_pos_int( $init ) || ( is_array( $init )
                && isset( $init['user_id'] )
                && isset( $init['item_data'] )
                && isset( $init['webform_id'] ) ) );

        if ( Ewz_Base::is_pos_int( $init ) ) {
            $this->create_from_id( $init );
        } elseif ( is_array( $init ) ) {
            $this->create_from_data( $init );
        }
    }

    /**
     * Create a new item object from the item_id by getting the data from the database
     *
     * @param  int  $id: the item id
     * @return none
     */
    protected function create_from_id( $id ) {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $id ) );
        $dbitem = $wpdb->get_row(
                $wpdb->prepare( "SELECT item_id, " .
                        implode( ',', array_keys( self::$varlist ) ) .
                        " FROM " . EWZ_ITEM_TABLE . " WHERE item_id=%d", $id ), ARRAY_A );
        if ( !$dbitem ) {
            throw new EWZ_Exception( 'Unable to find matching item', $id );
        } else {
            $this->set_data( $dbitem );
        }
    }

    /**
     * Create an item object from $data
     *
     * @param  array  $data
     * @return none
     */
    protected function create_from_data( $data ) {
        assert( is_array( $data ) );
        if ( !array_key_exists( 'item_id', $data ) ) {
            $data['item_id'] = 0;
        }
        $this->set_data( $data );
        $this->check_errors();
    }

    /**
     * Return the layout id for the item's webform
     *
     * @return  int
     */
    protected function get_layout_id() {
        $webform = new Ewz_Webform( $this->webform_id );
        return $webform->layout_id;
    }

    protected function get_num_items_allowed(){
        $layout = new Ewz_Layout( $this->get_layout_id() );
        return $layout->max_num_items;
    }

    /********************  Validation  *******************************/

    /**
     * Check for various error conditions
     *
     * @param  none
     * @return none
     */
    protected function check_errors() {
        if ( isset( $this->item_files ) ) {
            if ( is_string( $this->item_files ) ) {
                $this->item_files = unserialize( $this->item_files );
            }
        }
        foreach ( self::$varlist as $key => $type ) {
            settype( $this->$key, $type );
        }
        if ( !get_user_by( 'id', $this->user_id ) ) {
            throw new EWZ_Exception( 'No such user', $this->user_id );
        }
        if ( !Ewz_Webform::is_valid_webform( $this->webform_id ) ) {
            throw new EWZ_Exception( 'No such webform', $this->webform_id );
        }
    }

    /* * ******************  Database Updates ********************* */

    /**
     * Add extra info uploaded via admin .csv file ( see webforms ), and save
     * This function must also validate, since the input comes from a
     * user-written text file, not a web page which would be validated earlier.
     *
     * @param post_title
     * @param post_excerpt
     * @param post_content
     * @return none
     */
    public function set_uploaded_info( $data ) {
        assert( is_array( $data ) );
        $ddata = $data;
        $layout_id = $this->get_layout_id();
        // $data is expected to have the following structure:
        // field_ident, title,  excerpt, content [, field_ident, title,  excerpt, content,... ]
        $row = 0;
        while ( isset( $ddata[0] ) ) {
            assert( is_string( $ddata[0] ) );
            $field_ident = array_shift( $ddata );

            ++$row;
            if ( !isset( $field_ident ) ) {
                throw new EWZ_Exception( "Missing field identifier in row $row of .csv file" );
            }
            $field_id = Ewz_Field::field_id_from_ident_arr(
                    array( 'layout_id' => $layout_id, 'field_ident' => $field_ident ) );

            $title = array_shift( $ddata );
            if ( !isset( $title ) ) {
                throw new EWZ_Exception( "Missing field title in row $row of .csv file" );
            }
            $excerpt = array_shift( $ddata );
            if ( !isset( $excerpt ) ) {
                throw new EWZ_Exception( "Missing field excerpt in row $row of .csv file" );
            }
            $content = array_shift( $ddata );
            if ( !isset( $content ) ) {
                throw new EWZ_Exception( "Missing field content in row $row of .csv file" );
            }

            // sanitize_text_field checks for invalid UTF-8, converts single < characters to entities,
            // strips all html tags, removes line breaks, tabs and extra white space, strips octets.
            $this->item_data[$field_id]['ptitle'] = sanitize_text_field( $title );
            $this->item_data[$field_id]['pexcerpt'] = sanitize_text_field( $excerpt );
            $this->item_data[$field_id]['pcontent'] = sanitize_text_field( $content );
        }
        $this->save();
    }

    /**
     * Save the item to the database
     *
     * Update or insert the item data
     *
     * @param none
     * @return none
     */
    public function save() {
        global $wpdb;

        $this->check_errors();  // raises exceptions if errors found
        if ( !( $this->user_id == get_current_user_id()   // user can edit own data
                ||
                Ewz_Permission::can_manage_webform( $this->webform_id ) )   // admin can manage webform
        ) {
            throw new EWZ_Exception( 'Insufficient permissions to edit item',
                    "item $this->item_id in webform $this->webform_id" );
        }

        //**NB: need to stripslashes *before* serialize, otherwise character counts are wrong
        // WP automatically adds slashes for quotes
        $data = stripslashes_deep( array(
            'user_id' => $this->user_id,
            'webform_id' => $this->webform_id,
            'item_data' => serialize( stripslashes_deep( $this->item_data ) ),
                ) );

        // don't update item_files unless there was a real image upload,

        // ***** This assumes that if any image for the item is uploaded, all are
        //       uploaded at once, which is what has to happen in the current gui.
        // *****

        //***** No stripslashes here - wp doesn't escape $_FILES as it does $_POST
        if ( isset( $this->item_files ) && count( $this->item_files ) > 0 ) {
            $data['item_files'] = serialize( $this->item_files ) ;
        }

        $datatypes = array( '%d', '%d', '%s', '%s' );
        if ( $this->item_id ) {
            $rows = $wpdb->update( EWZ_ITEM_TABLE,
                    $data,      array( 'item_id' => $this->item_id ),
                    $datatypes, array( '%d' ) );

            if ( ( false === $rows ) || ( $rows > 1 ) ) {
                throw new EWZ_Exception( 'Problem updating item ' . basename( $item_file['fname'] ) . ', please reload the page to see your current status.' ,  
                                         $this->item_id );
            }
            // only alter last_change if there really was a change
            if ( 1 == $rows ) {
                $wpdb->update( EWZ_ITEM_TABLE,
                        array( 'last_change' => current_time( 'mysql' ) ),
                        array( 'item_id' => $this->item_id ),
                        array( '%s' ),
                        array( '%d' ) );
            }
        } else {
            $errors = '';
            $num = $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_ITEM_TABLE .
                                                   " WHERE user_id = %d AND webform_id = %d ",
                                                   $this->user_id, $this->webform_id ) );
            if( $this->get_num_items_allowed() < ( $num + 1 ) ){
                foreach( $this->item_files as $item_file ){
                    $errors .= $this->delete_file( $item_file );
                }                
                throw new EWZ_Exception( 'Too many items uploaded, no data saved for ' . basename( $item_file['fname'] ) . "\n", $errors );
            } else {
                $data['last_change'] = current_time( 'mysql' );
                array_push( $datatypes, '%s' );


                $wpdb->insert( EWZ_ITEM_TABLE, $data, $datatypes );
                $inserted = $wpdb->insert_id;
                if ( !$inserted ) {
                    foreach( $this->item_files as $item_file ){
                        $errors .= $this->delete_file( $item_file );
                    }
                    throw new EWZ_Exception( 'Sorry, there was a problem creating the item '. basename( $item_file['fname'] ) . ", please refresh the page to see your current status.\n", $errors );
                }
            }
        }
    }

    /**
     * Delete the item from the database and it's files from the server
     *
     * @param  none
     * @return none
     */
    public function delete() {
        global $wpdb;

        if ( !( ( $this->user_id == get_current_user_id() )        // user can edit own data
                ||
                Ewz_Permission::can_manage_webform( $this->webform_id ) )   // admin can manage webform
        ) {
            throw new EWZ_Exception( 'Insufficient permissions to edit item', "item $this->item_id in webform $this->webform_id" );
        }

        $errors = '';
        $rows_deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM " . EWZ_ITEM_TABLE . " WHERE item_id = %d ", $this->item_id ) );
        assert( is_int($rows_deleted));
        if ( $rows_deleted > 1 ) {
            $errors = "WARNING: Deleting items from database: $rows_deleted rows deleted. ";
        } elseif ( 1 == $rows_deleted ) {
            $errors = '';
        } else {
            $errors = "ERROR: Failed to delete item from database. ";
        }

        // Attempt to delete all files, don't just raise exception after first failure
        foreach ( $this->item_files as $item_file ) {
            $errors .= $this->delete_file( $item_file );
        }
        if ( $errors ) {
            throw new EWZ_Exception( "Problem deleting item:\n$errors" );
        }
    }

    /**
     * Delete a file and its thumbnail
     * 
     * @param   $item_file  array   
     * @return  
     */
    public function   delete_file( $item_file ){
        assert( is_array( $item_file ) );

        $errmsg = '';
        if( isset( $item_file['fname']) && $item_file['fname'] ){
            $fname = $item_file['fname'];
            if ( file_exists( $fname ) ) {
                $status = unlink( "$fname" );
                if ( !$status ) {
                    $errmsg = "Failed to delete image file: " . basename( $fname );
                }
            } else {
                $errmsg = "Attempted to delete file " . basename( $fname ) . ", file not found.";
            }
        }

        if( isset( $item_file['thumb_url'] ) && $item_file['thumb_url'] ){
            $thumbname = ewz_url_to_file( $item_file['thumb_url'] );
            if ( file_exists( "$thumbname" ) ) {
                $status = unlink( "$thumbname" );
                if ( !$status ) {
                    $errmsg .= "\nFailed to delete thumbnail file for: " . basename( $fname );
                }
            } else {
                $errmsg .=  "\nAttempted to delete thumbnail file for " . basename( $fname ) . ", file not found.";
            }
        }
        return $errmsg;
    }
}

