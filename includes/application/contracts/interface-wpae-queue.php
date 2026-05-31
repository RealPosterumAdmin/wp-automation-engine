<?php

interface WPAE_Queue_Interface {

	public function register_handler( $job_name, callable $handler );

	public function dispatch( $job_name, array $payload = array() );
}
