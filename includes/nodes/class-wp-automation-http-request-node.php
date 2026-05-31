<?php

class WP_Automation_HTTP_Request_Node implements WP_Automation_Node {

	protected $http_client;

	public function __construct( WPAE_WordPress_Http_Client $http_client ) {
		$this->http_client = $http_client;
	}

	public function get_type() {
		return 'http_request';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'HTTP-запрос',
			'icon'   => 'rest-api',
			'fields' => array(
				array(
					'name'    => 'scope',
					'label'   => 'Область',
					'type'    => 'select',
					'options' => array(
						array(
							'value' => 'global',
							'label' => 'Глобальная',
						),
						array(
							'value' => 'local',
							'label' => 'Локальная',
						),
					),
				),
				array(
					'name'  => 'target_key',
					'label' => 'Ключ результата',
					'type'  => 'text',
				),
				array(
					'name'    => 'method',
					'label'   => 'Метод',
					'type'    => 'select',
					'options' => array(
						array(
							'value' => 'GET',
							'label' => 'GET',
						),
						array(
							'value' => 'POST',
							'label' => 'POST',
						),
					),
				),
				array(
					'name'  => 'url',
					'label' => 'URL',
					'type'  => 'text',
				),
				array(
					'name'  => 'query',
					'label' => 'Query-параметры',
					'type'  => 'object',
				),
				array(
					'name'  => 'headers',
					'label' => 'Заголовки',
					'type'  => 'object',
				),
				array(
					'name'    => 'auth_type',
					'label'   => 'Авторизация',
					'type'    => 'select',
					'options' => array(
						array(
							'value' => 'none',
							'label' => 'Без авторизации',
						),
						array(
							'value' => 'basic',
							'label' => 'Basic Auth',
						),
					),
				),
				array(
					'name'  => 'basic_auth',
					'label' => 'Basic Auth',
					'type'  => 'object',
				),
				array(
					'name'  => 'body',
					'label' => 'Тело запроса',
					'type'  => 'mixed',
				),
				array(
					'name'  => 'timeout',
					'label' => 'Таймаут (сек)',
					'type'  => 'text',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config     = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$scope      = isset( $config['scope'] ) ? (string) $config['scope'] : 'global';
		$target_key = sanitize_key( $config['target_key'] ?? '' );
		$url        = (string) $executor->resolve_value( $config['url'] ?? '', $context );

		if ( '' === $target_key ) {
			$executor->log_node( $context, $node, 'Не указан ключ для сохранения ответа HTTP.', 'error' );
			return;
		}

		$query   = $executor->resolve_value( $config['query'] ?? array(), $context );
		$headers = $executor->resolve_value( $config['headers'] ?? array(), $context );
		$body    = $executor->resolve_value( $config['body'] ?? null, $context );

		if ( is_array( $query ) && ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		if ( ! is_array( $headers ) ) {
			$headers = array();
		}

		if ( 'basic' === ( $config['auth_type'] ?? 'none' ) ) {
			$basic_auth = $executor->resolve_value( $config['basic_auth'] ?? array(), $context );
			$username   = is_array( $basic_auth ) ? (string) ( $basic_auth['username'] ?? '' ) : '';
			$password   = is_array( $basic_auth ) ? (string) ( $basic_auth['password'] ?? '' ) : '';

			if ( '' === $username ) {
				$executor->log_node( $context, $node, 'Для Basic Auth не указан логин.', 'error' );
				return;
			}

			$headers['Authorization'] = 'Basic ' . base64_encode( $username . ':' . $password );
		}

		if ( is_array( $body ) || is_object( $body ) ) {
			$body = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

			if ( ! isset( $headers['Content-Type'] ) ) {
				$headers['Content-Type'] = 'application/json';
			}
		}

		$response = $this->http_client->request_json(
			$url,
			array(
				'method'  => $config['method'] ?? 'GET',
				'headers' => $headers,
				'body'    => $body,
				'timeout' => $config['timeout'] ?? 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$executor->log_node( $context, $node, $response->get_error_message(), 'error' );
			return;
		}

		$payload = array(
			'url'         => $response['url'],
			'status_code' => $response['status_code'],
			'headers'     => $response['headers'],
			'body'        => $response['body'],
		);

		$context->set_variable( $target_key, $payload, 'local' === $scope ? 'local' : 'global' );
		$executor->log_node( $context, $node, 'HTTP-запрос выполнен успешно.', 'success', array( 'target_key' => $target_key, 'status_code' => $response['status_code'] ) );
	}
}
