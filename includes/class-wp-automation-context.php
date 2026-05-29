<?php

class WP_Automation_Context {

	protected $workflow;
	protected $trigger_data;
	protected $variables;
	protected $local_variables;
	protected $current_item;
	protected $runtime;

	public function __construct( array $workflow, array $trigger_data = array() ) {
		$this->workflow        = $workflow;
		$this->trigger_data    = $trigger_data;
		$this->variables       = isset( $workflow['variables'] ) && is_array( $workflow['variables'] ) ? $workflow['variables'] : array();
		$this->local_variables = array();
		$this->current_item    = null;
		$this->runtime         = array(
			'started_at' => current_time( 'mysql', true ),
			'workflow'   => $workflow['id'],
		);
	}

	public function get_workflow() {
		return $this->workflow;
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
