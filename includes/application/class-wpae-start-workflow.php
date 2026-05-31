<?php

class WPAE_Start_Workflow {

	protected $workflow_repository;
	protected $queue;

	public function __construct( WPAE_Workflow_Repository_Interface $workflow_repository, WPAE_Queue_Interface $queue ) {
		$this->workflow_repository = $workflow_repository;
		$this->queue               = $queue;
	}

	public function start( $workflow_id, array $trigger_data = array(), array $options = array() ) {
		$workflow = $this->workflow_repository->find( $workflow_id );

		if ( ! $workflow instanceof WPAE_Workflow ) {
			return new WP_Error( 'workflow_not_found', __( 'Сценарий не найден.', 'wp-automation-engine' ) );
		}

		if ( ! $workflow->is_enabled() && empty( $options['force'] ) ) {
			return new WP_Error( 'workflow_disabled', __( 'Сценарий отключен.', 'wp-automation-engine' ) );
		}

		return $this->queue->dispatch(
			'execute_workflow',
			array(
				'workflow'    => $workflow->to_array(),
				'trigger'     => $trigger_data,
				'start_flags' => $options,
			)
		);
	}
}
