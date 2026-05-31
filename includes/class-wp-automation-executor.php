<?php

class WP_Automation_Executor {

	protected $storage;
	protected $node_factory;

	public function __construct( WP_Automation_Storage $storage, WP_Automation_Node_Factory $node_factory ) {
		$this->storage      = $storage;
		$this->node_factory = $node_factory;
	}

	public function execute_workflow( array $workflow, array $trigger_data = array() ) {
		$context = new WP_Automation_Context( $workflow, $trigger_data );

		$this->storage->log(
			array(
				'workflow_id' => $workflow['id'],
				'status'      => 'started',
				'message'     => 'Сценарий запущен.',
				'context'     => array( 'trigger' => $trigger_data ),
			)
		);

		try {
			$this->execute_nodes( $workflow['nodes'], $context );

			$this->storage->log(
				array(
					'workflow_id' => $workflow['id'],
					'status'      => 'success',
					'message'     => 'Сценарий завершен.',
					'context'     => array( 'variables' => $context->to_array()['variables'] ),
				)
			);
		} catch ( Exception $exception ) {
			$this->storage->log(
				array(
					'workflow_id' => $workflow['id'],
					'status'      => 'error',
					'message'     => $exception->getMessage(),
				)
			);
		}
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
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $nested_value ) {
				$value[ $key ] = $this->resolve_value( $nested_value, $context );
			}

			return $value;
		}

		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( preg_match( '/^\{\{\s*([^}]+)\s*\}\}$/', $value, $matches ) ) {
			return $context->resolve_path( trim( $matches[1] ) );
		}

		return preg_replace_callback(
			'/\{\{\s*([^}]+)\s*\}\}/',
			static function ( $matches ) use ( $context ) {
				$resolved = $context->resolve_path( trim( $matches[1] ) );

				if ( is_scalar( $resolved ) || null === $resolved ) {
					return (string) $resolved;
				}

				return wp_json_encode( $resolved );
			},
			$value
		);
	}

	public function log_node( WP_Automation_Context $context, array $node, $message, $status = 'success', array $extra_context = array() ) {
		$this->storage->log(
			array(
				'workflow_id' => $context->get_workflow()['id'],
				'node_id'     => $node['id'] ?? '',
				'status'      => $status,
				'message'     => $message,
				'context'     => $extra_context,
			)
		);
	}
}
