<?php

class WP_Automation_Kernel {

	protected $storage;
	protected $executor;

	public function __construct( WP_Automation_Storage $storage, WP_Automation_Executor $executor ) {
		$this->storage  = $storage;
		$this->executor = $executor;
	}

	public function handle_workflow_trigger( $workflow_id, array $trigger_data = array() ) {
		$workflow = $this->storage->get_workflow( $workflow_id );

		if ( ! $workflow || empty( $workflow['enabled'] ) ) {
			return;
		}

		$this->executor->execute_workflow( $workflow, $trigger_data );
	}
}
