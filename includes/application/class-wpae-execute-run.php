<?php

class WPAE_Execute_Run {

	protected $executor;

	public function __construct( WP_Automation_Executor $executor ) {
		$this->executor = $executor;
	}

	public function execute( WPAE_Workflow $workflow, array $trigger_data = array() ) {
		return $this->executor->execute_workflow( $workflow->to_array(), $trigger_data );
	}
}
