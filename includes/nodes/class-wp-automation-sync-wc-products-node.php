<?php

class WP_Automation_Sync_WC_Products_Node implements WP_Automation_Node {

	protected $sync_service;
	protected $state_store;

	public function __construct( WPAE_WooCommerce_Sync_Service $sync_service, WPAE_Workflow_State_Store $state_store ) {
		$this->sync_service = $sync_service;
		$this->state_store  = $state_store;
	}

	public function get_type() {
		return 'sync_wc_products';
	}

	public function get_schema() {
		return array(
			'type'   => $this->get_type(),
			'label'  => 'Синхронизировать товары WooCommerce',
			'icon'   => 'cart',
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
					'name'  => 'progress_key',
					'label' => 'Ключ прогресса',
					'type'  => 'text',
				),
				array(
					'name'    => 'mode',
					'label'   => 'Режим прогресса',
					'type'    => 'select',
					'options' => array(
						array(
							'value' => 'resume',
							'label' => 'Продолжить',
						),
						array(
							'value' => 'reset',
							'label' => 'Сбросить и начать заново',
						),
					),
				),
				array(
					'name'  => 'batch_size',
					'label' => 'Размер batch',
					'type'  => 'text',
				),
				array(
					'name'  => 'guid_key',
					'label' => 'Ключ GUID',
					'type'  => 'text',
				),
				array(
					'name'  => 'sku_key',
					'label' => 'Ключ SKU',
					'type'  => 'text',
				),
				array(
					'name'  => 'name_key',
					'label' => 'Ключ названия',
					'type'  => 'text',
				),
				array(
					'name'  => 'price_key',
					'label' => 'Ключ цены',
					'type'  => 'text',
				),
				array(
					'name'  => 'stock_key',
					'label' => 'Ключ остатка',
					'type'  => 'text',
				),
				array(
					'name'  => 'unit_key',
					'label' => 'Ключ единицы измерения',
					'type'  => 'text',
				),
				array(
					'name'  => 'category_guid_key',
					'label' => 'Ключ GUID категорий',
					'type'  => 'text',
				),
				array(
					'name'  => 'description_key',
					'label' => 'Ключ описания',
					'type'  => 'text',
				),
				array(
					'name'    => 'status',
					'label'   => 'Статус товара',
					'type'    => 'select',
					'options' => array(
						array(
							'value' => 'publish',
							'label' => 'Опубликован',
						),
						array(
							'value' => 'draft',
							'label' => 'Черновик',
						),
					),
				),
			),
		);
	}

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor ) {
		$config      = isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array();
		$source      = $config['source'] ?? '';
		$items       = $context->resolve_path( $source );
		$workflow_id = $context->get_workflow()['id'] ?? '';
		$scope       = isset( $config['scope'] ) ? (string) $config['scope'] : 'global';
		$target_key  = sanitize_key( $config['target_key'] ?? 'product_sync_result' );
		$progress_key = sanitize_key( $config['progress_key'] ?? 'product_sync' );

		if ( ! is_array( $items ) ) {
			$executor->log_node( $context, $node, 'Источник товаров должен быть массивом.', 'error', array( 'source' => $source ) );
			return;
		}

		if ( 'reset' === ( $config['mode'] ?? 'resume' ) ) {
			$this->state_store->delete_state( $workflow_id, $progress_key );
		}

		$state      = $this->state_store->get_state( $workflow_id, $progress_key );
		$offset     = isset( $state['offset'] ) ? max( 0, absint( $state['offset'] ) ) : 0;
		$batch_size = max( 1, absint( $config['batch_size'] ?? 100 ) );
		$total      = count( $items );
		$batch      = array_slice( array_values( $items ), $offset, $batch_size );
		$summary    = array(
			'created'   => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'errors'    => 0,
			'processed' => 0,
			'batch_size' => $batch_size,
			'offset'    => $offset,
			'total'     => $total,
			'finished'  => false,
			'last_guid' => '',
		);

		foreach ( $batch as $item ) {
			if ( ! is_array( $item ) ) {
				++$summary['skipped'];
				continue;
			}

			$result = $this->sync_service->sync_product(
				$item,
				array(
					'guid_key'          => $config['guid_key'] ?? 'Ref_Key',
					'sku_key'           => $config['sku_key'] ?? 'SKU',
					'name_key'          => $config['name_key'] ?? 'Description',
					'price_key'         => $config['price_key'] ?? 'Price',
					'stock_key'         => $config['stock_key'] ?? 'Quantity',
					'unit_key'          => $config['unit_key'] ?? 'Unit',
					'category_guid_key' => $config['category_guid_key'] ?? 'Category_Key',
					'description_key'   => $config['description_key'] ?? 'DescriptionFull',
					'status'            => $config['status'] ?? 'publish',
				)
			);

			if ( is_wp_error( $result ) ) {
				++$summary['errors'];
				$executor->log_node( $context, $node, $result->get_error_message(), 'error' );
				continue;
			}

			++$summary['processed'];
			$summary['last_guid'] = $result['guid'];

			if ( 'created' === $result['action'] ) {
				++$summary['created'];
			} else {
				++$summary['updated'];
			}
		}

		$next_offset         = min( $total, $offset + count( $batch ) );
		$summary['finished'] = $next_offset >= $total;
		$summary['remaining'] = max( 0, $total - $next_offset );
		$summary['next_offset'] = $next_offset;
		$summary['updated_at'] = current_time( 'mysql', true );

		$state_to_store = array(
			'offset'      => $summary['finished'] ? 0 : $next_offset,
			'total'       => $total,
			'batch_size'  => $batch_size,
			'processed'   => isset( $state['processed'] ) ? absint( $state['processed'] ) + count( $batch ) : count( $batch ),
			'created'     => isset( $state['created'] ) ? absint( $state['created'] ) + $summary['created'] : $summary['created'],
			'updated'     => isset( $state['updated'] ) ? absint( $state['updated'] ) + $summary['updated'] : $summary['updated'],
			'skipped'     => isset( $state['skipped'] ) ? absint( $state['skipped'] ) + $summary['skipped'] : $summary['skipped'],
			'errors'      => isset( $state['errors'] ) ? absint( $state['errors'] ) + $summary['errors'] : $summary['errors'],
			'finished'    => $summary['finished'],
			'remaining'   => $summary['remaining'],
			'last_guid'   => $summary['last_guid'],
			'updated_at'  => $summary['updated_at'],
			'last_batch'  => $summary,
		);

		$this->state_store->save_state( $workflow_id, $progress_key, $state_to_store );
		$runtime_progress = $context->resolve_path( 'runtime.sync_progress' );

		if ( ! is_array( $runtime_progress ) ) {
			$runtime_progress = array();
		}

		$runtime_progress[ $progress_key ] = $state_to_store;
		$context->set_runtime_value( 'sync_progress', $runtime_progress );
		$executor->persist_context_snapshot( $context );
		$context->set_variable( $target_key, $state_to_store, 'local' === $scope ? 'local' : 'global' );

		$executor->log_node(
			$context,
			$node,
			$summary['finished'] ? 'Синхронизация товаров WooCommerce завершена.' : 'Batch синхронизации товаров WooCommerce обработан.',
			'success',
			$state_to_store
		);
	}
}
