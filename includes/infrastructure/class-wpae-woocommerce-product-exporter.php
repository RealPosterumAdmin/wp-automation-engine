<?php

class WPAE_WooCommerce_Product_Exporter {

	public function export( array $options = array() ) {
		if ( ! post_type_exists( 'product' ) ) {
			return new WP_Error( 'woocommerce_products_unavailable', __( 'Тип записей product недоступен.', 'wp-automation-engine' ) );
		}

		$post_status = $options['post_status'] ?? array( 'publish', 'draft', 'pending', 'trash' );

		if ( is_string( $post_status ) ) {
			$post_status = array_filter( array_map( 'trim', explode( ',', $post_status ) ) );
		}

		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'post_status'    => is_array( $post_status ) && ! empty( $post_status ) ? $post_status : array( 'publish', 'draft', 'pending', 'trash' ),
				'fields'         => 'ids',
			)
		);
		$out   = array();

		foreach ( $posts as $id ) {
			$out[] = array(
				'id'           => (int) $id,
				'title'        => get_the_title( $id ),
				'sku'          => get_post_meta( $id, '_sku', true ),
				'product_guid' => get_post_meta( $id, 'product_guid', true ),
				'price'        => get_post_meta( $id, '_regular_price', true ),
			);
		}

		return $out;
	}
}
