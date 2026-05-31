<?php

class WPAE_Node_Factory_Registry implements WPAE_Node_Registry_Interface {

	protected $node_factory;

	public function __construct( WP_Automation_Node_Factory $node_factory ) {
		$this->node_factory = $node_factory;
	}

	public function create( array $node ) {
		return $this->node_factory->create( $node );
	}

	public function get_schemas() {
		return $this->node_factory->get_schemas();
	}
}
