<?php

class WP_Automation_Executor {

	protected $storage;
	protected $node_factory;
	protected $run_repository;
	protected $expression_evaluator;
	protected $lock_manager;
	protected $event_bus;
	protected $payload_storage;
	protected $workflow_scheduler;

	public function __construct( WP_Automation_Storage $storage, WP_Automation_Node_Factory $node_factory, WPAE_Run_Repository_Interface $run_repository = null, WPAE_Expression_Evaluator_Interface $expression_evaluator = null, WPAE_Lock_Manager_Interface $lock_manager = null, WPAE_Event_Bus_Interface $event_bus = null, WPAE_Payload_Storage_Interface $payload_storage = null, WPAE_Workflow_Scheduler_Interface $workflow_scheduler = null ) {
		$this->storage              = $storage;
		$this->node_factory         = $node_factory;
		$this->run_repository       = $run_repository;
		$this->expression_evaluator = $expression_evaluator;
		$this->lock_manager         = $lock_manager;
		$this->event_bus            = $event_bus;
		$this->payload_storage      = $payload_storage;
		$this->workflow_scheduler   = $workflow_scheduler;
	}

	public function execute_workflow( array $workflow, array $trigger_data = array() ) {
		$workflow_entity = WPAE_Workflow::from_array( $workflow );
		$version         = WPAE_Workflow_Version::from_workflow( $workflow_entity );
		$lock_key        = 'workflow_' . $workflow_entity->get_id();

		if ( $this->lock_manager && ! $this->lock_manager->acquire( $lock_key, 300 ) ) {
			$this->storage->log(
				array(
					'workflow_id' => $workflow_entity->get_id(),
					'status'      => 'skipped',
					'message'     => 'Сценарий уже выполняется.',
					'context'     => array( 'trigger' => $trigger_data ),
				)
			);

			return null;
		}

		$run = WPAE_Run::start( $workflow_entity, $version, $trigger_data, current_time( 'mysql', true ) );

		if ( $this->run_repository ) {
			$this->run_repository->create( $run );
		}

		$context = new WP_Automation_Context(
			$workflow,
			$trigger_data,
			array(
				'run_id'           => $run->get_id(),
				'workflow_version' => $version->get_hash(),
				'payload'          => $this->extract_payload_runtime( $trigger_data ),
				'batch'            => $this->extract_batch_runtime( $trigger_data ),
			)
		);

		$this->storage->log(
			array(
				'workflow_id' => $workflow['id'],
				'status'      => 'started',
				'message'     => 'Сценарий запущен.',
				'context'     => array(
					'trigger' => $trigger_data,
					'run_id'  => $run->get_id(),
				),
			)
		);

		try {
			$this->execute_nodes( $workflow['nodes'], $context );

			$this->storage->log(
				array(
					'workflow_id' => $workflow['id'],
					'status'      => 'success',
					'message'     => 'Сценарий завершен.',
					'context'     => array(
						'variables' => $context->to_array()['variables'],
						'run_id'    => $run->get_id(),
					),
				)
			);

			if ( $this->run_repository ) {
				$this->run_repository->update(
					$run->get_id(),
					array(
						'status'           => 'success',
						'finished_at'      => current_time( 'mysql', true ),
						'context_snapshot' => $context->to_array(),
					)
				);
			}
		} catch ( Exception $exception ) {
			$this->storage->log(
				array(
					'workflow_id' => $workflow['id'],
					'status'      => 'error',
					'message'     => $exception->getMessage(),
					'context'     => array(
						'run_id' => $run->get_id(),
					),
				)
			);

			if ( $this->run_repository ) {
				$this->run_repository->update(
					$run->get_id(),
					array(
						'status'           => 'error',
						'finished_at'      => current_time( 'mysql', true ),
						'context_snapshot' => $context->to_array(),
						'error_message'    => $exception->getMessage(),
					)
				);
			}
		} finally {
			if ( $this->lock_manager ) {
				$this->lock_manager->release( $lock_key );
			}
		}

		return $run->get_id();
	}

