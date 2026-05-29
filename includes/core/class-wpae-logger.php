<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Logger {
public function log( $message, $context = array() ) {
$entry = '[WPAE] ' . wp_strip_all_tags( (string) $message );

if ( ! empty( $context ) ) {
$encoded = wp_json_encode( $context );

if ( false !== $encoded ) {
$entry .= ' ' . $encoded;
}
}

error_log( $entry );
}
}
