<?php

class WPAE_WordPress_Event_Bus implements WPAE_Event_Bus_Interface {

	public function dispatch( $event_name, array $payload = array() ) {
		do_action( 'wp_automation_engine_internal_event_' . $event_name, $payload );
	}
}
