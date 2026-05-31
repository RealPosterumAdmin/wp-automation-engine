<?php

class WPAE_WordPress_Http_Client {

	public function request_json( $url, array $args = array() ) {
		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( $response['body'], true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'wpae_invalid_json_response', __( 'Удаленный сервер вернул некорректный JSON.', 'wp-automation-engine' ) );
		}

		$response['body'] = $decoded;

		return $response;
	}

	public function request( $url, array $args = array() ) {
		$url = esc_url_raw( (string) $url );

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'wpae_invalid_request_url', __( 'Указан некорректный адрес запроса.', 'wp-automation-engine' ) );
		}

		$request_args = array(
			'method'      => isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : 'GET',
			'timeout'     => isset( $args['timeout'] ) ? max( 1, absint( $args['timeout'] ) ) : 30,
			'headers'     => isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array(),
			'body'        => $args['body'] ?? null,
			'redirection' => 3,
		);

		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'wpae_http_request_failed',
				sprintf(
					/* translators: %s: error message. */
					__( 'Не удалось выполнить HTTP-запрос: %s', 'wp-automation-engine' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'wpae_http_bad_status',
				sprintf(
					/* translators: %d: response status code. */
					__( 'Удаленный сервер вернул ошибку HTTP %d.', 'wp-automation-engine' ),
					$status_code
				),
				array(
					'status_code' => $status_code,
					'body'        => $body,
				)
			);
		}

		return array(
			'url'         => $url,
			'status_code' => $status_code,
			'headers'     => wp_remote_retrieve_headers( $response ),
			'body'        => $body,
		);
	}
}
