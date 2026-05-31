<?php

class WP_Automation_Dispatch_Event_Node implements WP_Automation_Node {

	public function get_type() {
		return 'dispatch_event';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Отправить событие',
			'icon'   => 'megaphone',
			'fields' => array(
				array(
					'name'  => 'event',
					'label' => 'Имя события',
					'type'  => 'text',
				),
				array(
					'name'  => 'payload',
					'label' => 'Данные',
					'type'  => 'object',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config  = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$event   = isset( $config['event'] ) ? sanitize_text_field( $config['event'] ) : '';
		$payload = $executor->resolve_value( $config['payload'] ?? array(), $context );

		if ( '' === $event ) {
			$executor->log_node( $context, $node, 'Не указано имя события.', 'error' );
			return;
		}

		$executor->dispatch_internal_event( $event, is_array( $payload ) ? $payload : array( 'value' => $payload ) );
		$executor->log_node( $context, $node, 'Внутреннее событие отправлено.', 'success', array( 'event' => $event ) );
	}
}
