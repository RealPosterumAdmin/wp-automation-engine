<?php

class WP_Automation_Sync_OneC_Product_Node extends WP_Automation_Abstract_Entity_Node {

	protected $synchronizer;

	public function __construct( WPAE_Sync_OneC_Product $synchronizer ) {
		$this->synchronizer = $synchronizer;
	}

	public function get_type() {
		return 'sync_onec_product';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => '1С: синхронизировать товар WooCommerce',
			'icon'   => 'products',
			'fields' => array(
				array(
					'name'  => 'source',
					'label' => 'Источник товара',
					'type'  => 'path',
				),
				array(
					'name'    => 'update_name_on_existing',
					'label'   => 'Обновлять название существующего товара',
					'type'    => 'select',
					'options' => array(
						array( 'value' => 'no', 'label' => 'Нет' ),
						array( 'value' => 'yes', 'label' => 'Да' ),
					),
				),
				array(
					'name'    => 'sync_category_on_update',
					'label'   => 'Обновлять категорию у существующего товара',
					'type'    => 'select',
					'options' => array(
						array( 'value' => 'no', 'label' => 'Нет' ),
						array( 'value' => 'yes', 'label' => 'Да' ),
					),
				),
				array(
					'name'  => 'store_in',
					'label' => 'Сохранить ID товара в переменную',
					'type'  => 'text',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config      = $this->get_config( $node );
		$source      = (string) $this->resolve_value_from_config( $config, 'source', $context, $executor, 'current_item' );
		$product     = $context->resolve_path( $source );
		$store_in    = sanitize_key( (string) $this->resolve_value_from_config( $config, 'store_in', $context, $executor, 'synced_product_id' ) );
		$result      = $this->synchronizer->sync(
			is_array( $product ) ? $product : array(),
			array(
				'update_name_on_existing' => (string) $this->resolve_value_from_config( $config, 'update_name_on_existing', $context, $executor, 'no' ),
				'sync_category_on_update' => (string) $this->resolve_value_from_config( $config, 'sync_category_on_update', $context, $executor, 'no' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$executor->log_node(
				$context,
				$node,
				$result->get_error_message(),
				'error',
				array(
					'source'  => $source,
					'ref_key' => is_array( $product ) ? ( $product['Ref_Key'] ?? '' ) : '',
				)
			);
			return;
		}

		$this->store_value( $context, $store_in, (int) $result['product_id'] );
		$executor->log_node(
			$context,
			$node,
			! empty( $result['created'] ) ? 'Товар WooCommerce создан из данных 1С.' : 'Товар WooCommerce обновлен из данных 1С.',
			'success',
			array(
				'product_id' => (int) $result['product_id'],
				'ref_key'    => $result['ref_key'] ?? '',
				'store_in'   => $store_in,
			)
		);
	}
}
