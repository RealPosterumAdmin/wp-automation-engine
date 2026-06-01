<?php

class WP_Automation_Export_WooCommerce_Products_Node implements WP_Automation_Node {

	protected $exporter;

	public function __construct( WPAE_Export_WooCommerce_Products $exporter ) {
		$this->exporter = $exporter;
	}

	public function get_type() {
		return 'export_woocommerce_products';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'WooCommerce: экспортировать товары',
			'icon'   => 'database-export',
			'fields' => array(
				array(
					'name'    => 'target',
					'label'   => 'Куда сохранить',
					'type'    => 'select',
					'options' => array(
						array( 'value' => 'payload', 'label' => 'JSON-пакет' ),
						array( 'value' => 'variable', 'label' => 'Переменную' ),
					),
				),
				array(
					'name'  => 'target_key',
					'label' => 'Ключ назначения',
					'type'  => 'text',
				),
				array(
					'name'  => 'store_in',
					'label' => 'Сохранить результат в переменную',
					'type'  => 'text',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config     = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$target     = sanitize_key( (string) $executor->resolve_value( $config['target'] ?? 'payload', $context ) );
		$target_key = sanitize_key( (string) $executor->resolve_value( $config['target_key'] ?? 'woo_products_export', $context ) );
		$store_in   = sanitize_key( (string) $executor->resolve_value( $config['store_in'] ?? 'woo_products_export', $context ) );
		$result     = $this->exporter->export();

		if ( is_wp_error( $result ) ) {
			$executor->log_node( $context, $node, $result->get_error_message(), 'error' );
			return;
		}

		if ( 'variable' === $target ) {
			$context->set_variable( $target_key, $result, 'global' );
			if ( '' !== $store_in && $store_in !== $target_key ) {
				$context->set_variable( $store_in, $result, 'global' );
			}
			$executor->log_node( $context, $node, 'Товары WooCommerce экспортированы в переменную.', 'success', array( 'count' => count( $result ), 'target_key' => $target_key ) );
			return;
		}

		$reference = $executor->save_payload(
			$target_key,
			$result,
			array(
				'source'      => '',
				'integration' => 'woocommerce',
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
					'total'       => $reference['total_items'] ?? count( $result ),
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
			'Товары WooCommerce экспортированы в JSON-пакет.',
			'success',
			array(
				'count'      => count( $result ),
				'payload_id' => $reference['id'] ?? '',
				'store_in'   => $store_in,
			)
		);
	}
}
