<?php

class WPAE_Storage_Run_Repository implements WPAE_Run_Repository_Interface {

	protected $storage;

	public function __construct( WP_Automation_Storage $storage ) {
		$this->storage = $storage;
	}

	public function create( WPAE_Run $run ) {
		$this->storage->create_run( $run->to_array() );
	}

	public function update( $run_id, array $attributes ) {
		$this->storage->update_run( $run_id, $attributes );
	}

	public function append_step( $run_id, WPAE_Step_Run $step ) {
		$this->storage->append_run_step( $run_id, $step->to_array() );
	}

	public function find( $run_id ) {
		return $this->storage->get_run( $run_id );
	}

	public function all( $workflow_id = '' ) {
		return $this->storage->get_runs( $workflow_id );
	}
}
