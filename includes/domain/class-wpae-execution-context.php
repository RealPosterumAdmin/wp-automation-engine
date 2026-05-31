<?php

class WPAE_Execution_Context {

	protected $workflow;
	protected $trigger_data;
	protected $variables;
	protected $local_variables;
	protected $current_item;
	protected $runtime;
	protected $item_stack;
	protected $named_items;

	public function __construct( array $workflow, array $trigger_data = array(), array $variables = array(), array $runtime = array() ) {
		$this->workflow        = $workflow;
		$this->trigger_data    = $trigger_data;
		$this->variables       = $variables;
		$this->local_variables = array();
		$this->current_item    = null;
		$this->item_stack      = array();
		$this->named_items     = array();
		$this->runtime         = array_merge(
			array(
				'started_at' => gmdate( 'Y-m-d H:i:s' ),
				'workflow'   => $workflow['id'] ?? '',
			),
			$runtime
		);
	}

	public function get_workflow() {
		return $this->workflow;
	}

	public function get_runtime() {
		return $this->runtime;
	}

	public function set_runtime_value( $key, $value ) {
		$this->runtime[ $key ] = $value;
	}

	public function set_variable( $key, $value, $scope = 'global' ) {
		if ( 'local' === $scope ) {
			$this->local_variables[ $key ] = $value;
			return;
		}

		$this->variables[ $key ] = $value;
	}

	public function set_current_item( $value ) {
		$this->current_item = $value;
	}

	public function push_item_context( $name, $value ) {
		$this->item_stack[] = new WPAE_Item_Context( $name, $value, $this->current_item );
		$this->current_item = $value;

		if ( '' !== $name ) {
			$this->named_items[ $name ] = $value;
		}
	}

	public function pop_item_context() {
		$frame = array_pop( $this->item_stack );

		if ( ! $frame instanceof WPAE_Item_Context ) {
			$this->current_item = null;
			$this->named_items  = array();
			return;
		}

		$this->current_item = $frame->get_previous_item();
		$this->named_items  = array();

		foreach ( $this->item_stack as $item_context ) {
			if ( $item_context instanceof WPAE_Item_Context && '' !== $item_context->get_name() ) {
				$this->named_items[ $item_context->get_name() ] = $item_context->get_value();
			}
		}
	}

	public function clear_local_variables() {
		$this->local_variables = array();
	}

	public function to_array() {
		return array(
			'workflow'     => $this->workflow,
			'trigger'      => $this->trigger_data,
			'variables'    => $this->variables,
			'local'        => $this->local_variables,
			'current_item' => $this->current_item,
			'items'        => $this->named_items,
			'runtime'      => $this->runtime,
		);
	}

	public function resolve_path( $path ) {
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return null;
		}

		$segments = explode( '.', $path );
		$root     = array_shift( $segments );

		switch ( $root ) {
			case 'variables':
				$value = $this->variables;
				break;
			case 'local':
				$value = $this->local_variables;
				break;
			case 'trigger':
				$value = $this->trigger_data;
				break;
			case 'workflow':
				$value = $this->workflow;
				break;
			case 'current_item':
				$value = $this->current_item;
				break;
			case 'runtime':
				$value = $this->runtime;
				break;
			case 'items':
				$value = $this->named_items;
				break;
			default:
				return null;
		}

		foreach ( $segments as $segment ) {
			if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
				$value = $value[ $segment ];
				continue;
			}

			if ( is_object( $value ) && isset( $value->{$segment} ) ) {
				$value = $value->{$segment};
				continue;
			}

			return null;
		}

		return $value;
	}
}
