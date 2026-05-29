<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

abstract class WPAE_Abstract_Trigger implements WPAE_Trigger_Interface {
protected $agent;

public function __construct( $agent ) {
$this->agent = $agent;
}

protected function dispatch( $trigger, $payload = array() ) {
$this->agent->handle_trigger( $trigger, $payload );
}
}
