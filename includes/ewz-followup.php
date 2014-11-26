<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . 'includes/ewz-upload.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-exception.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-item.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-webform.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-layout.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-followup-input.php' );

/**
 * Function called by  ewz_followup shortcode
 * [ewz_followup  webforms="example,pair,test,next" show="excerpt,content"]
 * Form to populate the followup field if it exists, for any item uploaded by the
 * current user to any of the webforms defined by the "webforms" attribute.
 *
 * @param   $atts  shortcode attributes ( 'webforms' and 'field' )
 * @return  html for the form
 */
function ewz_followup( $atts ) {
    assert( is_array( $atts ) );
    $layouts = array();
    $webforms = array();

    /* ****************** */
    /* Check user status  */
    /* ****************** */
    get_currentuserinfo();

    // the field params that we assume are the same for all webforms
    $followup_data = array(
                           'fdata' =>  array(),
                           'required' => false,
                           'field_type' => '',
                           'field_header' => '',
                            );
    try{
        if ( !is_user_logged_in() ) {
            return 'Sorry, you must be logged in to see this.';
        }
        // get the followup field.
        // this assumes all the webforms use the same layout, or at least have
        // exactly the same specs for the 'followupQ' field
        if ( !array_key_exists( 'idents', $atts ) ) {
            throw new EWZ_Exception( 'Missing idents attribute for shortcode' );
        }
        $idents = explode( ',', $atts['idents'] );
        $valid_idents = Ewz_Webform::get_all_idents();

        // prefix each show item with "p" to match the way it is stored on the database
        $show_data = array();
        $show_admin_data = false;
        if( array_key_exists( 'show', $atts ) ){
            foreach( explode( ',', $atts['show'] ) as $data ){
                if( !in_array( $data, array( 'title', 'excerpt', 'content','item_data' ) ) ){
                    throw new EWZ_Exception(  'Invalid data specification in shortcode' );
                }
                if( $data == 'item_data'){
                    $show_admin_data = true;
                } else {
                    array_push( $show_data,  "p$data" );
                }
            }
        }
        foreach( $idents as $ident ){
            if ( !in_array( $ident, $valid_idents ) ) {
                throw new EWZ_Exception(  'Invalid identifier "' . $ident . '" in shortcode' );
            }
            $webforms[$ident] = new Ewz_Webform( $ident );
            $layouts[$ident] = new Ewz_Layout( $webforms[$ident]->layout_id );
            $follow_id = $layouts[$ident]->contains_followup();
            if( $follow_id  ){
                $f = new Ewz_Field( $follow_id );
                $followup_data = array(
                           'fdata' =>  $f->fdata,
                           'required' => $f->required,
                           'field_type' => $f->field_type,
                           'field_header' => $f->field_header,
                           'Xmaxnums' => isset( $f->Xmaxnums ) ? $f->Xmaxnums : null,
                            );
            }
        }
    } catch ( Exception $e ) {
        return $e->getMessage();
    }

    $message = '';
    if ( $_POST  ) {
        try{
            $input = new Ewz_Followup_Input( stripslashes_deep( $_POST ), $followup_data );
            ewz_process_followup_input( $input->get_input_data() );
            $message = "Items updated successfully.";
         } catch( Exception $e ) {
            $message .= $e->getMessage();
            $message .= "\n";
        }
    }

    $ewzF = array( 'webforms' => array(),
                   'errmsg'   => $message,
                   'f_field'  => $followup_data,
                   );


    // generate output
    $output = '';
    if( $followup_data['field_type'] ){
        $output .= '<form id="foll_form" autocomplete="off" method="POST" onSubmit="return f_validate()" action="' . esc_js( esc_attr( get_permalink() ) ) . '" >';
        $output .= wp_nonce_field( 'ewzupload', 'ewzuploadnonce', true, false );
    }
    $output .= '<div class="ewz_overflow" id="scrollablediv">';

    $rownum = 0;
    foreach( $idents as $ident ){

        $webform = $webforms[$ident];
        $layout = $layouts[$ident];
        $webform->layout = $layout;
        $items = Ewz_Item::get_items_for_webform( $webform->webform_id, true );
        $output .= "<h2>$webform->webform_title</h2>\n";

        if( $items ){
            $output .= ewz_followup_display(
                                        $rownum,
                                        $items,
                                        $layout->fields,
                                        $webform->webform_id,
                                        $show_data,
                                        $show_admin_data
                                        );
            $output .= '<br>';
            array_push( $ewzF['webforms'], $webform );
            $rownum += count( $items );
        }
    }

    $ewzF['jsvalid'] = Ewz_Base::validate_using_javascript();     // this is set to false for testing server validation

    // this passes ewzF to Javascript
    wp_localize_script( "ewz-followup", "ewzF",  $ewzF );

    if( $followup_data['field_type'] ){
        $output .= '</div>';
        $output .= '<br><center><button type="submit" id="f_submit">Submit</button></center></form><br><br>';
    }
    
    return $output;
}

