<?php

class WP_Automation_Import_OneC_Products_Node implements WP_Automation_Node {

	protected $importer;

	public function __construct( WPAE_Import_OneC_Products $importer ) {
		$this->importer = $importer;
	}

	public function get_type() {
		return 'import_onec_products';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => '1С: импортировать товары',
			'icon'   => 'database-import',
			'fields' => array(
				array(
					'name'  => 'root_guid',
					'label' => 'Корневой GUID',
					'type'  => 'text',
				),
				array(
					'name'  => 'base_url',
					'label' => 'URL OData',
					'type'  => 'text',
				),
				array(
					'name'  => 'username',
					'label' => 'Логин',
					'type'  => 'text',
				),
				array(
					'name'  => 'password',
					'label' => 'Пароль',
					'type'  => 'text',
				),
				array(
					'name'  => 'payload_key',
					'label' => 'Ключ JSON-пакета',
					'type'  => 'text',
				),
				array(
					'name'    => 'create_categories',
					'label'   => 'Создавать категории',
					'type'    => 'select',
					'options' => array(
						array( 'value' => 'yes', 'label' => 'Да' ),
						array( 'value' => 'no', 'label' => 'Нет' ),
					),
				),
				array(
					'name'    => 'verify_ssl',
					'label'   => 'Проверять SSL',
					'type'    => 'select',
					'options' => array(
						array( 'value' => 'yes', 'label' => 'Да' ),
						array( 'value' => 'no', 'label' => 'Нет' ),
					),
				),
				array(
					'name'  => 'timeout',
					'label' => 'Таймаут',
					'type'  => 'mixed',
				),
				array(
					'name'  => 'store_in',
					'label' => 'Сохранить ссылку в переменную',
					'type'  => 'text',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config   = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$result   = $this->importer->import(
			array(
				'root_guid'         => (string) $executor->resolve_value( $config['root_guid'] ?? '', $context ),
				'base_url'          => (string) $executor->resolve_value( $config['base_url'] ?? '', $context ),
				'username'          => (string) $executor->resolve_value( $config['username'] ?? '', $context ),
				'password'          => (string) $executor->resolve_value( $config['password'] ?? '', $context ),
				'payload_key'       => (string) $executor->resolve_value( $config['payload_key'] ?? 'onec_products', $context ),
				'create_categories' => (string) $executor->resolve_value( $config['create_categories'] ?? 'yes', $context ),
				'verify_ssl'        => (string) $executor->resolve_value( $config['verify_ssl'] ?? 'yes', $context ),
				'timeout'           => (int) $executor->resolve_value( $config['timeout'] ?? 60, $context ),
			)
		);
		$store_in = sanitize_key( (string) $executor->resolve_value( $config['store_in'] ?? 'onec_payload', $context ) );

		if ( is_wp_error( $result ) ) {
			$executor->log_node( $context, $node, $result->get_error_message(), 'error' );
			return;
		}

		$payload = is_array( $result['payload'] ?? null ) ? $result['payload'] : array();
		$context->merge_runtime(
			array(
				'payload' => $payload,
				'batch'   => array(
					'offset'      => 0,
					'limit'       => 0,
					'total'       => $payload['total_items'] ?? ( $result['total'] ?? 0 ),
					'next_offset' => 0,
					'source'      => $payload['source'] ?? '',
				),
			)
		);

		if ( '' !== $store_in ) {
			$context->set_variable( $store_in, $payload, 'global' );
		}

		$executor->log_node(
			$context,
			$node,
			'Товары из 1С импортированы в JSON-пакет.',
			'success',
			array(
				'payload_id' => $payload['id'] ?? '',
				'total'      => $result['total'] ?? 0,
				'root_guid'  => $result['root_guid'] ?? '',
				'store_in'   => $store_in,
			)
		);
	}
}
