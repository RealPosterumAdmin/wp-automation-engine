<?php

class WP_Automation_Schedule_Workflow_Node implements WP_Automation_Node {

	public function get_type() {
		return 'schedule_workflow';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Отложить обработку',
			'icon'   => 'clock',
			'fields' => array(
				array(
					'name'    => 'mode',
					'label'   => 'Режим',
					'type'    => 'select',
					'options' => array(
						array( 'value' => 'cron', 'label' => 'Запуск сценария по cron' ),
						array( 'value' => 'event', 'label' => 'Внутреннее событие' ),
					),
				),
				array(
					'name'  => 'workflow_id',
					'label' => 'Сценарий',
					'type'  => 'text',
				),
				array(
					'name'  => 'event_name',
					'label' => 'Имя события',
					'type'  => 'text',
				),
				array(
					'name'  => 'delay_seconds',
					'label' => 'Задержка в секундах',
					'type'  => 'mixed',
				),
				array(
					'name'  => 'payload_id',
					'label' => 'ID пакета',
					'type'  => 'text',
				),
				array(
					'name'  => 'payload_source',
					'label' => 'Источник элементов',
					'type'  => 'text',
				),
				array(
					'name'  => 'offset',
					'label' => 'Следующее смещение',
					'type'  => 'mixed',
				),
				array(
					'name'  => 'limit',
					'label' => 'Размер части',
					'type'  => 'mixed',
				),
				array(
					'name'  => 'trigger_data',
					'label' => 'Дополнительные данные',
					'type'  => 'object',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config         = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$mode           = sanitize_key( (string) $executor->resolve_value( $config['mode'] ?? 'cron', $context ) );
		$workflow_id    = sanitize_key( (string) $executor->resolve_value( $config['workflow_id'] ?? '', $context ) );
		$event_name     = sanitize_text_field( (string) $executor->resolve_value( $config['event_name'] ?? '', $context ) );
		$delay_seconds  = max( 0, (int) $executor->resolve_value( $config['delay_seconds'] ?? 60, $context ) );
		$payload_id     = (string) $executor->resolve_value( $config['payload_id'] ?? '{{payload.id}}', $context );
		$payload_source = sanitize_text_field( (string) $executor->resolve_value( $config['payload_source'] ?? '{{payload.source}}', $context ) );
		$offset         = max( 0, (int) $executor->resolve_value( $config['offset'] ?? '{{batch.next_offset}}', $context ) );
		$limit          = max( 1, (int) $executor->resolve_value( $config['limit'] ?? '{{batch.limit}}', $context ) );
		$trigger_data   = $executor->resolve_value( $config['trigger_data'] ?? array(), $context );
		$trigger_data   = is_array( $trigger_data ) ? $trigger_data : array();

		if ( '' !== $payload_id ) {
			$trigger_data['payload'] = array(
				'id'     => $payload_id,
				'source' => $payload_source,
			);
			$trigger_data['batch'] = array(
				'offset' => $offset,
				'limit'  => $limit,
				'source' => $payload_source,
			);
		}

		if ( 'event' === $mode ) {
			if ( '' === $event_name ) {
				$executor->log_node( $context, $node, 'Не указано имя внутреннего события.', 'error' );
				return;
			}

			$executor->dispatch_internal_event( $event_name, $trigger_data );
			$executor->log_node( $context, $node, 'Внутреннее событие для обработки отправлено.', 'success', array( 'event' => $event_name ) );
			return;
		}

		$result = $executor->schedule_workflow( $workflow_id, $trigger_data, $delay_seconds );

		if ( is_wp_error( $result ) ) {
			$executor->log_node( $context, $node, $result->get_error_message(), 'error' );
			return;
		}

		$executor->log_node(
			$context,
			$node,
			'Сценарий поставлен в отложенную обработку.',
			'success',
			array(
				'workflow_id'   => $workflow_id,
				'delay_seconds' => $delay_seconds,
				'payload_id'    => $payload_id,
				'offset'        => $offset,
				'limit'         => $limit,
			)
		);
	}
}
