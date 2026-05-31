<?php

class WPAE_Storage_Workflow_Repository implements WPAE_Workflow_Repository_Interface {

	protected $storage;

	public function __construct( WP_Automation_Storage $storage ) {
		$this->storage = $storage;
	}

	public function find( $workflow_id ) {
		$workflow = $this->storage->get_workflow( $workflow_id );

		return is_array( $workflow ) ? WPAE_Workflow::from_array( $workflow ) : null;
	}

	public function all() {
		$workflows = array();

		foreach ( $this->storage->get_workflows() as $workflow ) {
			$workflows[] = WPAE_Workflow::from_array( $workflow );
		}

		return $workflows;
	}
}
