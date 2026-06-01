<?php

class WPAE_WordPress_Workflow_Scheduler implements WPAE_Workflow_Scheduler_Interface {

	public function schedule_single( $workflow_id, array $trigger_data = array(), $timestamp = 0 ) {
		$workflow_id = sanitize_key( $workflow_id );

		if ( '' === $workflow_id ) {
			return new WP_Error( 'missing_workflow_id', __( 'Не указан сценарий для отложенного запуска.', 'wp-automation-engine' ) );
		}

		$timestamp = (int) $timestamp;

		if ( $timestamp <= time() ) {
			$timestamp = time() + 1;
		}

		wp_schedule_single_event( $timestamp, 'wp_automation_engine_run_scheduled_workflow', array( $workflow_id, $trigger_data ) );

		return true;
	}
}
