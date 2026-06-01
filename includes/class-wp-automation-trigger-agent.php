<?php

class WP_Automation_Trigger_Agent {

	protected $storage;
	protected $kernel;
	protected $trigger_registry;

	public function __construct( WP_Automation_Storage $storage, WP_Automation_Kernel $kernel, WPAE_Trigger_Registry $trigger_registry ) {
		$this->storage          = $storage;
		$this->kernel           = $kernel;
		$this->trigger_registry = $trigger_registry;
	}

	public function bootstrap() {
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
		add_action( 'wp_automation_engine_run_cron_workflow', array( $this, 'run_cron_workflow' ), 10, 1 );
		add_action( 'wp_automation_engine_run_scheduled_workflow', array( $this, 'run_scheduled_workflow' ), 10, 2 );

		foreach ( $this->storage->get_workflows() as $workflow ) {
			if ( empty( $workflow['enabled'] ) ) {
				$this->unschedule_cron_workflow( $workflow );
				continue;
			}

			$trigger = isset( $workflow['trigger'] ) ? $workflow['trigger'] : array();

			if ( ! in_array( $trigger['type'] ?? '', $this->trigger_registry->get_supported_types(), true ) ) {
				continue;
			}

			switch ( $trigger['type'] ?? '' ) {
				case 'action':
					$this->register_action_trigger( $workflow );
					break;
				case 'filter':
					$this->register_filter_trigger( $workflow );
					break;
				case 'cron':
					$this->schedule_cron_workflow( $workflow );
					break;
				case 'internal_event':
					$this->register_internal_event_trigger( $workflow );
					break;
				case 'manual':
					break;
			}
		}
	}

	public function register_cron_schedules( $schedules ) {
		$schedules['every_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every Minute', 'wp-automation-engine' ),
		);

		return $schedules;
	}

	public function run_cron_workflow( $workflow_id ) {
		$this->kernel->handle_workflow_trigger(
			$workflow_id,
			array(
				'type' => 'cron',
			)
		);
	}

	public function run_scheduled_workflow( $workflow_id, $trigger_data = array() ) {
		$this->kernel->handle_workflow_trigger(
			$workflow_id,
			array_merge(
				array(
					'type' => 'scheduled',
				),
				is_array( $trigger_data ) ? $trigger_data : array()
			)
		);
	}

	private function register_action_trigger( array $workflow ) {
		$hook = $workflow['trigger']['hook'] ?? '';

		if ( '' === $hook ) {
			return;
		}

		add_action(
			$hook,
			function () use ( $workflow, $hook ) {
				$this->kernel->handle_workflow_trigger(
					$workflow['id'],
					array(
						'type' => 'action',
						'hook' => $hook,
						'args' => func_get_args(),
					)
				);
			},
			10,
			99
		);
	}

	private function register_filter_trigger( array $workflow ) {
		$hook = $workflow['trigger']['hook'] ?? '';

		if ( '' === $hook ) {
			return;
		}

		add_filter(
			$hook,
			function ( $value ) use ( $workflow, $hook ) {
				$this->kernel->handle_workflow_trigger(
					$workflow['id'],
					array(
						'type'  => 'filter',
						'hook'  => $hook,
						'value' => $value,
						'args'  => func_get_args(),
					)
				);

				return $value;
			},
			10,
			99
		);
	}

	private function register_internal_event_trigger( array $workflow ) {
		$event_name = $workflow['trigger']['event'] ?? '';

		if ( '' === $event_name ) {
			return;
		}

		add_action(
			'wp_automation_engine_internal_event_' . $event_name,
			function ( $payload = array() ) use ( $workflow, $event_name ) {
				$this->kernel->handle_workflow_trigger(
					$workflow['id'],
					array(
						'type'    => 'internal_event',
						'event'   => $event_name,
						'payload' => is_array( $payload ) ? $payload : array(),
					)
				);
			},
			10,
			1
		);
	}

	private function schedule_cron_workflow( array $workflow ) {
		$schedule = $workflow['trigger']['schedule'] ?? '';

		if ( '' === $schedule ) {
			return;
		}

		$next_run = wp_next_scheduled( 'wp_automation_engine_run_cron_workflow', array( $workflow['id'] ) );

		if ( false === $next_run ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $schedule, 'wp_automation_engine_run_cron_workflow', array( $workflow['id'] ) );
		}
	}

	private function unschedule_cron_workflow( array $workflow ) {
		wp_clear_scheduled_hook( 'wp_automation_engine_run_cron_workflow', array( $workflow['id'] ) );
	}
}
