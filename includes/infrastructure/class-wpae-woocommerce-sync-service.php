<?php

class WPAE_WooCommerce_Sync_Service {

	const PRODUCT_GUID_META_KEY  = '_wpae_product_guid';
	const PRODUCT_UNIT_META_KEY  = '_wpae_unit';
	const CATEGORY_GUID_META_KEY = '_wpae_category_guid';
	const CATEGORY_PARENT_META_KEY = '_wpae_parent_guid';

	protected $tree_builder;

	public function __construct( WPAE_Catalog_Tree_Builder $tree_builder = null ) {
		$this->tree_builder = $tree_builder ? $tree_builder : new WPAE_Catalog_Tree_Builder();
	}

	public function is_available() {
		return class_exists( 'WC_Product_Simple' ) && taxonomy_exists( 'product_cat' );
	}

	public function sync_categories( array $items, array $config = array(), callable $logger = null ) {
		if ( ! $this->is_available() ) {
			return new WP_Error( 'wpae_woocommerce_missing', __( 'WooCommerce недоступен. Синхронизация категорий невозможна.', 'wp-automation-engine' ) );
		}

		$guid_key        = isset( $config['guid_key'] ) ? (string) $config['guid_key'] : 'Ref_Key';
		$parent_guid_key = isset( $config['parent_guid_key'] ) ? (string) $config['parent_guid_key'] : 'Parent_Key';
		$name_key        = isset( $config['name_key'] ) ? (string) $config['name_key'] : 'Description';
		$children_key    = isset( $config['children_key'] ) ? (string) $config['children_key'] : 'children';
		$guid_meta_key   = isset( $config['guid_meta_key'] ) ? (string) $config['guid_meta_key'] : self::CATEGORY_GUID_META_KEY;

		$tree_data = $this->input_has_children( $items, $children_key )
			? array(
				'tree'  => $items,
				'stats' => array(
					'total'      => count( $items ),
					'roots'      => count( $items ),
					'duplicates' => 0,
					'invalid'    => 0,
					'orphans'    => 0,
				),
			)
			: $this->tree_builder->build_tree( $items, $guid_key, $parent_guid_key, $children_key );

		$summary = array(
			'created'    => 0,
			'updated'    => 0,
			'skipped'    => 0,
			'errors'     => 0,
			'guid_map'   => array(),
			'tree_stats' => $tree_data['stats'],
		);

		foreach ( $tree_data['tree'] as $item ) {
			$this->sync_category_branch( $item, 0, $summary, $guid_key, $parent_guid_key, $name_key, $children_key, $guid_meta_key, $logger );
		}

		return $summary;
	}

