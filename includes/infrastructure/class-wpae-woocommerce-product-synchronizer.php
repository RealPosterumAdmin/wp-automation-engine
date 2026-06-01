<?php

class WPAE_WooCommerce_Product_Synchronizer {

	public function synchronize( array $product_data, array $options = array() ) {
		if ( ! class_exists( 'WC_Product' ) || ! class_exists( 'WC_Product_Simple' ) || ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error( 'woocommerce_unavailable', __( 'WooCommerce недоступен.', 'wp-automation-engine' ) );
		}

		$name    = sanitize_text_field( (string) ( $product_data['Description'] ?? '' ) );
		$ref_key = sanitize_text_field( (string) ( $product_data['Ref_Key'] ?? '' ) );

		if ( '' === $name ) {
			return new WP_Error( 'onec_product_name_missing', __( 'У товара из 1С отсутствует название.', 'wp-automation-engine' ) );
		}

		if ( '' === $ref_key ) {
			return new WP_Error( 'onec_product_guid_missing', __( 'У товара из 1С отсутствует GUID.', 'wp-automation-engine' ) );
		}

		$product_id  = $this->find_product_id_by_guid( $ref_key );
		$product     = $product_id > 0 ? wc_get_product( $product_id ) : null;
		$is_new      = ! $product;
		$product     = $product ? $product : new WC_Product_Simple();
		$update_name = $this->resolve_boolean_option( $options, 'update_name_on_existing', false );
		$sync_cat    = $this->resolve_boolean_option( $options, 'sync_category_on_update', false );

		if ( $is_new || $update_name ) {
			$product->set_name( $name );
		}

		if ( $is_new ) {
			$product->set_status( 'draft' );
		}

		$category_id = $this->resolve_category_id( $product_data['Parent_Key'] ?? '' );

		if ( $category_id > 0 && ( $is_new || $sync_cat ) ) {
			$product->set_category_ids( array( $category_id ) );
		}

		$product->set_sku( '' );

		if ( ! empty( $product_data['Артикул'] ) ) {
			$product->set_sku( (string) $product_data['Артикул'] );
		}

		if ( isset( $product_data['ЦенаПродажи'] ) && '' !== (string) $product_data['ЦенаПродажи'] ) {
			$price = (string) str_replace( ',', '.', (string) $product_data['ЦенаПродажи'] );
			$product->set_regular_price( $price );
			$product->set_price( $price );
		}

		if ( isset( $product_data['Остаток'] ) && '' !== (string) $product_data['Остаток'] ) {
			$stock = (float) str_replace( ',', '.', (string) $product_data['Остаток'] );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $stock );
		}

		$saved_id = (int) $product->save();

		if ( $saved_id <= 0 ) {
			return new WP_Error( 'onec_product_save_failed', __( 'Не удалось сохранить товар WooCommerce.', 'wp-automation-engine' ) );
		}

		update_post_meta( $saved_id, 'product_guid', $ref_key );

		if ( array_key_exists( 'ЕдиницаИзмерения', $product_data ) ) {
			$unit = $product_data['ЕдиницаИзмерения'];

			if ( null === $unit || '' === (string) $unit ) {
				delete_post_meta( $saved_id, 'unit_data' );
			} else {
				update_post_meta( $saved_id, 'unit_data', $unit );
			}
		}

		if ( ! empty( $product_data['Code'] ) ) {
			$this->sync_product_code_attribute( $saved_id, $product, (string) $product_data['Code'] );
		}

		return array(
			'product_id' => $saved_id,
			'created'    => $is_new,
			'ref_key'    => $ref_key,
		);
	}

	protected function find_product_id_by_guid( $ref_key ) {
		$found = get_posts(
			array(
				'post_type'   => 'product',
				'meta_key'    => 'product_guid',
				'meta_value'  => $ref_key,
				'fields'      => 'ids',
				'numberposts' => 1,
				'post_status' => array( 'publish', 'draft', 'trash', 'pending' ),
			)
		);

		return ! empty( $found ) ? (int) $found[0] : 0;
	}

	protected function resolve_category_id( $parent_ref_key ) {
		$parent_ref_key = sanitize_text_field( (string) $parent_ref_key );

		if ( '' === $parent_ref_key ) {
			return 0;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 1,
				'meta_query' => array(
					array(
						'key'     => '_1c_ref_key',
						'value'   => $parent_ref_key,
						'compare' => '=',
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}

		return (int) $terms[0]->term_id;
	}

	protected function sync_product_code_attribute( $product_id, WC_Product $product, $code ) {
		$taxonomy = 'pa_product_code';
		$code     = trim( (string) $code );

		if ( '' === $code || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$term = term_exists( $code, $taxonomy );

		if ( ! $term ) {
			$term = wp_insert_term( $code, $taxonomy );
		}

		if ( is_wp_error( $term ) ) {
			return;
		}

		$term_id       = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
		$current_terms = wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );

		if ( ! is_wp_error( $current_terms ) && ! in_array( $term_id, $current_terms, true ) ) {
			wp_set_object_terms( $product_id, $term_id, $taxonomy, true );
		}

		$attributes = (array) $product->get_attributes();

		if ( array_key_exists( $taxonomy, $attributes ) ) {
			return;
		}

		$attribute = new WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( array( $term_id ) );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		$attributes[ $taxonomy ] = $attribute;
		$product->set_attributes( $attributes );
		$product->save();
	}

	protected function resolve_boolean_option( array $options, $key, $default ) {
		if ( ! array_key_exists( $key, $options ) ) {
			return (bool) $default;
		}

		$value = $options[ $key ];

		if ( is_bool( $value ) ) {
			return $value;
		}

		return ! in_array( strtolower( (string) $value ), array( '0', 'false', 'no', 'off' ), true );
	}
}
