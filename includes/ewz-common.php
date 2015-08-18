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
function ewz_to_valid_fname( $string, $appendU )
{
    assert( is_string( $string ) );
    assert( is_bool( $appendU ) );
    // replace all non-alphanum chars except '_', '-', '.' with '_'
    $string1 = preg_replace( '/[^a-zA-Z0-9_\-\.]/', '_', $string );

    // replace all but last dot -- otherwise, a file with more than one dot fails
    $numdots = substr_count( $string, '.' );
    $string2 = preg_replace( '/\./', '_', $string1, $numdots - 1 );

    if( $appendU ){ 
        // append '_' if name ends in digit, so wp's added numbers can be seen,
        // and so no filename is completely numeric (wp does odd things if filename is just a number)
        $string2 = preg_replace( '/(\d)\./', '${1}_.', $string2 );
    }

    return $string2;
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
 * @param   array  $opts each element being an array of the form
 *                   ('value'=>XXX, 'display'=>YYY, ['selected'=>true/false])
 * @param   $none_if_only1  boolean  if true, return an empty string if there is only one option
 * @return  string html option list
 */
function ewz_option_list( $opts, $none_if_only1 = false ){
    assert( is_array( $opts ) );
    assert( is_bool( $none_if_only1 ) );
    $return = '';
    if( $none_if_only1 && count( $opts ) < 2 ){
        return '';
    }
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
/**
 * Return the html for displaying a single text-input field
 *
 * @param   string  $ename       escaped js name of field
 * @param   int     $id          id to use for the field
 * @param   string  $savedval    data currently stored for the field
 * @param   array   $field       field info
 * @return  string   -- the html
 */
function ewz_display_str_form_field( $ename, $id, $savedval, $field )
{
    assert( is_string( $id ) );
    assert( is_string( $ename ) );
    assert( is_string( $savedval ) );
    assert( Ewz_Base::is_nn_int( $field->fdata['fieldwidth'] ) );

    if( !isset( $field->fdata['textrows']  ) ){
        $field->fdata['textrows'] = 1;
    }
    if( $field->fdata['textrows'] > 1 ){
        return '<textarea class="ewz_area" name="' . $ename .
                       '" id="' . $id .
                       '" style="width:' . esc_attr( $field->fdata['fieldwidth'] ) . 'em;' .
                                 ' height:' . esc_attr( $field->fdata['textrows'] ) .'em;' . '"' .
                       '" maxlength="' . esc_attr( $field->fdata['maxstringchars'] ) .
                       '">' . $savedval . '</textarea>';
        
    } else {
        return '<input type="text" name="' . $ename .
                       '" id="' . $id .
                       '" size="' . esc_attr( $field->fdata['fieldwidth'] ) .
                       '" maxlength="' . esc_attr( $field->fdata['maxstringchars'] ) .
                       '" value="' . esc_attr( $savedval ) .
                '">';
    }
}

/**
 * Return the html for displaying a single image field
 *
 * @param   string  $ename       escaped js name of field
 * @param   int     $webform_id
 * @param   string  $savedval    data currently stored for the field
 * @param   array   $field       field info
 * @return  string   -- the html
 */
function ewz_display_img_form_field( $ename, $id, $savedval, $field, $webform_id )
{
    assert( is_string( $id ) );
    assert( is_string( $ename ) );
    assert( is_string( $savedval ) || $savedval === null );
    assert( Ewz_Base::is_pos_int( $field->field_id ) );
    assert( Ewz_Base::is_pos_int( $webform_id ) );

    $esc_wid = esc_attr( $webform_id );
    if ( $savedval ) {
        $ret  = '<input type="hidden" name="' . $ename . '" value="ewz_img_upload" >';
        $ret .= '<img id="' . $id . '" src="' . esc_url( $savedval ) . '">';
        return $ret;
    } else {
        $qname = "'" . $id . "'";
        $fid = esc_attr( $field->field_id );
        $imginfo = '<input type="file"  name="' . $ename .  '" id="' . $id .
                           '" onchange="fileSelected(' . $fid . ', ' . $qname . ', ' . $esc_wid . ' );">';
        // watch no spaces here - they put newlines between the divs
        $imginfo .= '<div id="dv_' . $id . '" style="display:none">';
        $imginfo .= '<div id="nm_' . $id . '"></div>';
        $imginfo .= '<div id="sz_' . $id . '"></div>';
        $imginfo .= '<div id="tp_' . $id . '"></div>';
        $imginfo .= '<div id="wh_' . $id . '"></div>';
        $imginfo .= '</div>';
        return $imginfo;
    }
}

/**
 * Return the html for displaying a single option field
 *
 * @param   string $ename       escaped js name of field
 * @param   int    $webform_id
 * @param   mixed  $savedval    data currently stored for the field
 * @param   array  $field       field info
 * @return  string   -- the html
 */
function ewz_display_opt_form_field( $ename, $id, $savedval, $field, $fixed )
{
    assert( is_string( $ename ) );
    assert( is_string( $id ) );
    assert( is_string( $savedval ) );
    assert( is_object( $field ) );
    assert( is_bool( $fixed ) );

    $txt = '<select name="' . $ename . '" id="' . $id .'">';
    $txt .= '  <option value="" ' .  ( $fixed ? ' disabled="disabled"' : '' ) . '></option>' ;
    foreach ( $field->fdata['options'] as $n => $dataval ) {
        $txt .= '<option value="' . esc_attr( $dataval['value'] ) . '"';
        if ( $dataval['value'] === $savedval ) {
            $txt .=         ' selected="selected" ';
        } elseif( $fixed ){
            $txt .=         ' disabled="disabled"';
        }
        $txt .= '>' . esc_attr( $dataval['label'] ) . '</option>';
    }
    $txt .= '</select>';
    return $txt;
}

/**
 * Display a radio button
 *
 * Radios in the same column must have the same name, so use the field id for that.
 * Give the button a value equal to the name we would normally have used.
 * If it is checked, when submitting, Javascript disables it and creates a new hidden
 * input with name equal to the value of the radio button and value '1'.
 *
 * @param
 * @return
 */
function ewz_display_rad_form_field( $ename,  $id, $savedval, $field )
{
    assert( is_string( $ename ) );
    assert( is_string( $id ) );
    assert( in_array( $savedval, array( null, 1, 0 ) ) );
    assert( is_object( $field ) );
    assert( Ewz_Base::is_pos_int( $field->field_id ) );

    return '<input type="radio"  name="radio' . $field->field_id . '"' .
                   ' id="' . $id . '"' .
                   ' value="' . $ename .'" ' .
                   ($savedval ? ' checked="checked" ' : '') .
            '>';
}


function ewz_display_chk_form_field( $ename, $id, $savedval )
{
    assert( is_string( $id ) );
    assert( is_string( $ename ) );
    assert( in_array( $savedval, array( null, 1, 0 ) ) );

    return '<input type="checkbox"  name="' . $ename .
                   '" value="1"  id="' . $id . '"' .
          ($savedval ? ' checked="checked" ' : '') .
            '>';
}
