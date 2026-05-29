<?php

class WP_Automation_Set_Variable_Node implements WP_Automation_Node {

	public function get_type() {
		return 'set_variable';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Set Variable',
			'icon'   => 'update',
			'fields' => array(
				array( 'name' => 'scope', 'type' => 'select', 'options' => array( 'global', 'local' ) ),
				array( 'name' => 'key', 'type' => 'text' ),
				array( 'name' => 'value', 'type' => 'mixed' ),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$key    = isset( $config['key'] ) ? sanitize_key( $config['key'] ) : '';

		if ( '' === $key ) {
			$executor->log_node( $context, $node, 'Variable key is missing', 'error' );
			return;
		}

		$scope = isset( $config['scope'] ) ? $config['scope'] : 'global';
		$value = $executor->resolve_value( $config['value'] ?? null, $context );

		$context->set_variable( $key, $value, $scope );
		$executor->log_node( $context, $node, 'Variable updated', 'success', array( 'key' => $key, 'scope' => $scope ) );
	}
}
