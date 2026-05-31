<?php

class WP_Automation_Node_Factory {

	protected $condition_evaluator;
	protected $http_client;
	protected $tree_builder;
	protected $woocommerce_sync_service;
	protected $workflow_state_store;
	protected $json_export_service;
	protected $nodes = array();

	public function __construct( WP_Automation_Condition_Evaluator $condition_evaluator, WPAE_WordPress_Http_Client $http_client = null, WPAE_Catalog_Tree_Builder $tree_builder = null, WPAE_WooCommerce_Sync_Service $woocommerce_sync_service = null, WPAE_Workflow_State_Store $workflow_state_store = null, WPAE_JSON_Export_Service $json_export_service = null ) {
		$this->condition_evaluator      = $condition_evaluator;
		$this->http_client              = $http_client;
		$this->tree_builder             = $tree_builder;
		$this->woocommerce_sync_service = $woocommerce_sync_service;
		$this->workflow_state_store     = $workflow_state_store;
		$this->json_export_service      = $json_export_service;
		$this->register_builtin_nodes();
	}

	public function register( WP_Automation_Node $node ) {
		$type = $node->get_type();

		if ( '' === $type ) {
			return $this;
		}

		$this->nodes[ $type ] = $node;

		return $this;
	}

	public function create( array $node ) {
		$type = isset( $node['type'] ) ? $node['type'] : '';

		if ( ! isset( $this->nodes[ $type ] ) ) {
			return null;
		}

		return clone $this->nodes[ $type ];
	}

	public function get_schemas() {
		$schemas = array();

		foreach ( $this->nodes as $node ) {
			$schemas[] = $node->get_schema();
		}

		return $schemas;
	}

	protected function register_builtin_nodes() {
		$this->register( new WP_Automation_Set_Variable_Node() );
		$this->register( new WP_Automation_Dispatch_Event_Node() );
		$this->register( new WP_Automation_Action_Node() );
		$this->register( new WP_Automation_If_Node( $this->condition_evaluator ) );
		$this->register( new WP_Automation_Loop_Node() );

		if ( $this->http_client ) {
			$this->register( new WP_Automation_HTTP_Request_Node( $this->http_client ) );
		}

		if ( $this->tree_builder ) {
			$this->register( new WP_Automation_Build_Catalog_Tree_Node( $this->tree_builder ) );
		}

		if ( $this->woocommerce_sync_service ) {
			$this->register( new WP_Automation_Sync_WC_Categories_Node( $this->woocommerce_sync_service ) );
		}

		if ( $this->woocommerce_sync_service && $this->workflow_state_store ) {
			$this->register( new WP_Automation_Sync_WC_Products_Node( $this->woocommerce_sync_service, $this->workflow_state_store ) );
		}

		if ( $this->json_export_service && $this->workflow_state_store ) {
			$this->register( new WP_Automation_Export_JSON_Node( $this->json_export_service, $this->workflow_state_store ) );
		}
	}
}
