<?php

class WP_Automation_Set_Variable_Node implements WP_Automation_Node {

	public function get_type() {
		return 'set_variable';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Установить переменную',
			'icon'   => 'update',
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
					'name'  => 'key',
					'label' => 'Ключ',
					'type'  => 'text',
				),
				array(
					'name'  => 'value',
					'label' => 'Значение',
					'type'  => 'mixed',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$key    = isset( $config['key'] ) ? sanitize_key( $config['key'] ) : '';

		if ( '' === $key ) {
			$executor->log_node( $context, $node, 'Не указан ключ переменной.', 'error' );
			return;
		}

		$scope = isset( $config['scope'] ) ? $config['scope'] : 'global';
		$value = $executor->resolve_value( $config['value'] ?? null, $context );

		$context->set_variable( $key, $value, $scope );
		$executor->log_node( $context, $node, 'Переменная обновлена.', 'success', array( 'key' => $key, 'scope' => $scope ) );
	}
}
