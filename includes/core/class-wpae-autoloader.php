<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Autoloader {
protected static $base_dir = '';

public static function register( $base_dir ) {
self::$base_dir = trailingslashit( $base_dir );
spl_autoload_register( array( __CLASS__, 'autoload' ) );
}

public static function autoload( $class_name ) {
if ( 0 !== strpos( $class_name, 'WPAE_' ) ) {
return;
}

$relative_class = strtolower( str_replace( '_', '-', substr( $class_name, 5 ) ) );
$filename       = 'class-wpae-' . $relative_class . '.php';
$directories    = array(
self::$base_dir . 'includes/core/',
self::$base_dir . 'includes/triggers/',
self::$base_dir . 'includes/actions/',
);

foreach ( $directories as $directory ) {
$file = $directory . $filename;

if ( file_exists( $file ) ) {
require_once $file;
return;
}
}
}
}
