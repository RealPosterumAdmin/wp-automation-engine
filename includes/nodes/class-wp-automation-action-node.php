<?php

class WP_Automation_Action_Node implements WP_Automation_Node {

	public function get_type() {
		return 'action';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Run WordPress Action',
			'icon'   => 'admin-generic',
			'fields' => array(
				array( 'name' => 'hook', 'type' => 'text' ),
				array( 'name' => 'payload', 'type' => 'object' ),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config  = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$hook    = isset( $config['hook'] ) ? sanitize_text_field( $config['hook'] ) : '';
		$payload = $executor->resolve_value( $config['payload'] ?? array(), $context );

		if ( '' === $hook ) {
			$executor->log_node( $context, $node, 'Action hook is missing', 'error' );
			return;
		}

		do_action( $hook, $payload, $context->to_array() );
		$executor->log_node( $context, $node, 'WordPress action dispatched', 'success', array( 'hook' => $hook ) );
	}
}
