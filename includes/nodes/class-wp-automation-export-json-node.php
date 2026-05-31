<?php

class WP_Automation_Export_JSON_Node implements WP_Automation_Node {

	protected $export_service;
	protected $state_store;

	public function __construct( WPAE_JSON_Export_Service $export_service, WPAE_Workflow_State_Store $state_store ) {
		$this->export_service = $export_service;
		$this->state_store    = $state_store;
	}

	public function get_type() {
		return 'export_json';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Экспортировать JSON',
			'icon'   => 'media-code',
			'fields' => array(
				array(
					'name'  => 'source',
					'label' => 'Источник',
					'type'  => 'path',
				),
				array(
					'name'  => 'filename',
					'label' => 'Имя файла',
					'type'  => 'text',
				),
				array(
					'name'  => 'state_key',
					'label' => 'Ключ состояния',
					'type'  => 'text',
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
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config      = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$source      = $config['source'] ?? '';
		$data        = $context->resolve_path( $source );
		$workflow_id = $context->get_workflow()['id'] ?? '';
		$scope       = isset( $config['scope'] ) ? (string) $config['scope'] : 'global';
		$target_key  = sanitize_key( $config['target_key'] ?? 'export_result' );
		$state_key   = sanitize_key( $config['state_key'] ?? 'last_export' );
		$filename    = (string) $executor->resolve_value( $config['filename'] ?? '', $context );

		$result = $this->export_service->export( $workflow_id, $filename, $data );

		if ( is_wp_error( $result ) ) {
			$executor->log_node( $context, $node, $result->get_error_message(), 'error' );
			return;
		}

		$this->state_store->save_state(
			$workflow_id,
			$state_key,
			array(
				'type'       => 'export',
				'updated_at' => current_time( 'mysql', true ),
				'file'       => $result,
			)
		);

		$context->set_variable( $target_key, $result, 'local' === $scope ? 'local' : 'global' );
		$context->set_runtime_value( 'last_export', $result );
		$executor->persist_context_snapshot( $context );
		$executor->log_node( $context, $node, 'JSON успешно экспортирован.', 'success', $result );
	}
}
