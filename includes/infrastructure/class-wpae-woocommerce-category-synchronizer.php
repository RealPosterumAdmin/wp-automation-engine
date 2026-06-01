<?php

class WPAE_WooCommerce_Category_Synchronizer {

	public function synchronize( $ref_key, $name = 'Ошибка', $parent_ref_key = null ) {
		$ref_key        = sanitize_text_field( (string) $ref_key );
		$name           = sanitize_text_field( (string) $name );
		$parent_ref_key = null !== $parent_ref_key ? sanitize_text_field( (string) $parent_ref_key ) : null;

		if ( '' === $ref_key ) {
			return 0;
		}

		$term      = $this->find_by_ref_key( $ref_key );
		$parent_id = 0;

		if ( $parent_ref_key ) {
			$parent_term = $this->find_by_ref_key( $parent_ref_key );
			$parent_id   = $parent_term ? (int) $parent_term->term_id : 0;
		}

		$term_name = '' !== $name ? $name : 'Категория ' . substr( $ref_key, 0, 8 );

		if ( $term ) {
			$update_result = wp_update_term(
				$term->term_id,
				'product_cat',
				array(
					'name'   => $term_name,
					'parent' => $parent_id,
				)
			);

			if ( is_wp_error( $update_result ) ) {
				return (int) $term->term_id;
			}

			return (int) $term->term_id;
		}

		$insert_result = wp_insert_term(
			$term_name,
			'product_cat',
			array(
				'parent' => $parent_id,
			)
		);

		if ( is_wp_error( $insert_result ) ) {
			return 0;
		}

		$term_id = (int) ( $insert_result['term_id'] ?? 0 );

		if ( $term_id > 0 ) {
			update_term_meta( $term_id, '_1c_ref_key', $ref_key );
		}

		return $term_id;
	}

	protected function find_by_ref_key( $ref_key ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 1,
				'meta_query' => array(
					array(
						'key'     => '_1c_ref_key',
						'value'   => $ref_key,
						'compare' => '=',
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return $terms[0];
	}
}