	public function sync_product( array $item, array $config = array() ) {
		if ( ! $this->is_available() ) {
			return new WP_Error( 'wpae_woocommerce_missing', __( 'WooCommerce недоступен. Синхронизация товаров невозможна.', 'wp-automation-engine' ) );
		}

		$guid_key          = isset( $config['guid_key'] ) ? (string) $config['guid_key'] : 'Ref_Key';
		$sku_key           = isset( $config['sku_key'] ) ? (string) $config['sku_key'] : 'SKU';
		$name_key          = isset( $config['name_key'] ) ? (string) $config['name_key'] : 'Description';
		$price_key         = isset( $config['price_key'] ) ? (string) $config['price_key'] : 'Price';
		$stock_key         = isset( $config['stock_key'] ) ? (string) $config['stock_key'] : 'Quantity';
		$unit_key          = isset( $config['unit_key'] ) ? (string) $config['unit_key'] : 'Unit';
		$description_key   = isset( $config['description_key'] ) ? (string) $config['description_key'] : 'DescriptionFull';
		$category_guid_key = isset( $config['category_guid_key'] ) ? (string) $config['category_guid_key'] : 'Category_Key';
		$guid_meta_key     = isset( $config['guid_meta_key'] ) ? (string) $config['guid_meta_key'] : self::PRODUCT_GUID_META_KEY;
		$unit_meta_key     = isset( $config['unit_meta_key'] ) ? (string) $config['unit_meta_key'] : self::PRODUCT_UNIT_META_KEY;
		$status            = isset( $config['status'] ) ? (string) $config['status'] : 'publish';

		$guid = isset( $item[ $guid_key ] ) ? trim( (string) $item[ $guid_key ] ) : '';

		if ( '' === $guid ) {
			return new WP_Error( 'wpae_product_guid_missing', __( 'У товара отсутствует GUID.', 'wp-automation-engine' ) );
		}

		$sku                = isset( $item[ $sku_key ] ) ? trim( (string) $item[ $sku_key ] ) : '';
		$existing_product_id = $this->find_product_id_by_guid( $guid, $guid_meta_key );
		$product_id_by_sku   = '' !== $sku && function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( $sku ) : 0;

		if ( is_wp_error( $existing_product_id ) ) {
			return $existing_product_id;
		}

		if ( $existing_product_id && $product_id_by_sku && $existing_product_id !== $product_id_by_sku ) {
			return new WP_Error( 'wpae_product_duplicate', __( 'Найден конфликт между GUID товара и существующим SKU.', 'wp-automation-engine' ) );
		}

		$product_id = $existing_product_id ? $existing_product_id : $product_id_by_sku;
		$product    = $product_id ? wc_get_product( $product_id ) : new WC_Product_Simple();

		if ( ! $product ) {
			return new WP_Error( 'wpae_product_load_failed', __( 'Не удалось подготовить объект товара WooCommerce.', 'wp-automation-engine' ) );
		}

		$name = isset( $item[ $name_key ] ) ? trim( (string) $item[ $name_key ] ) : '';

		$product->set_name( '' !== $name ? $name : $guid );
		$product->set_status( in_array( $status, array( 'publish', 'draft', 'private' ), true ) ? $status : 'publish' );

		if ( '' !== $sku ) {
			$product->set_sku( $sku );
		}

		$price = $this->normalize_decimal( $item[ $price_key ] ?? null );

		if ( null !== $price ) {
			$product->set_regular_price( (string) $price );
			$product->set_price( (string) $price );
		}

		$stock = $this->normalize_decimal( $item[ $stock_key ] ?? null );

		if ( null !== $stock ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $stock );
			$product->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );
		}

		$description = isset( $item[ $description_key ] ) ? (string) $item[ $description_key ] : '';

		if ( '' !== $description ) {
			$product->set_description( $description );
		}

		$product->update_meta_data( $guid_meta_key, $guid );
		$product->update_meta_data( $unit_meta_key, isset( $item[ $unit_key ] ) ? (string) $item[ $unit_key ] : '' );

		$category_guids = $this->normalize_guid_list( $item[ $category_guid_key ] ?? array() );
		$category_ids   = $this->find_category_ids_by_guids( $category_guids );
		$product->set_category_ids( $category_ids );

		$product_id = $product->save();

		if ( ! $product_id ) {
			return new WP_Error( 'wpae_product_save_failed', __( 'Не удалось сохранить товар WooCommerce.', 'wp-automation-engine' ) );
		}

		return array(
			'action'     => $existing_product_id || $product_id_by_sku ? 'updated' : 'created',
			'product_id' => $product_id,
			'guid'       => $guid,
			'sku'        => $sku,
		);
	}

	protected function sync_category_branch( array $item, $parent_term_id, array &$summary, $guid_key, $parent_guid_key, $name_key, $children_key, $guid_meta_key, callable $logger = null ) {
		$guid = isset( $item[ $guid_key ] ) ? trim( (string) $item[ $guid_key ] ) : '';
		$name = isset( $item[ $name_key ] ) ? trim( (string) $item[ $name_key ] ) : '';

		if ( '' === $guid || '' === $name ) {
			++$summary['skipped'];
			if ( $logger ) {
				call_user_func( $logger, 'Пропущена категория без GUID или названия.', 'skipped', array( 'item' => $item ) );
			}
			return;
		}

		$term_id = $this->find_category_id_by_guid( $guid, $guid_meta_key );

		if ( is_wp_error( $term_id ) ) {
			++$summary['errors'];
			if ( $logger ) {
				call_user_func( $logger, $term_id->get_error_message(), 'error', array( 'guid' => $guid ) );
			}
			return;
		}

		if ( $term_id ) {
			$result = wp_update_term(
				$term_id,
				'product_cat',
				array(
					'name'   => $name,
					'parent' => (int) $parent_term_id,
				)
			);

			if ( is_wp_error( $result ) ) {
				++$summary['errors'];
				if ( $logger ) {
					call_user_func( $logger, $result->get_error_message(), 'error', array( 'guid' => $guid ) );
				}
				return;
			}

			++$summary['updated'];
		} else {
			$result = wp_insert_term(
				$name,
				'product_cat',
				array(
					'parent' => (int) $parent_term_id,
				)
			);

			if ( is_wp_error( $result ) ) {
				++$summary['errors'];
				if ( $logger ) {
					call_user_func( $logger, $result->get_error_message(), 'error', array( 'guid' => $guid ) );
				}
				return;
			}

			$term_id = (int) $result['term_id'];
			++$summary['created'];
		}

		update_term_meta( $term_id, $guid_meta_key, $guid );
		update_term_meta( $term_id, self::CATEGORY_PARENT_META_KEY, isset( $item[ $parent_guid_key ] ) ? (string) $item[ $parent_guid_key ] : '' );
		$summary['guid_map'][ $guid ] = $term_id;

		foreach ( $item[ $children_key ] ?? array() as $child_item ) {
			if ( ! is_array( $child_item ) ) {
				continue;
			}

			$this->sync_category_branch( $child_item, $term_id, $summary, $guid_key, $parent_guid_key, $name_key, $children_key, $guid_meta_key, $logger );
		}
	}

	protected function input_has_children( array $items, $children_key ) {
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item[ $children_key ] ) ) {
				return true;
			}
		}

		return false;
	}

	protected function find_category_id_by_guid( $guid, $meta_key ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 2,
				'meta_query' => array(
					array(
						'key'   => $meta_key,
						'value' => $guid,
					),
				),
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		if ( count( $terms ) > 1 ) {
			return new WP_Error( 'wpae_category_duplicate_guid', __( 'Найдено несколько категорий WooCommerce с одинаковым GUID.', 'wp-automation-engine' ) );
		}

		return empty( $terms ) ? 0 : (int) $terms[0]->term_id;
	}

	protected function find_product_id_by_guid( $guid, $meta_key ) {
		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 2,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => $meta_key,
						'value' => $guid,
					),
				),
			)
		);

		if ( count( $posts ) > 1 ) {
			return new WP_Error( 'wpae_product_duplicate_guid', __( 'Найдено несколько товаров WooCommerce с одинаковым GUID.', 'wp-automation-engine' ) );
		}

		return empty( $posts ) ? 0 : (int) $posts[0];
	}

	protected function find_category_ids_by_guids( array $guids ) {
		$term_ids = array();

		foreach ( $guids as $guid ) {
			$term_id = $this->find_category_id_by_guid( $guid, self::CATEGORY_GUID_META_KEY );

			if ( is_wp_error( $term_id ) || ! $term_id ) {
				continue;
			}

			$term_ids[] = $term_id;
		}

		return array_values( array_unique( array_map( 'intval', $term_ids ) ) );
	}

	protected function normalize_guid_list( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,;]+/', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $item ) {
						return trim( (string) $item );
					},
					$value
				)
			)
		);
	}

	protected function normalize_decimal( $value ) {
		if ( '' === $value || null === $value ) {
			return null;
		}

		if ( is_string( $value ) ) {
			$value = str_replace( ',', '.', $value );
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return 0 + $value;
	}
}
