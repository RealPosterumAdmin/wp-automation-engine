<?php

class WP_Automation_Storage {

	const WORKFLOWS_OPTION = 'wp_automation_engine_workflows';
	const LOG_OPTION       = 'wp_automation_engine_execution_log';
	const MAX_LOG_ITEMS    = 50;

	public function bootstrap_defaults() {
		if ( false !== get_option( self::WORKFLOWS_OPTION, false ) ) {
			return;
		}

		update_option(
			self::WORKFLOWS_OPTION,
			array(
				array(
					'id'        => WP_Automation_Engine::DEFAULT_WORKFLOW_ID,
					'name'      => 'Example workflow',
					'enabled'   => false,
					'trigger'   => array(
						'type' => 'action',
						'hook' => 'init',
					),
					'variables' => array(
						'message' => 'Hello from WP Automation Engine',
					),
					'nodes'     => array(
						array(
							'id'     => 'node_set_message',
							'type'   => 'set_variable',
							'config' => array(
								'scope' => 'global',
								'key'   => 'message',
								'value' => 'Workflow executed on {{trigger.hook}}',
							),
						),
						array(
							'id'     => 'node_dispatch_event',
							'type'   => 'dispatch_event',
							'config' => array(
								'event'   => 'workflow.finished',
								'payload' => array(
									'workflow_id' => '{{workflow.id}}',
									'message'     => '{{variables.message}}',
								),
							),
						),
						array(
							'id'     => 'node_fire_action',
							'type'   => 'action',
							'config' => array(
								'hook'    => 'wp_automation_engine_workflow_finished',
								'payload' => array(
									'workflow_id' => '{{workflow.id}}',
									'message'     => '{{variables.message}}',
								),
							),
						),
					),
				),
			),
			false
		);

		if ( false === get_option( self::LOG_OPTION, false ) ) {
			add_option( self::LOG_OPTION, array(), '', false );
		}
	}

	public function get_workflows() {
		$workflows = get_option( self::WORKFLOWS_OPTION, array() );

		if ( ! is_array( $workflows ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( array( $this, 'normalize_workflow' ), $workflows )
			)
		);
	}

	public function save_workflows( array $workflows ) {
		$normalized = array_values(
			array_filter(
				array_map( array( $this, 'normalize_workflow' ), $workflows )
			)
		);

		update_option( self::WORKFLOWS_OPTION, $normalized, false );
	}

	public function create_workflow( array $workflow ) {
		$workflows = $this->get_workflows();

		if ( empty( $workflow['id'] ) ) {
			$workflow['id'] = $this->generate_workflow_id();
		}

		$normalized = $this->normalize_workflow( $workflow );

		if ( null === $normalized ) {
			return null;
		}

		$workflows[] = $normalized;
		update_option( self::WORKFLOWS_OPTION, $workflows, false );

		return $normalized;
	}

	public function update_workflow( $workflow_id, array $workflow ) {
		$workflow_id = sanitize_key( $workflow_id );

		if ( '' === $workflow_id ) {
			return null;
		}

		$workflow['id'] = $workflow_id;
		$normalized     = $this->normalize_workflow( $workflow );

		if ( null === $normalized ) {
			return null;
		}

		$workflows = $this->get_workflows();

		foreach ( $workflows as $index => $existing_workflow ) {
			if ( $existing_workflow['id'] !== $workflow_id ) {
				continue;
			}

			$workflows[ $index ] = $normalized;
			update_option( self::WORKFLOWS_OPTION, $workflows, false );

			return $normalized;
		}

		return null;
	}

	public function delete_workflow( $workflow_id ) {
		$workflow_id = sanitize_key( $workflow_id );

		if ( '' === $workflow_id ) {
			return false;
		}

		$workflows = $this->get_workflows();
		$updated   = array_values(
			array_filter(
				$workflows,
				static function ( $workflow ) use ( $workflow_id ) {
					return isset( $workflow['id'] ) && $workflow['id'] !== $workflow_id;
				}
			)
		);

		if ( count( $updated ) === count( $workflows ) ) {
			return false;
		}

		wp_clear_scheduled_hook( 'wp_automation_engine_run_cron_workflow', array( $workflow_id ) );
		update_option( self::WORKFLOWS_OPTION, $updated, false );

		return true;
	}

	public function workflow_exists( $workflow_id ) {
		return null !== $this->get_workflow( $workflow_id );
	}

	public function get_workflow( $workflow_id ) {
		foreach ( $this->get_workflows() as $workflow ) {
			if ( $workflow['id'] === $workflow_id ) {
				return $workflow;
			}
		}

		return null;
	}

	public function get_logs() {
		$logs = get_option( self::LOG_OPTION, array() );

		return is_array( $logs ) ? $logs : array();
	}

	public function log( array $entry ) {
		$logs   = $this->get_logs();
		$logs[] = array(
			'time'        => current_time( 'mysql', true ),
			'workflow_id' => isset( $entry['workflow_id'] ) ? sanitize_text_field( $entry['workflow_id'] ) : '',
			'node_id'     => isset( $entry['node_id'] ) ? sanitize_text_field( $entry['node_id'] ) : '',
			'status'      => isset( $entry['status'] ) ? sanitize_text_field( $entry['status'] ) : 'info',
			'message'     => isset( $entry['message'] ) ? sanitize_text_field( $entry['message'] ) : '',
			'context'     => isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array(),
		);

		if ( count( $logs ) > self::MAX_LOG_ITEMS ) {
			$logs = array_slice( $logs, -1 * self::MAX_LOG_ITEMS );
		}

		update_option( self::LOG_OPTION, $logs, false );
	}

	public function clear_cron_events() {
		foreach ( $this->get_workflows() as $workflow ) {
			$trigger = isset( $workflow['trigger'] ) ? $workflow['trigger'] : array();

			if ( 'cron' !== ( $trigger['type'] ?? '' ) ) {
				continue;
			}

			wp_clear_scheduled_hook( 'wp_automation_engine_run_cron_workflow', array( $workflow['id'] ) );
		}
	}

	private function normalize_workflow( $workflow ) {
		if ( ! is_array( $workflow ) ) {
			return null;
		}

		$id = isset( $workflow['id'] ) ? sanitize_key( $workflow['id'] ) : '';

		if ( '' === $id ) {
			return null;
		}

		$trigger = isset( $workflow['trigger'] ) && is_array( $workflow['trigger'] ) ? $workflow['trigger'] : array();

		return array(
			'id'        => $id,
			'name'      => isset( $workflow['name'] ) ? sanitize_text_field( $workflow['name'] ) : $id,
			'enabled'   => ! empty( $workflow['enabled'] ),
			'trigger'   => array(
				'type'     => isset( $trigger['type'] ) ? sanitize_key( $trigger['type'] ) : '',
				'hook'     => isset( $trigger['hook'] ) ? sanitize_text_field( $trigger['hook'] ) : '',
				'schedule' => isset( $trigger['schedule'] ) ? sanitize_key( $trigger['schedule'] ) : '',
				'event'    => isset( $trigger['event'] ) ? sanitize_text_field( $trigger['event'] ) : '',
			),
			'variables' => isset( $workflow['variables'] ) && is_array( $workflow['variables'] ) ? $workflow['variables'] : array(),
			'nodes'     => isset( $workflow['nodes'] ) && is_array( $workflow['nodes'] ) ? $workflow['nodes'] : array(),
		);
	}

	private function generate_workflow_id() {
		do {
			$workflow_id = 'workflow_' . strtolower( wp_generate_password( 8, false, false ) );
		} while ( $this->workflow_exists( $workflow_id ) );

		return $workflow_id;
	}
}
