<?php

class WP_Automation_Update_Post_Node extends WP_Automation_Abstract_Entity_Node {

	public function get_type() {
		return 'update_post';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Обновить запись WordPress',
			'icon'   => 'edit',
			'fields' => array(
				array(
					'name'  => 'post_id',
					'label' => 'ID записи',
					'type'  => 'mixed',
				),
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
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config  = $this->get_config( $node );
		$post_id = (int) $this->resolve_value_from_config( $config, 'post_id', $context, $executor, 0 );
		$post    = $this->resolve_array_from_config( $config, 'post', $context, $executor );
		$meta    = $this->resolve_array_from_config( $config, 'meta', $context, $executor );

		if ( $post_id <= 0 ) {
			$executor->log_node( $context, $node, 'Не указан ID записи для обновления.', 'error' );
			return;
		}

		$post['ID'] = $post_id;
		$result     = wp_update_post( wp_slash( $post ), true, false );

		if ( is_wp_error( $result ) ) {
			$executor->log_node( $context, $node, $result->get_error_message(), 'error' );
			return;
		}

		$this->update_meta_fields( $post_id, $meta, 'update_post_meta', 'delete_post_meta' );
		$executor->log_node( $context, $node, 'Запись WordPress обновлена.', 'success', array( 'post_id' => $post_id ) );
	}
}
