<?php

class WP_Automation_Sync_WC_Categories_Node implements WP_Automation_Node {

	protected $sync_service;

	public function __construct( WPAE_WooCommerce_Sync_Service $sync_service ) {
		$this->sync_service = $sync_service;
	}

	public function get_type() {
		return 'sync_wc_categories';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Синхронизировать категории WooCommerce',
			'icon'   => 'category',
			'fields' => array(
				array(
					'name'  => 'source',
					'label' => 'Источник',
					'type'  => 'path',
				),
				array(
					'name'    => 'scope',
					'label'   => 'Область результата',
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
					'name'  => 'guid_key',
					'label' => 'Ключ GUID',
					'type'  => 'text',
				),
				array(
					'name'  => 'parent_guid_key',
					'label' => 'Ключ родителя',
					'type'  => 'text',
				),
				array(
					'name'  => 'name_key',
					'label' => 'Ключ названия',
					'type'  => 'text',
				),
				array(
					'name'  => 'children_key',
					'label' => 'Ключ детей',
					'type'  => 'text',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config     = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$source     = $config['source'] ?? '';
		$target_key = sanitize_key( $config['target_key'] ?? 'category_sync_result' );
		$scope      = isset( $config['scope'] ) ? (string) $config['scope'] : 'global';
		$items      = $context->resolve_path( $source );

		if ( ! is_array( $items ) ) {
			$executor->log_node( $context, $node, 'Источник категорий должен быть массивом.', 'error', array( 'source' => $source ) );
			return;
		}

		$result = $this->sync_service->sync_categories(
			$items,
			array(
				'guid_key'        => $config['guid_key'] ?? 'Ref_Key',
				'parent_guid_key' => $config['parent_guid_key'] ?? 'Parent_Key',
				'name_key'        => $config['name_key'] ?? 'Description',
				'children_key'    => $config['children_key'] ?? 'children',
			),
			function ( $message, $status = 'info', array $extra_context = array() ) use ( $context, $node, $executor ) {
				$executor->log_node( $context, $node, (string) $message, $status, $extra_context );
			}
		);

		if ( is_wp_error( $result ) ) {
			$executor->log_node( $context, $node, $result->get_error_message(), 'error' );
			return;
		}

		$context->set_variable( $target_key, $result, 'local' === $scope ? 'local' : 'global' );
		$executor->log_node( $context, $node, 'Категории WooCommerce синхронизированы.', 'success', $result );
	}
}
