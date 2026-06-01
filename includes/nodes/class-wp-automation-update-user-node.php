<?php

class WP_Automation_Update_User_Node extends WP_Automation_Abstract_Entity_Node {

	public function get_type() {
		return 'update_user';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Обновить пользователя',
			'icon'   => 'id',
			'fields' => array(
				array(
					'name'  => 'user_id',
					'label' => 'ID пользователя',
					'type'  => 'mixed',
				),
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
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config  = $this->get_config( $node );
		$user_id = (int) $this->resolve_value_from_config( $config, 'user_id', $context, $executor, 0 );
		$user    = $this->resolve_array_from_config( $config, 'user', $context, $executor );
		$meta    = $this->resolve_array_from_config( $config, 'meta', $context, $executor );

		if ( $user_id <= 0 ) {
			$executor->log_node( $context, $node, 'Не указан ID пользователя для обновления.', 'error' );
			return;
		}

		$user['ID'] = $user_id;
		$result     = wp_update_user( wp_slash( $user ) );

		if ( is_wp_error( $result ) ) {
			$executor->log_node( $context, $node, $result->get_error_message(), 'error' );
			return;
		}

		$this->update_meta_fields( $user_id, $meta, 'update_user_meta', 'delete_user_meta' );
		$executor->log_node( $context, $node, 'Пользователь обновлен.', 'success', array( 'user_id' => $user_id ) );
	}
}
