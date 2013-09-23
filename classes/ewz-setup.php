<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "/classes/ewz-permission.php");
require_once( EWZ_PLUGIN_DIR . "/includes/ewz-common.php");

/** ***************************************************
 * static functions that run during activation/deactivation
 * uninstall is done in uninstall.php
 * ************************************************** */

class Ewz_Setup
{
    public static function activate_or_install_ewz()
    {
        global $wpdb;

        // Make sure all the global constants get set
        if( !defined( 'EWZ_LAYOUT_TABLE' ) ){
            ewz_init_globals();
        }
        
        self::create_custom_file();

        $layout_table = EWZ_LAYOUT_TABLE;
        $field_table = EWZ_FIELD_TABLE;
        $webform_table = EWZ_WEBFORM_TABLE;
        $item_table = EWZ_ITEM_TABLE;

        // will just update if they already exist
        self::create_db_tables();

        $rowcount = $wpdb->get_var( "SELECT count(*) FROM $layout_table" );
        if( !file_exists(  EWZ_IMG_UPLOAD_DIR ) ){
            mkdir( EWZ_IMG_UPLOAD_DIR );
        }

        // return if there is data already in the tables
        if( $rowcount > 0 ){
            return;
        }

        // there is no existing data, create the sample stuff
        $wpdb->query("ALTER TABLE $layout_table AUTO_INCREMENT=33;");
        $wpdb->query("ALTER TABLE $field_table AUTO_INCREMENT=152;");
        $wpdb->query("ALTER TABLE $webform_table AUTO_INCREMENT=287;");
        $wpdb->query("ALTER TABLE $item_table AUTO_INCREMENT=413;");

        $ids1 = self::create_base_layout1();
        $ids2 = self::create_base_layout2();

        $webform_id1 = self::create_sample_webform( "Standard Competition Format", "standard", $ids1['layout_id'] );
        $webform_id2 = self::create_sample_webform( "Image Pair Form", "pair", $ids2['layout_id'] );

        self::create_sample_data1( EWZ_IMG_UPLOAD_DIR, $webform_id2, "standard", $ids2 );
        self::set_initial_permissions();
    }

    /** ***************************************************
     * Create the database tables, and insert two rows in the layout table
     * ************************************************** */
    public static function create_db_tables()
    {
        global $ewz_db_version;
        $ewz_db_version = "1.0";

        error_log( "EWZ: creating tables " );
        $layout_table = EWZ_LAYOUT_TABLE;
        $field_table = EWZ_FIELD_TABLE;
        $webform_table = EWZ_WEBFORM_TABLE;
        $item_table = EWZ_ITEM_TABLE;

        //You must put each field on its own line in your SQL statement.
        //You must have two spaces between the words PRIMARY KEY and the definition of your primary key.
        //You must use the key word KEY rather than its synonym INDEX and you must include at least one KEY.
        //You must not use any apostrophes or backticks around field names.

        //NB: wordpress does not support table constraints (!)

        $create_layout_sql = "CREATE TABLE $layout_table (
                                           layout_id smallint(6) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                           layout_name char(60) NOT NULL UNIQUE,
                                           max_num_items smallint(3) NOT NULL,
                                           restrictions longtext NULL,
                                           extra_cols longtext NULL
                                                     );";


