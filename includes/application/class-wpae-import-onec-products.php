<?php

class WPAE_Import_OneC_Products {

	protected $client;
	protected $category_synchronizer;
	protected $payload_storage;

	public function __construct( WPAE_OneC_OData_Client $client, WPAE_WooCommerce_Category_Synchronizer $category_synchronizer, WPAE_Payload_Storage_Interface $payload_storage ) {
		$this->client                = $client;
		$this->category_synchronizer = $category_synchronizer;
		$this->payload_storage       = $payload_storage;
	}

	public function import( array $options = array() ) {
		$root_guid = trim(
			(string) (
				$options['root_guid']
				?? ( defined( 'ONEC_ROOT_GUID' ) ? (string) constant( 'ONEC_ROOT_GUID' ) : '' )
			)
		);

		if ( '' === $root_guid ) {
			return new WP_Error( 'onec_root_guid_missing', __( 'Не указан корневой GUID каталога 1С.', 'wp-automation-engine' ) );
		}

		$items = $this->collect_products_recursive(
			$root_guid,
			array_merge(
				$options,
				array(
					'create_categories' => $this->resolve_boolean_option( $options, 'create_categories', true ),
				)
			)
		);

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$payload_key = sanitize_key( (string) ( $options['payload_key'] ?? 'onec_products' ) );
		$reference   = $this->payload_storage->save(
			$items,
			array(
				'key'       => '' !== $payload_key ? $payload_key : 'onec_products',
				'source'    => '',
				'status'    => 'ready',
				'integration'=> '1c',
				'root_guid' => $root_guid,
			)
		);

		if ( is_wp_error( $reference ) ) {
			return $reference;
		}

		$payload = $reference instanceof WPAE_Payload_Reference ? $reference->to_array() : $reference;

		return array(
			'items'      => $items,
			'total'      => count( $items ),
			'payload'    => $payload,
			'root_guid'  => $root_guid,
		);
	}

	protected function collect_products_recursive( $parent_ref, array $options ) {
		$items = $this->client->fetch_children( $parent_ref, $options );

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$products = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$is_folder = ! empty( $item['IsFolder'] );
			$ref_key   = sanitize_text_field( (string) ( $item['Ref_Key'] ?? '' ) );

			if ( $is_folder && $this->resolve_boolean_option( $options, 'create_categories', true ) && '' !== $ref_key ) {
				$this->category_synchronizer->synchronize(
					$ref_key,
					$item['Description'] ?? 'Каталог',
					$item['Parent_Key'] ?? null
				);
			}

			if ( $is_folder && '' !== $ref_key ) {
				$children = $this->collect_products_recursive( $ref_key, $options );

				if ( is_wp_error( $children ) ) {
					return $children;
				}

				$products = array_merge( $products, $children );
				continue;
			}

			$products[] = array(
				'Description'       => $item['Description'] ?? '',
				'Ref_Key'           => $ref_key,
				'Parent_Key'        => $item['Parent_Key'] ?? '',
				'Code'              => $item['Code'] ?? '',
				'Артикул'           => $item['Артикул'] ?? '',
				'ЦенаПродажи'       => $item['ЦенаПродажи'] ?? '',
				'ЕдиницаИзмерения'  => $item['ЕдиницаИзмерения'] ?? '',
				'Остаток'           => $item['Остаток'] ?? '',
			);
		}

		return $products;
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