/**
 * Save the followup data to the database
 *
 * @param   $input            uploaded data already validated
 * @param   $followup_field   Ewz_Field with ident 'followupQ'
 */
function ewz_process_followup_input( $input )
{
    assert( is_array( $input ) );
    foreach ( $input['rdata'] as $row => $datavalues ) {
        foreach( $datavalues as $item_id => $val ){
            $item_obj = new Ewz_Item( $item_id );
            $wform = new Ewz_Webform( $item_obj->webform_id );
            $layout = new Ewz_Layout( $wform->layout_id );
            foreach( $layout->fields as $field ){
                if( $field->field_ident == 'followupQ' ){
                    $item_obj->item_data[$field->field_id] =  Array(
                                                                     'field_id' => $field->field_id,
                                                                     'value' => $val,
                                                                    );
                    $item_obj->save();
                    break;
                }
            }
        }
    }
}


/**
 * Return the html string for the display of a single webform on the followup page
 *
 * @param    $init_rownum     first row number for the webform ( needed for radio button )
 * @param    $stored_items
 * @param    $fields
 * @param    $webform_id
 * @param    $show_data       extra data uploaded by admin, to be displayed below image

 * @return  html string
 */
function ewz_followup_display( $init_rownum, $stored_items, $fields, $webform_id, $show_data, $show_admin_data ){
    assert( Ewz_Base::is_nn_int( $init_rownum ) );
    assert( is_array( $stored_items ) );
    assert( is_array( $fields ) );
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( is_array( $show_data ) );
    assert( is_bool( $show_admin_data ) );

    $rownum = $init_rownum;

    $fields_arr = array_values($fields);
    $labels = array( );
    foreach( $fields_arr as $field ) {
        if ( 'opt' == $field->field_type ) {
            foreach ( $field->fdata['options'] as $data ) {
                $labels[$field->field_header][$data['value']] = $data['label'];
            }
        }
    }
    $output = '<div id="ewz_stored_' . esc_attr( $webform_id ) . '">';
    if ( count( $stored_items ) > 0 ) {
        $output .= '<table id="datatable_' . esc_attr( $webform_id ) . '"  class="ewz_upload_table"><thead>';
        $output .= '   <tr>';
        // admin_data column
        if( $show_admin_data ){
            $output .=   '<th> </th>';
        }
        foreach ( $fields_arr as $n => $field ) {
            assert( $n < count( $fields ) );
            // in this followup page only, ignore fields whose identifier contains the string XFQ
            if(  strpos( $field->field_ident, 'XFQ' ) === false ){
                $output .=   '<th>' . esc_html( $field->field_header ) . '</th>';
            }
        }
        $output .=    '</tr></thead><tbody>';

        foreach ( $stored_items as $m => $item ) {
            assert( $m < count($stored_items));
            $output .= '<tr>';
            // admin_data column
            if( $show_admin_data ){
                $output .=  ewz_display_admin_data( $item );
            }
            foreach ( $fields_arr as  $field ) {
                if(  strpos( $field->field_ident, 'XFQ' ) === false ){
                    $output .= '<td>';

                    // the special followup field, which is the only input on the form now
                    if( $field->field_ident == 'followupQ' ){
                        $savedval = ewz_get_saved_value( $field, $item );
                        $output .= ewz_display_followup_field( $rownum, $webform_id, $savedval, $field, $item->item_id );
                    }  else {
                        // display the other fields the same way we do for the upload feedback
                        if ( 'img' == $field->field_type ) {
                            // an image field
                            $output .=  ewz_display_image_field_with_data( $field, $item, $show_data );
                        } else {
                            // a data field
                            if ( isset( $item->item_data[$field->field_id] ) ) {
                                $output .= esc_html( ewz_display_item( $field, $item->item_data[$field->field_id]['value'] ) );
                            }
                        }
                        $output .= '</td>';
                    }
                }
            }
            $output .= '</tr>';
            ++$rownum;
        }
        $output .= "</tbody></table>";
    }
    $output .= '</div>';       // <div id="stored">
    return $output;
}


