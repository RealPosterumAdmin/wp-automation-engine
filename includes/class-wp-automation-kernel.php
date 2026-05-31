<?php

class WP_Automation_Kernel {

	protected $dispatch_trigger;

	public function __construct( WPAE_Dispatch_Trigger $dispatch_trigger ) {
		$this->dispatch_trigger = $dispatch_trigger;
	}

	public function handle_workflow_trigger( $workflow_id, array $trigger_data = array() ) {
		return $this->dispatch_trigger->dispatch( $workflow_id, $trigger_data );
	}

	public function run_manual_workflow( $workflow_id, array $trigger_data = array() ) {
		return $this->dispatch_trigger->dispatch(
			$workflow_id,
			array_merge(
				array(
					'type' => 'manual',
				),
				$trigger_data
			),
			array(
				'force' => true,
			)
		);
	}
}
