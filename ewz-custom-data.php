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
 * To make use of it, delete the '//' at the **start** of each line
 *  ( not the '//' in the middle ), and edit the line as indicated.
 * Do not make any other changes unless you are a php programmer.
 *
 * Everything from '//' to the end of a line is a comment that will be ignored by the program.
 *
 *
 */

class Ewz_Custom_Data
{
    /*************************************************
     * SECTION 1: you need one line for each item
     *
     *************************************************/
//    public $custom1;
//    public $custom2;

    /* if needed, add more lines here for "$custom3", "$custom4", etc. */


    public static $data =
        array(

              /*************************************************
               * SECTION 2: you need one line for each item
               *************************************************/
              /* Change 'Member Level' to whatever label you wish to use for your first piece of data */
              /* NO apostrophes or quotation marks                                                    */
//                           'custom1' => 'Member Level',

              /* Change 'Membership Number' to whatever label you wish to use for your second piece of data */
              /* NO apostrophes or quotation marks                                                    */
//                           'custom2' => 'Membership Number',

              /* if needed, add more lines here for "custom3", "custom4", etc. */

              );


   public function  __construct( $user_id ){
       assert( Ewz_Base::is_pos_int( $user_id ) );
        /*************************************************
         * SECTION 3: you need one line for each item
         *
         *************************************************/
//        $this->custom1 =
//            get_cimyFieldValue( $user_id, 'COMPETITION_LEVEL' );   // This is the function that gets the "custom1" data for a member with id $user_id  -- change it as needed

//        $this->custom2 =
//            get_cimyFieldValue( $user_id, 'MEMBER_NUMBER' );       // This is the function that gets the "custom2" data for a member with id $user_id -- change it as needed   

        /* if needed, add more lines here for "custom3", "custom4", etc. */
    }
}
