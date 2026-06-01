<?php

class WP_Automation_Save_Payload_Node implements WP_Automation_Node {

	public function get_type() {
		return 'save_payload';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Сохранить JSON-пакет',
			'icon'   => 'media-code',
			'fields' => array(
				array(
					'name'  => 'value',
					'label' => 'Данные',
					'type'  => 'mixed',
				),
				array(
					'name'  => 'payload_key',
					'label' => 'Ключ пакета',
					'type'  => 'text',
				),
				array(
					'name'  => 'source',
					'label' => 'Источник элементов',
					'type'  => 'text',
				),
				array(
					'name'  => 'store_in',
					'label' => 'Сохранить ссылку в переменную',
					'type'  => 'text',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config      = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$value       = $executor->resolve_value( $config['value'] ?? null, $context );
		$payload_key = sanitize_key( (string) $executor->resolve_value( $config['payload_key'] ?? '', $context ) );
		$source      = sanitize_text_field( (string) $executor->resolve_value( $config['source'] ?? '', $context ) );
		$store_in    = sanitize_key( (string) $executor->resolve_value( $config['store_in'] ?? 'payload_ref', $context ) );
		$reference   = $executor->save_payload(
			$payload_key,
			$value,
			array(
				'source' => $source,
			)
		);

		if ( is_wp_error( $reference ) ) {
			$executor->log_node( $context, $node, $reference->get_error_message(), 'error' );
			return;
		}

		$context->merge_runtime(
			array(
				'payload' => $reference,
				'batch'   => array(
					'offset'      => 0,
					'limit'       => 0,
					'total'       => $reference['total_items'] ?? 0,
					'next_offset' => 0,
					'source'      => $reference['source'] ?? '',
				),
			)
		);

		if ( '' !== $store_in ) {
			$context->set_variable( $store_in, $reference, 'global' );
		}

		$executor->log_node(
			$context,
			$node,
			'JSON-пакет сохранен.',
			'success',
			array(
				'payload_id' => $reference['id'] ?? '',
				'store_in'   => $store_in,
				'source'     => $source,
			)
		);
	}
}
