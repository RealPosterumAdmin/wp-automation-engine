<?php

class WP_Automation_Admin {

	protected $plugin_name;
	protected $version;
	protected $storage;
	protected $node_factory;

	public function __construct( $plugin_name, $version, WP_Automation_Storage $storage, WP_Automation_Node_Factory $node_factory ) {
		$this->plugin_name  = $plugin_name;
		$this->version      = $version;
		$this->storage      = $storage;
		$this->node_factory = $node_factory;
	}

	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'Automation Engine', 'wp-automation-engine' ),
			__( 'Automation Engine', 'wp-automation-engine' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'render_admin_page' ),
			'dashicons-randomize'
		);
	}

	public function handle_form_submission() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['wp_automation_engine_save_workflows'] ) ) {
			return;
		}

		check_admin_referer( 'wp_automation_engine_save_workflows' );

		$raw_json = isset( $_POST['wp_automation_engine_workflows_json'] ) ? wp_unslash( $_POST['wp_automation_engine_workflows_json'] ) : '';
		$decoded  = json_decode( $raw_json, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			add_settings_error(
				$this->plugin_name,
				'invalid_json',
				__( 'Invalid workflow JSON.', 'wp-automation-engine' ),
				'error'
			);
			return;
		}

		if ( isset( $decoded['id'] ) ) {
			$decoded = array( $decoded );
		}

		if ( ! is_array( $decoded ) ) {
			add_settings_error(
				$this->plugin_name,
				'invalid_structure',
				__( 'Workflow JSON must be an object or an array of workflow objects.', 'wp-automation-engine' ),
				'error'
			);
			return;
		}

		$this->storage->save_workflows( $decoded );
		$this->storage->clear_cron_events();

		add_settings_error(
			$this->plugin_name,
			'workflows_saved',
			__( 'Workflows saved.', 'wp-automation-engine' ),
			'updated'
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		settings_errors( $this->plugin_name );

		$workflows = $this->storage->get_workflows();
		$logs      = array_reverse( $this->storage->get_logs() );
		$schemas   = $this->node_factory->get_schemas();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'WP Automation Engine', 'wp-automation-engine' ); ?></h1>
			<p><?php echo esc_html__( 'Edit workflow JSON directly, then use the recent execution log to debug runtime flow.', 'wp-automation-engine' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'wp_automation_engine_save_workflows' ); ?>
				<p>
					<label for="wp-automation-engine-workflows-json"><strong><?php echo esc_html__( 'Workflow JSON', 'wp-automation-engine' ); ?></strong></label>
				</p>
				<textarea id="wp-automation-engine-workflows-json" name="wp_automation_engine_workflows_json" rows="24" style="width:100%;font-family:monospace;"><?php echo esc_textarea( wp_json_encode( $workflows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
				<p>
					<button type="submit" class="button button-primary" name="wp_automation_engine_save_workflows" value="1"><?php echo esc_html__( 'Save workflows', 'wp-automation-engine' ); ?></button>
				</p>
			</form>

			<h2><?php echo esc_html__( 'Available Node Schemas', 'wp-automation-engine' ); ?></h2>
			<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;overflow:auto;"><?php echo esc_html( wp_json_encode( $schemas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>

			<h2><?php echo esc_html__( 'Recent Execution Log', 'wp-automation-engine' ); ?></h2>
			<?php if ( empty( $logs ) ) : ?>
				<p><?php echo esc_html__( 'No workflow executions yet.', 'wp-automation-engine' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Time', 'wp-automation-engine' ); ?></th>
							<th><?php echo esc_html__( 'Workflow', 'wp-automation-engine' ); ?></th>
							<th><?php echo esc_html__( 'Node', 'wp-automation-engine' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'wp-automation-engine' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'wp-automation-engine' ); ?></th>
							<th><?php echo esc_html__( 'Context', 'wp-automation-engine' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['time'] ?? '' ); ?></td>
							<td><?php echo esc_html( $log['workflow_id'] ?? '' ); ?></td>
							<td><?php echo esc_html( $log['node_id'] ?? '' ); ?></td>
							<td><?php echo esc_html( $log['status'] ?? '' ); ?></td>
							<td><?php echo esc_html( $log['message'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( wp_json_encode( $log['context'] ?? array(), JSON_UNESCAPED_SLASHES ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
