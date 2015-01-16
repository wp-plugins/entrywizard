<?php

defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-item.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-permission.php");
require_once( EWZ_CUSTOM_DIR . "ewz-custom-data.php");

/* * ***********************************************************
 * Interaction with the EWZ_WEBFORM table
 *
 * *********************************************************** */

class Ewz_Webform extends Ewz_Base {

    const DELETE_ITEMS = 1;
    const FAIL_IF_ITEMS = 0;

    const SPREADSHEET = 0;
    const IMAGES = 1;
    const BOTH = 2;

    // key
    public $webform_id;
    // database
    public $layout_id;
    public $num_items;
    public $webform_title;
    public $webform_ident;
    public $webform_order;
    public $upload_open;
    public $open_for;
    public $prefix;
    public $apply_prefix;
    public $gen_fname;
    public $attach_prefs;

    // extra
    public $can_download;
    public $can_edit_webform;
    public $can_manage_webform;
    public $itemcount;
    public $open_for_string = '';
    public $auto_close;
    public $auto_date;
    public $auto_time;

    private $files_done;     // files added to zipfile for download

    // keep list of db data names/types as a convenience for iteration and so we can easily add new ones.
    // Dont include webform_id
    public static $varlist = array(
                                   'layout_id' => 'integer',
                                   'num_items'  => 'integer',
                                   'webform_title' => 'string',
                                   'webform_ident' => 'string',
                                   'webform_order' => 'integer',
                                   'upload_open' => 'boolean',
                                   'open_for' => 'array',
                                   'prefix' => 'string',
                                   'apply_prefix' => 'boolean',
                                   'gen_fname' => 'boolean',
                                   'attach_prefs' => 'string',
                                   );

