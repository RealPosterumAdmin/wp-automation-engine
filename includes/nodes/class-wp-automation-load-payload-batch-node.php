<?php

class WP_Automation_Load_Payload_Batch_Node implements WP_Automation_Node {

	public function get_type() {
		return 'load_payload_batch';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Загрузить часть JSON-пакета',
			'icon'   => 'database-import',
			'fields' => array(
				array(
					'name'  => 'payload_id',
					'label' => 'ID пакета',
					'type'  => 'text',
				),
				array(
					'name'  => 'source',
					'label' => 'Источник элементов',
					'type'  => 'text',
				),
				array(
					'name'  => 'offset',
					'label' => 'Смещение',
					'type'  => 'mixed',
				),
				array(
					'name'  => 'limit',
					'label' => 'Размер части',
					'type'  => 'mixed',
				),
				array(
					'name'  => 'store_in',
					'label' => 'Переменная для элементов',
					'type'  => 'text',
				),
				array(
					'name'  => 'meta_in',
					'label' => 'Переменная для метаданных',
					'type'  => 'text',
				),
				array(
					'name'    => 'on_complete',
					'label'   => 'После завершения',
					'type'    => 'select',
					'options' => array(
						array( 'value' => 'keep', 'label' => 'Оставить' ),
						array( 'value' => 'archive', 'label' => 'Архивировать' ),
						array( 'value' => 'delete', 'label' => 'Удалить' ),
					),
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config      = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$payload_id  = (string) $executor->resolve_value( $config['payload_id'] ?? '{{payload.id}}', $context );
		$source      = sanitize_text_field( (string) $executor->resolve_value( $config['source'] ?? '{{payload.source}}', $context ) );
		$offset      = max( 0, (int) $executor->resolve_value( $config['offset'] ?? '{{batch.next_offset}}', $context ) );
		$limit       = max( 1, (int) $executor->resolve_value( $config['limit'] ?? 50, $context ) );
		$store_in    = sanitize_key( (string) $executor->resolve_value( $config['store_in'] ?? 'payload_items', $context ) );
		$meta_in     = sanitize_key( (string) $executor->resolve_value( $config['meta_in'] ?? 'payload_batch', $context ) );
		$on_complete = sanitize_key( (string) $executor->resolve_value( $config['on_complete'] ?? 'keep', $context ) );
		$batch       = $executor->load_payload_batch( $payload_id, $offset, $limit, $source );

		if ( is_wp_error( $batch ) ) {
			$executor->log_node( $context, $node, $batch->get_error_message(), 'error' );
			return;
		}

		$context->set_variable( $store_in, $batch['items'], 'global' );
		$context->set_variable( $meta_in, $batch, 'global' );

		if ( ! $batch['has_more'] ) {
			if ( 'archive' === $on_complete ) {
				$executor->archive_payload( $payload_id );
			} elseif ( 'delete' === $on_complete ) {
				$executor->delete_payload( $payload_id );
			}
		}

		$executor->log_node(
			$context,
			$node,
			'Часть JSON-пакета загружена.',
			'success',
			array(
				'payload_id'  => $payload_id,
				'offset'      => $batch['offset'],
				'limit'       => $batch['limit'],
				'total'       => $batch['total'],
				'next_offset' => $batch['next_offset'],
				'has_more'    => $batch['has_more'],
			)
		);
	}
}
