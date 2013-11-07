<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . 'classes/ewz-user.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/ewz-webform.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-webform-input.php' );
require_once( EWZ_PLUGIN_DIR . 'classes/validation/ewz-CSV-input.php' );
require_once( EWZ_PLUGIN_DIR . 'includes/ewz-admin-list-items.php' );
require_once( EWZ_CUSTOM_DIR . 'ewz-custom-data.php' );


/**
 * Store the per-item info in an uploaded csv file for a webform
 *
 * Checks to make sure item is attached to webform - otherwise, webform param is not
 * used, but seems a good idea to force a separate upload for each form.
 *
 * @param    $webform_id    webform
 * @return   success message
 */
function ewz_process_uploaded_csv( $webform_id )
{
    assert( Ewz_Base::is_pos_int( $webform_id ) );

    // needed for Windows files ???
   ini_set('auto_detect_line_endings', true);
   if( !isset( $_FILES['csv_data'] ) ){
       return( "No uploaded files");
   }
   $csvfile_data = $_FILES['csv_data'];

   $file_tmp_name = $csvfile_data['tmp_name'];

   if( 'text/csv' != $csvfile_data['type'] ){
       throw new EWZ_Exception( "File type is " . $csvfile_data['type'] . ", should be text/csv" );
   }
   if( $csvfile_data['error'] ){
     throw new EWZ_Exception( 'Failed to upload .csv file' );
   }
   if ( !is_readable($file_tmp_name) ) {
       throw new EWZ_Exception( 'Failed to read uploaded .csv file' );
   }
   if ( false === ($fh = fopen($file_tmp_name, 'r')) ) {
       throw new EWZ_Exception( 'Failed to open uploaded .csv file' );
   }

   $f_h = fopen($file_tmp_name, "r");
   $n = 0;
   $ok = '';
   while (( $data = fgetcsv($f_h, 1000 ))){

       if( isset( $data[0] ) ){
           $item_id = array_shift( $data );
           if( !is_numeric( $item_id ) ){
               throw new EWZ_Exception( "File is not in correct format - please read help." );
           }
           $item = new Ewz_Item( $item_id );
           if( $item->webform_id == $webform_id ){
               $item->set_uploaded_info( array_pad( $data, 4, '' ) );
               ++$n;
               $ok .= "$item_id, ";
           } else {
               // item_id is part of user-generated input, so include it in error message
               throw new EWZ_Exception( "Item $item_id does not belong to this webform" );
           }
       }
   }
   fclose( $f_h );
   return "Data for $n items successfully saved";
}

/**************************** Main Webforms Function ********************************
 * Generates the main web form management page
 *
 * Checks for POST data, validates, sanitizes and processes it
 * Then outputs the page
 * The output consists mainly of json data and javascript in order to save transmitting
 *       almost the same html for each possible webform.
 * The main javascript is found in "javascript/ewz-webforms.js", which is enqueued
 *
 * @param  None
 * @return none
 */

