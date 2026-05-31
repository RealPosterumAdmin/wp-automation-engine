<?php

class WP_Automation_Loop_Node implements WP_Automation_Node {

	public function get_type() {
		return 'loop';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Loop',
			'icon'   => 'backup',
			'fields' => array(
				array( 'name' => 'source', 'type' => 'path' ),
				array( 'name' => 'item_name', 'type' => 'text' ),
				array( 'name' => 'nodes', 'type' => 'nodes' ),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config      = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$source      = isset( $config['source'] ) ? $config['source'] : '';
		$items       = $context->resolve_path( $source );
		$child_nodes = isset( $config['nodes'] ) && is_array( $config['nodes'] ) ? $config['nodes'] : array();

		if ( ! is_array( $items ) ) {
			$executor->log_node( $context, $node, 'Loop source is not iterable', 'error', array( 'source' => $source ) );
			return;
		}

		foreach ( $items as $item ) {
			$context->set_current_item( $item );
			$context->clear_local_variables();
			$executor->execute_nodes( $child_nodes, $context );
		}

		$context->set_current_item( null );
		$context->clear_local_variables();
		$executor->log_node( $context, $node, 'Loop completed', 'success', array( 'iterations' => count( $items ) ) );
	}
}
