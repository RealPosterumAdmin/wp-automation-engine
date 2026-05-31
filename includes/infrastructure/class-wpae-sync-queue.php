<?php

class WPAE_Sync_Queue implements WPAE_Queue_Interface {

	protected $handlers = array();

	public function register_handler( $job_name, callable $handler ) {
		$this->handlers[ (string) $job_name ] = $handler;
	}

	public function dispatch( $job_name, array $payload = array() ) {
		$job_name = (string) $job_name;

		if ( ! isset( $this->handlers[ $job_name ] ) ) {
			return null;
		}

		return call_user_func( $this->handlers[ $job_name ], $payload );
	}
}
