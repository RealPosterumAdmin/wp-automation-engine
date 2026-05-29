<?php

class WP_Automation_Node_Factory {

	protected $condition_evaluator;

	public function __construct( WP_Automation_Condition_Evaluator $condition_evaluator ) {
		$this->condition_evaluator = $condition_evaluator;
	}

	public function create( array $node ) {
		$type = isset( $node['type'] ) ? $node['type'] : '';

		switch ( $type ) {
			case 'set_variable':
				return new WP_Automation_Set_Variable_Node();
			case 'dispatch_event':
				return new WP_Automation_Dispatch_Event_Node();
			case 'action':
				return new WP_Automation_Action_Node();
			case 'if':
				return new WP_Automation_If_Node( $this->condition_evaluator );
			case 'loop':
				return new WP_Automation_Loop_Node();
			default:
				return null;
		}
	}

	public function get_schemas() {
		return array(
			array(
				'type'   => 'set_variable',
				'label'  => 'Set Variable',
				'icon'   => 'update',
				'fields' => array(
					array( 'name' => 'scope', 'type' => 'select', 'options' => array( 'global', 'local' ) ),
					array( 'name' => 'key', 'type' => 'text' ),
					array( 'name' => 'value', 'type' => 'mixed' ),
				),
			),
			array(
				'type'   => 'dispatch_event',
				'label'  => 'Dispatch Event',
				'icon'   => 'megaphone',
				'fields' => array(
					array( 'name' => 'event', 'type' => 'text' ),
					array( 'name' => 'payload', 'type' => 'object' ),
				),
			),
			array(
				'type'   => 'action',
				'label'  => 'Run WordPress Action',
				'icon'   => 'admin-generic',
				'fields' => array(
					array( 'name' => 'hook', 'type' => 'text' ),
					array( 'name' => 'payload', 'type' => 'object' ),
				),
			),
			array(
				'type'   => 'if',
				'label'  => 'Conditional Branch',
				'icon'   => 'randomize',
				'fields' => array(
					array( 'name' => 'condition', 'type' => 'condition' ),
					array( 'name' => 'on_true', 'type' => 'nodes' ),
					array( 'name' => 'on_false', 'type' => 'nodes' ),
				),
			),
			array(
				'type'   => 'loop',
				'label'  => 'Loop',
				'icon'   => 'backup',
				'fields' => array(
					array( 'name' => 'source', 'type' => 'path' ),
					array( 'name' => 'item_name', 'type' => 'text' ),
					array( 'name' => 'nodes', 'type' => 'nodes' ),
				),
			),
		);
	}
}