	public function execute_nodes( array $nodes, WP_Automation_Context $context ) {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$handler = $this->node_factory->create( $node );

			if ( ! $handler ) {
				$this->storage->log(
					array(
						'workflow_id' => $context->get_workflow()['id'],
						'node_id'     => $node['id'] ?? '',
						'status'      => 'skipped',
						'message'     => 'Неизвестный тип узла.',
					)
				);
				continue;
			}

			$handler->execute( $node, $context, $this );
		}
	}

	public function resolve_value( $value, WP_Automation_Context $context ) {
		if ( $this->expression_evaluator ) {
			return $this->expression_evaluator->resolve( $value, $context );
		}

		return $value;
	}

	public function log_node( WP_Automation_Context $context, array $node, $message, $status = 'success', array $extra_context = array() ) {
		$log_context = array_merge(
			$extra_context,
			array(
				'run_id' => $context->get_runtime()['run_id'] ?? '',
			)
		);

		$this->storage->log(
			array(
				'workflow_id' => $context->get_workflow()['id'],
				'node_id'     => $node['id'] ?? '',
				'status'      => $status,
				'message'     => $message,
				'context'     => $log_context,
			)
		);

		if ( $this->run_repository && ! empty( $log_context['run_id'] ) ) {
			$this->run_repository->append_step(
				$log_context['run_id'],
				new WPAE_Step_Run(
					array(
						'time'      => current_time( 'mysql', true ),
						'node_id'   => $node['id'] ?? '',
						'node_type' => $node['type'] ?? '',
						'status'    => $status,
						'message'   => $message,
						'context'   => $extra_context,
					)
				)
			);
		}
	}

	public function dispatch_internal_event( $event_name, array $payload = array() ) {
		if ( $this->event_bus ) {
			$this->event_bus->dispatch( $event_name, $payload );
			return;
		}

		do_action( 'wp_automation_engine_internal_event_' . $event_name, $payload );
	}

	public function save_payload( $payload_key, $data, array $metadata = array() ) {
		if ( ! $this->payload_storage ) {
			return new WP_Error( 'payload_storage_unavailable', __( 'Хранилище JSON-пакетов недоступно.', 'wp-automation-engine' ) );
		}

		$metadata['key'] = sanitize_key( $payload_key );
		$reference       = $this->payload_storage->save( $data, $metadata );

		if ( is_wp_error( $reference ) ) {
			return $reference;
		}

		return $reference instanceof WPAE_Payload_Reference ? $reference->to_array() : $reference;
	}

	public function load_payload_batch( $payload_id, $offset = 0, $limit = 50, $source = '' ) {
		if ( ! $this->payload_storage ) {
			return new WP_Error( 'payload_storage_unavailable', __( 'Хранилище JSON-пакетов недоступно.', 'wp-automation-engine' ) );
		}

		return $this->payload_storage->read_batch( $payload_id, $offset, $limit, $source );
	}

	public function archive_payload( $payload_id ) {
		if ( ! $this->payload_storage ) {
			return new WP_Error( 'payload_storage_unavailable', __( 'Хранилище JSON-пакетов недоступно.', 'wp-automation-engine' ) );
		}

		return $this->payload_storage->archive( $payload_id );
	}

	public function delete_payload( $payload_id ) {
		if ( ! $this->payload_storage ) {
			return new WP_Error( 'payload_storage_unavailable', __( 'Хранилище JSON-пакетов недоступно.', 'wp-automation-engine' ) );
		}

		return $this->payload_storage->delete( $payload_id );
	}

	public function schedule_workflow( $workflow_id, array $trigger_data = array(), $delay_seconds = 0 ) {
		if ( ! $this->workflow_scheduler ) {
			return new WP_Error( 'workflow_scheduler_unavailable', __( 'Планировщик сценариев недоступен.', 'wp-automation-engine' ) );
		}

		$scheduled_at = time() + max( 0, (int) $delay_seconds );

		return $this->workflow_scheduler->schedule_single( $workflow_id, $trigger_data, $scheduled_at );
	}

	protected function extract_payload_runtime( array $trigger_data ) {
		if ( isset( $trigger_data['payload'] ) && is_array( $trigger_data['payload'] ) ) {
			return $trigger_data['payload'];
		}

		if ( isset( $trigger_data['payload_reference'] ) && is_array( $trigger_data['payload_reference'] ) ) {
			return $trigger_data['payload_reference'];
		}

		return array();
	}

	protected function extract_batch_runtime( array $trigger_data ) {
		if ( isset( $trigger_data['batch'] ) && is_array( $trigger_data['batch'] ) ) {
			return $trigger_data['batch'];
		}

		return array(
			'offset' => 0,
			'limit'  => 50,
		);
	}
}
