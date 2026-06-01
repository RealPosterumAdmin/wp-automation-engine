<?php

abstract class WP_Automation_Abstract_Entity_Node implements WP_Automation_Node {

	protected function get_config( array $node ) {
		return isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
	}

	protected function resolve_value_from_config( array $config, $key, WP_Automation_Context $context, WP_Automation_Executor $executor, $default = null ) {
		if ( ! array_key_exists( $key, $config ) ) {
			return $default;
		}

		return $executor->resolve_value( $config[ $key ], $context );
	}

	protected function resolve_array_from_config( array $config, $key, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$value = $this->resolve_value_from_config( $config, $key, $context, $executor, array() );

		return is_array( $value ) ? $value : array();
	}

	protected function store_value( WP_Automation_Context $context, $variable_name, $value, $scope = 'global' ) {
		$variable_name = sanitize_key( $variable_name );

		if ( '' === $variable_name ) {
			return;
		}

		$context->set_variable( $variable_name, $value, $scope );
	}

	protected function update_meta_fields( $object_id, array $meta, callable $update_callback, callable $delete_callback = null ) {
		foreach ( $meta as $meta_key => $meta_value ) {
			$meta_key = sanitize_key( $meta_key );

			if ( '' === $meta_key ) {
				continue;
			}

			if ( null === $meta_value && $delete_callback ) {
				call_user_func( $delete_callback, $object_id, $meta_key );
				continue;
			}

			call_user_func( $update_callback, $object_id, $meta_key, $meta_value );
		}
	}
}
