<?php

class WP_Automation_Node_Factory {

	protected $condition_evaluator;
	protected $nodes = array();
	protected $payload_storage;
	protected $workflow_scheduler;

	public function __construct( WP_Automation_Condition_Evaluator $condition_evaluator, WPAE_Payload_Storage_Interface $payload_storage = null, WPAE_Workflow_Scheduler_Interface $workflow_scheduler = null ) {
		$this->condition_evaluator = $condition_evaluator;
		$this->payload_storage     = $payload_storage;
		$this->workflow_scheduler  = $workflow_scheduler;
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
		$this->register( new WP_Automation_Http_Request_Node() );
		$this->register( new WP_Automation_Save_Payload_Node() );
		$this->register( new WP_Automation_Load_Payload_Batch_Node() );
		$this->register( new WP_Automation_Schedule_Workflow_Node() );
		$this->register( new WP_Automation_Create_Post_Node() );
		$this->register( new WP_Automation_Update_Post_Node() );
		$this->register( new WP_Automation_Create_User_Node() );
		$this->register( new WP_Automation_Update_User_Node() );
		$this->register( new WP_Automation_Woo_Create_Product_Node() );
		$this->register( new WP_Automation_Woo_Update_Product_Node() );
	}
}
