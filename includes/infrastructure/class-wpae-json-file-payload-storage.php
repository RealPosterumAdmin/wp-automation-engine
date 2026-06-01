<?php

class WPAE_JSON_File_Payload_Storage implements WPAE_Payload_Storage_Interface {

	const DIRECTORY_NAME = 'wp-automation-engine/payloads';

	public function save( $data, array $metadata = array() ) {
		$directory = $this->ensure_directory( $this->get_base_directory() );

		if ( ! $directory ) {
			return new WP_Error( 'payload_directory_unavailable', __( 'Не удалось подготовить директорию для JSON-пакетов.', 'wp-automation-engine' ) );
		}

		$payload_id = $this->generate_payload_id( $metadata['id'] ?? '' );
		$payload    = array(
			'id'          => $payload_id,
			'key'         => sanitize_key( $metadata['key'] ?? $payload_id ),
			'status'      => sanitize_key( $metadata['status'] ?? 'ready' ),
			'source'      => sanitize_text_field( $metadata['source'] ?? '' ),
			'total_items' => $this->count_items( $data, $metadata['source'] ?? '' ),
			'created_at'  => current_time( 'mysql', true ),
			'updated_at'  => current_time( 'mysql', true ),
			'metadata'    => $this->normalize_value( $metadata ),
			'data'        => $this->normalize_value( $data ),
		);
		$file_path  = $this->get_payload_file_path( $payload_id );
		$payload['file'] = $file_path;

		if ( false === file_put_contents( $file_path, wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) ) ) {
			return new WP_Error( 'payload_write_failed', __( 'Не удалось сохранить JSON-пакет.', 'wp-automation-engine' ) );
		}

		return WPAE_Payload_Reference::from_array( $payload );
	}

	public function read( $payload_id ) {
		$file_path = $this->get_payload_file_path( $payload_id );

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'payload_not_found', __( 'JSON-пакет не найден.', 'wp-automation-engine' ) );
		}

		$raw = file_get_contents( $file_path );

		if ( false === $raw ) {
			return new WP_Error( 'payload_read_failed', __( 'Не удалось прочитать JSON-пакет.', 'wp-automation-engine' ) );
		}

		$payload = json_decode( $raw, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $payload ) ) {
			return new WP_Error( 'payload_decode_failed', __( 'JSON-пакет поврежден.', 'wp-automation-engine' ) );
		}

		$payload['file'] = $file_path;

		return $payload;
	}

	public function read_batch( $payload_id, $offset = 0, $limit = 50, $source = '' ) {
		$payload = $this->read( $payload_id );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$source_path = '' !== $source ? (string) $source : (string) ( $payload['source'] ?? '' );
		$data        = $payload['data'] ?? array();
		$items       = $this->resolve_source( $data, $source_path );
		$items       = $this->normalize_items( $items );
		$offset      = max( 0, (int) $offset );
		$limit       = max( 1, (int) $limit );
		$total       = count( $items );
		$batch_items = array_slice( $items, $offset, $limit );
		$next_offset = $offset + count( $batch_items );

		return array(
			'reference' => WPAE_Payload_Reference::from_array( $payload )->to_array(),
			'items'     => array_values( $batch_items ),
			'offset'    => $offset,
			'limit'     => $limit,
			'total'     => $total,
			'source'    => $source_path,
			'has_more'  => $next_offset < $total,
			'next_offset' => $next_offset,
		);
	}

	public function update_status( $payload_id, $status, array $attributes = array() ) {
		$payload = $this->read( $payload_id );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$payload['status']     = sanitize_key( $status );
		$payload['updated_at'] = current_time( 'mysql', true );

		foreach ( $attributes as $key => $value ) {
			$payload[ $key ] = $this->normalize_value( $value );
		}

		if ( false === file_put_contents( $payload['file'], wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) ) ) {
			return new WP_Error( 'payload_update_failed', __( 'Не удалось обновить JSON-пакет.', 'wp-automation-engine' ) );
		}

		return WPAE_Payload_Reference::from_array( $payload );
	}

	public function archive( $payload_id ) {
		$payload = $this->read( $payload_id );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$archive_directory = $this->ensure_directory( trailingslashit( $this->get_base_directory() ) . 'archive' );

		if ( ! $archive_directory ) {
			return new WP_Error( 'payload_archive_directory_unavailable', __( 'Не удалось подготовить архив JSON-пакетов.', 'wp-automation-engine' ) );
		}

		$archived_path      = trailingslashit( $archive_directory ) . basename( $payload['file'] );
		$payload['status']  = 'archived';
		$payload['file']    = $archived_path;
		$payload['archived_at'] = current_time( 'mysql', true );
		$payload['updated_at']  = current_time( 'mysql', true );

		if ( false === file_put_contents( $archived_path, wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) ) ) {
			return new WP_Error( 'payload_archive_failed', __( 'Не удалось архивировать JSON-пакет.', 'wp-automation-engine' ) );
		}

		if ( file_exists( $this->get_payload_file_path( $payload_id ) ) ) {
			wp_delete_file( $this->get_payload_file_path( $payload_id ) );
		}

		return WPAE_Payload_Reference::from_array( $payload );
	}

	public function delete( $payload_id ) {
		$file_path = $this->get_payload_file_path( $payload_id );

		if ( file_exists( $file_path ) ) {
			return wp_delete_file( $file_path );
		}

		return true;
	}

	protected function get_base_directory() {
		if ( defined( 'ODATA_PRODUCTS_DIR' ) && '' !== trim( (string) constant( 'ODATA_PRODUCTS_DIR' ) ) ) {
			$custom_directory = untrailingslashit( (string) constant( 'ODATA_PRODUCTS_DIR' ) );

			if ( is_dir( $custom_directory ) || wp_mkdir_p( $custom_directory ) ) {
				return $custom_directory;
			}
		}

		$uploads = wp_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . self::DIRECTORY_NAME;
	}

	protected function ensure_directory( $directory ) {
		if ( wp_mkdir_p( $directory ) ) {
			return $directory;
		}

		return '';
	}

	protected function get_payload_file_path( $payload_id ) {
		return trailingslashit( $this->get_base_directory() ) . sanitize_file_name( $payload_id ) . '.json';
	}

	protected function generate_payload_id( $requested_id = '' ) {
		$requested_id = sanitize_key( $requested_id );

		if ( '' !== $requested_id ) {
			return $requested_id;
		}

		return 'payload_' . strtolower( wp_generate_password( 12, false, false ) );
	}

	protected function normalize_value( $value ) {
		$encoded = wp_json_encode( $value );

		if ( false === $encoded ) {
			return array();
		}

		return json_decode( $encoded, true );
	}

	protected function count_items( $data, $source ) {
		$items = $this->resolve_source( $data, $source );
		$items = $this->normalize_items( $items );

		return count( $items );
	}

	protected function resolve_source( $data, $source ) {
		$source = trim( (string) $source );

		if ( '' === $source ) {
			return $data;
		}

		$segments = explode( '.', $source );
		$value    = $data;

		foreach ( $segments as $segment ) {
			if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
				$value = $value[ $segment ];
				continue;
			}

			return array();
		}

		return $value;
	}

	protected function normalize_items( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}

		if ( $this->is_list( $items ) ) {
			return array_values( $items );
		}

		return array( $items );
	}

	protected function is_list( array $value ) {
		$expected_key = 0;

		foreach ( array_keys( $value ) as $key ) {
			if ( $key !== $expected_key ) {
				return false;
			}

			++$expected_key;
		}

		return true;
	}
}
