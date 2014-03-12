<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/validation/ewz-input.php");

/* Validation for the csv file upload form on Webforms page */
/* Processed by webforms.php                                */

class Ewz_CSV_Input extends Ewz_Input
{

     function __construct( $form_data ) {
         parent::__construct( $form_data );
         assert( is_array( $form_data ) );
         $this->rules = array(

                  '_wp_http_referer'=> array( 'type' => 'string', 'req' => false, 'val' => '' ),
                  'ewzmode'         => array( 'type' => 'limited',  'req' => true,  'val' => array('csv','itmcsvdata' ) ),
                  'ewznonce'        => array( 'type' => 'anonce', 'req' => true,  'val' => '' ),
                  'webform_id'      => array( 'type' => 'seq',    'req' => true,  'val' => '' ),
                  'csv_btn'         => array( 'type' => 'noval',  'req' => true,  'val' => '' ),
                  'page'            => array( 'type' => 'fixed',  'req' => true,  'val' => 'entrywizard' )
                  );
        $this->validate();
     }
}