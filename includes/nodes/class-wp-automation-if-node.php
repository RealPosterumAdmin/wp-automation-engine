<?php

class WP_Automation_If_Node implements WP_Automation_Node {

	protected $condition_evaluator;

	public function __construct( WP_Automation_Condition_Evaluator $condition_evaluator ) {
		$this->condition_evaluator = $condition_evaluator;
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config        = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$condition     = isset( $config['condition'] ) && is_array( $config['condition'] ) ? $config['condition'] : array();
		$condition_met = $this->condition_evaluator->evaluate( $condition, $context );
		$branch        = $condition_met ? ( $config['on_true'] ?? array() ) : ( $config['on_false'] ?? array() );

		$executor->log_node( $context, $node, $condition_met ? 'Condition matched' : 'Condition did not match' );
		$executor->execute_nodes( is_array( $branch ) ? $branch : array(), $context );
	}
}
