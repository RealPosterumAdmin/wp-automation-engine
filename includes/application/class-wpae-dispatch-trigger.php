<?php

class WPAE_Dispatch_Trigger {

	protected $start_workflow;

	public function __construct( WPAE_Start_Workflow $start_workflow ) {
		$this->start_workflow = $start_workflow;
	}

	public function dispatch( $workflow_id, array $trigger_data = array(), array $options = array() ) {
		return $this->start_workflow->start( $workflow_id, $trigger_data, $options );
	}
}
