<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . 'classes/ewz-exception.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-permission.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-permission-input.php' );

/**
 * Save the permissions from $inputdata, which should have been validated already
 *
 * For each permissions type, if there is a  $inputdata value, save the value in user_meta
 *                            if there is no $inputdata value, delete all matching items from user_meta
 *
 * @return Boolean  true for success, false for failure
 */
function ewz_save_permissions( $inputdata )
{
    assert( is_array( $inputdata ) );

    $user_id = $inputdata['ewz_user_perm'];
    foreach ( Ewz_Permission::$ewz_caps as $key ) {
             if ( array_key_exists( $key, $inputdata ) ) {
                Ewz_Permission::add_perm( $user_id, $key, $inputdata[$key] );
            } else {
                Ewz_Permission::remove_perm( $user_id, $key );
            }
    }
    return true;
}

/**
 * Generates the main permissions management page
 *
 * Checks for POST data, validates and processes it
 * Then outputs the page
 */
function ewz_permissions_menu()
{
    if ( !current_user_can( 'manage_options' ) ) {
        throw new EWZ_Exception( "Sorry, you do not have permission to view this page" );
    }

    /***********************
     * process any changes
     ***********************/
    if ( !empty( $_POST ) ) {

        $perm_data = new Ewz_Permission_Input( $_POST );

        ewz_save_permissions( $perm_data->get_input_data() );
    }
    /******************
     * Get the  data
     ******************/
    $perms = Ewz_Permission::get_all_ewz_permissions();

    // Layouts, and the options string for a "select layout" drop-down
    $layouts_options = ewz_option_list( Ewz_Layout::get_layout_opt_array() );
    $layouts_sz = min( 10, substr_count( $layouts_options, '<option' ) + 2 );

    // Webforms, and the options string for a "select webform" drop-down
    $webforms = Ewz_Webform::get_all_webforms();
    $webforms_options = '';
    foreach ( $webforms as $webform ) {
    $webforms_options .= '<option value="' . esc_attr( $webform->webform_id ) . '">' .
            esc_html( $webform->webform_title . ' (' . $webform->webform_ident . ')' ) .'</option>';
    }
    $webforms_sz = min( 15, sizeof( $webforms ) + 2 );


    // the options strings for "select user"
    $all_users = Ewz_User::get_user_opt_array();
    $users_options = ewz_option_list( $all_users );
    $ewz_users = array();
    foreach( $all_users as $usr ){
        if( array_search( $usr['value'], array_map( function($p) { return $p->user_id; }, $perms ) ) ){
            array_push( $ewz_users, $usr );
        }
    }
    $ewz_users_options = ewz_option_list(  $ewz_users );



    /***********************
     * display the options
     ***********************/
    // When a user is selected,  the "uperms" div shows a summary of the selected user's permissions
    ?>

    <div class="wrap">
        <h2>EntryWizard Permissions Management</h2>
         <div class="ewz_boxed"><i>
         <p>This system operates on top of the normal Wordpress
         <a href='http://codex.wordpress.org/Roles_and_Capabilities'> roles and capabilities</a>.</p>

         <p>A user given "Edit all webforms" here, but without wordpress
         'list-user' capability, will not be able to see the list of users on the webforms page,
          and so will not be able to open the webform for specific users.</p>

         <p>Wordpress 'manage_options' capability is required to see this page.</p>
        </i></div>

        <form action=""  method="POST" onSubmit="return ewz_check_perm_input(this);" >
         <input type="hidden" name="ewzmode" value="permission">
         <div class="ewzform">
        <?php
               wp_nonce_field( 'ewzadmin', 'ewznonce' );
        ?>
        <h3>Select A User</h3>
        <div class="ewz_leftpad ewz_bottompad">
            <b>From All Users</b><br />
               <select name="ewz_user_perm" id="ewz_user_perm" onChange="ewz_show_perms();" >
                   <option value="0" selected="selected"> --- Select User --- </option>
                       <?php print $users_options; ?>
               </select>
            <div class="ewz_padded10"><b>OR</b></div>
            <b>From Users With Current EntryWizard Access</b><br />
               <select name="ewz_have_perm" id="ewz_have_perm" onChange="ewz_set_user();" >
                   <option value="0" selected="selected"> --- Select User --- </option>
                       <?php print $ewz_users_options; ?>
               </select>
               <div id="uperms"> </div>
         </div>
        <h3>Layout Permissions</h3>
        <div class="ewz_boxed">
            <p><i>"Edit"</i> a layout means change any of its data.  "Edit Any Layout" is required to create or delete layouts.</p>
            <p><i>"Assign"</i> a layout means set it as the layout for a web form. Permission to edit the web form is also required.</p>
        </div>
        <table class="form-table">
            <tr valign="top">
            <th scope="row" id="ewz_can_edit_layoutT">Edit Layouts</th>
            <td>
                <select name="ewz_can_edit_layout[]" id="ewz_can_edit_layout" multiple="multiple" size="<?php print esc_attr( $layouts_sz );  ?>">
                <option value="0"> -- None -- </option>
                <option value="-1">** Edit Any Layout **</option>
                <?php print $layouts_options ?>
                </select>
            </td>
            </tr>
            <tr valign="top">
            <th scope="row" id="ewz_can_assign_layoutT">Assign  Layouts</th>
            <td>
                <select name="ewz_can_assign_layout[]" id="ewz_can_assign_layout" multiple="multiple" size="<?php print esc_attr( $layouts_sz ); ?>">
                <option value="0"> -- None -- </option>
                <option value="-1">** Assign Any Layout **</option>
                <?php print $layouts_options ?>
                </select>
            </td>
            </tr>
        </table>
        <br />
        <h3>Web Form Permissions</h3>
        <div class="ewz_boxed">
            <p><i>"Manage"</i> &nbsp; a form means change it's name or identifier, open/close it for uploads,
                   and view, attach to pages or remove items.</p>
            <p><i>"Edit"</i> &nbsp; a form means do any of the above plus assign it's layout.</p>
            <p><i>"Edit Any Web Form"</i> &nbsp; is required to create or delete a web form.</p>
        </div>
        <table class="form-table">
            <tr valign="top">
            <th scope="row" id="ewz_can_edit_webformT">Edit Web Forms</th>
            <td>
                <select name="ewz_can_edit_webform[]" id="ewz_can_edit_webform" multiple="multiple"
                            size="<?php print esc_attr( $webforms_sz ); ?>">
                <option value="0"> -- None -- </option>
                <option value="-1">** Edit Any Web Form **</option>
                <?php print $webforms_options ?>
                </select>
            </td>
            </tr>
            <tr valign="top">
            <th scope="row" id="ewz_can_manage_webformT">Manage Web Forms</th>
            <td>
                <select name="ewz_can_manage_webform[]" id="ewz_can_manage_webform" multiple="multiple"
                            size="<?php print esc_attr( $webforms_sz ); ?>">
                <option value="0"> -- None -- </option>
                <option value="-1">** Manage Any Web Form **</option>
                <?php print $webforms_options ?>
                </select>
            </td>
            </tr>
            <tr valign="top" >
            <th scope="row" id="ewz_can_manage_webform_LT">Manage Web Forms With Selected Layouts </th>
            <td>
                <select name="ewz_can_manage_webform_L[]" id="ewz_can_manage_webform_L" multiple="multiple"
                            size="<?php print esc_attr( $layouts_sz - 2 ); ?>">
                    <option value="0"> -- None -- </option>
                <?php print $layouts_options ?>
                </select>
            </td>
            </tr>
            <tr valign="top">
            <th scope="row" id="ewz_can_download_webformT">Download Data From Web Forms</th>
            <td>
                <select name="ewz_can_download_webform[]" id="ewz_can_download_webform" multiple="multiple"
                            size="<?php print esc_attr( $webforms_sz ); ?>">
                <option value="0"> -- None -- </option>
                <option value="-1">** Download From Any Web Form **</option>
                <?php print $webforms_options ?>
                </select>
            </td>
            </tr>
            <tr valign="top">
            <th scope="row" id="ewz_can_download_webform_LT">Download Data From Any Web Form With Selected Layouts</th>
            <td>
                <select name="ewz_can_download_webform_L[]" id="ewz_can_download_webform_L" multiple="multiple"
                            size="<?php print esc_attr( $layouts_sz - 2 ); ?>">
                    <option value="0"> -- None -- </option>
                <?php print $layouts_options ?>
                </select>
            </td>
            </tr>
        </table>
        <br />
        <input type="submit" class="button-primary" value="Save Changes"  id="submit">
        </div>
        </form>
    </div> <!-- wrap -->

    <?php

    $ewzG = array('ewz_perms' => $perms);

    wp_localize_script( 'ewz-admin-permissions', 'ewzG1',   array( 'gvar'=>$ewzG ) );
}

// end ewz_permissions_menu

