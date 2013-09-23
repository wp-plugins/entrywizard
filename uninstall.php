<?php

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
   error_log( 'EWZ: Attempt to uninstall without WP_UNINSTALL_PLUGIN defined' );
   exit();
}

   global $wpdb;

   $prefix = $wpdb->prefix;

   define( 'EWZ_LAYOUT_TABLE', $prefix . 'ewz_layout' );
   define( 'EWZ_FIELD_TABLE', $prefix . 'ewz_field' );
   define( 'EWZ_WEBFORM_TABLE', $prefix . 'ewz_webform' );
   define( 'EWZ_ITEM_TABLE', $prefix . 'ewz_item' );

   error_log( 'EWZ: ********  uninstalling  *********' );


   $layout_table = EWZ_LAYOUT_TABLE;
   $field_table = EWZ_FIELD_TABLE;
   $webform_table = EWZ_WEBFORM_TABLE;
   $item_table = EWZ_ITEM_TABLE;

   if ( $wpdb->get_var( "SHOW TABLES LIKE '$item_table'" ) == $item_table ) {
      $wpdb->query( "DROP Table $item_table" );
   }
   if ( $wpdb->get_var( "SHOW TABLES LIKE '$field_table'" ) == $field_table ) {
      $wpdb->query( "DROP Table $field_table" );
   }
   if ( $wpdb->get_var( "SHOW TABLES LIKE '$webform_table'" ) == $webform_table ) {
      $wpdb->query( "DROP Table  $webform_table" );
   }
   if ( $wpdb->get_var( "SHOW TABLES LIKE '$layout_table'" ) == $layout_table ) {
      $wpdb->query( "DROP Table $layout_table" );
   }


   $meta_ids = $wpdb->get_col( 'SELECT umeta_id FROM ' . $wpdb->usermeta . " WHERE meta_key LIKE 'ewz_%'" );
   foreach ( $meta_ids as $umeta_id ) {
      delete_metadata_by_mid( 'user', $umeta_id );
   }

   // remove the uploaded images folder
    error_log( 'EWZ: removing images' );
    $updir_arr = wp_upload_dir();
    $ewzdir = $updir_arr['basedir'] . '/ewz_img_uploads';
    ewz_rrmdir( $ewzdir );
 
    
   function ewz_rrmdir( $dir ) {
      // no assert
      error_log( "EWZ: removing directory $dir" );
      if ( is_dir( $dir ) ) {
         $objects = scandir( $dir );
         foreach ( $objects as $object ) {
            if ( $object != "." && $object != ".." ) {
               if ( filetype( $dir . "/" . $object ) == "dir" ) {
                  ewz_rrmdir( $dir . "/" . $object );
               } else {
                  unlink( $dir . "/" . $object );
               }
            }
         }
         reset( $objects );
         rmdir( $dir );
      }
   }

