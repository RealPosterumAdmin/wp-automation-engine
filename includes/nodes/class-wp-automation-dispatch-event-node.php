<?php

class WP_Automation_Dispatch_Event_Node implements WP_Automation_Node {

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config  = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$event   = isset( $config['event'] ) ? sanitize_text_field( $config['event'] ) : '';
		$payload = $executor->resolve_value( $config['payload'] ?? array(), $context );

		if ( '' === $event ) {
			$executor->log_node( $context, $node, 'Event name is missing', 'error' );
			return;
		}

		do_action( 'wp_automation_engine_internal_event_' . $event, is_array( $payload ) ? $payload : array( 'value' => $payload ) );
		$executor->log_node( $context, $node, 'Internal event dispatched', 'success', array( 'event' => $event ) );
	}
}
