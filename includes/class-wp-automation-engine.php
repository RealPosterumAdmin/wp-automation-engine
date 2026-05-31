<?php

class WP_Automation_Engine {

	const DEFAULT_WORKFLOW_ID = 'workflow_1';

	protected $loader;
	protected $plugin_name;
	protected $version;
	protected $storage;
	protected $node_factory;
	protected $kernel;

	public function __construct() {
		$this->version     = defined( 'WP_AUTOMATION_ENGINE_VERSION' ) ? WP_AUTOMATION_ENGINE_VERSION : '1.0.0';
		$this->plugin_name = 'wp-automation-engine';

		$this->load_dependencies();
		$this->set_locale();
		$this->boot_runtime();
		$this->define_admin_hooks();
	}

	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-i18n.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-storage.php';
		$this->require_files_from_directory( plugin_dir_path( dirname( __FILE__ ) ) . 'includes/domain/*.php' );
		$this->require_files_from_directory( plugin_dir_path( dirname( __FILE__ ) ) . 'includes/application/contracts/*.php' );
		$this->require_files_from_directory( plugin_dir_path( dirname( __FILE__ ) ) . 'includes/infrastructure/*.php' );
		$this->require_files_from_directory( plugin_dir_path( dirname( __FILE__ ) ) . 'includes/application/*.php' );
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-context.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-condition-evaluator.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/interface-wp-automation-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-set-variable-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-dispatch-event-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-action-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-if-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-loop-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-http-request-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-build-catalog-tree-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-sync-wc-categories-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-sync-wc-products-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-export-json-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-node-factory.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-executor.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-kernel.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-trigger-agent.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin/class-wp-automation-admin.php';

		$this->loader = new WP_Automation_Loader();
	}

	private function require_files_from_directory( $pattern ) {
		$files = glob( $pattern );

		if ( empty( $files ) ) {
			return;
		}

		sort( $files );

		foreach ( $files as $file ) {
			require_once $file;
		}
	}

	private function set_locale() {
		$plugin_i18n = new WP_Automation_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain', 10, 0 );
	}

	private function boot_runtime() {
		$this->storage      = new WP_Automation_Storage();
		$this->storage->bootstrap_defaults();
		$condition_engine    = new WP_Automation_Condition_Evaluator();
		$http_client         = new WPAE_WordPress_Http_Client();
		$tree_builder        = new WPAE_Catalog_Tree_Builder();
		$workflow_state_store = new WPAE_Workflow_State_Store();
		$json_export_service = new WPAE_JSON_Export_Service();
		$woocommerce_sync    = new WPAE_WooCommerce_Sync_Service( $tree_builder );
		$this->node_factory  = new WP_Automation_Node_Factory( $condition_engine, $http_client, $tree_builder, $woocommerce_sync, $workflow_state_store, $json_export_service );
		$workflow_repository = new WPAE_Storage_Workflow_Repository( $this->storage );
		$run_repository      = new WPAE_Storage_Run_Repository( $this->storage );
		$queue               = new WPAE_Sync_Queue();
		$lock_manager        = new WPAE_Option_Lock_Manager();
		$event_bus           = new WPAE_WordPress_Event_Bus();
		$expression_engine   = new WPAE_Basic_Expression_Evaluator();
		$trigger_registry    = new WPAE_Trigger_Registry();
		$executor            = new WP_Automation_Executor( $this->storage, $this->node_factory, $run_repository, $expression_engine, $lock_manager, $event_bus );
		$execute_run         = new WPAE_Execute_Run( $executor );
		$start_workflow      = new WPAE_Start_Workflow( $workflow_repository, $queue );
		$dispatch_trigger    = new WPAE_Dispatch_Trigger( $start_workflow );
		$this->kernel        = new WP_Automation_Kernel( $dispatch_trigger );
		$trigger_agent       = new WP_Automation_Trigger_Agent( $this->storage, $this->kernel, $trigger_registry );

		$queue->register_handler(
			'execute_workflow',
			static function ( array $payload ) use ( $execute_run ) {
				return $execute_run->execute(
					WPAE_Workflow::from_array( $payload['workflow'] ?? array() ),
					isset( $payload['trigger'] ) && is_array( $payload['trigger'] ) ? $payload['trigger'] : array()
				);
			}
		);

		$trigger_agent->bootstrap();
	}

	private function define_admin_hooks() {
		$plugin_admin = new WP_Automation_Admin( $this->plugin_name, $this->version, $this->storage, $this->node_factory, $this->kernel );

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu', 10, 0 );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'handle_form_submission', 10, 0 );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_assets', 10, 1 );
	}

	public function run() {
		$this->loader->run();
	}

	public static function activate() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-storage.php';
		$storage = new WP_Automation_Storage();
		$storage->bootstrap_defaults();
	}

	public static function deactivate() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-storage.php';
		$storage = new WP_Automation_Storage();
		$storage->clear_cron_events();
	}
}
