<?php

class WP_Automation_Node_Factory {

	protected $condition_evaluator;
	protected $nodes = array();

	public function __construct( WP_Automation_Condition_Evaluator $condition_evaluator ) {
		$this->condition_evaluator = $condition_evaluator;
		$this->register_builtin_nodes();
	}

	public function register( WP_Automation_Node $node ) {
		$type = $node->get_type();

		if ( '' === $type ) {
			return $this;
		}

		$this->nodes[ $type ] = $node;

		return $this;
	}

	public function create( array $node ) {
		$type = isset( $node['type'] ) ? $node['type'] : '';

		if ( ! isset( $this->nodes[ $type ] ) ) {
			return null;
		}

		return clone $this->nodes[ $type ];
	}

	public function get_schemas() {
		$schemas = array();

		foreach ( $this->nodes as $node ) {
			$schemas[] = $node->get_schema();
		}

		return $schemas;
	}

	protected function register_builtin_nodes() {
		$this->register( new WP_Automation_Set_Variable_Node() );
		$this->register( new WP_Automation_Dispatch_Event_Node() );
		$this->register( new WP_Automation_Action_Node() );
		$this->register( new WP_Automation_If_Node( $this->condition_evaluator ) );
		$this->register( new WP_Automation_Loop_Node() );
	}
}
