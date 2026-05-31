<?php

interface WPAE_Event_Bus_Interface {

	public function dispatch( $event_name, array $payload = array() );
}
