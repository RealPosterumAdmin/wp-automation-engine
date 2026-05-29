<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Action_Registry {
protected $actions = array();

public function register( $name, $action ) {
if ( ! $action instanceof WPAE_Action_Interface ) {
return;
}

$this->actions[ sanitize_key( $name ) ] = $action;
}

public function get( $name ) {
$key = sanitize_key( $name );

return isset( $this->actions[ $key ] ) ? $this->actions[ $key ] : null;
}
}
