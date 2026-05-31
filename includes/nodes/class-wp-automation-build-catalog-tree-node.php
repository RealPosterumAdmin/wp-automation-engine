<?php

class WP_Automation_Build_Catalog_Tree_Node implements WP_Automation_Node {

	protected $tree_builder;

	public function __construct( WPAE_Catalog_Tree_Builder $tree_builder ) {
		$this->tree_builder = $tree_builder;
	}

	public function get_type() {
		return 'build_catalog_tree';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Построить дерево каталога',
			'icon'   => 'networking',
			'fields' => array(
				array(
					'name'  => 'source',
					'label' => 'Источник',
					'type'  => 'path',
				),
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
					'name'  => 'target_key',
					'label' => 'Ключ результата',
					'type'  => 'text',
				),
				array(
					'name'  => 'id_key',
					'label' => 'Ключ GUID',
					'type'  => 'text',
				),
				array(
					'name'  => 'parent_key',
					'label' => 'Ключ родителя',
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
		$target_key = sanitize_key( $config['target_key'] ?? '' );
		$scope      = isset( $config['scope'] ) ? (string) $config['scope'] : 'global';
		$items      = $context->resolve_path( $source );

		if ( '' === $target_key ) {
			$executor->log_node( $context, $node, 'Не указан ключ результата для дерева каталога.', 'error' );
			return;
		}

		if ( ! is_array( $items ) ) {
			$executor->log_node( $context, $node, 'Источник дерева каталога должен быть массивом.', 'error', array( 'source' => $source ) );
			return;
		}

		$tree = $this->tree_builder->build_tree(
			$items,
			(string) ( $config['id_key'] ?? 'Ref_Key' ),
			(string) ( $config['parent_key'] ?? 'Parent_Key' ),
			(string) ( $config['children_key'] ?? 'children' )
		);

		$context->set_variable( $target_key, $tree, 'local' === $scope ? 'local' : 'global' );
		$executor->log_node( $context, $node, 'Дерево каталога построено.', 'success', $tree['stats'] );
	}
}
