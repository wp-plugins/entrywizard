<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . '/classes/ewz-base.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-exception.php' );

    /* used only in asserts */
    function ewz_is_valid_ewz_path( $val ) {
       assert( is_string( $val ) );
       $info = pathinfo( $val );
       // strncmp returns 0 on
       return( strpos( $info['dirname'], EWZ_IMG_UPLOAD_DIR ) === 0 );
    }

    /* used only in asserts */
    function ewz_is_valid_ewz_url( $val ){
        assert( is_string( $val ) );
        return( strpos( $val, EWZ_IMG_UPLOAD_URL ) === 0 );
    }


/**
 * Get rid of dangerous special characters in a string to be used as part of a file path
 *
 * replace all special chars with '_'
 * replace all but last dot with '_'
 * append '_' to base name if name ends in a digit ( so wp's added numbers can be seen, and so
 *      no filename is completely numeric -  wp does odd things if filename is just a number )
 *
 * @param   string  $string
 * @return  string
 */
function ewz_to_valid_fname( $string )
{
    assert( is_string( $string ) );

    // replace all special chars with _
    $string1 = preg_replace( '/[^a-zA-Z0-9_\-\.]/', '_', $string );

    // replace all but last dot -- otherwise, a file with more than one dot fails
    $numdots = substr_count( $string, '.' );
    $string2 = preg_replace( '/\./', '_', $string1, $numdots - 1 );

    // append '_' if name ends in digit, so wp's added numbers can be seen,
    // and so no filename is completely numeric (wp does odd things if filename is just a number)
    $string = preg_replace( '/(\d)\./', '${1}_.', $string2 );

    return $string;
}


/**
 * Recursive version of wp function esc_html
 *
 * @param  mixed  $data
 * @return mixed
 */
function ewz_html_esc( $data ) {
    // no assert
    if ( is_array( $data ) ) {
        foreach ( $data as $key => $value ) {
            $data[$key] = ewz_html_esc( $data[$key] );
        }
    } elseif ( is_object( $data ) ) {
        foreach ( $data as $key => $value ) {
            $data->{$key} = ewz_html_esc( $data->$key );
        }
    } elseif  ( is_string( $data ) ) {

        $data = esc_html( $data );
    }
    return $data;
}


/**
 * Generate an html option list from an array, escaped for display
 *
 * @param   array  $opts, each element being an array of the form
 *                   ('value'=>XXX, 'display'=>YYY, ['selected'=>true/false])
 * @return  string html option list
 */
function ewz_option_list( $opts ){
    assert( is_array( $opts ) );
    $return = '';
    foreach( $opts as $option ){
        assert( is_array( $option ) );
        assert( array_key_exists( 'value', $option ) );
        assert( array_key_exists( 'display', $option ) );

        $return .= '<option value="' . esc_attr( $option['value'] ) . '" ';
        if( isset( $option['selected'] ) &&  $option['selected'] ){
            $return .= 'selected="selected"';
        }
        $return .= '>' . esc_html( $option['display'] ) . '</option>';
    }
    return $return;
}


/**
 * Generate a name for the thumbnail file from the filename
 *
 * Replace .ext with  something like "-200x150.ext", following
 *              the convention used by Wordpress
 * @param   $fpath  input filename ( full path )
 * @return  $fname with .ext replaced by -thumb.ext
 */
function ewz_thumbfile_path( $fpath ){
    assert( ewz_is_valid_ewz_path( $fpath ) );

    $ext = pathinfo( $fpath, PATHINFO_EXTENSION );

    $dim =  ewz_thumbfile_dimensions();

    return preg_replace( "/\.$ext$/", "-{$dim['w']}x{$dim['h']}.$ext", $fpath );
}
/**
 * Return the dimension array matching WP's 'thumbnail' size options
 *
 * @param   none
 * @return  array ( 'w'=>thumbnail width, 'h'=>thumbnail height )
 */
function ewz_thumbfile_dimensions(){

    $w = intval( get_option( 'thumbnail_size_w' ) );
    if( !$w ){ $w = 128; }
    $h = intval( get_option( 'thumbnail_size_h' ) );
    if( !$h ){ $h = 96; }

    return array('w'=>$w, 'h'=>$h);
}

/**
 * Generate a url from a path in the uploads directory
 *
 * @param   $fname  input filename, must be in the uploads directory
 * @return  url
 */
function ewz_file_to_url( $fname ){
    assert( ewz_is_valid_ewz_path( $fname ) );

    $uploads = wp_upload_dir();

    if( strncmp( $fname, $uploads['basedir'], strlen($uploads['basedir']) ) ){
        throw new EWZ_Exception( "Input filename $fname is not in the uploads path" );
    }
    return str_replace( $uploads['basedir'], $uploads['baseurl'], $fname );
}

/**
 * Generate a filename from the url of a file in the uploads directory
 *
 * @param   $url  input url, must be in the uploads directory
 * @return  string  filename
 */
function ewz_url_to_file( $url ){
    assert( ewz_is_valid_ewz_url( $url ) );

    $uploads = wp_upload_dir();
    $starts_with = ( strpos( $url, $uploads['baseurl'] ) === 0 );
    if( !$starts_with ) {
        throw new EWZ_Exception( "Input url $url is not in the uploads path" );
    }
    return str_replace( $uploads['baseurl'], $uploads['basedir'], $url );
}