function ewz_webforms_menu()
{
    if ( !Ewz_Permission::can_see_webform_page() ) {
        wp_die( "Sorry, you do not have permission to view this page" );
    }

    $message = '';

    /**********************************************************************/
    /* First, process any changes -  sanitize, reformat and save */
    /**********************************************************************/
    // $ewzpage = ewz_admin_control();
    if( isset( $_POST['ewzmode'] ) ){
       try {
           switch( $_POST['ewzmode'] ){
               // 'spread', 'download', 'images'  are caught by the ewz_download
               // function in ewz_admin.php, which is hooked to plugins_loaded

           case  'webform':
               // validate all input data ( except uploaded files )
               $input = new Ewz_Webform_Input( array_merge( $_POST, $_GET ) );
               $webform = new Ewz_Webform( $input->get_input_data() );
                                     // text fields are sanitized before saving
               $webform->save();
               break;

           case 'csv':
               // upload a csv of titles, content, etc for images
               // first validate $_POST, $_GET - raises exceptions on problems
               $input = new Ewz_CSV_Input( array_merge( $_POST, $_GET ) );

               // then process $_FILES
               $message = ewz_process_uploaded_csv( $_POST['webform_id'] );
               break;

           default:
               throw new EWZ_Exception( "Invalid mode " . $_POST['ewzmode'] );
           }
       } catch( Exception $e ) {
          $message .= $e->getMessage();
       }
    }
    try{

        /******************************************************/
        /* Get the user list, webforms and the layout options */
        /* Format the data for html output where necessary    */
        /******************************************************/

        $user_options =  Ewz_User::get_user_opt_array();

        $webforms = array_values(array_filter( Ewz_Webform::get_all_webforms(),
                                               "Ewz_Permission::can_view_webform" ));
                 // list of users for whom the webform may be opened,
                 // or null if current user has no permission to list them

        $can_edit_all_webforms = Ewz_Permission::can_edit_all_webforms();
                                            // needed to create a new one

        $nonce_string = wp_nonce_field( 'ewzadmin', 'ewznonce', true, false );

        foreach ( $webforms as $webform ) {
            $webform->user_options = $user_options;
            foreach ( $webform->open_for as $user ) {
                foreach( $webform->user_options as $u_options ){
                    if( $u_options['value'] == $user ){
                        $u_options['selected'] = true;
                    }
                }
            }

            $layout = new Ewz_Layout( $webform->layout_id );

            // easier in Javascript if this is set
            $webform->layout_name = $layout->layout_name;
            $webform->webform_title = $webform->webform_title;

            $webform = ewz_html_esc( $webform );

            // has to be escaped separately because it contains html
            $webform->user_options = ewz_option_list( ewz_html_esc( $webform->user_options ) );

            // generate the options lists for each field of the selected layout
            foreach ( $layout->fields as $field ) {
                $webform->field_names[$field->field_id] = ewz_html_esc( $field->field_header );
                $webform->field_options[$field->field_id] = ewz_option_list( ewz_html_esc( $field->get_field_list_array() ) );
            }

            $l_options = Ewz_Layout::get_layout_opt_array( 'Ewz_Permission',
                                                         'can_assign_layout',
                                                         $webform->layout_id );
            $webform->layouts_options = ewz_option_list( ewz_html_esc( $l_options ) );
            $base_options = $webform->layouts_options;
        }

        /*******************************/
        /* Pass the data to Javascript */
        /*******************************/
        $ewzG = array( 'webforms' => $webforms );
        $ewzG['list_page'] = admin_url( 'admin.php?page=entrywizlist' );

        // the webform that should start off open
        $openwebform_id = 0;
        if ( array_key_exists( 'openwebform', $_GET ) ){
            $formid = $_GET['openwebform'];
            if( preg_match( '/^[0-9]+$/', $formid ) && strlen( $formid ) < 10 ) {
                $openwebform_id = $formid;
            }
        }
        $ewzG['openform_id'] = $openwebform_id;
        $ewzG['message'] = esc_html( $message );
        $ewzG['base_options'] = $base_options;


        $ewzG['user_options'] = $user_options;
        $ewzG['nonce_string'] = $nonce_string;
        $ewzG['can_edit_all'] = $can_edit_all_webforms;
        $ewzG['load_gif'] = plugins_url( 'images/loading.gif', dirname(__FILE__) ) ;
        $ewzG['helpIcon'] = plugins_url( 'images/help.png' , dirname(__FILE__) ) ;
        $ewzG['maxUploadMb'] = EWZ_MAX_SIZE_MB;

        $ewzG['errmsg'] = array(
            'warn' => '*** WARNING ***' ,
            'reallydelete' => 'Really delete the entire webform?',
            'noundo' => 'This action cannot be undone',
            'hasitems' => 'Deleting this webform will also delete ALL its uploaded items.',
            'formTitle' => 'Please enter a title for the webform.',
            'formIdent' => 'Each webform must have an identifier that starts with a lower case letter
                and consists only of lower case letters, digits, dashes and underscores.',
            'formPrefix' => 'The prefix may contain only letters, digits, dashes, underscores
                and the special expressions listed in the help window.',
            'prefixApply' => 'You have opted to apply a prefix without setting the prefix to be used.',
        );
        $ewzG['jsvalid'] = Ewz_Base::validate_using_javascript();
                           // normally true, set false to test server validation

        wp_localize_script( 'ewz-admin-webforms', 'ewzG1',  array( 'gvar'=>$ewzG ) );

    ?>
    <div class="wrap">
        <h2>EntryWizard Web Form Management</h2>
         <i>Last Changed Jul 18, 02:45 pm</i>
         <p>A webform may be inserted into any page using the shortcode &nbsp;
             <span style="font-size: 120%;">
                 <b>&#91;ewz_show_webform &nbsp; identifier="xxx"&#93;</b></span>
             &nbsp; where xxx is the identifier you created for the form</p>
        <div id="ewz_management"> </div>

        <div id="help-text" style="display:none">

        <!-- HELP POPUP -->
        <div id="wlayout_help" class="wp-dialog" >
            <p>The layouts are created in the Layouts admin page.
                You select one here. It determines what information is required,
                and in what order the items appear in the form.</p>
        </div>

        <!-- HELP POPUP identifier -->
        <div id="ident_help" class="wp-dialog" >
            <p>The  main use of this identifier is to specify which webform to
                use in the "ewz_show_webform" shortcode.  If your identifier is
                "main", then in the page where you wish the webform to appear,
                type
            <pre>[ewz_show_webform identifier="main"]</pre></p>
            <p>The identifier is also used as part of the filename when
                downloading images.</p>
            <p>To make it acceptable as part of a filename on most systems, it
                must consist of <b>letters, digits or underscores</b> only,
                and start with a letter.</p>
            <p>It should be kept as short as possible, but no two webforms may
                have the same identifier.</p>
        </div>

        <!-- HELP POPUP open-for -->
        <div id="openfor_help" class="wp-dialog" >
            <p>When the webform is closed, an <b>Administrator</b> has the option of opening it
                temporarily for selected members only.
                This may be useful if someone needs to change a submission, or
                is late for an acceptable reason,
                but you do not wish anyone else to be able to upload.</p>
            <p>Checking this box will open a list of users for you to select.
                Use the Shift or Control keys to select multiple members.
                When you "Save Changes", your selected users will be able to
                upload but others will not.</p>
            <p>Note that to see the user list, you need wordpress <b>"list_users"</b> capability,
               which, in a default wordpress installation, is restricted to administrators.</p>
            <p><i>Opening the webform or selecting a different set of members
                    automatically cancels any existing selection.</i></p>


        </div>

        <!-- HELP POPUP open webform -->
        <div id="open_help" class="wp-dialog" >
            <p>When this box is checked, users may upload images using the webform.
                When is it not checked, they see only a "Sorry, not open for upload"
                message.</p>
            <p>When the webform is closed, you have the option of opening it
                temporarily for selected members only.
                This may be useful if someone needs to change a submission, or
                is late for an acceptable reason,
                but you do not wish anyone else to be able to upload</p>
        </div>

        <!-- HELP POPUP title -->
        <div id="title_help" class="wp-dialog" >
            <p>The title is displayed at the top of the webform. No two webforms
                may have the same title.</p>
        </div>

        <!-- HELP POPUP csv upload -->
        <div id="csv_help" class="wp-dialog" >
             <p>You may optionally upload a .csv file containing three extra items
                 of data to be stored for a field. For images you attach to a page,
                 these three items are used for the Title, Excerpt and Content of
                 the image.  Otherwise, they may be used for any purpose.
            <p>*The file must be in plain text, "comma-delimited" format.<br>
                The easiest way to generate the .csv file is to save a spreadsheet
                in .csv format.</p>
            <p>*The first column must contain the wordpress item sequence number.
                You may get this by including the WP Item ID under Extra Data in
                the layouts tab.</p>
            <p>*The rest of the columns contain, in this order, the image-field
                identifier, title, excerpt, content for each image you wish to
                annotate in this way</p>
              For example, if you have assigned identifiers 'ident1' and 'ident5'
              to image columns 1 and 5, the line:
           <br><i>700,"ident1","Title 1","Excerpt 1","Content1","ident5","title
               for column 5", "Excerpt #5","Content for image in column 5"</i><br>
               would create Title, Excerpt and Content values for columns 1 and 5
               of item 700
            </p>

        </div>

        <!-- HELP POPUP data selection -->
        <div id="datasel_help" class="wp-dialog" >
            <p>The data management area for the webform lets you select all its
                items or just some of them.
               A selection list is generated here for each field in the webform's
               layout, except for required text and image fields.
               With the selected images you may do one of several things:
            <p>* Download a spreadsheet containing all the uploaded information
                connected to the items</p>
            <p>  ( <i>The spreadsheet is in .csv (comma-delimited) form, which
                    should open easily in most spreadsheet software.
                 The separator/delimiter is a comma (,), and the text delimiter is a
                    double quote (").</i></p>
            <p>* Download the same spreadsheet together with the uploaded image
                files</p>
            <p>* Download just the uploaded image files</p>
           <?php if( Ewz_Permission::can_manage_some_webform() ) { ?>
                <p>* View the images and data ( Requires permission to manage the webform ).
                With the image thumbnails visible, you may then inspect data, remove individual items,
                 or attach images to a page or post.</p>
            <?php } ?>
        </div>

        <!-- HELP POPUP prefix -->
        <div id="prefix_help" class="wp-dialog" >
            <p><u>If you wish</u>, you may choose to add a prefix to each of the
                image file names.<br>  
                The prefix may be applied either to each file as soon as it is uploaded to the server,<br>
                or only to files which are downloaded using the Download Images buttons below.<br>
            </p>
             <p> Applying the prefix immediately on upload is safer if you download a lot of image files at once.
                 If using the buttons below takes too long and times out, you may then use ftp to download the 
                 files from the ewz_img_uploads folder in your wordpress uploads folder.
             </p>
             <p>
                The prefix may contain the  following expressions, which will
                be replaced as indicated:
            <table class="ewz_border">
                <tbody><tr><th class="b">Expression</th><th class="b">Replacement</th></tr>


                    <tr><td class="b">[~WFM] </td>
                        <td class="b"> Identifier for this webform</td></tr>
                    <tr><td class="b">[~UID] </td>
                        <td class="b"> Submitter's Wordpress ID Number</td></tr>
                    <tr><td class="b">[~FLD] </td>
                        <td class="b"> Wordpress ID of the field the image was uploaded under<br>
                            (only useful if an item may contain more than one image)  </td></tr>
            <?php
                 foreach( Ewz_Custom_Data::$data as $key => $value ) {
                    $code = str_replace( 'custom', '~CD', esc_html( $key ) );
            ?>
                                <tr><td class="b">[<?php print $code; ?>] </td>
                                    <td class="b"> <?php print esc_html( $value ); ?> ( Custom data )</td>
                                </tr>
            <?php
                   }
            ?>
                </tbody></table>
            For instance, suppose <ol><li> You set the identifier for this webform
                    as "group1" and enter &nbsp; <b>"2012Jan-[~WFM]-[~UID]"</b>
                    in the Optional Prefix box above</li>
                <li> A member with wordpress id number <b>257</b> uploads images</li>
                <li> One of these images has the filename <b>myimage.jpg</b></li>
            </ol>
            Then the image will be downloaded with the filename
            &nbsp; <b>"2012Jan-group1-257-my_image.jpg".</b>.  This may be useful
            if you wish the images to sort or group in a particular way.
            </p>
        </div>

        </div>
    </div> <!-- wrap -->

    <?php

    } catch( Exception $e ){
        wp_die( $e->getMessage() );
    }

}

// end function ewz_webforms_menu


/* * ********************** End Main Webforms Function ******************* */