function ewz_display_admin_data( $item ){
     assert( is_object( $item ) );
     $str= '';
     if ( array_key_exists( 'admin_data', $item->item_data ) ) {
        $str = '<td class="admin_data">' .  $item->item_data['admin_data']  . '</td>';
     } else {
         $str = '<td></td>';
     }
     return $str;
}
  
/**
 * Return the html string for the display of an image field with extra uploaded data
 *
 * @param   $field
 * @param   $item
 * @param   $show_data
 *
 * @return  html string
 */
function  ewz_display_image_field_with_data( $field, $item, $show_data ){
    assert( is_object( $field ) );
    assert( is_object( $item ) );
    assert( is_array( $show_data ) );

    $str= '';
    if ( array_key_exists( $field->field_id, $item->item_files ) ) {
        if ( is_array( $item->item_files[$field->field_id] ) ) {
            $str .= '<img src="' . esc_attr( $item->item_files[$field->field_id]['thumb_url'] ) . '">';
            $str .= '<br>' . esc_html( basename( $item->item_files[$field->field_id]['fname'] ) );
            foreach( $show_data as $line ){
                if( isset( $item->item_data[$field->field_id][$line] ) ){
                    $str .= "<br>- " . $item->item_data[$field->field_id][$line];
                }
            }
        } else {
            $str .= esc_html( $item->item_files[$field->field_id] );
        }
    }
    return $str;
}


function ewz_display_followup_field( $rownum, $webform_id, $savedval, $field, $item_id = NULL )
{
    assert( Ewz_Base::is_nn_int( $rownum ) || $rownum == '' );
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( in_array( $field->field_type, array( 'str', 'opt', 'img', 'rad', 'chk' ) ) );
    assert( is_bool($savedval ) || is_int( $savedval ) ||  is_string( $savedval ) || $savedval === null );
    assert( is_null( $item_id ) || Ewz_Base::is_pos_int( $item_id ) );

    if( !is_null( $item_id ) ){
        $name = "rdata[$rownum][" . $item_id . "]";
    } else {
        $name = "rdata[$rownum][" . $field->field_id . "]";
    }
    $display = '';

    switch ( $field->field_type ) {
    case 'str': $display = ewz_display_str_followup_field( $name, $webform_id, $savedval, $field );
            break;
    case 'opt': $display = ewz_display_opt_followup_field( $name, $webform_id, $savedval, $field );
            break;
    case 'rad': $display = ewz_display_rad_followup_field( $name, $webform_id, $savedval, $field );
            break;
    case 'chk': $display = ewz_display_chk_followup_field( $name, $webform_id, $savedval );
            break;
    case 'img': throw new EWZ_Exception( "A followup field may not be of Image type");
            break;
    default:
        throw new EWZ_Exception( "Invalid field type " . $field->field_type );
    }
    return $display;
}


