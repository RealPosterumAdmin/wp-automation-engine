<?php

class WPAE_Workflow {

	protected $id;
	protected $name;
	protected $enabled;
	protected $trigger;
	protected $variables;
	protected $nodes;

	public function __construct( $id, $name, $enabled, WPAE_Trigger_Definition $trigger, array $variables, array $nodes ) {
		$this->id        = (string) $id;
		$this->name      = (string) $name;
		$this->enabled   = (bool) $enabled;
		$this->trigger   = $trigger;
		$this->variables = $variables;
		$this->nodes     = $nodes;
	}

	public static function from_array( array $workflow ) {
		return new self(
			$workflow['id'] ?? '',
			$workflow['name'] ?? '',
			! empty( $workflow['enabled'] ),
			WPAE_Trigger_Definition::from_array( $workflow['trigger'] ?? array() ),
			isset( $workflow['variables'] ) && is_array( $workflow['variables'] ) ? $workflow['variables'] : array(),
			isset( $workflow['nodes'] ) && is_array( $workflow['nodes'] ) ? $workflow['nodes'] : array()
		);
	}

	public function get_id() {
		return $this->id;
	}

	public function get_name() {
		return $this->name;
	}

	public function is_enabled() {
		return $this->enabled;
	}

	public function get_trigger() {
		return $this->trigger;
	}

	public function get_variables() {
		return $this->variables;
	}

	public function get_nodes() {
		return $this->nodes;
	}

	public function to_array() {
		return array(
			'id'        => $this->id,
			'name'      => $this->name,
			'enabled'   => $this->enabled,
			'trigger'   => $this->trigger->to_array(),
			'variables' => $this->variables,
			'nodes'     => $this->nodes,
		);
	}
}
