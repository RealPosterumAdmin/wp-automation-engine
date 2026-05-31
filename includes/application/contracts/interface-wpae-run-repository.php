<?php

interface WPAE_Run_Repository_Interface {

	public function create( WPAE_Run $run );

	public function update( $run_id, array $attributes );

	public function append_step( $run_id, WPAE_Step_Run $step );

	public function find( $run_id );

	public function all( $workflow_id = '' );
}
