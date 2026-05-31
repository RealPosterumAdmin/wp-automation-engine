<?php

class WP_Automation_Engine {

	const DEFAULT_WORKFLOW_ID = 'workflow_1';

	protected $loader;
	protected $plugin_name;
	protected $version;
	protected $storage;
	protected $node_factory;

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
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-context.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-condition-evaluator.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/interface-wp-automation-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-set-variable-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-dispatch-event-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-action-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-if-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/nodes/class-wp-automation-loop-node.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-node-factory.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-executor.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-kernel.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-automation-trigger-agent.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin/class-wp-automation-admin.php';

		$this->loader = new WP_Automation_Loader();
	}

	private function set_locale() {
		$plugin_i18n = new WP_Automation_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain', 10, 0 );
	}

	private function boot_runtime() {
		$this->storage      = new WP_Automation_Storage();
		$this->storage->bootstrap_defaults();
		$condition_engine   = new WP_Automation_Condition_Evaluator();
		$this->node_factory = new WP_Automation_Node_Factory( $condition_engine );
		$executor           = new WP_Automation_Executor( $this->storage, $this->node_factory );
		$kernel             = new WP_Automation_Kernel( $this->storage, $executor );
		$trigger_agent      = new WP_Automation_Trigger_Agent( $this->storage, $kernel );

		$trigger_agent->bootstrap();
	}

	private function define_admin_hooks() {
		$plugin_admin = new WP_Automation_Admin( $this->plugin_name, $this->version, $this->storage, $this->node_factory );

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
