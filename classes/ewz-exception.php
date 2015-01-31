<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

class EWZ_Exception extends Exception {

    /**
     * Make sure an error is logged ( when error logging is on ) as soon as it happens
     *
     * When error logging is on, make the constructor log the exception
     * Generate the message from the translation of $user_message, plus the input data if present.
     *
     * @param   $user_message  The message to be translated and passed to the Exception
     * @param   $data1         Extra data to be logged but not displayed
     */
    public function __construct( $user_message, $data1='' ) {
        assert( is_string( $user_message ) );
        // no assert
        if(  empty( $data1 ) ){
            $data1 = '#';
        } elseif(  is_string( $data1 ) || is_int( $data1 ) ){
            $data1 = esc_attr( $data1 );
        } else {
            $data1 = serialize( esc_attr( $data1 ) );
        }

        // format in one line for error log. ~ is replaced with newline for js alerts
        $logmsg = sprintf( "%s ;; %s", $user_message, $data1 );

        $file = str_replace( EWZ_PLUGIN_DIR, '', parent::getFile() );

        error_log( "EWZ: $logmsg~{ $file, line " . parent::getLine() . " }~" );

        $preamble = "*** EntryWizard ERROR ***\n";
        parent::__construct( "$preamble $user_message", 0, null );
    }
}
