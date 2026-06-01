<?php

class WP_Automation_Woo_Update_Product_Node extends WP_Automation_Abstract_Entity_Node {

	public function get_type() {
		return 'woo_update_product';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'WooCommerce: обновить товар',
			'icon'   => 'products',
			'fields' => array(
				array(
					'name'  => 'product_id',
					'label' => 'ID товара',
					'type'  => 'mixed',
				),
				array(
					'name'  => 'product',
					'label' => 'Данные товара',
					'type'  => 'object',
				),
				array(
					'name'  => 'meta',
					'label' => 'Метаполя',
					'type'  => 'object',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			$executor->log_node( $context, $node, 'WooCommerce недоступен.', 'error' );
			return;
		}

		$config     = $this->get_config( $node );
		$product_id = (int) $this->resolve_value_from_config( $config, 'product_id', $context, $executor, 0 );
		$data       = $this->resolve_array_from_config( $config, 'product', $context, $executor );
		$meta       = $this->resolve_array_from_config( $config, 'meta', $context, $executor );
		$product    = $product_id > 0 ? wc_get_product( $product_id ) : null;

		if ( ! $product ) {
			$executor->log_node( $context, $node, 'Не найден товар WooCommerce для обновления.', 'error' );
			return;
		}

		$this->apply_product_data( $product, $data );
		$product->save();
		$this->update_meta_fields( $product_id, $meta, 'update_post_meta', 'delete_post_meta' );
		$executor->log_node( $context, $node, 'Товар WooCommerce обновлен.', 'success', array( 'product_id' => $product_id ) );
	}

	protected function apply_product_data( WC_Product $product, array $data ) {
		$map = array(
			'name'              => 'set_name',
			'slug'              => 'set_slug',
			'status'            => 'set_status',
			'sku'               => 'set_sku',
			'regular_price'     => 'set_regular_price',
			'sale_price'        => 'set_sale_price',
			'description'       => 'set_description',
			'short_description' => 'set_short_description',
			'catalog_visibility'=> 'set_catalog_visibility',
			'stock_status'      => 'set_stock_status',
			'featured'          => 'set_featured',
			'virtual'           => 'set_virtual',
			'downloadable'      => 'set_downloadable',
		);

		foreach ( $map as $key => $method ) {
			if ( array_key_exists( $key, $data ) && method_exists( $product, $method ) ) {
				$product->{$method}( $data[ $key ] );
			}
		}

		if ( array_key_exists( 'manage_stock', $data ) && method_exists( $product, 'set_manage_stock' ) ) {
			$product->set_manage_stock( (bool) $data['manage_stock'] );
		}

		if ( array_key_exists( 'stock_quantity', $data ) && method_exists( $product, 'set_stock_quantity' ) ) {
			$product->set_stock_quantity( (int) $data['stock_quantity'] );
		}
	}
}
