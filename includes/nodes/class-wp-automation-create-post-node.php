<?php

class WP_Automation_Create_Post_Node extends WP_Automation_Abstract_Entity_Node {

	public function get_type() {
		return 'create_post';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Создать запись WordPress',
			'icon'   => 'admin-post',
			'fields' => array(
				array(
					'name'  => 'post',
					'label' => 'Данные записи',
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
		$post     = $this->resolve_array_from_config( $config, 'post', $context, $executor );
		$meta     = $this->resolve_array_from_config( $config, 'meta', $context, $executor );
		$store_in = sanitize_key( (string) $this->resolve_value_from_config( $config, 'store_in', $context, $executor, 'created_post_id' ) );

		if ( empty( $post ) ) {
			$executor->log_node( $context, $node, 'Не переданы данные для создания записи.', 'error' );
			return;
		}

		$post_id = wp_insert_post( wp_slash( $post ), true, false );

		if ( is_wp_error( $post_id ) ) {
			$executor->log_node( $context, $node, $post_id->get_error_message(), 'error' );
			return;
		}

		$this->update_meta_fields( $post_id, $meta, 'update_post_meta', 'delete_post_meta' );
		$this->store_value( $context, $store_in, (int) $post_id );
		$executor->log_node( $context, $node, 'Запись WordPress создана.', 'success', array( 'post_id' => (int) $post_id ) );
	}
}
