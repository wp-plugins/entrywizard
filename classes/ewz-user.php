<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

class Ewz_User
{
   /**
    * Return the array of users for use in an option lise
    *
    * @return array
    */
    public static function get_user_opt_array()
    {
        if( !current_user_can( 'list_users' ) ){
            return array();
        }
	$users = get_users( 'orderby=nicename' );
	$options = array();
	foreach ( $users as $user ) {
            $display =  $user->display_name . ' ( ' . $user->user_login . ', ' .  $user->user_email . ' )';
	    array_push( $options, array(  'value' => $user->ID, 'display' => $display ) );
	}
	return $options;
    }
}

