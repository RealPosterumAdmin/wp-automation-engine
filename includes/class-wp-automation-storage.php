<?php

class WP_Automation_Storage {

	const WORKFLOWS_OPTION = 'wp_automation_engine_workflows';
	const LOG_OPTION       = 'wp_automation_engine_execution_log';
	const RUNS_OPTION      = 'wp_automation_engine_runs';
	const MAX_LOG_ITEMS    = 50;

	public function bootstrap_defaults() {
		$workflows = get_option( self::WORKFLOWS_OPTION, false );

		if ( false === $workflows ) {
			update_option( self::WORKFLOWS_OPTION, $this->get_default_workflows(), false );
		} elseif ( is_array( $workflows ) ) {
			$merged_workflows = $this->merge_missing_default_workflows( $workflows );

			if ( $merged_workflows !== $workflows ) {
				update_option( self::WORKFLOWS_OPTION, $merged_workflows, false );
			}
		}

		if ( false === get_option( self::LOG_OPTION, false ) ) {
			add_option( self::LOG_OPTION, array(), '', false );
		}

		if ( false === get_option( self::RUNS_OPTION, false ) ) {
			add_option( self::RUNS_OPTION, array(), '', false );
		}
	}

	private function get_default_workflows() {
		return array(
			array(
				'id'        => WP_Automation_Engine::DEFAULT_WORKFLOW_ID,
				'name'      => 'Пример сценария',
				'enabled'   => false,
				'trigger'   => array(
					'type' => 'action',
					'hook' => 'init',
				),
				'variables' => array(
					'message' => 'Привет из WP Automation Engine',
				),
				'nodes'     => array(
					array(
						'id'     => 'node_set_message',
						'type'   => 'set_variable',
						'config' => array(
							'scope' => 'global',
							'key'   => 'message',
							'value' => 'Сценарий выполнен на хуке {{trigger.hook}}',
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
			array(
				'id'        => 'onec_import_products',
				'name'      => '1С → WooCommerce: импорт каталога',
				'enabled'   => false,
				'trigger'   => array(
					'type' => 'manual',
				),
				'variables' => array(
					'batch_size' => 1000,
				),
				'nodes'     => array(
					array(
						'id'     => 'import_catalog',
						'type'   => 'import_onec_products',
						'config' => array(
							'payload_key'       => 'onec_products',
							'create_categories' => 'yes',
							'verify_ssl'        => 'yes',
							'timeout'           => 60,
							'store_in'          => 'onec_payload',
						),
					),
					array(
						'id'     => 'schedule_batch_processing',
						'type'   => 'schedule_workflow',
						'config' => array(
							'mode'           => 'cron',
							'workflow_id'    => 'onec_process_products_batch',
							'delay_seconds'  => 0,
							'payload_id'     => '{{payload.id}}',
							'payload_source' => '{{payload.source}}',
							'offset'         => 0,
							'limit'          => '{{variables.batch_size}}',
						),
					),
				),
			),
			array(
				'id'        => 'onec_process_products_batch',
				'name'      => '1С → WooCommerce: обработка батча товаров',
				'enabled'   => true,
				'trigger'   => array(
					'type' => 'manual',
				),
				'variables' => array(
					'batch_size' => 1000,
				),
				'nodes'     => array(
					array(
						'id'     => 'load_batch',
						'type'   => 'load_payload_batch',
						'config' => array(
							'payload_id'  => '{{payload.id}}',
							'offset'      => '{{batch.offset}}',
							'limit'       => '{{batch.limit}}',
							'store_in'    => 'payload_items',
							'meta_in'     => 'payload_batch',
							'on_complete' => 'delete',
						),
					),
					array(
						'id'     => 'loop_products',
						'type'   => 'loop',
						'config' => array(
							'source'    => 'variables.payload_items',
							'item_name' => 'item',
							'nodes'     => array(
								array(
									'id'     => 'sync_product',
									'type'   => 'sync_onec_product',
									'config' => array(
										'source'                  => 'current_item',
										'update_name_on_existing' => 'no',
										'sync_category_on_update' => 'no',
										'store_in'                => 'synced_product_id',
									),
								),
							),
						),
					),
					array(
						'id'     => 'continue_if_needed',
						'type'   => 'if',
						'config' => array(
							'condition' => array(
								'left'       => array(
									'type'  => 'path',
									'value' => 'batch.has_more',
								),
								'comparison' => '==',
								'right'      => array(
									'type'  => 'value',
									'value' => true,
								),
							),
							'on_true'   => array(
								array(
									'id'     => 'schedule_next_batch',
									'type'   => 'schedule_workflow',
									'config' => array(
										'mode'           => 'cron',
										'workflow_id'    => 'onec_process_products_batch',
										'delay_seconds'  => 60,
										'payload_id'     => '{{payload.id}}',
										'payload_source' => '{{payload.source}}',
										'offset'         => '{{batch.next_offset}}',
										'limit'          => '{{batch.limit}}',
									),
								),
							),
							'on_false'  => array(),
						),
					),
				),
			),
			array(
				'id'        => 'woocommerce_export_products',
				'name'      => 'WooCommerce: экспорт товаров в JSON',
				'enabled'   => false,
				'trigger'   => array(
					'type' => 'manual',
				),
				'variables' => array(),
				'nodes'     => array(
					array(
						'id'     => 'export_products',
						'type'   => 'export_woocommerce_products',
						'config' => array(
							'target'     => 'payload',
							'target_key' => 'woo_products_export',
							'store_in'   => 'woo_products_export',
						),
					),
				),
			),
		);
	}

	private function merge_missing_default_workflows( array $workflows ) {
		$existing_ids = array();

		foreach ( $workflows as $workflow ) {
			if ( is_array( $workflow ) && ! empty( $workflow['id'] ) ) {
				$existing_ids[] = (string) $workflow['id'];
			}
		}

		foreach ( $this->get_default_workflows() as $default_workflow ) {
			if ( in_array( $default_workflow['id'], $existing_ids, true ) ) {
				continue;
			}

			$workflows[] = $default_workflow;
		}

		return $workflows;
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

	public function get_runs( $workflow_id = '' ) {
		$runs = get_option( self::RUNS_OPTION, array() );

		if ( ! is_array( $runs ) ) {
			return array();
		}

		$runs = array_values(
			array_filter(
				array_map( array( $this, 'normalize_run' ), $runs )
			)
		);

		if ( '' === $workflow_id ) {
			return $runs;
		}

		return array_values(
			array_filter(
				$runs,
				static function ( $run ) use ( $workflow_id ) {
					return isset( $run['workflow_id'] ) && $run['workflow_id'] === $workflow_id;
				}
			)
		);
	}

	public function get_run( $run_id ) {
		foreach ( $this->get_runs() as $run ) {
			if ( $run['id'] === $run_id ) {
				return $run;
			}
		}

		return null;
	}

	public function create_run( array $run ) {
		$normalized = $this->normalize_run( $run );

		if ( null === $normalized ) {
			return null;
		}

		$runs   = $this->get_runs();
		$runs[] = $normalized;
		update_option( self::RUNS_OPTION, $runs, false );

		return $normalized;
	}

	public function update_run( $run_id, array $attributes ) {
		$runs = $this->get_runs();

		foreach ( $runs as $index => $run ) {
			if ( $run['id'] !== $run_id ) {
				continue;
			}

			$merged = array_merge( $run, $attributes );

			if ( isset( $attributes['steps'] ) && is_array( $attributes['steps'] ) ) {
				$merged['steps'] = $attributes['steps'];
			}

			$normalized = $this->normalize_run( $merged );

			if ( null === $normalized ) {
				return null;
			}

			$runs[ $index ] = $normalized;
			update_option( self::RUNS_OPTION, $runs, false );

			return $normalized;
		}

		return null;
	}

	public function append_run_step( $run_id, array $step ) {
		$run = $this->get_run( $run_id );

		if ( ! $run ) {
			return null;
		}

		$steps   = isset( $run['steps'] ) && is_array( $run['steps'] ) ? $run['steps'] : array();
		$steps[] = $this->normalize_step( $step );

		return $this->update_run(
			$run_id,
			array(
				'steps' => $steps,
			)
		);
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

	public function clear_workflow_cron_event( $workflow_id ) {
		$workflow_id = sanitize_key( $workflow_id );

		if ( '' === $workflow_id ) {
			return;
		}

		wp_clear_scheduled_hook( 'wp_automation_engine_run_cron_workflow', array( $workflow_id ) );
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

	private function normalize_run( $run ) {
		if ( ! is_array( $run ) ) {
			return null;
		}

		$id = isset( $run['id'] ) ? sanitize_key( $run['id'] ) : '';

		if ( '' === $id ) {
			return null;
		}

		$steps = array();

		if ( isset( $run['steps'] ) && is_array( $run['steps'] ) ) {
			foreach ( $run['steps'] as $step ) {
				$steps[] = $this->normalize_step( $step );
			}
		}

		return array(
			'id'               => $id,
			'workflow_id'      => isset( $run['workflow_id'] ) ? sanitize_key( $run['workflow_id'] ) : '',
			'workflow_name'    => isset( $run['workflow_name'] ) ? sanitize_text_field( $run['workflow_name'] ) : '',
			'workflow_version' => isset( $run['workflow_version'] ) ? sanitize_text_field( $run['workflow_version'] ) : '',
			'status'           => isset( $run['status'] ) ? sanitize_key( $run['status'] ) : 'pending',
			'trigger_data'     => $this->normalize_debug_value( $run['trigger_data'] ?? array() ),
			'steps'            => $steps,
			'started_at'       => isset( $run['started_at'] ) ? sanitize_text_field( $run['started_at'] ) : '',
			'finished_at'      => isset( $run['finished_at'] ) ? sanitize_text_field( $run['finished_at'] ) : '',
			'context_snapshot' => $this->normalize_debug_value( $run['context_snapshot'] ?? array() ),
			'error_message'    => isset( $run['error_message'] ) ? sanitize_text_field( $run['error_message'] ) : '',
		);
	}

	private function normalize_step( $step ) {
		if ( ! is_array( $step ) ) {
			$step = array();
		}

		return array(
			'time'      => isset( $step['time'] ) ? sanitize_text_field( $step['time'] ) : current_time( 'mysql', true ),
			'node_id'   => isset( $step['node_id'] ) ? sanitize_text_field( $step['node_id'] ) : '',
			'node_type' => isset( $step['node_type'] ) ? sanitize_text_field( $step['node_type'] ) : '',
			'status'    => isset( $step['status'] ) ? sanitize_key( $step['status'] ) : 'info',
			'message'   => isset( $step['message'] ) ? sanitize_text_field( $step['message'] ) : '',
			'context'   => $this->normalize_debug_value( $step['context'] ?? array() ),
		);
	}

	private function normalize_debug_value( $value ) {
		$encoded = wp_json_encode( $value );

		if ( false === $encoded ) {
			return array();
		}

		$decoded = json_decode( $encoded, true );

		return null === $decoded ? array() : $decoded;
	}

	private function generate_workflow_id() {
		do {
			$workflow_id = 'workflow_' . strtolower( wp_generate_password( 8, false, false ) );
		} while ( $this->workflow_exists( $workflow_id ) );

		return $workflow_id;
	}
}
