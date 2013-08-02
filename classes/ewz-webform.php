<?php

defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-item.php");
require_once( EWZ_PLUGIN_DIR . "classes/ewz-permission.php");
require_once( EWZ_PLUGIN_DIR . "ewz-custom-data.php");

/* * ***********************************************************
 * Interaction with the EWZ_WEBFORM table
 *
 * *********************************************************** */

class Ewz_Webform extends Ewz_Base {

    // key
    public $webform_id;
    // database
    public $layout_id;
    public $webform_title;
    public $webform_ident;
    public $upload_open;
    public $open_for;
    public $prefix;
    // extra
    public $can_download;
    public $can_edit_webform;
    public $can_manage_webform;
    public $itemcount;
    public $open_for_string = '';

    public static function remove_prefix_subs( $string ) {
        assert( is_string( $string ) );
        $string2 = preg_replace( '/\[~UID\]|\[~WFM\]|\[~ITM\]|\[~ORD\]|\[~FLD\]/', '', $string );
        return $string2;
    }

    // keep list of db data names/types as a convenience for iteration and so we can easily add new ones.
    // Dont include webform_id
    public static $varlist = array(
        'layout_id' => 'integer',
        'webform_title' => 'string',
        'webform_ident' => 'string',
        'upload_open' => 'boolean',
        'open_for' => 'array',
        'prefix' => 'string',
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
                        " WHERE layout_id = %d  ORDER by webform_id", $layout_id ) );
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
    public static function get_all_webforms() {
        global $wpdb;
        $list = $wpdb->get_col( "SELECT webform_id  FROM " . EWZ_WEBFORM_TABLE . " ORDER BY webform_id" );
        $webforms = array( );
        foreach ( $list as $webform_id ) {
            $webform = new Ewz_Webform( $webform_id );
            array_push( $webforms, $webform );
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
        $list = $wpdb->get_col( "SELECT webform_ident  FROM " . EWZ_WEBFORM_TABLE . " ORDER BY webform_id" );
        return $list;
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

    /*     * ****************** Construction ************************* */

    /**
     * Assign the object variables from an array
     *
     * Calls parent::base_set_data with the list of variables and the data structure
     *
     * @param  array  $data: input data.
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
        if ( Ewz_Base::is_pos_int( $init ) ) {
            $this->create_from_id( $init );
        } elseif ( is_string( $init ) ) {
            $this->create_from_ident( $init );
        } elseif ( is_array( $init ) ) {
            $this->create_from_data( $init );
        } else {
            throw new EWZ_Exception( 'Invalid webform constructor' );
        }
        if ( $this->webform_id ) {
            $this->itemcount = Ewz_Item::get_itemcount_for_webform( $this->webform_id, false );
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
                    implode( ', ', array_map( create_function( '$v', 'return get_userdata($v)->user_login;' ), $this->open_for ) ) .
                    ' only';
        }
        $this->can_download = Ewz_Permission::can_download( $this );
        $this->can_edit_webform = Ewz_Permission::can_edit_webform( $this );
        $this->can_manage_webform = Ewz_Permission::can_manage_webform( $this );
    }

    /**
     * Create a new webform object from the webform_id by getting the data from the database
     *
     * @param  int  $id: the webform id
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
            throw new EWZ_Exception( 'Unable to find matching webform', $id );
        }
        $this->set_data( $dbwebform );
    }

    /**
     * Create a new webform object from the webform ident by getting the data from the database
     *
     * @param  string  $ident: the webform ident
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
            throw new EWZ_Exception( 'Unable to find matching webform', $ident );
        }
        $this->set_data( $dbwebform );
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
            $data['open_for'] = array_map( create_function( '$v', 'return (int)$v;' ),
                    array_filter( array_key_exists( 'o_user', $data ) ? $data['o_user'] : array( ), create_function( '$v', 'return ($v != "");' ) ) );
        }
        if ( !array_key_exists( 'webform_id', $data ) ) {
            $data['webform_id'] = 0;
        }
        $this->set_data( $data );
        $this->check_errors();
    }

    /*     * ******************  Download Functions ********************* */

    /**
     * Print the zip archive of images to stdout
     *
     * @param   boolean $include_ss   if true, add the spreadsheet to the archive
     * @return  none
     */
    public function download_images( $items, $include_ss ) {
        assert( is_array( $items ) );
        assert( is_bool( $include_ss ) );
        if ( count( $items ) < 1 ) {
            throw new EWZ_Exception( "No matching items found." );
        }
        $date = date( 'Ymd' );
        $up = $this->ewz_upload_dir( wp_upload_dir() );  // uploads/ewz_img_uploads/$this->webform_ident
        if ( !is_dir( $up['path'] ) ) {
            mkdir( $up['path'] );
        }

        $archive_fname = "ewz_" . $this->webform_ident . "_$date.zip";
        $archive_path = $up['path'] . "/$archive_fname";
        $archive_url = $up['url'] . "/$archive_fname";

        // remove any other zip files from this webform
        array_map( "unlink", glob( $up['url'] . "/ewz_" . $this->webform_ident . "*.zip" ) );

        // create a new archive
        $this->make_zip_archive( $items, $archive_path, $include_ss );
        // display it, making sure the redirect location is not cached
        // Date in the past
        header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
        // always modified
        header( "Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . " GMT" );
        // HTTP/1.1
        header( "Cache-Control: no-store, no-cache, must-revalidate" );
        header( "Cache-Control: post-check=0, pre-check=0", false );
        // HTTP/1.0
        header( "Pragma: no-cache" );

        header( "Location: $archive_url" );
        exit;
    }

    /**
     * Create the zip archive of images ( and possibly spreadsheet )
     *
     * @param   $items  array of items to be zipped
     * @param   $fpath  full path name of archive to be created
     * @param   boolean $include_ss   if true, add the spreadsheet to the archive
     * @return  string  status message
     */
    protected function make_zip_archive( $items, $fpath, $include_ss ) {
        assert( is_array( $items ) );
        assert( strpos( $fpath, EWZ_IMG_UPLOAD_DIR ) === 0 );
        assert( is_bool( $include_ss ) );

        if ( !$this->can_download ) {
            throw new EWZ_Exception( "No Permission" );
        }
        set_time_limit( EWZ_FILE_DOWNLOAD_TIME );

        $tmpn = 0;
        $zip = new ZipArchive();
        $msg = '';
        if ( $zip->open( $fpath, ZIPARCHIVE::OVERWRITE ) ) {
            foreach ( $items as $item ) {
                $custom = new Ewz_Custom_Data( $item->user_id );
                foreach ( $item->item_files as $item_file ) {
                    if ( !isset( $item_file['fname'] ) ) {
                        continue;   // ignore items with no image file
                    }
                    ++$tmpn;
                    // a very rough-and-ready way to allow more time if there are a lot of image files
                    // adds EWZ_FILE_DOWNLOAD_TIME seconds to the time limit for every 50 files.
                    if ( $tmpn > 50 ) {
                        set_time_limit( EWZ_FILE_DOWNLOAD_TIME );
                        $tmpn = 0;
                    }
                    $subst_data = array(
                        'field_id' => $item_file['field_id'],
                        'user_id' => $item->user_id,
                        'item_id' => $item->item_id,
                    );
                    foreach ( $custom as $custkey => $custval ) {
                        $subst_data[$custkey] = $custval;
                    }
                    if ( is_file( $item_file['fname'] ) ) {
                        $zip->addFile( $item_file['fname'], $this->do_substitutions( $this->prefix, $subst_data ) . basename( $item_file['fname'] ) );
                    } else {
                        $msg .= "\n\nUnable to find file " . basename( $item_file['fname'] );
                    }
                }
            }
            if ( $include_ss ) {
                $csv_fname = $this->download_spreadsheet( $items, true );
                if ( is_file( $csv_fname ) ) {
                    $zip->addFile( $csv_fname, basename( $csv_fname ) );
                } else {
                    $msg .= "\n\nUnable to find .csv file ";
                }
            }
            if ( $msg ) {
                $zip->addFromString( 'ERRORS.txt', "\nThe following errors were encountered while generating the zip archive: $msg" );
            }
            $zip->close();
        } else {
            throw new EWZ_Exception( "Sorry, there was a problem creating the zip archive.  If this continues, please contact your administrator." );
        }
        return $msg;
    }

    /**
     * Prints the requested spreadsheet to stdout
     *
     * Generates a .csv-formatted summary of the uploaded items for the webform
     *
     * @param  boolean  $generate_file  -- if true, generate a file, otherwise print to stdout
     * @return none
     */
    public function download_spreadsheet( $items, $generate_file ) {
        assert( is_array( $items ) );
        assert( is_bool( $generate_file ) );

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


        if ( $generate_file ) {
            $out = fopen( sys_get_temp_dir() . "/ewz_" . $this->webform_ident . ".csv", 'w' );
        } else {
            $filename = 'webdata_' . date( 'Ymd' ) . '.csv';

            header( "Content-Disposition: attachment; filename=\"$filename\"" );
            header( "Content-Type: application/octet-stream" );
            header( "Cache-Control: no-cache" );

            $out = fopen( "php://output", 'w' );  // write directly to php output, not to a file
        }
        foreach ( $rows as $r ) {
            fputcsv( $out, $r, "," );
        }
        fclose( $out );
        if ( $generate_file ) {
            return sys_get_temp_dir() . "/ewz_" . $this->webform_ident . ".csv";
        } else {
            // this forces the download dialog
            exit();
        }
    }

    /**
     * Return the header row for the spreadsheet
     *
     * @param   $fields       fields array from layout - data input via the webform
     * @param   $extra_cols   extra columns array from layout - other data for display
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
            if ( $sscol >= 0 ) {
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
     * @param   $fields       fields array from layout - description of data input via the webform
     * @param   $extra_cols   extra columns array from layout - other data for display
     * @param   $items        data uploaded via webform
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

    private function get_custom_data_for_ss( $item, $extra_cols, $maxcol ) {
        $customrow = array_fill( 0, $maxcol + 1, '' );
        $user = get_userdata( $item->user_id );
        $display = Ewz_Layout::get_all_display_data();
        $custom = new Ewz_Custom_Data( $item->user_id );

        $wform = $this;

        foreach ( $extra_cols as $xcol => $sscol ) {
            if ( $sscol >= 0 ) {
                // could be done more succinctly using $$display[$xcol]['dobject']
                // but harder to understand, and fools the ide into generating a warning
                // $rows[$n][$sscol] = Ewz_Layout::get_extra_data_item( $$display[$xcol]['dobject'], $display[$xcol]['value'] );
                assert( empty( $customrow[$sscol] ) );
                $datasource = '';
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
        return $customrow;
    }

    private function get_file_data_for_ss( $fields, $item, $maxcol ) {
        $filerow = array_fill( 0, $maxcol + 1, '' );
        $custom1 = new Ewz_Custom_Data( $item->user_id );
        if ( $item->item_files ) {
            foreach ( $item->item_files as $field_id => $item_file ) {
                // do the prefix substitutions
                $subst_data = array(
                    'field_id' => $item_file['field_id'],
                    'user_id' => $item->user_id,
                    'item_id' => $item->item_id,
                );
                foreach ( $custom1 as $custkey => $custval ) {
                    $subst_data[$custkey] = $custval;
                }
                $field = $fields[$field_id];
                if ( $field->ss_column >= 0 ) {
                    assert( empty($filerow[$field->ss_column] ) );
                    if ( isset( $item_file['fname'] ) ) {
                        $filerow[$field->ss_column] =
                                $this->do_substitutions( $this->prefix, $subst_data ) . basename( $item_file['fname'] );
                    } else {
                        $filerow[$field->ss_column] = '';
                    }
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

    private function get_item_data_for_ss( $fields, $item, $maxcol ) {
        $itemrow = array_fill( 0, $maxcol + 1, '' );
        foreach ( $item->item_data as $field_id => $field_value_arr ) {
            if ( array_key_exists( $field_id, $fields ) ) {
                $field = $fields[$field_id];
                if ( $field->ss_column >= 0 ) {
                    assert( empty( $itemrow[$field->ss_column] ) );
                    $itemrow[$field->ss_column] = $field_value_arr['value'];
                }
                if ( 'str' == $field->field_type ) {
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
                if ( ( 'img' == $field->field_type ) && ( $field_value_arr['value'] == 'ewz_img_upload' ) ) {
                    $itemrow[$field->ss_column] = '';
                }
            }
        }
        return $itemrow;
    }

    /*     * ******************  Utility Functions ********************* */

    /**
     * Get the upload directory for this webform
     *
     * Used as a filter like this to set the active upload directory:
     *      add_filter('upload_dir', 'ewz_upload_dir');
     *      ... do the upload ...
     *      remove_filter('upload_dir', 'ewz_upload_dir');
     *
     * @param   array  default upload_dir (output of wp_upload_dir()) with components "dir" "url" "error"
     * @return  array  $upload
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
        }

        if ( in_array( get_current_user_id(), $this->open_for ) ) {
            return true;
        }
        return false;
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
     * @param   string  $prefix:  string to be changed
     * @param   array   $data:    data required to generate the substitute values
     * @return  changed $prefix
     */
    public function do_substitutions( $in_prefix, $data ) {
        assert( is_string( $in_prefix ) );
        assert( is_array( $data ) );

        $placeholders = array( '[~UID]',         '[~WFM]',             '[~ITM]',         '[~FLD]' );
        $replacements = array( $data['user_id'], $this->webform_ident, $data['item_id'], $data['field_id'] );
        for ( $n = 1; $n <= 9; ++$n ) {
            if ( isset( $data["custom$n"] ) ) {
                array_push( $placeholders, '[~CD' . $n . ']' );
                array_push( $replacements, $data['custom' . $n] );
            }
        }
        $out_prefix = str_replace( $placeholders, $replacements, $in_prefix );
        return $out_prefix;
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

    /*     * ******************  Database Updates ********************* */

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

            $curr_webform = new Ewz_Webform( $this->webform_id );

            if ( ( $curr_webform->layout_id !== $this->layout_id ) && !Ewz_Permission::can_edit_webform( $curr_webform ) ) {
                throw new EWZ_Exception( 'No changes saved. Insufficient permissions to change layout', $curr_webform->layout_id );
            }
            if ( !Ewz_Permission::can_manage_webform( $curr_webform ) ) {
                throw new EWZ_Exception( "No changes saved. Insufficient permissions to edit webform '$this->webform_title' )" );
            }
        } else {
            if ( !Ewz_Permission::can_edit_all_webforms() ) {
                throw new EWZ_Exception( 'No changes saved. Insufficient permissions to create a webform' );
            }
        }
        // ok, we have all the permissions, go ahead

        $this->check_errors();

        $data = stripslashes_deep( array(
            'layout_id' => $this->layout_id,
            'webform_title' => $this->webform_title,
            'webform_ident' => $this->webform_ident,
            'upload_open' => $this->upload_open ? 1 : 0,
            'open_for' => serialize( $this->open_for ),
            'prefix' => $this->prefix,
                ) );
        $datatypes = array( '%d', '%s', '%s', '%d', '%s', '%s' );

        if ( $this->webform_id ) {
            $rows = $wpdb->update( EWZ_WEBFORM_TABLE, $data, array( 'webform_id' => $this->webform_id ), $datatypes, array( '%d' ) );
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
    public function delete( $delete_items = false ) {
        assert( is_bool( $delete_items ) || empty( $delete_items ) );
        global $wpdb;
        if ( $this->webform_id ) {
            if ( !Ewz_Permission::can_edit_all_webforms() ) {
                throw new EWZ_Exception( "No changes saved. Insufficient permissions to delete webform '$this->webform_title'" );
            }

            $this->get_items();
            if ( $delete_items ) {
                foreach ( $this->items as $item ) {
                    $item->delete();
                }
            } else {
                $n = count( $this->items );
                if ( $n > 0 ) {
                    throw new EWZ_Exception( "Webform has $n items attached." );
                }
            }

            $rowsaffected = $wpdb->query( $wpdb->prepare( "DELETE FROM " . EWZ_WEBFORM_TABLE . " where webform_id = %d", $this->webform_id ) );
            if ( 1 == $rowsaffected ) {
                return "1 webform deleted.";
            } else {
                throw new EWZ_Exception( "Problem deleting webform '$this->webform_title '" );
            }
        }
    }

}