    /**
     * Return an array of all webforms using the input layout
     *
     * @param   int  $layout_id   id of the layout
     * @return  array of all Ewz_Webforms using $layout_id
     */
    public static function get_webforms_for_layout( $layout_id ) {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $layout_id ) );

        $list = $wpdb->get_col( $wpdb->prepare( "SELECT webform_id  FROM " . EWZ_WEBFORM_TABLE .
                                                " WHERE layout_id = %d  ORDER by webform_order", $layout_id ) );
        $webforms = array( );
        foreach ( $list as $webform_id ) {
            $webform = new Ewz_Webform( $webform_id );
            array_push( $webforms, $webform );
        }
        return $webforms;
    }

    /**
     * Return an array of all webforms
     *
     * @param   none
     * @return  array of all  webforms
     */
    public static function get_all_webforms( $filter = 'truefunc' ) {
        global $wpdb;
        assert(is_string( $filter ) );
        $list = $wpdb->get_col( "SELECT webform_id  FROM " . EWZ_WEBFORM_TABLE . " ORDER BY webform_order" );
        $webforms = array( );
        foreach ( $list as $webform_id ) {
            if ( call_user_func( array( 'Ewz_Permission',  $filter ), $webform_id ) ) {
                $webform = new Ewz_Webform( $webform_id );
                array_push( $webforms, $webform );
            }
        }
        return $webforms;
    }

    /**
     * Return an array of all webform idents
     *
     * @param   none
     * @return  array of all  webforms
     */
    public static function get_all_idents() {
        global $wpdb;
        $list = $wpdb->get_col( "SELECT webform_ident  FROM " . EWZ_WEBFORM_TABLE . " ORDER BY webform_order" );
        return $list;
    }
    /**
     * Get a count of the webforms
     *
     * @return   int count
     */
    public static function count_webforms( ) {
        global $wpdb;
        $count = $wpdb->get_var(  "SELECT count(*) FROM " . EWZ_WEBFORM_TABLE  );
        return $count;
    }

    /**
     * Make sure the input webform_id is a valid one
     *
     * @param    int     $webform_id
     * @return   boolean true if $webform_id is the key for a EWZ_WEBFORM_TABLE row, otherwise false
     */
    public static function is_valid_webform( $webform_id ) {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $webform_id ) );
        $wcount = $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM " . EWZ_WEBFORM_TABLE . " WHERE  webform_id = %d", $webform_id ) );
        return ( 1 == $wcount );
    }

    /**
     * Save the order of the webforms
     *
     * @return  number of rows updated
     */
    public static function save_webform_order( $wf_orders ) {
        global $wpdb;
        assert( is_array($wf_orders['wforder']) );
        $n = 0;
        foreach( $wf_orders['wforder'] as $webform_id => $order ){
            $n = $n + $wpdb->query($wpdb->prepare("UPDATE " . EWZ_WEBFORM_TABLE . " wf " .
                                                  "   SET webform_order = %d WHERE webform_id = %d ", $order, $webform_id ) );  
        }
        return $n;
    }

    /**
     * Renumber the subsequent webforms when one is deleted
     */
    private static function renumber_webforms( $order ) {
        global $wpdb;
        assert( Ewz_Base::is_nn_int( $order ) );
        $wpdb->query($wpdb->prepare( "UPDATE " . EWZ_WEBFORM_TABLE . " wf " .
                                     "   SET webform_order = webform_order - 1 WHERE  webform_order > %d " , $order ) );  
    }


    /**
     * Deal with upgrade -- set num_items field in webforms 
     * 
     */
    public static function set_num_items(){
        global $wpdb;
        $wpdb->query( "UPDATE " . EWZ_WEBFORM_TABLE . " wf " .
                      "   SET num_items = ( SELECT  max_num_items from " .  EWZ_LAYOUT_TABLE . " lay " .
                      " WHERE lay.layout_id = wf.layout_id ) " );
    }
    /**
     * Deal with upgrade -- set webform_order field in webforms 
     * 
     */
    public static function set_webform_order(){
        global $wpdb;
        $list = $wpdb->get_col( "SELECT webform_id  FROM " . EWZ_WEBFORM_TABLE . " ORDER BY webform_id" );
        $n = 0;
        foreach ( $list as $webform_id ) {                      
            $wpdb->query( $wpdb->prepare( "UPDATE " . EWZ_WEBFORM_TABLE .
                                          "   SET webform_order = %d WHERE webform_id = %d ", $n, $webform_id ) );
            ++$n;
        }
    }


    /*     * ****************** Construction ************************* */

    /**
     * Assign the object variables from an array
     *
     * Calls parent::base_set_data with the list of variables and the data structure
     *
     * @param  array  $data input data.
     * @return none
     */
    public function set_data( $data ) {
        assert( is_array( $data ) );
        parent::base_set_data( array_merge( self::$varlist, array( 'webform_id' => 'integer' ) ), $data );
    }

    /**
     * Constructor
     *
     * @param  mixed  $init  webform_id or array of data
     * @return none
     */
    public function __construct( $init ) {
        assert( Ewz_Base::is_pos_int( $init ) || is_string( $init ) || is_array( $init ) );
        if ( is_numeric( $init ) ){
            if( Ewz_Base::is_pos_int( $init ) ) {
                $this->create_from_id( $init );
            } else {
                $this->create_from_id( intval( $init, 10 ) );
            } 
        } elseif ( is_string( $init ) ) {
            $this->create_from_ident( $init );
        } elseif ( is_array( $init ) ) {
            $this->create_from_data( $init );
        } else {
            throw new EWZ_Exception( 'Invalid webform constructor' );
        }
        if ( $this->webform_id ) {
            $this->itemcount = Ewz_Item::get_itemcount_for_webform( $this->webform_id );
        } else {
            // no webform_id means creating a new one
            if ( !Ewz_Permission::can_manage_all_webforms() ) {
                throw new EWZ_Exception( "No permission" );
            }
            $this->itemcount = 0;
        }

        // variables required for javascript
        if ( $this->open_for ) {
            $this->open_for_string = 'Currently open for ' .
                implode( ', ', array_map( function($v){ return get_userdata($v)->user_login; }, 
                                          $this->open_for ) ) .
                ' only';
        }
    }

    /**
     * Create a new webform object from the webform_id by getting the data from the database
     *
     * @param  int  $id the webform id
     * @return none
     */
    protected function create_from_id( $id ) {
        global $wpdb;
        assert( Ewz_Base::is_pos_int( $id ) );

        $dbwebform = $wpdb->get_row( $wpdb->prepare( "SELECT webform_id, " .
                                                     implode( ',', array_keys( self::$varlist ) ) .
                                                     " FROM " .
                                                     EWZ_WEBFORM_TABLE . " WHERE webform_id=%d", $id ), ARRAY_A );
        if ( !$dbwebform ) {
            throw new EWZ_Exception( 'Unable to find matching webform for id', $id );
        }
        $this->can_download = Ewz_Permission::can_download( $id, $dbwebform['layout_id'] );
        $this->can_edit_webform = Ewz_Permission::can_edit_webform( $id );
        $this->can_manage_webform = Ewz_Permission::can_manage_webform( $id, $dbwebform['layout_id'] );
        $layout = new Ewz_Layout( $dbwebform['layout_id'] );
        if( !$layout->override ||  !$dbwebform['num_items'] ){
            $dbwebform['num_items'] = $layout->max_num_items;
        }
        $this->set_data( $dbwebform );
        $this->set_auto_time();
    }

    /**
     * Create a new webform object from the webform ident by getting the data from the database
     *
     * @param  string   $ident      the webform ident
     * @return none
     */
    protected function create_from_ident( $ident ) {
        global $wpdb;
        assert( is_string( $ident ) );
        $dbwebform = $wpdb->get_row( $wpdb->prepare( "SELECT webform_id, " .
                                                     implode( ',', array_keys( self::$varlist ) ) .
                                                     " FROM " .
                                                     EWZ_WEBFORM_TABLE . " WHERE webform_ident=%s", $ident ), ARRAY_A );
        if ( !$dbwebform ) {
            throw new EWZ_Exception( 'Unable to find matching webform for identifier', $ident );
        }
        $this->can_download = Ewz_Permission::can_download( $dbwebform['webform_id'], $dbwebform['layout_id']);
        $this->can_edit_webform = Ewz_Permission::can_edit_webform( $dbwebform['webform_id'] );
        $this->can_manage_webform = Ewz_Permission::can_manage_webform( $dbwebform['webform_id'], $dbwebform['layout_id']  );

        $layout = new Ewz_Layout( $dbwebform['layout_id'] );
        if( !$layout->override ||  !$dbwebform['num_items'] ){
            $dbwebform['num_items'] = $layout->max_num_items;
        }
        $this->set_data( $dbwebform );
        $this->set_auto_time();
    }

    /**
     * Create a webform object from $data
     *
     * @param  array  $data
     * @return none
     */
    protected function create_from_data( $data ) {
        assert( is_array( $data ) );
        if ( array_key_exists( 'upload_open', $data ) && $data['upload_open'] ) {
            $data['open_for'] = array( );
        } else {
            $data['open_for'] = array_map( function($v){ return (int)$v; },
                                           array_filter( array_key_exists( 'o_user', $data ) ? 
                                                         $data['o_user'] : array( ), function($v){ return ($v != ""); } ) );
        }
        if ( !array_key_exists( 'webform_id', $data ) ) {
            $data['webform_id'] = 0;
        } else {
            $data['webform_id'] = intval( $data['webform_id'], 10 );
        }    
        if ( !array_key_exists( 'layout_id', $data ) ) {
            $data['layout_id'] = 0;
        } else {
            $data['layout_id'] = intval( $data['layout_id'], 10 );
        }
        $layout = new Ewz_Layout( $data['layout_id'] );
        if( !$layout->override || !$data['num_items'] ){
            $data['num_items'] = $layout->max_num_items;
        }
        $this->set_data( $data );
        $this->can_download = Ewz_Permission::can_download( $data['webform_id'], $data['layout_id'] );
        $this->can_edit_webform = Ewz_Permission::can_edit_webform( $data['webform_id'] );
        $this->can_manage_webform = Ewz_Permission::can_manage_webform( $data['webform_id'], $data['layout_id']  );
        $this->check_errors();
        if( $data['auto_close'] ){ 
            $this->schedule_closing( $data['auto_date'] . ' '.  $data['auto_time'] );
        } else {
            $this->unschedule_closing();
        }
        $this->set_auto_time();
    }


    /*     * ******************  Download Functions ********************* */

    /**
     * Generate the zip archive file of images using PHPs ZipArchive
     * Called if there is no access to a "zip" command on the server
     *
     * Use a random filename because the file will be temporarily stored in a web-accessible folder
     *
     * @param   array   $items      
     * @param   int     $inclusion  if self::BOTH, add the spreadsheet to the archive
     * @return  none
     */
    public function generate_zip_archive( $items, $inclusion ) {
        assert( is_array( $items ) );
        assert(  $inclusion == self::IMAGES || $inclusion == self::BOTH  );
        if ( !$this->can_download ) {
             throw new EWZ_Exception( "No Permission" );
         }
        if ( count( $items ) < 1 ) {
            throw new EWZ_Exception( "No matching items found." );
        }
        $date = current_time( 'Ymd_Hi' );                // for filename ewz_{$date}_{$rand}.zip of archive file
        $up = $this->ewz_upload_dir( wp_upload_dir() );  // uploads/ewz_img_uploads/$this->webform_ident
        if ( !is_dir( $up['path'] ) ) {
            throw new EWZ_Exception( "Images not found." );
        }

        $rand = $this->randstring(15);
        $key  = $this->randstring(5);
        $archive_fname = "ewz_{$date}_{$rand}.zip";
        $archive_path = $up['path'] . "/$archive_fname";
        $archive_url = $up['url'] . "/$archive_fname";

        update_option( "ewz_{$this->webform_id}_{$key}",  $archive_url );

        // remove any old zip files from this webform
        $zipfiles =  glob( $up['path'] . "/ewz_*.zip" );
        $now = time();
        foreach( $zipfiles as $file ){
            if( $now - filemtime( $file ) > 1200 ){    // 20 minutes
                unlink( $file );
            }
        }

        // create a new archive and return the option name that contains the filename
        $this->zip_items( $items, $archive_path, $inclusion );
        return "ewz_{$this->webform_id}_{$key}";
    }

    /*
     * Use the server "zip" command to generate the zip archive of images, 
     * ( optionally including the csv file ) and print it to stdout. 
     *
     * This avoids the overhead of generating a file, and the need for a
     * second call to read it. The output is slowed down to avoid large IO spikes
     * that may trigger blocking by the webhost.
     * If the csv file was included, delete it when done.
     *                                                                               
     * @param   array   $items                                                       
     * @param   int     $inclusion  if self::BOTH, add the spreadsheet to the archive 
     * @return  none                                                                  
     */    
    public function gen_and_echo_archive( $items, $inclusion ){  
        assert( is_array( $items ) );
        assert( is_int( $inclusion ) );   // self::IMAGES or self::BOTH

        if ( !$this->can_download ) {
            throw new EWZ_Exception( "No Permission" );
        }
        if ( !$items ) {
            throw new EWZ_Exception( "No Items Found" );
        }
        $bufsize = 8192;  // seems to be the standard default

        $date = current_time( 'Ymd_Hi' );  // for filename ewz_{$date}.zip of downloaded zip attachment
        $fnames = $this->get_fname_list( $items,  $inclusion );
        
        self::ewz_disable_gzip();    

        // options for linux "zip" command:
        // -0 = no compression
        // -j = strip path, use only filename
        // -q = no extra output info
        $fp = popen("zip -0 -j -q - $fnames", 'r');
        if( !$fp ){
            error_log("EWZ: Zip command failed");
            throw new Ewz_Exception("Zip command failed");
        }

        header( 'Content-Type: application/octet-stream');
        header( "Content-disposition: attachment; filename=ewz_{$date}.zip; ");

        while( !feof($fp) ) {
            echo fread($fp, $bufsize);
            flush();
            usleep(100);  // to avoid IO spikes
        }
        pclose($fp);
        if ( self::BOTH == $inclusion ){
            $matches = array();
            preg_match( '/ ([^ ]+) *$/', $fnames, $matches );
            if( is_file( $matches[1]  ) ) {
                unlink( $matches[1] );
            }
        }
        exit;
    }

    /**
     * Echo to stdout the file defined by $archive_key, then delete it
     * 
     * @param  string    $archive_key
     * @return none
     */
    public function echo_stored_archive( $archive_key ){  
        assert( is_string( $archive_key ) );

        if ( !$this->can_download ) {
            throw new EWZ_Exception( "No Permission" );
        }
        $archive_url =  get_option( $archive_key, admin_url( 'admin.php?page=entrywizard' ) );
        delete_option( $archive_key );
        
        self::ewz_disable_gzip();         

        // display it, making sure the redirect location is not cached
        header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
        header( "Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . " GMT" );
        header( "Cache-Control: no-store, no-cache, must-revalidate" );
        header( "Cache-Control: post-check=0, pre-check=0", false );
        header( "Pragma: no-cache" );
        header( "Location: $archive_url" );
        exit;
    }
    


    /**
     * Return the list of filenames ( complete paths ) defined by $items, including the
     * csv file if $inclusion == self::BOTH, to be included in the zip archive
     * No file renaming here - just return whatever is defined in the item
     * 
     * @param   array of Ewz_Items  $items       
     * @param   int                 $inclusion     self::BOTH or self::IMAGES
     * @return  string    space-separated list of filenames to be included in the zip archive
     */
    protected function get_fname_list( $items,  $inclusion )
    {
        assert( is_array( $items ) );
        assert(  $inclusion == self::IMAGES || $inclusion == self::BOTH  );

        if ( !$this->can_download ) {
            throw new EWZ_Exception( "No Permission" );
        }
        $msg = '';
        $this->files_done = array();
                            
        $fnames = '';
        foreach ( $items as $item ) {
            foreach ( $item->item_files as $item_file ) {
                if ( !isset( $item_file['fname'] ) ) {
                    continue;   // ignore items with no image file
                }
                if ( is_file( $item_file['fname'] ) ) {
                    $fnames .= $item_file['fname'] . " ";
                    $this->files_done[$item_file['fname']] = true;
                } else {
                    error_log("EWZ: cant find " .  $item_file['fname'] );
                    $msg .= "\n\nUnable to find file " . basename( $item_file['fname'] );
                }
            }
        }
        $csv_fname = '';
        if ( self::BOTH == $inclusion  ) {
            $csv_fname = $this->download_spreadsheet( $items, true );
            if ( is_file( $csv_fname ) ) {
                $fnames .= $csv_fname;
            } else {
                $msg .= "\n\nUnable to find .csv file ";
            }
        }
        if ( $msg ) {
            error_log("EWZ: error generating file list: $msg");
        }
        return $fnames;
    }


    /**
     * Create the zip archive of images ( and possibly spreadsheet ) using PHPs ZipArchive
     * If there is a prefix set but not yet applied, apply it to the
     * filename in the archive
     *
     * @param   array of Ewz_Items  $items     items to be zipped
     * @param   string              $fpath        full path name of archive to be created
     * @param   int                 $inclusion       if self::BOTH, add the spreadsheet to the archive
     * @return  string   status message
     */
    protected function zip_items( $items, $fpath, $inclusion ) {
        assert( is_array( $items ) );
        assert( strpos( $fpath, EWZ_IMG_UPLOAD_DIR ) === 0 );
        assert(  $inclusion == self::IMAGES || $inclusion == self::BOTH  );

        if ( !$this->can_download ) {
            throw new EWZ_Exception( "No Permission" );
        }
        set_time_limit( EWZ_FILE_DOWNLOAD_TIME );
        $tmpn = 0;

        $zip = new ZipArchive();
        $msg = '';
        $this->files_done = array();
                            
        $fields = Ewz_Field::get_fields_for_layout( $this->layout_id, 'ss_column' );

        $result = $zip->open( $fpath, ZipArchive::CREATE|ZipArchive::OVERWRITE );
        if ( $result === TRUE ) {
            foreach ( $items as $item ) {
                foreach ( $item->item_files as $item_file ) {
                    if ( !isset( $item_file['fname'] ) ) {
                        continue;   // ignore items with no image file
                    }
                    
                     ++$tmpn;
                     // a very rough-and-ready way to allow more time if there are a lot of image files
                     // adds EWZ_FILE_DOWNLOAD_TIME seconds to the time limit for every 25 files.
                     if ( $tmpn > 25 ) {
                         set_time_limit( EWZ_FILE_DOWNLOAD_TIME );
                         $tmpn = 0;
                     }
                    $newfilename = '';
                    if ( is_file( $item_file['fname'] ) ) {
                        if( $this->apply_prefix ){
                            // if the prefix/rename was already done, just add the file with it's current name
                            $newfilename = basename( $item_file['fname'] );
                            
                        } else {
                            // if a prefix/rename has been set but not yet done, add it now
                            $newfilename = $this->get_new_filename( $fields, $item, $item_file );
                        }
                        $zip->addFile( $item_file['fname'],  $newfilename );
                        $this->files_done[$newfilename] = true;

                    } else {
                        error_log("EWZ: cant find " .  $item_file['fname'] );
                        $msg .= "\n\nUnable to find file " . basename( $item_file['fname'] );
                    }
                }
            }

            $csv_fname = '';
            if ( self::BOTH == $inclusion  ) {
                $csv_fname = $this->download_spreadsheet( $items, true );
                if ( is_file( $csv_fname ) ) {
                    $zip->addFile( $csv_fname, basename( $csv_fname ) );
                } else {
                    $msg .= "\n\nUnable to find .csv file ";
                }
            }
            if ( $msg ) {
                error_log("EWZ: zip error: $msg");
                $zip->addFromString( 'ERRORS.txt', 
                                     "\nThe following errors were encountered while generating the zip archive: $msg" );
            }
            if( $zip->close() === FALSE ){
                throw new EWZ_Exception( "Failed to close zip file. " . $msg );
            }
            if ( ( self::BOTH == $inclusion ) && is_file( $csv_fname ) ) {
                unlink( $csv_fname );
            }
        } else {
            throw new EWZ_Exception( "Sorry, there was a problem creating the zip archive.  If this continues, please contact your administrator.", $result );
        }
    }

    /**
     * Return a filename generated from the prefix expression
     * 
     * @param  array of Ewz_Fields  $fields         the fields of the layout
     * @param  Ewz_item             $item           the item containing the file
     * @param  array of file data   $item_file      a single component of the $item->item_files array   
     * @return string      the generated filename (including extension)
     */
    public function get_new_filename( $fields, $item, $item_file ){
        assert( is_array( $fields ) );
        assert( is_object( $item ) );
        assert( is_array( $item_file ) );

        if( $this->prefix ){
            $custom = new Ewz_Custom_Data( $item->user_id );
            $subst_data = array( 
                                'file_field_id' => $item_file['field_id'],
                                'user_id' => $item->user_id,
                                'item_id' => $item->item_id,
                                 );
            foreach ( $custom as $custkey => $custval ) {
                $subst_data[$custkey] = $custval;
            }
            foreach ( $fields as $fid=>$field ){
                if( isset( $item->item_data[$fid] ) ){
                    $subst_data[$fid] = $item->item_data[$fid];
                } else {
                    $subst_data[$fid] = '';
                }
            }
            if( $this->gen_fname ){
                $ext = pathinfo( $item_file['fname'], PATHINFO_EXTENSION );
                $prefix1 = $this->generated_prefix( $subst_data );
                if ( strpos( $prefix1, '[~1]' ) !== false ) {
                    // make sure the filename is unique -- replace '[~1]' by 1,2,3,... until it is
                    $uniq = $this->get_fname_num( $prefix1, $ext );
                    $prefix1 = str_replace( '[~1]', $uniq, $prefix1 );
                }     
                return $prefix1 . '.' . $ext;
            } else {
                return $this->generated_prefix( $subst_data ) . basename( $item_file['fname'] );
            }
        } else {
            return  basename( $item_file['fname'] );
        }
    }

    /**
     * Return a number to replace '[~1]' to make the filename unique in it's download batch.
     * Substitutes 1,2,3,.... in turn for '[~1]' in $in_fname until
     *              $this->files_done["$in_fname.$ext"] is not defined.
     * @param  string  $in_fname  an input filename that should contain the string  '[~1]'
     * @param  string  $ext       a filename extension
     * @return string   an integer between 1 and 999 as a string
     */
    public function get_fname_num( $in_fname, $ext ){
        assert( is_string( $in_fname ) );
        assert( is_string( $ext ) );
        if( strlen( $in_fname ) < 5 ){
            return '';
        }        
        if( strpos( $in_fname, '[~1]' ) === false ){
            return '';
        }
        $num = 1;
        while( $num < 999 ){
            $testfname = str_replace( '[~1]', "$num", $in_fname );
            if( !isset( $this->files_done["{$testfname}.{$ext}"] ) ){
                return "$num";
            }
            ++$num;
        }
        return "$num";   
    }

    /**
     * Generate the requested spreadsheet and print to stdout or save as a file
     *
     * Generates a .csv-formatted summary of the uploaded items for the webform
     * Prints to stdout if $include == self::SPREADSHEET, otherwise saves to a file
     *    with a randomized filename for later downloading
     *
     * @param  array of Ewz_Items  $items    array  -- items for downloading
     * @param  int                 $include  self::SPREADSHEET if just downloading spreadsheet, 
     *                                       self::BOTH if downloading images and spreadsheet
     * @return string   filename of saved file, no return if data sent to stdout
     */
    public function download_spreadsheet( $items, $include ) {
        assert( is_array( $items ) );
        assert(  $include == self::SPREADSHEET || $include == self::BOTH );

        if ( !$this->can_download ) {
            throw new EWZ_Exception( "No Permission" );
        }
        if ( count( $items ) < 1 ) {
            throw new EWZ_Exception( "No matching items found." );
        }

        $fields = Ewz_Field::get_fields_for_layout( $this->layout_id, 'ss_column' );
        $extra_cols = Ewz_Layout::get_extra_cols( $this->layout_id );
        $rows = $this->get_rows_for_ss( $fields, $extra_cols, $items );

        // sort each row by it's key, which is the ss column
        foreach ( array_keys($rows) as $k ) {
            ksort( $rows[$k]);
        }
        $out = '';
        $outpath = '';
        if ( $include == self::BOTH ) {
            $up = $this->ewz_upload_dir( wp_upload_dir() );  // uploads/ewz_img_uploads/$this->webform_ident
            // create the upload dir if it doesnt exist
            if ( !is_dir( $up['path'] ) ) {
                mkdir( $up['path'] );
                $outp = fopen( $up['path'] . "/index.php", 'w' );
                fwrite( $outp, "<?php\n   //No listing\n?>" );
                fclose( $outp );
            }
            $date = current_time( 'Ymd_Hi' );      // for filename ewz_{$date}_{$rand}.csv of spreadsheet within archive
            $rand =  $this->randstring(5);
            $outpath = $up['path'] . "/ewz_{$date}_{$rand}.csv";
            $out = fopen( $outpath, 'w' );
            if( !$out ){
                throw new EWZ_Exception( "Failed to open csv file for writing");
            }
        } else {
            $now = current_time( 'Ymd_Hi' );   // for filename ewzdata_{$now}.csv of downloaded .csv attachment
            $filename = "ewzdata_{$now}.csv";  
            header( "Content-Disposition: attachment; filename=\"$filename\"" );
            header( "Content-Type: text/csv" );
            header( "Cache-Control: no-cache" );

            $out = fopen( "php://output", 'w' );  // write directly to php output, not to a file
            if( !$out ){
                throw new EWZ_Exception( "Failed to open php output for writing");
            }
        }
        foreach ( $rows as $r ) {
            fputcsv( $out, $r, "," );
        }
        fclose( $out );
        if ( $include == self::BOTH ) {
            return $outpath;
        } else {
            // this forces the download dialog
            exit();
        }
    }

    /**
     * Return the header row for the spreadsheet
     *
     * @param   array of Ewz_Field     $fields     fields array from layout - data input via the webform
     * @param   array of (string=>int) $extra_cols extra columns array from layout - other data for display
     * @return  array of headers
     */
    protected function get_headers_for_ss( $fields, $extra_cols ) {
        assert( is_array( $fields ) );
        assert( is_array( $extra_cols ) );
        $img_data = array( 'ss_col_w' => 'Width', 'ss_col_h' => 'Height', 'ss_col_o' => 'Orientation' );
        $hrow = array( );
        foreach ( $fields as $field ) {
            $txt_data = array( 'ss_col_fmt' => 'Formatted ' . $field->field_ident );
            if ( $field->ss_column >= 0 ) {
                $hrow[$field->ss_column] = $field->field_ident;
            }
            if ( 'img' == $field->field_type ) {
                foreach ( $img_data as $ss_img_col => $ss_img_header ) {
                    $ssimgcol = $field->fdata[$ss_img_col];
                    if ( $ssimgcol >= 0 ) {
                        $hrow[$ssimgcol] = $ss_img_header;
                    }
                }
            }
            if ( 'str' == $field->field_type ) {
                foreach ( $txt_data as $ss_txt_col => $ss_txt_header ) {
                    $sstxtcol = $field->fdata[$ss_txt_col];
                    if ( $sstxtcol >= 0 ) {
                        $hrow[$sstxtcol] = $ss_txt_header;
                    }
                }
            }
        }
        $dheads = Ewz_Layout::get_all_display_headers();
        foreach ( $extra_cols as $xcol => $sscol ) {
            if ( $sscol >= 0 && isset( $dheads[$xcol] ) ) {
                $hrow[$sscol] = $dheads[$xcol]['header'];
            }
        }
        $maxk = max( array_keys( $hrow ) );
        for ( $i = 0; $i <= $maxk; ++$i ) {
            if ( !isset( $hrow[$i] ) ) {
                $hrow[$i] = "Blank " . chr(65 + $i);
            }
        }
        return $hrow;
    }

    /**
     * Return the body of the spreadsheet
     *
     * @param   array of Ewz_Field     $fields     fields array from layout - data input via the webform
     * @param   array of (string=>int) $extra_cols extra columns array from layout - other data for display
     * @param   array of Ewz_Item      $items        data uploaded via webform
     * @return  array to be displayed in spreadsheet
     *
     */
    protected function get_rows_for_ss( $fields, $extra_cols, $items ) {
        assert( is_array( $fields ) );
        assert( is_array( $extra_cols ) );
        assert( is_array( $items ) );
        $rows = array( );
        $rows[0] = $this->get_headers_for_ss( $fields, $extra_cols );
        $maxcol = max( array_keys( $rows[0] ) );
        $n = 1;
                            
        $this->files_done = array();                  
        foreach ( $items as $item ) {

            $itemdata = $this->get_item_data_for_ss( $fields, $item, $maxcol );

            $filedata = $this->get_file_data_for_ss( $fields, $item, $maxcol );

            $customdata = $this->get_custom_data_for_ss( $item, $extra_cols, $maxcol );

            for ( $col = 0; $col <= $maxcol; ++$col ) {
                if ( $itemdata[$col] ) {
                    $rows[$n][$col] = $itemdata[$col];
                } elseif ( $filedata[$col] ) {
                    $rows[$n][$col] = $filedata[$col];
                } elseif ( $customdata[$col] ) {
                    $rows[$n][$col] = $customdata[$col];
                } else {
                    $rows[$n][$col] = '';
                }
            }
            ++$n;
        }
        return $rows;
    }


   /**
    * Return the custom data to be displayed in the spreadsheet for an item
    *
    * @param   Ewz_Item               $item         item the data is to be attached to
    * @param   array of (string=>int) $extra_cols   extra columns array from layout
    * @param   int                    $maxcol       max column to be shown
    * @return  array of string        the spreadsheet data for the custom fields
    */
    private function get_custom_data_for_ss( $item, $extra_cols, $maxcol ) {
        assert( is_object( $item ) );
        assert( is_array( $extra_cols ) );
        assert( is_int( $maxcol ) );

        $customrow = array_fill( 0, $maxcol + 1, '' );
        $user = get_userdata( $item->user_id );
        $display = Ewz_Layout::get_all_display_data();
        $custom = new Ewz_Custom_Data( $item->user_id );

        $wform = $this;

        foreach ( $extra_cols as $xcol => $sscol ) {
            if ( $sscol >= 0 ) {
                // could be done more succinctly using $$display[$xcol]['dobject']
                // but harder to understand, and fools an ide into generating a warning
                // $rows[$n][$sscol] = Ewz_Layout::get_extra_data_item( $$display[$xcol]['dobject'], $display[$xcol]['value'] );
                $datasource = '';
                // dont crash on undefined custom data
                if( isset( $display[$xcol] ) ){
                    assert( empty( $customrow[$sscol] ) );
                    switch ( $display[$xcol]['dobject'] ) {
                    case 'wform':
                        $datasource = $wform;
                        break;
                    case 'user':
                        $datasource = $user;
                        break;
                    case 'item':
                        $datasource = $item;
                        break;
                    case 'custom':
                        $datasource = $custom;
                        break;
                    default:
                        throw new EWZ_Exception( 'Invalid data source ' . $display[$xcol]['dobject'] );
                    }
                    $customrow[$sscol] = Ewz_Layout::get_extra_data_item( $datasource, $display[$xcol]['value'] );
                }
            }
        }
        return $customrow;
    }


   /**
    * Get the image file information for an item for the spreadsheet
    * 
    * @param   array of Ewz_Field   $fields     the fields for the layout
    * @param   Ewz_Item             $item       the item the image file(s) are attached to 
    * @param   int                  $maxcol     max column to be shown
    *
    * @return  array of string      the spreadsheet entries for the image(s)
    */

    private function get_file_data_for_ss( $fields, $item, $maxcol ) {
        assert( is_array( $fields ) );
        assert( is_object( $item ) );
        assert( is_int( $maxcol ) );
        $filerow = array_fill( 0, $maxcol + 1, '' );
        if ( $item->item_files ) {
            foreach ( $item->item_files as $field_id => $item_file ) {
                $field = $fields[$field_id];
                if ( $field->ss_column >= 0 ) {
                    assert( empty($filerow[$field->ss_column] ) );
                    if ( isset( $item_file['fname'] ) ) {
                        // if the prefix was already applied, no change
                        if( $this->apply_prefix ){
                            $filerow[$field->ss_column] = basename( $item_file['fname'] );
                        } else {
                            // applying prefix/rename now
                            $filerow[$field->ss_column] = $this->get_new_filename( $fields, $item, $item_file );
                        }
                    } else {
                        $filerow[$field->ss_column] = '';
                    }
                    $this->files_done[ $filerow[$field->ss_column] ] = true;
                }
                if ( 'img' == $field->field_type ) {
                    if ( isset( $item_file['width'] ) && $field->fdata['ss_col_w'] >= 0 ) {
                        assert( empty( $filerow[$field->fdata['ss_col_w']] ) );
                        $filerow[$field->fdata['ss_col_w']] = str_replace( "\t", ' ', $item_file['width'] );
                    }
                    if ( isset( $item_file['height'] ) && $field->fdata['ss_col_h'] >= 0 ) {
                        assert( empty( $filerow[$field->fdata['ss_col_h']] ) );
                        $filerow[$field->fdata['ss_col_h']] = str_replace( "\t", ' ', $item_file['height'] );
                    }
                    if ( isset( $item_file['orient'] ) && $field->fdata['ss_col_o'] >= 0 ) {
                        assert( empty( $filerow[$field->fdata['ss_col_o']] ) );
                        $filerow[$field->fdata['ss_col_o']] = str_replace( "\t", ' ', $item_file['orient'] );
                    }
                }
            }
        }
        return $filerow;
    }

   /* Get the spreadsheet entries for the uploaded data for an item 
    *
    * @param   array of Ewz_Field   $fields     the fields for the layout
    * @param   Ewz_Item             $item       the item the image file(s) are attached to 
    * @param   int                  $maxcol     max column to be shown
    *
    * @return  array of string      the spreadsheet entries for the image(s)
    */

    private function get_item_data_for_ss( $fields, $item, $maxcol ) {
        assert( is_array( $fields ) );
        assert( is_object( $item ) );
        assert( is_int( $maxcol ) );

        $itemrow = array_fill( 0, $maxcol + 1, '' );
        foreach ( $item->item_data as $field_id => $field_value_arr ) {
            if ( array_key_exists( $field_id, $fields ) ) {
                $field = $fields[$field_id];
                if ( $field->ss_column >= 0  && isset( $field_value_arr['value'] ) ) {
                    assert( empty( $itemrow[$field->ss_column] ) );
                    $itemrow[$field->ss_column] = $field_value_arr['value'];
                }
                if ( 'str' == $field->field_type ) {
                    // initcaps any "formatted text" column
                    if ( $field->fdata['ss_col_fmt'] >= 0 ) {
                        assert( empty( $itemrow[$field->fdata['ss_col_fmt']] ) );
                        // if the field is already mixed case, don't touch it
                        if ( preg_match( '/[A-Z]/', $field_value_arr['value'] ) && preg_match( '/[a-z]/', $field_value_arr['value'] ) ) {
                            $itemrow[$field->fdata['ss_col_fmt']] = $field_value_arr['value'];
                        } else {
                            $itemrow[$field->fdata['ss_col_fmt']] = ucwords( strtolower( $field_value_arr['value'] ) );
                        }
                    }
                }
                // filename columns will be set by get_file_data_for_ss
                if ( ( 'img' == $field->field_type ) && 
                     isset( $field_value_arr['value'] ) && 
                     ( $field_value_arr['value'] == 'ewz_img_upload' ) ) {
                    $itemrow[$field->ss_column] = '';
                }
            }
        }
        return $itemrow;
    }

    /*     * ******************  Utility Functions ********************* */

    /**
     * Schedule the closing of this webform for uploads  
     * 
     * @param    string  $close_time   time in Y-m-d H:i:s format at which to close the webform
     * @return   none
     */
    public function schedule_closing( $close_time ){
        assert( is_string( $close_time ) );
        if( !$this->can_manage_webform ){ 
            throw new EWZ_Exception( "No Permission" );
        }
        // Remove existing cron event for this webform if one exists
        wp_clear_scheduled_hook( 'ewz_do_close_webform', array( $this->webform_id) );
        $curr_tz = date_default_timezone_get();
        $tz_opt = get_option('timezone_string');
        if( $tz_opt ){
            date_default_timezone_set( $tz_opt );
        }
        $ctime = strtotime ( $close_time );
        wp_schedule_single_event(  $ctime, 'ewz_do_close_webform', array( $this->webform_id ) );
        if( $tz_opt ){
            date_default_timezone_set( $curr_tz );
        }
            
    }

    public function unschedule_closing( ){
        if( !$this->can_manage_webform ){ 
            throw new EWZ_Exception( "No Permission" );
        }
        // Remove existing cron event for this webform if one exists
        wp_clear_scheduled_hook( 'ewz_do_close_webform', array( $this->webform_id) );
    }


    /**
     * Add the action to schedule the close
     *
     * This function is hooked into init by entrywizard.php
     */ 
    public static function schedule_close(){
        // Hook the close_webform function into the action ewz_do_close_webform
        add_action( 'ewz_do_close_webform', 'Ewz_Webform::close_webform', 10, 1 );
    }

   /**
    * Actually close the webform 
    * 
    * @param   int    $webform_id   the webform to be closed
    * @return  none
    */
    public static function close_webform( $webform_id ){
        assert( Ewz_Base::is_pos_int( $webform_id ) );
        $w = new Ewz_Webform( $webform_id );
        $w->upload_open = false;
        $w->save();
        error_log( "EWZ: webform " . $w->webform_ident . " closed " );
    }


   /**
    * Set the value to be displayed to the user by checking wp_next_scheduled
    * -- this value is not stored in the ewz tables
    *
    * @return  none
    */
    public function set_auto_time(){
        // $nexttime is a unix timestamp in GMT
        $nexttime = wp_next_scheduled('ewz_do_close_webform', array( $this->webform_id ) );
        if( $nexttime ){
            $dateformat = get_option('date_format');
            $curr_tz = date_default_timezone_get();
            $tz_opt = get_option('timezone_string'); 
            if( $tz_opt ){ 
                date_default_timezone_set( $tz_opt ); 
            } 
            $this->auto_close = true;
            // format
            //$this->auto_date = strftime ( self::toStrftimeFormat( $dateformat ), $nexttime );
            $this->auto_date = date( $dateformat, $nexttime );
            //$this->auto_time = strftime ( '%H:%M:%S', $nexttime );
            $this->auto_time = date( 'H:i:s', $nexttime );
            $this->close_at =  $nexttime;
            if( $tz_opt ){ 
                date_default_timezone_set( $curr_tz );
            }
        } else {
            $this->auto_close = false;
            $this->auto_date = '';
            $this->auto_time = '';
            $this->close_at = 0;
        }
    }

    /**
     * Return a string consisting of the html options for selecting a time of day
     */
    public function get_close_opt_array( )
    {
        $options = array();
        $tformat = get_option( 'time_format' );
        if( !$tformat ){
            $tformat = 'H:i';
        }
        for( $h=0; $h < 24; ++$h ) {

            for( $m =0; $m < 60; $m+=15 ){

                $val = sprintf( "%02s:%02s:00", $h, $m );

                $date =  new DateTime( $val );
                $display = $date->format( $tformat );

                if ( $this->auto_time == $val ) {
                    $is_sel = true;
                } else {
                    $is_sel = false;
                }
                array_push( $options, array( 'value' => $val,
                                             'display' => $display,
                                             'selected' => $is_sel ) );
            }
        }
        return $options;
    }

    /**
     * Get the upload directory for this webform
     *
     * Used as a filter like this to set the active upload directory:
     *      add_filter('upload_dir', 'ewz_upload_dir');
     *      ... do the upload ...
     *      remove_filter('upload_dir', 'ewz_upload_dir');
     *
     * @param   array of string  $upload_data   default upload_dir (output of wp_upload_dir()) with components "dir" "url" "error"
     * @return  array  of string  altered value of input array 
     */
    public function ewz_upload_dir( $upload_data ) {
        assert( is_array( $upload_data ) );
        $upload_data['subdir'] = '/' . EWZ_IMG_UPLOAD_SUBDIR . '/' . $this->webform_ident;
        $upload_data['path'] = EWZ_IMG_UPLOAD_DIR . '/' . $this->webform_ident;
        $upload_data['url'] = EWZ_IMG_UPLOAD_URL . '/' . $this->webform_ident;
        return $upload_data;
    }

    /**
     * Is the webform open for upload by the current user
     *
     * @return boolean
     */
    public function open_for_current_user() {
        if ( $this->upload_open ) {
            return true;
        } elseif ( in_array( get_current_user_id(), $this->open_for ) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Create a variable "items" in the object, which is an array of all items attached to the webform
     * Don't do this routinely - could be slow and may not be needed
     *
     * @param  none
     * @return none
     */
    protected function get_items() {
        $this->items = Ewz_Item::get_items_for_webform( $this->webform_id, false );
    }


    /**
     * Make substitutions in the prefix for some expressions
     *
     * @param   array   $data    data required to generate the substitute values
     * @return  changed $prefix
     */
    public function generated_prefix( $data ) {
        assert( is_array( $data ) );
        if( $this->prefix ){
            $placeholders = array( '[~UID]',         '[~WFM]',                    '[~FLD]' );
            $replacements = array( $data['user_id'], $this->webform_ident,  $data['file_field_id'] );
            for ( $n = 1; $n <= 9; ++$n ) {
                if ( isset( $data["custom$n"] ) ) {
                    array_push( $placeholders, '[~CD' . $n . ']' );
                    array_push( $replacements, $data['custom' . $n] );
                }
            }
            foreach(  Ewz_Field::get_fields_for_layout( $this->layout_id, 'ss_column' ) as $field ){
                if( in_array( $field->field_type, array( 'opt', 'rad', 'chk' ) ) && $field->field_ident != 'followupQ' ){
                    $val = '';
                    if( isset ( $data[$field->field_id]['value'] ) ){
                        $val = $data[$field->field_id]['value'];
                        array_push( $replacements, $val );
                    } else {
                        array_push( $replacements, '' );
                    }
                    array_push( $placeholders, '[~' . $field->field_ident . ']' );
                }
            }
            $out_prefix = str_replace( $placeholders, $replacements, $this->prefix ); 
            return $out_prefix;
        } else {
            return $this->prefix;
        }
    }

    /*     * ******************  Validation  ****************************** */

    /**
     * Check for various error conditions and throw an exception when one is found
     *
     * @param  none
     * @return none
     */
    protected function check_errors() {
        global $wpdb;
        if ( is_string( $this->open_for ) ) {
            $this->open_for = unserialize( $this->open_for );
        }
        foreach ( self::$varlist as $key => $type ) {
            settype( $this->$key, $type );
        }
        if ( !isset( $this->webform_title ) ) {
            throw new EWZ_Exception( 'A webform must have a title' );
        }
        if ( !isset( $this->webform_ident ) ) {
            throw new EWZ_Exception( 'A webform must have an identifier' );
        }
        $used1 = $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_WEBFORM_TABLE .
                                                 " WHERE webform_title = %s AND webform_id != %d", $this->webform_title, $this->webform_id ) );
        if ( $used1 > 0 ) {
            throw new EWZ_Exception( "Webform title  '$this->webform_title' is already in use" );
        }
        $used2 = $wpdb->get_var( $wpdb->prepare( "SELECT count(*)  FROM " . EWZ_WEBFORM_TABLE .
                                                 " WHERE webform_ident = %s AND webform_id != %d", $this->webform_ident, $this->webform_id ) );
        if ( $used2 > 0 ) {
            throw new EWZ_Exception( 'Webform identifier ' . $this->webform_ident . ' is already in use' );
        }
        return true;
    }

    /**
     * Get the saved options for attaching images to pages
     *
     * Returned array has values for  'ewz_page_sel' -- id of the selected page, 
     *                                'img_size' -- selected image size option,
     *                                'img_comment' -- boolean, allow comments on attached image
     *                                'dups_ok' -- boolean, allow images to be attached more than once to the same page
     * If there is more than one image field, it may also include a value for 'ifield' -- the image column to use
     * @param   none
     * @return  array of attachment preferences
     */
    public function get_attach_prefs() {
        global $wpdb;

        $prefs = array( 'ewz_page_sel' => 0, 'img_size' => 'thumbnail', 'img_comment' => false, 'dups_ok' => false );
        if ( $this->webform_id ) {

            $wf_prefs = unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT attach_prefs FROM " . EWZ_WEBFORM_TABLE .
                                                                  " WHERE  webform_id = %d", $this->webform_id ) ) );
            if( $wf_prefs ){
                $prefs =  $wf_prefs;
            }
        }
        return $prefs;
    }

    /*     * ******************  Database Updates ********************* */


    /**
     * Save the options for attaching images to pages
     *
     * Input  array should have values for  'ewz_page_sel' -- id of the selected page, 
     *                                      'img_size' -- selected image size option,
     *                                      'img_comment' -- boolean, allow comments on attached image
     *                                      'dups_ok' -- boolean, allow an image to be attached more than once
     *
     * @param  array of attachment preferences
     * @return none
     */
    public function save_attach_prefs( $prefs ) {
        assert( is_array( $prefs ) );
        global $wpdb;

        if ( $this->webform_id ) {

            if( !$this->can_manage_webform ) {
                throw new EWZ_Exception( 'No permission to manage webform' );
            }
            $data = stripslashes_deep( array( 'attach_prefs' => serialize( $prefs ) ) );

            $rows = $wpdb->update( EWZ_WEBFORM_TABLE, $data, array( 'webform_id' => $this->webform_id ), array( '%s' ), array( '%d' ) );
            if ( $rows > 1 ) {
                throw new EWZ_Exception( "Problem setting attach options for the webform '$this->webform_title'" );
            }
        }
    }

    /**
     * Save the webform to the database
     *
     * Check for permissions, then update or insert the webform data
     *
     * @param none
     * @return none
     */
    public function save() {
        global $wpdb;
        if ( $this->webform_id ) {
            // check the old layout_id against the new -- if they are different, need the right permission
            $old_webform = new Ewz_Webform( $this->webform_id );

            if ( ( $old_webform->layout_id !== $this->layout_id ) &&  !$this->can_edit_webform ) {
                throw new EWZ_Exception( 'No changes saved. Insufficient permissions to change layout ', $old_webform->layout_id );
            }
            if ( !$this->can_manage_webform && !defined( 'DOING_CRON' )) {
                throw new EWZ_Exception( "No changes saved. Insufficient permissions to change webform '$this->webform_title' )" );
            }
        } else {
            if ( !Ewz_Permission::can_edit_all_webforms() ) {
                throw new EWZ_Exception( 'No changes saved. Insufficient permissions to create a webform' );
            }
            $this->webform_order = self::count_webforms();
        }
        // ok, we have all the permissions, go ahead

        $this->check_errors();

        $data = stripslashes_deep( array(
                                         'layout_id' => $this->layout_id,               // %d
                                         'num_items' => $this->num_items,               // %d
                                         'webform_title' => $this->webform_title,       // %s
                                         'webform_ident' => $this->webform_ident,       // %s
                                         'webform_order' => $this->webform_order,       // %d
                                         'upload_open' => $this->upload_open ? 1 : 0,   // %d
                                         'open_for' => serialize( $this->open_for ),    // %s
                                         'prefix' => $this->prefix,                     // %s
                                         'apply_prefix' => $this->apply_prefix ? 1 : 0, // %d
                                         'gen_fname' => $this->gen_fname ? 1 : 0,       // %d
                                         ) );
        $datatypes = array( '%d',   // = layout_id
                            '%d',   // = num_items
                            '%s',   // = webform_title
                            '%s',   // = webform_ident
                            '%d',   // = webform_order
                            '%d',   // = upload_open
                            '%s',   // = open_for
                            '%s',   // = prefix
                            '%d',   // = apply_prefix
                            '%d',   // = gen_fname
                            );

        if ( $this->webform_id ) {
            $rows = $wpdb->update( EWZ_WEBFORM_TABLE, 
                                   $data,       array( 'webform_id' => $this->webform_id ), 
                                   $datatypes,  array( '%d' ) );
            if ( $rows > 1 ) {
                throw new EWZ_Exception( "Problem updating the webform '$this->webform_title'" );
            }
        } else {
            $wpdb->insert( EWZ_WEBFORM_TABLE, $data, $datatypes );
            $inserted = $wpdb->insert_id;
            if ( !$inserted ) {
                throw new EWZ_Exception( "Problem creating the webform '$this->webform_title'" );
            }
        }
    }

    /**
     * Delete the webform from the database
     *
     * @param  none
     * @return none
     */
    public function delete( $delete_items = self::FAIL_IF_ITEMS ) {
        assert( $delete_items == self::DELETE_ITEMS || 
                $delete_items == self::FAIL_IF_ITEMS );
        global $wpdb;
        if ( $this->webform_id ) {
            if ( !Ewz_Permission::can_edit_all_webforms() ) {
                throw new EWZ_Exception( "No changes saved. Insufficient permissions to delete webform '$this->webform_title'" );
            }

            $this->get_items();
            $errmsg = '';
            if ( $delete_items == self::DELETE_ITEMS ) {
                foreach ( $this->items as $item ) {
                    try { 
                        $item->delete();
                    } catch( EWZ_Exception $e ) {
                        $errmsg .= $e->getMessage();
                    }
                }
                if( $errmsg ){
                    throw new EWZ_Exception( $errmsg );
                } 
            } else {
                $n = count( $this->items );
                if ( $n > 0 ) {
                    throw new EWZ_Exception( "Webform has $n items attached." );
                }
            }

            $rowsaffected = $wpdb->query( $wpdb->prepare( "DELETE FROM " . EWZ_WEBFORM_TABLE . " WHERE webform_id = %d", $this->webform_id ) );
            if ( 1 == $rowsaffected ) {
                self::renumber_webforms($this->webform_order);
                return "1 webform deleted.";
            } else {
                throw new EWZ_Exception( "Problem deleting webform '$this->webform_title '" );
            }
        }
    }

    /*     * ******************  Utility Functions ********************* */
       
    /*
     * Used as a default filter when none is specified
     */
    public static function truefunc()
    {
        return true;
    }

    /**
     * Return a random string of lower-case alpha, upper-case alpha or integer characters
     *
     * @param  int   $len    length of string to return
     * @return string   
     */
    private function randstring( $len ){
        assert( Ewz_Base::is_pos_int($len ) );

        return implode('', array_map( function () { 
                    $type = rand(0, 2);
                    switch ( $type ){
                    case 0:  return chr( mt_rand(48, 57) );
                        break;
                    case 1: return chr(mt_rand(97, 122) );
                        break;
                    case 2: return chr( mt_rand(65, 90) );
                        break;
                    default: return chr(mt_rand(97, 122) );
                    }
                },
                range(0, $len - 1) ));                 
    }

    /**
     * Stop output buffering  
     */
    private static function ewz_disable_gzip(){
        if( extension_loaded( 'zlib' ) ){
            ini_set('zlib.output_compression', 0 );
        }
        if( ini_get( 'output_buffering' ) ){
            ini_set( 'output_buffering', false );
        }
        if( function_exists( 'apache_setenv' ) ){
            apache_setenv( 'no-gzip', 1 );
        }
    }
}

