<?php

class WPAE_OneC_OData_Client {

	public function fetch_children( $parent_ref, array $options = array() ) {
		$base_url = $this->resolve_base_url( $options );

		if ( '' === $base_url ) {
			return new WP_Error( 'onec_base_url_missing', __( 'Константа ONEC_BASE_URL не задана.', 'wp-automation-engine' ) );
		}

		$parent_ref = sanitize_text_field( (string) $parent_ref );

		if ( '' === $parent_ref ) {
			return new WP_Error( 'onec_parent_ref_missing', __( 'Не указан GUID родительского каталога 1С.', 'wp-automation-engine' ) );
		}

		$filter   = sprintf( "Parent_Key eq guid'%s'", $parent_ref );
		$select   = $this->resolve_select_fields( $options );
		$url      = add_query_arg(
			array(
				'$format' => 'json',
				'$filter' => $filter,
				'$select' => implode( ',', $select ),
			),
			trailingslashit( $base_url )
		);
		$username = array_key_exists( 'username', $options ) ? (string) $options['username'] : ( defined( 'ONEC_LOGIN' ) ? (string) constant( 'ONEC_LOGIN' ) : '' );
		$password = array_key_exists( 'password', $options ) ? (string) $options['password'] : ( defined( 'ONEC_PASS' ) ? (string) constant( 'ONEC_PASS' ) : '' );
		$timeout  = max( 1, (int) ( $options['timeout'] ?? 60 ) );
		$args     = array(
			'timeout'   => $timeout,
			'sslverify' => $this->resolve_boolean_option( $options, 'verify_ssl', true ),
			'headers'   => array(
				'Accept' => 'application/json',
			),
		);

		if ( '' !== $username || '' !== $password ) {
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( $username . ':' . $password );
		}

		$response = wp_remote_get( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'onec_request_failed',
				sprintf(
					/* translators: %s: error message. */
					__( 'Ошибка запроса к 1С: %s', 'wp-automation-engine' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );

		if ( $status_code >= 400 ) {
			return new WP_Error(
				'onec_http_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: response body. */
					__( '1С вернула HTTP %1$d: %2$s', 'wp-automation-engine' ),
					$status_code,
					substr( $body, 0, 500 )
				)
			);
		}

		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $data['value'] ) || ! is_array( $data['value'] ) ) {
			return new WP_Error( 'onec_invalid_response', __( '1С вернула некорректный JSON-ответ.', 'wp-automation-engine' ) );
		}

		return $data['value'];
	}

	protected function resolve_base_url( array $options ) {
		$base_url = array_key_exists( 'base_url', $options ) ? (string) $options['base_url'] : ( defined( 'ONEC_BASE_URL' ) ? (string) constant( 'ONEC_BASE_URL' ) : '' );

		return untrailingslashit( trim( $base_url ) );
	}

	protected function resolve_select_fields( array $options ) {
		$default = array(
			'Description',
			'Ref_Key',
			'Parent_Key',
			'IsFolder',
			'Code',
			'ЦенаПродажи',
			'ЕдиницаИзмерения',
			'Артикул',
			'Остаток',
		);
		$fields  = $options['select'] ?? $default;

		if ( is_string( $fields ) ) {
			$fields = array_filter( array_map( 'trim', explode( ',', $fields ) ) );
		}

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return $default;
		}

		return array_values(
			array_filter(
				array_map( 'sanitize_text_field', $fields )
			)
		);
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
