<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

require_once( EWZ_PLUGIN_DIR . "classes/ewz-base.php");

class Ewz_Permission {
    /* Using current_user_can('manage_options') to identify admin, who should be able to run this page */

    static $ewz_caps = array(
        'ewz_can_edit_layout',        // change anything to do with a layout
        'ewz_can_assign_layout',      // assign/change the layout for a webform
        'ewz_can_edit_webform',       // change anything to do with a webform
        'ewz_can_manage_webform',     // change anything for a webform except the layout
        'ewz_can_manage_webform_L',   // manage any webform with a given layout
        'ewz_can_download_webform',   // download anything from (ie uploaded via) a webform
        'ewz_can_download_webform_L', // download anything from a webform with a given layout
    );

    /**
     * Does the user have any sort of ewz permission?
     *
     * Used to determine if the ewz menu should appear at all.
     * Return true if user has any of the above listed ewz permissions,
     * or if user can manage options
     *
     * @param   None
     * @return  Boolean
     */
    public static function has_ewz_cap() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $uid = get_current_user_id();
        $has = false;
        foreach ( self::$ewz_caps as $cap ) {
            if ( get_user_meta( $uid, $cap, true ) ) {
                $has = true;
                break;
            }
        }
        return $has;
    }

    /**
     * Assign a permission to a user
     * Requires manage_options wordpress capability
     *
     * @param   $user_id  the user
     * @param   $cap      the permission to assign ( one of $ewz_caps )
     * @param   $value    the value to be stored ( array of layout or webform id's ),
     *                       which will be serialized by update_user_meta
     * @return  int or boolean  umeta_id on update, true if no change, false on failure.
     */
    public static function add_perm( $user_id, $cap, $value ) {
        assert( Ewz_Base::is_pos_int( $user_id ) );
        assert( in_array( $cap, self::$ewz_caps ) );
        assert( is_array( $value ) );

        if ( current_user_can( 'manage_options' ) ) {
            // $user->add_cap( $cap );
            $curr = get_user_meta( $user_id, $cap, true );
            if ( $curr == $value ) {
                return true;
            } else {
                update_user_meta( $user_id, $cap, $value );
            }
        } else {
            throw new EWZ_Exception( "No authority to add a permission" );
        }
    }

    /**
     * Remove a permission
     *
     * @param   $user_id  the user
     * @param   $cap      the permission to assign ( one of $ewz_caps )
     * @return  none
     */
    public static function remove_perm( $user_id, $cap ) {
        assert( Ewz_Base::is_pos_int( $user_id ) );
        assert( in_array( $cap, self::$ewz_caps ) );

        if ( current_user_can( 'manage_options' ) ) {
            $curr = get_user_meta( $user_id, $cap, true );
            if ( $curr ) {
                delete_user_meta( $user_id, $cap );
            }
        } else {
            throw new EWZ_Exception( "No authority to remove a permission." );
        }
    }

    /**
     * Remove ALL permissions ( when plugin is deleted )
     */
    public static function remove_all_ewz_perms() {
        if ( current_user_can( 'delete_plugins' ) ) {
            global $wpdb;

            $meta_ids = $wpdb->get_results( "SELECT user_id, meta_key FROM " .
                                            $wpdb->usermeta . " WHERE meta_key LIKE 'ewz_can%'", OBJECT );
            foreach ( $meta_ids as $umeta ) {
                 delete_user_meta( $umeta->user_id, $umeta->meta_key );
            }
        } else {
            throw new EWZ_Exception( "No authority to remove permissions" );
        }
    }

    /**
     * Get all the assigned permissions (for all users)
     *
     * For the Permissions page
     *
     * @param None
     * @return An array consisting of the user_id, the permissions key and the corresponding meta_value
     *         The meta_value is itself an array of layout or webform id's
     */
    public static function get_all_ewz_permissions() {
        global $wpdb;
        if ( current_user_can( 'manage_options' ) ) {
            $perm = '';
            $perms = $wpdb->get_results( "SELECT user_id, meta_key, meta_value " .
                "FROM $wpdb->usermeta WHERE meta_key like 'ewz_can%'", OBJECT );
            foreach ( $perms as $key=>$perm ) {
                $perms[$key]->meta_value = unserialize( $perm->meta_value );
            }
            return $perms;
        } else {
            throw new EWZ_Exception( "No authority to view permissions" );
        }
    }

    /**
     * Get all the assigned permissions for input user ( or current user )
     *
     * @param None
     * @return An array consisting of the  the permissions key and the corresponding meta_value
     *         The meta_value is itself an array of layout or webform id's
     */
    public static function get_ewz_permissions_for_user( $user_id = null ) {
        global $wpdb;
        if ( isset( $user_id ) ) {
            assert( Ewz_Base::is_pos_int( $user_id ) );
        } else {
            $user_id = get_current_user_id();
        }
        if ( current_user_can( 'manage_options' ) || get_current_user_id() == $user_id  ) {
            $perms = $wpdb->get_results( "SELECT meta_key, meta_value
                    FROM $wpdb->usermeta
                    WHERE user_id = $user_id AND meta_key LIKE 'ewz_can_%'", OBJECT );

            $allowed = array( );
            foreach ( self::$ewz_caps as $cap ) {
                $allowed[$cap] = array( );
            }
            foreach ( $perms as $perm ) {
                foreach ( unserialize( $perm->meta_value ) as $seq ) {
                    array_push( $allowed[$perm->meta_key], $seq );
                }
            }
            return $allowed;
        } else {
            throw new EWZ_Exception( "No authoritiy to view permissions");
        }
    }

    /**
     * Is there any webform the current user is allowed to see
     * (and user is thus allowed to see the webforms page)
     *
     * @return boolean
     */
    public static function can_see_webform_page() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( $perms_for_user['ewz_can_download_webform'] ) {
            return true;
        }
        if ( $perms_for_user['ewz_can_download_webform_L'] ) {
            return true;
        }
        if ( $perms_for_user['ewz_can_edit_webform'] ) {
            return true;
        }
        if ( $perms_for_user['ewz_can_manage_webform'] ) {
            return true;
        }
        if ( $perms_for_user['ewz_can_manage_webform_L'] ) {
            return true;
        }
        return false;
    }

    /**
     * Is the current user allowed to see at least one layout
     * (and is thus allowed to see the layouts page)
     *
     * @return boolean
     */
    public static function can_see_layout_page() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( $perms_for_user['ewz_can_edit_layout'] ) {
            return true;
        }
        return false;
    }

    /**
     * Is the current user allowed to edit all webforms ( needed to create a webform )
     *
     * @return boolean
     */
    public static function can_edit_all_webforms() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( in_array( -1, $perms_for_user['ewz_can_edit_webform'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Is the current user allowed to manage all webforms
     *
     * @return boolean
     */
    public static function can_manage_all_webforms() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( in_array( -1, $perms_for_user['ewz_can_manage_webform'] ) ) {
            return true;
        }
        if ( in_array( -1, $perms_for_user['ewz_can_edit_webform'] ) ) {
            return true;
        }
        return false;
    }
    /**
     * Is the current user allowed to manage/edit any webform
     *
     * @return boolean
     */
    public static function can_manage_some_webform() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( $perms_for_user['ewz_can_manage_webform'] )  {
            return true;
        }
        if ( $perms_for_user['ewz_can_manage_webform_L'] )  {
            return true;
        }
        if ( $perms_for_user['ewz_can_edit_webform'] )  {
            return true;
        }
        return false;
    }

    /**
     * Is the current user allowed to edit all layouts ( needed to create a layout )
     *
     * @return boolean
     */
    public static function can_edit_all_layouts() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( in_array( -1, $perms_for_user['ewz_can_edit_layout'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Is the current user allowed to download either from the specified webform
     * or from any form with its layout
     *
     * @param  object   $webform   (needs members webform_id and layout_id)
     * @return boolean
     */
    public static function can_download( $webform ) {
        assert( Ewz_Base::is_nn_int( $webform->webform_id ) );
        assert( Ewz_Base::is_pos_int( $webform->layout_id ) );

        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( in_array( -1, $perms_for_user['ewz_can_download_webform'] ) ) {
            return true;
        }
        if ( in_array( $webform->webform_id, $perms_for_user['ewz_can_download_webform'] ) ) {
            return true;
        }
        if ( in_array( $webform->layout_id, $perms_for_user['ewz_can_download_webform_L'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Is the current user allowed to see the specified webform
     *
     * @param  object    $webform   (needs member webform_id )
     * @return boolean
     */
    public static function can_view_webform( $webform ) {
        assert( Ewz_Base::is_nn_int( $webform->webform_id ) );

        if ( self::can_manage_webform( $webform ) ) {
            return true;
        }
        if ( self::can_download( $webform ) ) {
            return true;
        }
        return false;
    }

    /**
     * Is the current user allowed to edit the specified webform
     *
     * @param  object    $webform   (needs member webform_id )
     * @return boolean
     */
    public static function can_edit_webform( $webform ) {
        assert( Ewz_Base::is_nn_int( $webform->webform_id ) );

        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( in_array( -1, $perms_for_user['ewz_can_edit_webform'] ) ) {
            return true;
        }
        if ( in_array( $webform->webform_id, $perms_for_user['ewz_can_edit_webform'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Is the current user allowed to manage either the specified webform,
     *        any webform, or any webform with the specified webform's layout
     *
     * @param  mixed    $webform   ( webform_id or Ewz_Webform )
     * @return boolean
     */
    public static function can_manage_webform( $in_webform ) {
        assert( Ewz_Base::is_nn_int( $in_webform ) || Ewz_Base::is_nn_int( $in_webform->webform_id ) );

        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( in_array( -1, $perms_for_user['ewz_can_edit_webform'] ) ) {
            return true;
        }
        if ( in_array( -1, $perms_for_user['ewz_can_manage_webform'] ) ) {
            return true;
        }

        if ( is_object( $in_webform ) ) {
            $webform = $in_webform;
        } else {
            $webform = new Ewz_Webform( $in_webform );
        }
        if ( !isset( $webform->webform_id ) || !isset( $webform->layout_id ) ) {
            return false;
        }
        $wid = $webform->webform_id;
        $lid = $webform->layout_id;
        if ( in_array( $wid, $perms_for_user['ewz_can_edit_webform'] ) ) {
            return true;
        }
        if ( in_array( $wid, $perms_for_user['ewz_can_manage_webform'] ) ) {
            return true;
        }
        if ( in_array( $lid, $perms_for_user['ewz_can_manage_webform_L'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Is the current user allowed to edit the specified layout
     *
     * @param  object  OR int $layout  ( needs member $layout_id )
     *
     * @return boolean
     */
    public static function can_edit_layout( $layout ) {
        assert( Ewz_Base::is_nn_int( $layout ) || Ewz_Base::is_nn_int( $layout->layout_id ) );

        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        if ( is_object( $layout ) ) {
            $lid = $layout->layout_id;
        } else {
            $lid = $layout;
        }
        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( in_array( -1, $perms_for_user['ewz_can_edit_layout'] ) ) {
            return true;
        }
        if ( in_array( $lid, $perms_for_user['ewz_can_edit_layout'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Is the current user allowed to assign the specified layout to a webform
     *        (permission is also required to edit the webform)
     *
     * @param  object  $layout  ( needs member $layout_id )
     * @return boolean
     */
    public static function can_assign_layout( $layout ) {
        assert( Ewz_Base::is_pos_int( $layout ) || Ewz_Base::is_pos_int( $layout->layout_id ) );

        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        if ( is_object( $layout ) ) {
            $lid = $layout->layout_id;
        } else {
            $lid = $layout;
        }
        $perms_for_user = self::get_ewz_permissions_for_user();
        if ( in_array( -1, $perms_for_user['ewz_can_assign_layout'] ) ) {
            return true;
        }
        if ( in_array( $lid, $perms_for_user['ewz_can_assign_layout'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Validate the input $_POST data
     *
     * @return string  $bad_data  comma-separated list of bad data
     */


}