        $create_field_sql = "CREATE TABLE $field_table (
                                              field_id mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                              layout_id mediumint(9) NOT NULL,
                                              field_type char(3) NOT NULL,
                                              field_header char(50) NOT NULL,
                                              field_ident char(15) NOT NULL,
                                              required tinyint(1) NOT NULL,
                                              pg_column smallint(3) NOT NULL,
                                              ss_column smallint(3) NOT NULL,
                                              fdata longtext NOT NULL
                             );";


        $create_webform_sql = "CREATE TABLE $webform_table (
                                                webform_id smallint(6) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                                layout_id smallint(6) NOT NULL,
                                                webform_title varchar(100) NOT NULL UNIQUE,
                                                webform_ident char(15) NOT NULL UNIQUE,
                                                prefix char(25) NULL,
                                                upload_open tinyint(1) NOT NULL,
                                                open_for varchar(1000) NOT NULL
                                               ); ";


        $create_item_sql = "CREATE TABLE $item_table (
                                              item_id mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                              user_id bigint(20) NOT NULL,
                                              webform_id smallint(6) NOT NULL,
                                              last_change datetime NOT NULL,
                                              item_files mediumtext NOT NULL,
                                              item_data longtext NOT NULL
                                                               );";


        // item_data and item_files are indexed by field_id for convenient access
        // item_data  == ( field_id => ('field_id'=> field_id, 'value' => value),
        //                   field_id => ('field_id'=> field_id, 'value' => value),
        //                   ....  )
        // item_files == ( field_id => ('field_id'=> field_id, 'fname'=> filename, 'thumb_url'=>thumburl, 'type'=>type),
        //                 field_id => ('field_id'=> field_id, 'fname'=> filename, 'url'=>thumburl, 'type'=>type),
        //                    .... )

        // The dbDelta function examines the current table structure, compares it to the desired table structure,
        // and either adds or modifies the table as necessary, so it can be very handy for updates
        // (see wp-admin/upgrade-schema.php for more examples of how to use dbDelta).
        // Note that the dbDelta function is rather picky, however. For instance:
        // You have to put each field on its own line in your SQL statement.
        // You have to have two spaces between the words PRIMARY KEY and the definition of your primary key.
        // You must use the key word KEY rather than its synonym INDEX and you must include at least one KEY.

        // need this for the dbDelta function, not automatically loaded
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // create the tables if they don't already exist

            dbDelta( $create_layout_sql );
            dbDelta( $create_field_sql );
            dbDelta( $create_webform_sql );
            dbDelta( $create_item_sql );

            add_option( "ewz_db_version", $ewz_db_version );
    }

    /* ***************************************************
     * For development, deactivate removes all data
     * ************************************************** */

    public static function deactivate_ewz()
    {
        error_log( "EWZ: deactivating" );
        if ( CLEANUP_ON_DEACTIVATE ) {
            self::uninstall_ewz();
        }
        error_log( "EWZ: deactivated" );
    }

    /* ***************************************************
     * Drop our database tables and remove any entries we created in WP tables
     * ************************************************** */

    public static function uninstall_ewz()
    {

        // Make sure all the global constants get set
        if( !defined( 'EWZ_LAYOUT_TABLE' ) ){
            ewz_init_globals();
        }

        error_log( "EWZ: removing " . EWZ_IMG_UPLOAD_DIR );
        self::rrmdir( EWZ_IMG_UPLOAD_DIR );

        global $wpdb;

        error_log( "EWZ: removing all permissions" );
        Ewz_Permission::remove_all_ewz_perms();

        $meta_ids = $wpdb->get_col( $wpdb->prepare(
                                                   'SELECT umeta_id FROM ' . $wpdb->usermeta . " WHERE meta_key = '%s'" , 'ewz_itemsperpage' ) );

        foreach ( $meta_ids as $umeta_id ) {
            delete_metadata_by_mid( 'user', $umeta_id );
        }

        error_log( "EWZ: dropping tables" );
        $layout_table = EWZ_LAYOUT_TABLE;
        $field_table = EWZ_FIELD_TABLE;
        $webform_table = EWZ_WEBFORM_TABLE;
        $item_table = EWZ_ITEM_TABLE;

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$item_table'" ) == $item_table ) {
            error_log( "EWZ: dropping $item_table" );
            $wpdb->query( "DROP Table " . EWZ_ITEM_TABLE );
        }
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$webform_table'" ) == $webform_table ) {
            error_log( "EWZ: dropping $webform_table" );
            $wpdb->query( "DROP Table " . EWZ_WEBFORM_TABLE );
        }
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$field_table'" ) == $field_table ) {
            error_log( "EWZ: dropping $field_table" );
            $wpdb->query( "DROP Table " . EWZ_FIELD_TABLE );
        }
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$layout_table'" ) == $layout_table ) {
            error_log( "EWZ: dropping $layout_table" );
            $wpdb->query( "DROP Table " . EWZ_LAYOUT_TABLE );
            $found = $wpdb->get_var( "SHOW TABLES LIKE '$layout_table'" );
            error_log( "EWZ: checking for " . EWZ_LAYOUT_TABLE . " found $found");
        }
    }

    /*	 * **************************************************
     * Create two sample layouts to act as the starting point
     * ************************************************** */

    public static function create_base_layout1()
    {
        global $wpdb;
        $layout_table = EWZ_LAYOUT_TABLE;
        $field_table = EWZ_FIELD_TABLE;

        $type_field1 = array(
                             'field_type' => 'opt',
                             'field_header' => 'Type',
                             'field_ident' => 'Tp',
                             'required' => true,
                             'pg_column' => 0,
                             'ss_column' => 3,
                             'fdata' => array(
                                              'options' => array(
                                                                 array( 'value' => 'P',
                                                                        'label' => 'Print',
                                                                        'maxnum' => 0,
                                                                        ),
                                                                 array('value' => 'D',
                                                                       'label' => 'Digital',
                                                                       'maxnum' => 0,
                                                                       ),
                                                                 array('value' => 'S',
                                                                       'label' => 'Slide',
                                                                       'maxnum' => 0,
                                                                       ),
                                                                 ),
                                              ),
                             );

        $cat_field1 = array(
                            'field_type' => 'opt',
                            'field_header' => 'Category',
                            'field_ident' => 'Cat',
                            'required' => false,
                            'pg_column' => 1,
                            'ss_column' => 4,
                            'fdata' => array('options' => array(array('value' => 'A',
                                                                      'label' => 'Assigned Topic',
                                                                      'maxnum' => 2,
                                                                      ),
                                                                array('value' => 'P',
                                                                      'label' => 'Pictorial',
                                                                      'maxnum' => 2,
                                                                      ),
                                                                ),
                                             ),
                            );

        $title_field1 = array(
                              'field_type' => 'str',
                              'field_header' => 'Title',
                              'field_ident' => 'Title',
                              'required' => true,
                              'pg_column' => 2,
                              'ss_column' => 5,
                              'fdata' => array(
                                               'fieldwidth' => 20,
                                               'maxstringchars' => 50,
                                               'ss_col_fmt' => -1,
                                               ),
                              );

        $img_field1 = array(
                            'field_type' => 'img',
                            'field_header' => 'Image File',
                            'field_ident' => 'Filename',
                            'required' => false,
                            'pg_column' => 3,
                            'ss_column' => 0,
                            'fdata' => array(
                                             'max_img_w' => 1280,
                                             'ss_col_w'  => 14,
                                             'max_img_h' => 1024,
                                             'ss_col_h'  => 15,
                                             'canrotate' => true,
                                             'ss_col_o'  => 16,
                                             'max_img_size' => 1,
                                             'min_img_area' => 864000,
                                             'allowed_image_types' => array('image/jpeg',
                                                                            'image/pjpeg',
                                                                            'image/gif',
                                                                            ),
                                             ),
                            );



        $rows_affected = $wpdb->insert( $layout_table,
                                        array( 'layout_name' => "Standard Competition",
                                               'max_num_items' => 4,
                                               'restrictions' => '',
                                               'extra_cols' => ''
                                               ),
                                        array('%s', '%s', '%d', '%s')
                                        );
        assert($rows_affected === 1);
        $layout_id1 = $wpdb->insert_id;

        $rows_affected1 = $wpdb->insert( $field_table,
                                        array('layout_id' => $layout_id1,
                                              'field_type' => $type_field1['field_type'],
                                              'field_header' => $type_field1['field_header'],
                                              'field_ident' => $type_field1['field_ident'],
                                              'required' => $type_field1['required'],
                                              'pg_column' => $type_field1['pg_column'],
                                              'ss_column' => $type_field1['ss_column'],
                                              'fdata' => serialize( $type_field1['fdata'] )
                                              ) );
        $field_id1 = $wpdb->insert_id;
        assert($rows_affected1 === 1);

        $rows_affected2 = $wpdb->insert( $field_table,
                                         array('layout_id' => $layout_id1,
                                               'field_type' => $cat_field1['field_type'],
                                               'field_header' => $cat_field1['field_header'],
                                               'field_ident' => $cat_field1['field_ident'],
                                               'required' => $cat_field1['required'],
                                               'pg_column' => $cat_field1['pg_column'],
                                               'ss_column' => $cat_field1['ss_column'],
                                               'fdata' => serialize( $cat_field1['fdata'] )
                                               ) );
        $field_id2 = $wpdb->insert_id;
        assert($rows_affected2 === 1);

        $rows_affected3 = $wpdb->insert( $field_table,
                                        array('layout_id' => $layout_id1,
                                              'field_type' => $title_field1['field_type'],
                                              'field_header' => $title_field1['field_header'],
                                              'field_ident' => $title_field1['field_ident'],
                                              'required' => $title_field1['required'],
                                              'pg_column' => $title_field1['pg_column'],
                                              'ss_column' => $title_field1['ss_column'],
                                              'fdata' => serialize( $title_field1['fdata'] )
                                              ) );
        $field_id3 = $wpdb->insert_id;
        assert($rows_affected3 === 1);


        $rows_affected4 = $wpdb->insert( $field_table,
                                        array('layout_id' => $layout_id1,
                                              'field_type' => $img_field1['field_type'],
                                              'field_header' => $img_field1['field_header'],
                                              'field_ident' => $img_field1['field_ident'],
                                              'required' => $img_field1['required'],
                                              'pg_column' => $img_field1['pg_column'],
                                              'ss_column' => $img_field1['ss_column'],
                                              'fdata' => serialize( $img_field1['fdata'] )
                                              ) );
        $field_id4 = $wpdb->insert_id;
        assert($rows_affected4 === 1);


        $restr_data = array(
                            array( $field_id1 => 'D',
                                   $field_id2 => '~*~',
                                   $field_id3 => '~*~',
                                   $field_id4 => '~-~',
                                   'msg' => 'A Digital type must have an Image File'
                                   ));
        $xtra_data = array( "mnm" => 1,
                            "mem" => 2,
                            "dtu" => 10,
                            "iid" => -1,
                            "mid" => -1,
                            "mli" => 7,
                            );
        $wpdb->update( $layout_table,
                       array('restrictions' => serialize( $restr_data ),
                             'extra_cols' => serialize( $xtra_data )
                             ),
                       array('layout_id' => $layout_id1), array('%s')
                       );

        return array( 'field_id1'=>$field_id1,
                      'field_id2'=>$field_id2,
                      'field_id3'=>$field_id3,
                      'field_id4'=>$field_id4,
                      'layout_id'=>$layout_id1,
                      );
    }

    public static function create_base_layout2()
    {
        global $wpdb;
        $layout_table = EWZ_LAYOUT_TABLE;
        $field_table = EWZ_FIELD_TABLE;

        // Set up the fields

        $div_field2 = array(
                            'field_type'   => 'opt',
                            'field_header' => 'Division',
                            'field_ident'  => 'Division',
                            'required'     => true,
                            'pg_column'    => 0,
                            'ss_column'    => 7,
                            'fdata'        => array( 'options' => array( array('value' => 'N',
                                                                               'label' => 'Nature',
                                                                               'maxnum' => 2,
                                                                               ),
                                                                         array('value' => 'P',
                                                                               'label' => 'Portrait',
                                                                               'maxnum' => 2,
                                                                               ),
                                                                         array('value' => 'S',
                                                                               'label' => 'Still Life',
                                                                               'maxnum' => 2,
                                                                               ),
                                                                         ),
                                                     ),
                            );

        $title_field2 = array(
                              'field_type' => 'str',
                              'field_header' => 'Caption',
                              'field_ident' => 'caption',
                              'required' => false,
                              'pg_column' => 1,
                              'ss_column' => 9,
                              'fdata' => array(
                                               'fieldwidth' => 20,
                                               'maxstringchars' => 50,
                                               'ss_col_fmt' => -1,
                                               ),
                              );

        $orig_field2 = array(
                             'field_type' => 'img',
                             'field_header' => 'Original Image',
                             'field_ident' => 'original',
                             'required' => false,
                             'pg_column' => 2,
                             'ss_column' => 10,
                             'fdata' => array(
                                              'max_img_w' => 1280,
                                              'ss_col_w'  => -1,
                                              'max_img_h' => 1024,
                                              'ss_col_h'  => -1,
                                              'canrotate' => false,
                                              'ss_col_o'  => -1,
                                              'max_img_size' => 1,
                                              'min_img_area' => 864000,
                                              'allowed_image_types' => array('image/jpeg',
                                                                             'image/pjpeg',

                                                                             ),
                                              ),
                             );
        $final_field2 = array(
                              'field_type' => 'img',
                              'field_header' => 'Final Image',
                              'field_ident' => 'final',
                              'required' => true,
                              'pg_column' => 3,
                              'ss_column' => 0,
                              'fdata' => array(
                                               'max_img_w' => 1280,
                                               'ss_col_w'  => 14,
                                               'max_img_h' => 1024,
                                               'ss_col_h'  => 15,
                                               'canrotate' => false,
                                               'ss_col_o'  => 16,
                                               'max_img_size' => 1,
                                               'min_img_area' => 864000,
                                               'allowed_image_types' => array('image/jpeg',
                                                                              'image/pjpeg',

                                                                              ),
                                               ),
                              );

        // create the layout
        $rows_affected = $wpdb->insert( $layout_table,
                                        array( 'layout_name' => "Image Pair Layout",
                                               'max_num_items' => 4,
                                               )
                                        );
        $layout_id2 = $wpdb->insert_id;
        assert( $rows_affected === 1 );


        // insert the fields
        $rows_affected1 = $wpdb->insert( $field_table,
                                        array( 'layout_id' => $layout_id2,
                                               'field_type' => $div_field2['field_type'],
                                               'field_header' => $div_field2['field_header'],
                                               'field_ident' => $div_field2['field_ident'],
                                               'required' => $div_field2['required'],
                                               'pg_column' => $div_field2['pg_column'],
                                               'ss_column' => $div_field2['ss_column'],
                                               'fdata' => serialize( $div_field2['fdata'] )
                                               ) );
        $field_id1 = $wpdb->insert_id;
        assert( $rows_affected1 === 1 );


        $rows_affected2 = $wpdb->insert( $field_table,
                                        array('layout_id' => $layout_id2,
                                              'field_type' => $title_field2['field_type'],
                                              'field_header' => $title_field2['field_header'],
                                              'field_ident' => $title_field2['field_ident'],
                                              'required' => $title_field2['required'],
                                              'pg_column' => $title_field2['pg_column'],
                                              'ss_column' => $title_field2['ss_column'],
                                              'fdata' => serialize( $title_field2['fdata'] )
                                              ) );
        $field_id2 = $wpdb->insert_id;
        assert( $rows_affected2 === 1 );

        $rows_affected3 = $wpdb->insert( $field_table,
                                        array('layout_id' => $layout_id2,
                                              'field_type' => $orig_field2['field_type'],
                                              'field_header' => $orig_field2['field_header'],
                                              'field_ident' => $orig_field2['field_ident'],
                                              'required' => $orig_field2['required'],
                                              'pg_column' => $orig_field2['pg_column'],
                                              'ss_column' => $orig_field2['ss_column'],
                                              'fdata' => serialize( $orig_field2['fdata'] )
                                              ) );

        $field_id3 = $wpdb->insert_id;
        assert( $rows_affected3 === 1 );

        $rows_affected4 = $wpdb->insert( $field_table,
                                        array('layout_id' => $layout_id2,
                                              'field_type' => $final_field2['field_type'],
                                              'field_header' => $final_field2['field_header'],
                                              'field_ident' => $final_field2['field_ident'],
                                              'required' => $final_field2['required'],
                                              'pg_column' => $final_field2['pg_column'],
                                              'ss_column' => $final_field2['ss_column'],
                                              'fdata' => serialize( $final_field2['fdata'] )
                                              ) );

        $field_id4 = $wpdb->insert_id;
        assert( $rows_affected4 === 1 );

        $restr_data = array();

        $extra_data = array(
                            "dtu" => 13,   //~~
                            "iid" => 8, //~~
                            "mem" => 18, //~~
                            "mid" => -1,//~~
                            "mli" => 17, //~~
                            "mnm" => 2, //~~
                            "wfm" => -1,//~~
                            "wft" => 5,//~~
                            "wid" => -1,//~~
                            "custom1" => 3,//~~
                            "custom2" => 1,//~~
                            );

        $wpdb->update( $layout_table,
                       array('restrictions' => serialize( $restr_data ),
                             'extra_cols' => serialize( $extra_data )
                             ),
                       array('layout_id' => $layout_id2), array('%s')
                       );
        return array( 'field_id1'=>$field_id1,
                      'field_id2'=>$field_id2,
                      'field_id3'=>$field_id3,
                      'field_id4'=>$field_id4,
                      'layout_id'=>$layout_id2
                      );

    }

    public static function create_sample_webform( $webform_title, $webform_ident, $layout_id )
    {
        global $wpdb;
        // no assert
        $webform_table = EWZ_WEBFORM_TABLE;
        $rows_affected = $wpdb->insert( $webform_table,
                                        array('layout_id' => $layout_id,
                                              'webform_title' => $webform_title,
                                              'webform_ident' => $webform_ident,
                                              'upload_open' => 1,
                                              'open_for' => "a:0:{}",
                                              )
                                        );
        $webform_id = $wpdb->insert_id;
        assert( $rows_affected === 1 );

        return $webform_id;
    }

    public static function create_sample_data1(  $dir, $webform_id, $webform_ident, $ids ){
        global $wpdb;
        // no assert

        $item_table = EWZ_ITEM_TABLE;
        $path = EWZ_IMG_UPLOAD_DIR . '/' . $webform_ident;
        $url =  EWZ_IMG_UPLOAD_URL . '/' . $webform_ident;


        $files = array( $ids['field_id3'] => array( "field_id"  => $ids['field_id3'],
                                      "thumb_url" => "{$url}/sample1-thumb.jpg",
                                      "fname"     => "{$path}/sample1.jpg",
                                      "type"      => "image/jpg",
                                      "width"     => 677,
                                      "height"    => 1024,
                                      "orient"    => "P",
                                      ),
                        $ids['field_id4'] => array( "field_id"  => $ids['field_id4'],
                                      "thumb_url" => "{$url}/sample2-thumb.jpg",
                                      "fname"     => "{$path}/sample2.jpg",
                                      "type"      => "image/jpg",
                                      "width"     => 1280,
                                      "height"    => 960,
                                      "orient"    => "L",
                                      ),

                        );
        $data = array( $ids['field_id1'] => array( "field_id" => $ids['field_id1'],
                                     "value"    => "N"
                                     ),
                       $ids['field_id2'] => array( "field_id" => $ids['field_id2'],
                                     "value"    => "cccccccccc"
                                      ),
                       $ids['field_id3'] => array( "field_id" => $ids['field_id3'],
                                     "value"    => "ewz_img_upload"
                                     ),
                       $ids['field_id4'] => array( "field_id" => $ids['field_id4'],
                                     "value"    => "ewz_img_upload"
                                     ),
                      );
        $user_id = 1;

        $rows_affected = $wpdb->insert( $item_table,
                                         array( 'user_id'     => $user_id,
                                                'webform_id'  => $webform_id,
                                                'last_change' => current_time( 'mysql' ),
                                                'item_files'  => serialize( $files ),
                                                'item_data'   => serialize( $data )
                                                          )
                                        );
        assert( $rows_affected === 1 );

        $dir =  EWZ_IMG_UPLOAD_DIR . '/' . $webform_ident;
        if( !file_exists( $dir ) ){
            mkdir( $dir );
        }

        copy( dirname( __FILE__ ) . '/../images/sample1-thumb.jpg', "{$dir}/sample1-thumb.jpg" );
        copy( dirname( __FILE__ ) . '/../images/sample1.jpg', "{$dir}/sample1.jpg" );
        copy( dirname( __FILE__ ) . '/../images/sample2-thumb.jpg', "{$dir}/sample2-thumb.jpg" );
        copy( dirname( __FILE__ ). '/../images/sample2.jpg', "{$dir}/sample2.jpg" );


    }



    private static function set_initial_permissions()
    {
        $user = wp_get_current_user();

        Ewz_Permission::add_perm( $user->ID, 'ewz_can_edit_layout', array( "-1" ) );
        Ewz_Permission::add_perm( $user->ID, 'ewz_can_assign_layout', array( "-1" ) );
        Ewz_Permission::add_perm( $user->ID, 'ewz_can_edit_webform', array( "-1" ) );
        Ewz_Permission::add_perm( $user->ID, 'ewz_can_download_webform', array( "-1" ) );
    }

    private static function rrmdir( $dir ) {
        assert( is_string( $dir ) );

      error_log( "EWZ: removing directory $dir" );
      if ( is_dir( $dir ) ) {
         $objects = scandir( $dir );
         foreach ( $objects as $object ) {
            if ( $object != "." && $object != ".." ) {
               if ( filetype( $dir . "/" . $object ) == "dir" ) {
                  self::rrmdir( $dir . "/" . $object );
               } else {
                  unlink( $dir . "/" . $object );
               }
            }
         }
         reset( $objects );
         rmdir( $dir );
      }
   }

    private static function create_custom_file()
    {
        if( !is_file( EWZ_CUSTOM_DIR . "ewz-custom-data.php" ) ){
            if( !is_dir ( EWZ_CUSTOM_DIR ) ){
                mkdir( EWZ_CUSTOM_DIR );
            }
            $out = fopen( EWZ_CUSTOM_DIR . "ewz-custom-data.php", 'w' );

            /*******************************************************/
            $content = <<<EOD
<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");
/**
 * This file is only needed if you have additional data about users stored on
 * your database, beyond the basics that Wordpress uses.
 *
 * You also need to know the name of a php function that can be called to
 * retrieve this data.
 *
 * If you have no use for this file, just ignore it - it does nothing in its
 * unedited state.
 *
 * If you do edit it, you MUST save it in **PLAIN TEXT** format.
 *
 * The sample below is set up to make two items stored using the CIMY User Extra Fields
 * plugin available for download in your spreadsheet.  If you are using a different plugin,
 * you will need to find out from its documentation how to fill out the third section.
 *
 * To make use of it, delete the '//' from the beginning of any line starting '//' and edit the line as indicated.
 * ( '//' at the start of a line tells php to ignore the line )
 *
 * Do not make any other changes unless you are a php programmer.
 *
 */

class Ewz_Custom_Data
{
    /*************************************************
     * SECTION 1: you need one line for each item
     *
     *************************************************/
    // public $custom1;
    // public $custom2;
    /* if needed, add more lines here for "$custom3", "$custom4", etc. */


    public static $data =
        array(

              /*************************************************
               * SECTION 2: you need one line for each item
               *************************************************/
              /* Change 'Member Level' to whatever label you wish to use for your first piece of data */
              /* NO apostrophes or quotation marks                                                    */

     //                       'custom1' => 'Member Level',

              /* Change 'Membership Number' to whatever label you wish to use for your second piece of data */
              /* NO apostrophes or quotation marks                                                    */

    //                        'custom2' => 'Membership Number',

              /* if needed, add more lines here for "custom3", "custom4", etc. */

              );


   public function  __construct( $user_id ){
       assert( Ewz_Base::is_pos_int( $user_id ) );
        /*************************************************
         * SECTION 3: you need one line for each item
         *
         *************************************************/
        /* This is the function that gets the "custom1" data for a member with id $user_id  -- change it as needed */

     //    $this->custom1 = get_cimyFieldValue( $user_id, 'COMPETITION_LEVEL' );   

       /* This is the function that gets the "custom2" data for a member with id $user_id -- change it as needed  */

     //    $this->custom2 = get_cimyFieldValue( $user_id, 'MEMBER_NUMBER' );       

        /* if needed, add more lines here for "custom3", "custom4", etc. */
    }
}

EOD;
            /*******************************************************/
    
    fwrite( $out, $content );
    fclose( $out );

        
    }
}

}