<?php

class WP_Automation_Create_User_Node extends WP_Automation_Abstract_Entity_Node {

	public function get_type() {
		return 'create_user';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Создать пользователя',
			'icon'   => 'admin-users',
			'fields' => array(
				array(
					'name'  => 'user',
					'label' => 'Данные пользователя',
					'type'  => 'object',
				),
				array(
					'name'  => 'meta',
					'label' => 'Метаполя',
					'type'  => 'object',
				),
				array(
					'name'  => 'store_in',
					'label' => 'Сохранить ID в переменную',
					'type'  => 'text',
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config   = $this->get_config( $node );
		$user     = $this->resolve_array_from_config( $config, 'user', $context, $executor );
		$meta     = $this->resolve_array_from_config( $config, 'meta', $context, $executor );
		$store_in = sanitize_key( (string) $this->resolve_value_from_config( $config, 'store_in', $context, $executor, 'created_user_id' ) );

		if ( empty( $user ) ) {
			$executor->log_node( $context, $node, 'Не переданы данные для создания пользователя.', 'error' );
			return;
		}

		$user_id = wp_insert_user( wp_slash( $user ) );

		if ( is_wp_error( $user_id ) ) {
			$executor->log_node( $context, $node, $user_id->get_error_message(), 'error' );
			return;
		}

		$this->update_meta_fields( $user_id, $meta, 'update_user_meta', 'delete_user_meta' );
		$this->store_value( $context, $store_in, (int) $user_id );
		$executor->log_node( $context, $node, 'Пользователь создан.', 'success', array( 'user_id' => (int) $user_id ) );
	}
}
