<?php

class WPAE_Trigger_Registry implements WPAE_Trigger_Registry_Interface {

	protected $definitions = array();

	public function __construct() {
		$this->register_builtin_triggers();
	}

	public function register( WPAE_Trigger_Definition $definition ) {
		$this->definitions[ $definition->get_type() ] = $definition;
	}

	public function all() {
		return array_values( $this->definitions );
	}

	public function get_supported_types() {
		return array_keys( $this->definitions );
	}

	protected function register_builtin_triggers() {
		$this->register( new WPAE_Trigger_Definition( 'action', array() ) );
		$this->register( new WPAE_Trigger_Definition( 'filter', array() ) );
		$this->register( new WPAE_Trigger_Definition( 'cron', array() ) );
		$this->register( new WPAE_Trigger_Definition( 'internal_event', array() ) );
		$this->register( new WPAE_Trigger_Definition( 'manual', array() ) );
	}
}
