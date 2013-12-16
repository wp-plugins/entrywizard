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
    $followup_field = null;
    $layouts = array();
    $webforms = array();

    try{
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
        if( array_key_exists( 'show', $atts ) ){
            foreach( explode( ',', $atts['show'] ) as $data ){
                if( !in_array( $data, array( 'title', 'excerpt', 'content' ) ) ){
                    throw new EWZ_Exception(  'Invalid data specification in shortcode' );
                }
                array_push( $show_data,  "p$data" );
            }
        }
        foreach( $idents as $ident ){
            if ( !in_array( $ident, $valid_idents ) ) {
                throw new EWZ_Exception(  'Invalid identifier ' . $ident . ' in shortcode' );
            }
            $webforms[$ident] = new Ewz_Webform( $ident );        
            $layouts[$ident] = new Ewz_Layout( $webforms[$ident]->layout_id );
            $follow_id = $layouts[$ident]->contains_followup();        
            if( $follow_id  ){
                $followup_field = new Ewz_Field( $follow_id );
            }
        }
    } catch ( Exception $e ) {
        return $e->getMessage();
    }

    $message = '';
    if ( $_POST  ) {
        try{ 
            $input = new Ewz_Followup_Input( stripslashes_deep( $_POST ), $_FILES,  $followup_field );
            ewz_process_followup_input( $input->get_input_data(), $followup_field );
         } catch( Exception $e ) {
            $message .= $e->getMessage();
            $message .= "\n";
        }
    }
    
    $ewzF = array( 'webforms' => array(),
                   'errmsg'   => $message,
                   'f_field'  => $followup_field,
                   );


    // generate output
    $output = '';
    if( $followup_field ){
        $output .= '<form id="foll_form" autocomplete="off" method="POST" action="' . esc_js( esc_attr( get_permalink() ) ) . '" >';
        $output .= wp_nonce_field( 'ewzupload', 'ewzuploadnonce', true, false );
    }
    foreach( $idents as $ident ){

        $webform = $webforms[$ident];
        
        $layout = $layouts[$ident];
            $webform->layout = $layout;
            $output .= "<h2>$webform->webform_title</h2>\n";
            //   $followupID = Ewz_Field::field_id_from_ident_arr( array( 'layout_id' => $webform->layout_id,
            //                                                         'field_ident' => 'followupQ' ) );
            $items = Ewz_Item::get_items_for_webform( $webform->webform_id, true );
            $output .= ewz_followup_display( $items,
                                            $layout->fields,
                                            $webform->webform_id,
                                            $show_data
                                            );
            $output .= '<br>';
            array_push( $ewzF['webforms'], $webform );
    }

    // this passes ewzF to Javascript
    wp_localize_script( "ewz-followup", "ewzF",  $ewzF );

    if( $followup_field ){
        $output .= '<br><center><input type="submit" onclick="return f_validate()"></center></form>';
    }
    return $output;
}

/**
 * Save the followup data to the database 
 *
 * @param   $input            uploaded data already validated
 * @param   $followup_field   Ewz_Field with ident 'followupQ'
 */
function ewz_process_followup_input( $input, $followup_field )
{
    assert( is_array( $input ) );
    assert( is_object( $followup_field ) );
    foreach ( $input['rdata'] as $row => $datavalues ) {
        foreach( $datavalues as $item_id => $val ){
            $item_obj = new Ewz_Item( $item_id );
            $item_obj->item_data[$followup_field->field_id] =  Array(
                                                                     'field_id' => $followup_field->field_id,
                                                                     'value' => $val,
                                                                     );
            $item_obj->save();
        }
    } 
}


/**
 * Return the html string for the display of a single webform on the followup page 
 * 
 * @param    $stored_items
 * @param    $fields
 * @param    $webform_id
 * @param    $show_data

 * @return  html string
 */
function ewz_followup_display( $stored_items, $fields, $webform_id, $show_data ){
    assert( is_array( $stored_items ) );
    assert( is_array( $fields ) );
    assert( Ewz_Base::is_pos_int( $webform_id ) );
    assert( is_array( $show_data ) );

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
        foreach ( $fields_arr as $n => $field ) {
            assert( $n < count( $fields ) );
            $output .=   '<th>' . esc_html( $field->field_header ) . '</th>';
        }
        $output .=    '</tr></thead><tbody>';
        
        foreach ( $stored_items as $m => $item ) {
            assert( $m < count($stored_items));
            $output .= '<tr>';
            foreach ( $fields_arr as  $field ) {
                $output .= '<td>';

                // the special followup field, which is the only input on the form now
                if( $field->field_ident == 'followupQ' ){
                    $savedval = ewz_get_saved_value( $field, $item );
                    $output .= ewz_display_followup_field( '', $webform_id, $savedval, $field, $item->item_id );
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
            $output .= '</tr>';      
        }
        $output .= "</tbody></table>";      
    }
    $output .= '</div>';       // <div id="stored">
    return $output;
}

/**
 * Return the html string for the display of an image field with extra uploaded data
 * 
 * @param   $field
 * @param   $item
 * @param   $showdata
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
    assert( in_array( $field->field_type, array( 'str', 'opt', 'img' ) ) );
    assert( is_string( $savedval ) || $savedval === null );
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
    assert( is_string( $savedval ) );
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
