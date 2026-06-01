<?php

class WP_Automation_Http_Request_Node implements WP_Automation_Node {

	public function get_type() {
		return 'http_request';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'HTTP-запрос',
			'icon'   => 'cloud',
			'fields' => array(
				array(
					'name'  => 'url',
					'label' => 'URL',
					'type'  => 'text',
				),
				array(
					'name'    => 'method',
					'label'   => 'Метод',
					'type'    => 'select',
					'options' => array(
						array( 'value' => 'GET', 'label' => 'GET' ),
						array( 'value' => 'POST', 'label' => 'POST' ),
						array( 'value' => 'PUT', 'label' => 'PUT' ),
						array( 'value' => 'PATCH', 'label' => 'PATCH' ),
						array( 'value' => 'DELETE', 'label' => 'DELETE' ),
					),
				),
				array(
					'name'  => 'headers',
					'label' => 'Заголовки',
					'type'  => 'object',
				),
				array(
					'name'  => 'body',
					'label' => 'Body',
					'type'  => 'mixed',
				),
				array(
					'name'    => 'response_format',
					'label'   => 'Формат ответа',
					'type'    => 'select',
					'options' => array(
						array( 'value' => 'json', 'label' => 'JSON' ),
						array( 'value' => 'body', 'label' => 'Текст' ),
						array( 'value' => 'response', 'label' => 'Полный ответ' ),
					),
				),
				array(
					'name'    => 'target',
					'label'   => 'Сохранить в',
					'type'    => 'select',
					'options' => array(
						array( 'value' => 'variable', 'label' => 'Переменную' ),
						array( 'value' => 'payload', 'label' => 'JSON-пакет' ),
					),
				),
				array(
					'name'  => 'target_key',
					'label' => 'Ключ назначения',
					'type'  => 'text',
				),
				array(
					'name'  => 'payload_source',
					'label' => 'Источник элементов в payload',
					'type'  => 'text',
				),
				array(
					'name'  => 'timeout',
					'label' => 'Таймаут',
					'type'  => 'mixed',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config          = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$url             = (string) $executor->resolve_value( $config['url'] ?? '', $context );
		$method          = strtoupper( (string) $executor->resolve_value( $config['method'] ?? 'GET', $context ) );
		$headers         = $executor->resolve_value( $config['headers'] ?? array(), $context );
		$body            = $executor->resolve_value( $config['body'] ?? null, $context );
		$response_format = sanitize_key( (string) $executor->resolve_value( $config['response_format'] ?? 'json', $context ) );
		$target          = sanitize_key( (string) $executor->resolve_value( $config['target'] ?? 'variable', $context ) );
		$target_key      = sanitize_key( (string) $executor->resolve_value( $config['target_key'] ?? 'http_response', $context ) );
		$payload_source  = sanitize_text_field( (string) $executor->resolve_value( $config['payload_source'] ?? '', $context ) );
		$timeout         = max( 1, (int) $executor->resolve_value( $config['timeout'] ?? 15, $context ) );

		if ( '' === $url ) {
			$executor->log_node( $context, $node, 'Не указан URL для HTTP-запроса.', 'error' );
			return;
		}

		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => is_array( $headers ) ? $headers : array(),
		);

		if ( null !== $body && '' !== $body ) {
			$args['body'] = is_array( $body ) ? wp_json_encode( $body ) : $body;

			if ( is_array( $body ) && empty( $args['headers']['Content-Type'] ) ) {
				$args['headers']['Content-Type'] = 'application/json';
			}
		}

		$response = wp_remote_request( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) ) {
			$executor->log_node(
				$context,
				$node,
				sprintf( 'HTTP-запрос завершился ошибкой: %s', $response->get_error_message() ),
				'error',
				array(
					'url'    => $url,
					'method' => $method,
				)
			);
			return;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body_raw    = (string) wp_remote_retrieve_body( $response );
		$body_json   = json_decode( $body_raw, true );
		$result      = $this->build_result( $response_format, $status_code, $response, $body_raw, $body_json );

		if ( 'payload' === $target ) {
			$reference = $executor->save_payload(
				$target_key,
				$result,
				array(
					'source' => $payload_source,
					'status' => $status_code >= 200 && $status_code < 400 ? 'ready' : 'error',
					'request' => array(
						'url'    => $url,
						'method' => $method,
					),
				)
			);

			if ( is_wp_error( $reference ) ) {
				$executor->log_node( $context, $node, $reference->get_error_message(), 'error' );
				return;
			}

			$context->merge_runtime(
				array(
					'payload' => $reference,
					'batch'   => array(
						'offset'      => 0,
						'limit'       => 0,
						'total'       => $reference['total_items'] ?? 0,
						'next_offset' => 0,
						'source'      => $reference['source'] ?? '',
					),
				)
			);
			$context->set_variable( $target_key, $reference, 'global' );
		} else {
			$context->set_variable( $target_key, $result, 'global' );
		}

		$executor->log_node(
			$context,
			$node,
			'HTTP-запрос выполнен.',
			$status_code >= 200 && $status_code < 400 ? 'success' : 'error',
			array(
				'url'         => $url,
				'method'      => $method,
				'status_code' => $status_code,
				'target'      => $target,
				'target_key'  => $target_key,
			)
		);
	}

	protected function build_result( $response_format, $status_code, array $response, $body_raw, $body_json ) {
		$headers = wp_remote_retrieve_headers( $response );

		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		} elseif ( is_object( $headers ) ) {
			$headers = (array) $headers;
		}

		switch ( $response_format ) {
			case 'body':
				return $body_raw;
			case 'response':
				return array(
					'status_code' => $status_code,
					'headers'     => is_array( $headers ) ? $headers : array(),
					'body'        => $body_raw,
					'json'        => is_array( $body_json ) ? $body_json : null,
				);
			case 'json':
			default:
				return is_array( $body_json ) ? $body_json : array(
					'status_code' => $status_code,
					'body'        => $body_raw,
				);
		}
	}
}
