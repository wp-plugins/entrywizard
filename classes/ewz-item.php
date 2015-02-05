<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "includes/ewz-common.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");

/* * ******************************************************************** */
/* Interaction with the EWZ_ITEM table.                                   */
/* An item corresponds to a single row of uploaded data from a webform    */
/* * ******************************************************************** */

class Ewz_Item extends Ewz_Base {

    const USER_ITEMS = 1;
    const ALL_ITEMS = 0;

    // key
    public $item_id;
    // database
    public $user_id;
    public $webform_id;
    public $last_change;
    public $upload_date;
    public $item_files;
    public $item_data;

    public $layout_id;
    
    // Keep list of db data names/types as a convenience for iteration and so
    // we can easily add new ones. Dont include item_id here for safety
    public static $varlist = array(
        'user_id' => 'integer',
        'webform_id' => 'integer',
        'last_change' => 'string',
        'upload_date' => 'string',
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
     * Deal with upgrade where last_change was previously the only date 
     * 
     */
    public static function set_upload_date() {
        global $wpdb;
        $wpdb->query("UPDATE " . EWZ_ITEM_TABLE . " SET upload_date = last_change WHERE upload_date IS NULL" );
    }

    public static function delete_user_items( $user_id ){
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $user_id ) );
        $wpdb->query( $wpdb->prepare( "DELETE from " . EWZ_ITEM_TABLE . " WHERE user_id = %d", $user_id ) );
    }

    /**
     * Return an array of items attached to the input webform_id
     *
     * @param   int $webform_id
     * @param   int $user_only     self::USER_ITEMS (belonging to logged-in user)
     *                             or self::ALL_ITEMS 
     * @return  array of Ewz_Items
     */
    public static function get_items_for_webform( $webform_id, $user_only ) {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $webform_id ) );
        assert( $user_only == self::USER_ITEMS || $user_only == self::ALL_ITEMS );
        $clause = "";
        if ( $user_only == self::USER_ITEMS ) {
            $clause = " AND user_id = " . get_current_user_id();
        }
        $list = $wpdb->get_results( $wpdb->prepare(
               "SELECT item_id  FROM " .
                EWZ_ITEM_TABLE . " WHERE webform_id = %d" . $clause . " ORDER BY item_id",
               $webform_id ), OBJECT );
        $items = array( );
        foreach ( $list as $itm ) {
            $newItem = new Ewz_Item( $itm->item_id );
            array_push( $items, $newItem );
        }
        return $items;
    }


    /**
     * Return those members of the input items list that match the $field_opts list of values.
     *
     * @param array   $field_opts   validated $_POST array from webforms page
     * @param array   $in_items     list of items to be filtered
     * @return array  those members of the input items list that match all the options
     */
    public static function filter_items( $field_opts, $custom_opts, $extra_opts, $in_items ) {
        assert( is_array( $field_opts ) || empty( $field_opts ) );
        assert( is_array( $custom_opts ) || empty( $custom_opts ) );
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
            
            $custom = new Ewz_Custom_Data( $item->user_id );
            foreach ( $custom_opts as $key => $value ){                
                if( ( $value != '~*~' ) && ( $custom->$key !=  $value ) ){
                    continue 2;      // go to next item
                } 
            }
            array_push( $out_items, $item );
        }

        // only want those uploaded in the last 'uploaddays' days
        if ( isset( $extra_opts['uploaddays'] ) && ( $extra_opts['uploaddays'] > 0 ) ) {
            $days = $extra_opts['uploaddays'];
            $out_items = self::only_recent( $out_items, $days );
        }
        return $out_items;
    }

    /* Return only those members of the $items list that were uploaded within
     * the last $days days
     */
    private static function only_recent( $items, $days ) {
        assert( is_array( $items ) );
        assert( Ewz_Base::is_pos_int( $days ) );

        $filtered = array( );
        $seconds = 3600 * 24 * $days;
        // upload_date added in 0.9.9 -- use last_change if no value
        $curr_tz = date_default_timezone_get();
        $tz_opt = get_option('timezone_string'); 
        if( $tz_opt ){ 
            date_default_timezone_set( $tz_opt ); 
        } 
        foreach ( $items as $item ) {
             // strtotime returns unix timestamp
            $uploadedtime = strtotime( ( empty( $item->upload_date ) ?  $item->last_change : $item->upload_date )  );
            $now = time();  // current unix timestamp
            if ( ( $now - $uploadedtime ) < $seconds ) {
                array_push( $filtered, $item );
            }
        }
        if( $tz_opt ){
            date_default_timezone_set( $curr_tz );
        }
        return $filtered;
    }

    /******************** Construction **************************/

    /**
     * Assign the object variables from an array
     *
     * Calls parent::base_set_data with the list of variables and the data structure
     *
     * @param  array $data input data array.
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
     * @param  int  $id the item id
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
        $webform = new Ewz_Webform( $this->webform_id );
        $this->layout_id = $webform->layout_id;
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
        $webform = new Ewz_Webform( $this->webform_id );
        $this->layout_id = $webform->layout_id;
        $this->check_errors();
    }


    protected function get_num_items_allowed(){
        $webform = new Ewz_Webform( $this->webform_id);
        return $webform->num_items;
    }

    /********************  Validation  *******************************/

    /**
     * Check for various error conditions
     *
     * @param  none
     * @return none
     */
    protected function check_errors() {
        global $wpdb;

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
        foreach( $this->item_data as $data_id => $field_data ){
            if( ( $data_id != 'attachedto' ) && ( $data_id != 'admin_data' ) ){
                if( !Ewz_Field::is_valid_field( $data_id, $this->layout_id ) ){
                    throw new EWZ_Exception( "No such field for the layout", "field $data_id, layout " . $this->layout_id );
                }
            }
        }

        // check for change of owner or webform ( should not happen )
        if( $this->item_id ) {
            $dbitem = $wpdb->get_row(
                    $wpdb->prepare( "SELECT user_id, webform_id" .
                            " FROM " . EWZ_ITEM_TABLE . " WHERE item_id=%d", $this->item_id ), ARRAY_A );
            
            if ( !$dbitem ) {
                throw new EWZ_Exception( 'Unable to find matching item', $this->item_id );
            } 
            if ( $dbitem['user_id'] != $this->user_id ){
              throw new EWZ_Exception( 'Invalid user for item', "Uploaded by {$this->user_id}, owner " . $dbitem['user_id'] );
            }   
            if ( $dbitem['webform_id'] != $this->webform_id ){
              throw new EWZ_Exception( 'Invalid webform for item', "Uploaded by {$this->user_id}, owner " . $dbitem['user_id'] );
            }
        }               
    }

    /******************** Utilities  *******************************/
    /**
     * Record the page each image was attached to
     */
    public function record_attachment( $post_id ){
        assert( Ewz_Base::is_pos_int( $post_id ) );
        $title =  get_the_title( $post_id );
        if( isset( $this->item_data['attachedto'] ) ){
            $this->item_data['attachedto'] .= ', ';
        } else {
            $this->item_data['attachedto'] = '';
        }
        $this->item_data['attachedto'] .= $title;
        $this->save();
    }




    /* * ******************  Database Updates ********************* */

    /**
     * Add extra info uploaded via admin .csv file ( see webforms ), and save
     * This function must also validate, since the input comes from a
     * user-written text file, not a web page which would be validated earlier.
     *
     * @param $ddata ( field_ident, title,  excerpt, content [, field_ident, title,  excerpt, content,... ] )
     * @return none
     */
    public function set_uploaded_info( $ddata ) {
        assert( is_array( $ddata ) );
        // $ddata is expected to have the following structure:
        // field_ident, title,  excerpt, content [, field_ident, title,  excerpt, content,... ]
        $col = 1;
        while ( isset( $ddata[0] ) ) {
            assert( is_string( $ddata[0] ) );
            $field_ident = array_shift( $ddata );

            ++$col;
            if ( !isset( $field_ident ) ) {
                throw new EWZ_Exception( "Missing field identifier for $this->item_id in column $col of .csv file" );
            }
            $field_id = Ewz_Field::field_id_from_ident_arr(
                    array( 'layout_id' => $this->layout_id, 'field_ident' => $field_ident ) );

            $title = array_shift( $ddata );
            if ( !isset( $title ) ) {
                throw new EWZ_Exception( "Missing field title for $this->item_id in column $col of .csv file" );
            }
            $excerpt = array_shift( $ddata );
            if ( !isset( $excerpt ) ) {
                throw new EWZ_Exception( "Missing field excerpt for $this->item_id in column $col of .csv file" );
            }
            $content = array_shift( $ddata );
            if ( !isset( $content ) ) {
                throw new EWZ_Exception( "Missing field content for $this->item_id in column $col of .csv file" );
            }
            // wp_kses checks for invalid UTF-8, converts single < characters to entities,
            // *** strips all html tags except those in $allowed_html***,
            // removes other line breaks, tabs and extra white space, strips octets.

            // what wp allows in comments, plus <br>
            global $allowedtags; 

            $allowed_html = $allowedtags;
            $allowed_html['br'] = array();   

            // this is needed because the strings are single-quoted in some javascript
            $title = str_replace( "'", '&#039;', $title);
            $excerpt = str_replace( "'", '&#039;', $excerpt);
            $content = str_replace( "'", '&#039;', $content);

            $this->item_data[$field_id]['ptitle']   = wp_kses( $title,   $allowed_html );
            $this->item_data[$field_id]['pexcerpt'] = wp_kses( $excerpt, $allowed_html );
            $this->item_data[$field_id]['pcontent'] = wp_kses( $content, $allowed_html );
        }
        $this->save();
    }
    /**
     * Add extra data uploaded via admin .csv file ( see webforms ), and save
     * This function must also validate, since the input comes from a
     * user-written text file, not a web page which would be validated earlier.
     *
     * @param $admin_data ( field_ident, data )
     * @return none
     */
    public function set_uploaded_admin_data( $admin_data ) {
        assert( is_string( $admin_data ) );
        if ( !isset( $admin_data ) ) {
            throw new EWZ_Exception( "Missing field admin_data for item $this->item_id in .csv file" );
        }

        // wp_kses checks for invalid UTF-8, converts single < characters to entities,
        // *** strips all html tags except those in $allowed_html***,
        // removes other line breaks, tabs and extra white space, strips octets.

        // what wp allows in comments, plus <br>
        global $allowedtags; 

        $allowed_html = $allowedtags;
        $allowed_html['br'] = array();   

        // this is needed because the strings are single-quoted in some javascript
        $admin_data = str_replace( "'", '&#039;', $admin_data);

        $this->item_data['admin_data']   = wp_kses( $admin_data, $allowed_html );
        
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
                Ewz_Permission::can_manage_webform( $this->webform_id, $this->layout_id ) )   // admin can manage webform
        ) {
            throw new EWZ_Exception( 'Insufficient permissions to edit item',
                    "item $this->item_id in webform $this->webform_id" );
        }

        // for saving last_change and upload_date
        $curr_tz = date_default_timezone_get();
        $tz_opt = get_option('timezone_string'); 
        if( $tz_opt ){ 
            date_default_timezone_set( $tz_opt ); 
        } 

        //**NB: need to stripslashes *before* serialize, otherwise character counts are wrong
        // WP automatically adds slashes for quotes
        $data = stripslashes_deep( array(
                                         'user_id' => $this->user_id,            // %d
                                         'webform_id' => $this->webform_id,      // %d
                                         'item_data' => serialize( stripslashes_deep( $this->item_data ) ),  // %s
                                         ) );
        // *** don't update item_files unless there was a real image upload ***
        // item_files is set from the uploaded data, so does not contain any previously uploaded image files
        // when it is saved, it overwrites anything that was already there


        $datatypes = array( '%d',  // = user_id
                            '%d',  // = webform_id
                            '%s',  // = item_data
                            );

        if ( isset( $this->item_files ) && count( $this->item_files ) > 0 ) {
            // No stripslashes here - wp doesn't escape $_FILES as it does $_POST
            $data['item_files'] = serialize( $this->item_files ) ;  
            array_push( $datatypes, '%s' );
        }

        if ( $this->item_id ) {
           $rows = $wpdb->update( EWZ_ITEM_TABLE,
                                  $data,      array( 'item_id' => $this->item_id ),
                                  $datatypes, array( '%d' ) );

            if ( ( false === $rows ) || ( $rows > 1 ) ) {
                throw new EWZ_Exception( 'Problem updating item, please reload the page to see your current status.' ,
                                         $this->item_id );
            }
            // only alter last_change if there really was a change
            if ( 1 == $rows ) {
                $wpdb->update( EWZ_ITEM_TABLE,
                               array( 'last_change' => current_time( 'mysql' ) ), array( 'item_id' => $this->item_id ),
                               array( '%s' ),                                     array( '%d' ) 
                             );
            }

        } else {
            $errors = '';
            $num = $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_ITEM_TABLE .
                                                   " WHERE user_id = %d AND webform_id = %d ",
                                                   $this->user_id, $this->webform_id ) );
            if( $this->get_num_items_allowed() < ( $num + 1 ) ){
                if ( isset( $this->item_files ) ){
                    foreach( $this->item_files as $item_file ){
                        $errors .= $this->delete_file( $item_file );
                    }
                }
                throw new EWZ_Exception( "Too many items uploaded, no data saved.\n", $errors );
            } else {
                // actually creating a new item
                if( !isset( $data['item_files'] ) ){
                    $data['item_files'] = serialize( array() ) ;
                }
                $data['upload_date'] = current_time( 'mysql' );
                array_push( $datatypes, '%s' );
                $data['last_change'] = $data['upload_date'];
                array_push( $datatypes, '%s' );


                $wpdb->insert( EWZ_ITEM_TABLE, $data, $datatypes );
                $inserted = $wpdb->insert_id;
                if ( !$inserted ) {
                    if ( isset( $this->item_files ) ){
                        foreach( $this->item_files as $item_file ){
                            $errors .= $this->delete_file( $item_file );
                        }
                    }
                    throw new EWZ_Exception( "Sorry, there was a problem saving some items, please refresh the page to see your current status.\n", $errors );
                }
            }
        }
        if( $tz_opt ){
            date_default_timezone_set( $curr_tz );
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
                Ewz_Permission::can_manage_webform( $this->webform_id, $this->layout_id ) )   // admin can manage webform
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
        if( isset( $this->item_files ) ){
            foreach ( $this->item_files as $item_file ) {
                $errors .= $this->delete_file( $item_file );
            }
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