/**
 * Return the html for displaying a single text-input field
 *
 * @param   string  $name        js name of field
 * @param   int     $webform_id
 * @param   string  $savedval    data currently stored for the field
 * @param   array   $field       field info
 * @return  string   -- the html
 */
function ewz_display_str_followup_field( $name, $webform_id, $savedval, $field )
{
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( is_string( $name ) );
    assert( is_string( $savedval ) ||  Ewz_Base::is_nn_int( $savedval ) );
    assert( Ewz_Base::is_nn_int( $field->fdata['fieldwidth'] ) );

    $ename = esc_attr( $name );
    $iname = str_replace( '[', '_', str_replace( ']', '_', $ename ) );
    return '<input type="text" name="' . $ename .
                   '" id="' . $iname . '_' . esc_attr( $webform_id ) .
                   '" size="' . esc_attr( $field->fdata['fieldwidth'] ) .
                   '" maxlength="' . esc_attr( $field->fdata['maxstringchars'] ) .
                   '" value="' . esc_attr( $savedval ) .
            '">';
}



/**
 * Return the html for displaying a radio button field
 *
 * @param   string  $name        js name of field
 * @param   int     $webform_id
 * @param   string  $savedval    data currently stored for the field
 * @param   array   $field       field info
 * @return  string   -- the html
 */
function ewz_display_rad_followup_field( $name, $webform_id, $savedval, $field )
{
    assert( is_string( $name ) );
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( in_array( $savedval, array( null, 1, 0 ) ) );
    assert( is_object( $field ) );

    $ename = esc_attr( $name );
    $iname = str_replace( '[', '_', str_replace( ']', '_', $ename ) );

    return '<input type="radio"  name="radioFollowup"' .
                   ' id="' . $iname . '_' . esc_attr( $webform_id ) . '"' .
                   ' value="' . $ename .'" ' .
                   ($savedval ? ' checked="checked" ' : '') .
            '>';
}



/**
 * Return the html for displaying a checkbox input field
 *
 * @param   string  $name        js name of field
 * @param   int     $webform_id
 * @param   string  $savedval    data currently stored for the field
 * @param   array   $field       field info
 * @return  string   -- the html
 */
function ewz_display_chk_followup_field( $name, $webform_id, $savedval )
{
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( is_string( $name ) );
    assert( in_array( $savedval, array( null, 1, 0 ) ) );

    $ename = esc_attr( $name );

    $iname = str_replace( '[', '_', str_replace( ']', '_', $ename ) );
      return '<input type="checkbox"  value="1" name="' . $ename .
                   '" id="' . $iname . '_' . esc_attr( $webform_id ) . '"' .
          ($savedval ? ' checked="checked" ' : '') .
            '>';
}



/**
 * Return the html for displaying a single option field
 *
 * @param   string $name        js name of field
 * @param   int    $webform_id
 * @param   mixed  $savedval    data currently stored for the field
 * @param   array  $field       field info
 * @return  string   -- the html
 */
function ewz_display_opt_followup_field( $name, $webform_id, $savedval, $field )
{
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( is_string( $name ) );
    assert( is_string( $savedval ) );
    assert( Ewz_Base::is_pos_int( $field->field_id ) );

    $ename = esc_attr( $name );
    $iname = str_replace( '[', '_', str_replace( ']', '_', $ename ) );
    $txt = '<select name="' . $ename . '" id="' . $iname . '_' . esc_attr( $webform_id ) .'">';
    $txt .= '  <option value=""> </option>';
    foreach ( $field->fdata['options'] as $n=>$dataval ) {
        assert($n < count($field->fdata['options']) );
        $txt .= '<option value="' . esc_attr( $dataval['value'] ) . '"';
        if ( $dataval['value'] === $savedval ) {
            $txt .=         ' selected="selected" ';
        }
        $txt .= '>' . esc_attr( $dataval['label'] ) . '</option>';
    }
    $txt .= '</select>';
    return $txt;
}
