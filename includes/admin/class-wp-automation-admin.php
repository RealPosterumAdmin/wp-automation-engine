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

	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_' . $this->plugin_name !== $hook_suffix ) {
			return;
		}

		$plugin_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/wp-automation-engine.php';

		wp_enqueue_style(
			'wp-automation-engine-admin',
			plugins_url( 'assets/css/admin.css', $plugin_file ),
			array(),
			$this->version
		);

		wp_enqueue_script(
			'wp-automation-engine-admin',
			plugins_url( 'assets/js/admin.js', $plugin_file ),
			array(),
			$this->version,
			true
		);
	}

	public function handle_form_submission() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';

		if ( $this->plugin_name !== $page ) {
			return;
		}

		$this->handle_delete_request();
		$this->handle_save_request();
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->register_notice_from_query();

		$action = $this->get_current_action();
		?>
		<div class="wrap wpae-admin">
			<?php settings_errors( $this->plugin_name ); ?>
			<?php if ( 'new' === $action || 'edit' === $action ) : ?>
				<?php $this->render_editor_screen(); ?>
			<?php else : ?>
				<?php $this->render_workflows_screen(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function handle_delete_request() {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'delete' !== $action ) {
			return;
		}

		$workflow_id = isset( $_GET['id'] ) ? sanitize_key( wp_unslash( $_GET['id'] ) ) : '';

		if ( '' === $workflow_id ) {
			return;
		}

		check_admin_referer( 'wp_automation_engine_delete_workflow_' . $workflow_id );

		if ( $this->storage->delete_workflow( $workflow_id ) ) {
			$this->storage->clear_cron_events();
			$this->redirect_to_list( 'deleted' );
		}
	}

	private function handle_save_request() {
		if ( ! isset( $_POST['wp_automation_engine_save_workflow'] ) ) {
			return;
		}

		check_admin_referer( 'wp_automation_engine_save_workflow' );

		$workflow = $this->build_workflow_from_request();

		if ( is_wp_error( $workflow ) ) {
			foreach ( $workflow->get_error_messages() as $message ) {
				add_settings_error( $this->plugin_name, 'workflow_validation_error', $message, 'error' );
			}

			return;
		}

		$mode        = isset( $_POST['wp_automation_engine_workflow_mode'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_workflow_mode'] ) ) : 'create';
		$workflow_id = isset( $_POST['wp_automation_engine_existing_workflow_id'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_existing_workflow_id'] ) ) : '';

		if ( 'edit' === $mode ) {
			$this->storage->clear_workflow_cron_event( $workflow_id );
			$saved_workflow = $this->storage->update_workflow( $workflow_id, $workflow );
			$notice         = 'updated';
		} else {
			$saved_workflow = $this->storage->create_workflow( $workflow );
			$notice         = 'created';
		}

		if ( null === $saved_workflow ) {
			add_settings_error(
				$this->plugin_name,
				'workflow_save_error',
				__( 'Workflow could not be saved.', 'wp-automation-engine' ),
				'error'
			);
			return;
		}

		$this->storage->clear_cron_events();
		$this->redirect_to_edit( $saved_workflow['id'], $notice );
	}

	private function build_workflow_from_request() {
		$mode               = isset( $_POST['wp_automation_engine_workflow_mode'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_workflow_mode'] ) ) : 'create';
		$existing_id        = isset( $_POST['wp_automation_engine_existing_workflow_id'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_existing_workflow_id'] ) ) : '';
		$requested_id       = isset( $_POST['wp_automation_engine_workflow_id'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_workflow_id'] ) ) : '';
		$name               = isset( $_POST['wp_automation_engine_workflow_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_automation_engine_workflow_name'] ) ) : '';
		$trigger_type       = isset( $_POST['wp_automation_engine_trigger_type'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_trigger_type'] ) ) : 'action';
		$trigger_hook       = isset( $_POST['wp_automation_engine_trigger_hook'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_automation_engine_trigger_hook'] ) ) : '';
		$trigger_schedule   = isset( $_POST['wp_automation_engine_trigger_schedule'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_trigger_schedule'] ) ) : 'hourly';
		$trigger_event      = isset( $_POST['wp_automation_engine_trigger_event'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_automation_engine_trigger_event'] ) ) : '';
		$variables_json_raw = isset( $_POST['wp_automation_engine_workflow_variables_json'] ) ? wp_unslash( $_POST['wp_automation_engine_workflow_variables_json'] ) : '';
		$nodes_json_raw     = isset( $_POST['wp_automation_engine_workflow_nodes_json'] ) ? wp_unslash( $_POST['wp_automation_engine_workflow_nodes_json'] ) : '';
		$enabled            = ! empty( $_POST['wp_automation_engine_workflow_enabled'] );

		if ( '' === $name ) {
			return new WP_Error( 'missing_name', __( 'Workflow name is required.', 'wp-automation-engine' ) );
		}

		$allowed_trigger_types = array( 'action', 'filter', 'cron', 'internal_event' );

		if ( ! in_array( $trigger_type, $allowed_trigger_types, true ) ) {
			return new WP_Error( 'invalid_trigger_type', __( 'Trigger type is invalid.', 'wp-automation-engine' ) );
		}

		if ( 'edit' === $mode && '' === $existing_id ) {
			return new WP_Error( 'missing_workflow_id', __( 'Workflow ID is missing.', 'wp-automation-engine' ) );
		}

		if ( 'create' === $mode && '' !== $requested_id && $this->storage->workflow_exists( $requested_id ) ) {
			return new WP_Error( 'duplicate_workflow_id', __( 'Workflow ID already exists.', 'wp-automation-engine' ) );
		}

		if ( in_array( $trigger_type, array( 'action', 'filter' ), true ) && '' === $trigger_hook ) {
			return new WP_Error( 'missing_trigger_hook', __( 'Hook name is required for action and filter triggers.', 'wp-automation-engine' ) );
		}

		if ( 'cron' === $trigger_type && '' === $trigger_schedule ) {
			return new WP_Error( 'missing_trigger_schedule', __( 'Schedule is required for cron triggers.', 'wp-automation-engine' ) );
		}

		if ( 'internal_event' === $trigger_type && '' === $trigger_event ) {
			return new WP_Error( 'missing_trigger_event', __( 'Event name is required for internal event triggers.', 'wp-automation-engine' ) );
		}

		$variables = $this->decode_json_field( $variables_json_raw, __( 'Variables JSON must be a valid object or array.', 'wp-automation-engine' ) );

		if ( is_wp_error( $variables ) ) {
			return $variables;
		}

		$nodes = $this->decode_json_field( $nodes_json_raw, __( 'Nodes JSON must be a valid array.', 'wp-automation-engine' ) );

		if ( is_wp_error( $nodes ) ) {
			return $nodes;
		}

		return array(
			'id'      => 'edit' === $mode ? $existing_id : $requested_id,
			'name'    => $name,
			'enabled' => $enabled,
			'trigger' => array(
				'type'     => $trigger_type,
				'hook'     => $trigger_hook,
				'schedule' => $trigger_schedule,
				'event'    => $trigger_event,
			),
			'variables' => $variables,
			'nodes'     => $nodes,
		);
	}

	private function decode_json_field( $raw_value, $error_message ) {
		$raw_value = trim( $raw_value );

		if ( '' === $raw_value ) {
			return array();
		}

		$decoded = json_decode( $raw_value, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new WP_Error( 'invalid_json_field', $error_message );
		}

		return $decoded;
	}

	private function render_workflows_screen() {
		$workflows = $this->storage->get_workflows();
		$logs      = array_reverse( $this->storage->get_logs() );
		?>
		<div class="wpae-admin-header">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Workflows', 'wp-automation-engine' ); ?></h1>
			<a href="<?php echo esc_url( $this->get_new_url() ); ?>" class="page-title-action"><?php echo esc_html__( 'Add New', 'wp-automation-engine' ); ?></a>
		</div>
		<p><?php echo esc_html__( 'Manage workflows from a WordPress-style list instead of editing the full option JSON by hand.', 'wp-automation-engine' ); ?></p>

		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Name', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'ID', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Trigger', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Enabled', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Nodes', 'wp-automation-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $workflows ) ) : ?>
					<tr>
						<td colspan="5"><?php echo esc_html__( 'No workflows found.', 'wp-automation-engine' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $workflows as $workflow ) : ?>
						<tr>
							<td>
								<strong>
									<a href="<?php echo esc_url( $this->get_edit_url( $workflow['id'] ) ); ?>">
										<?php echo esc_html( $workflow['name'] ); ?>
									</a>
								</strong>
								<div class="row-actions">
									<span class="edit"><a href="<?php echo esc_url( $this->get_edit_url( $workflow['id'] ) ); ?>"><?php echo esc_html__( 'Edit', 'wp-automation-engine' ); ?></a></span>
									<span class="delete"> | <a href="<?php echo esc_url( $this->get_delete_url( $workflow['id'] ) ); ?>"><?php echo esc_html__( 'Delete', 'wp-automation-engine' ); ?></a></span>
								</div>
							</td>
							<td><code><?php echo esc_html( $workflow['id'] ); ?></code></td>
							<td><?php echo esc_html( $this->get_trigger_summary( $workflow['trigger'] ?? array() ) ); ?></td>
							<td>
								<span class="dashicons <?php echo ! empty( $workflow['enabled'] ) ? esc_attr( 'dashicons-yes-alt wpae-status-enabled' ) : esc_attr( 'dashicons-dismiss wpae-status-disabled' ); ?>" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php echo ! empty( $workflow['enabled'] ) ? esc_html__( 'Enabled', 'wp-automation-engine' ) : esc_html__( 'Disabled', 'wp-automation-engine' ); ?></span>
							</td>
							<td><?php echo esc_html( count( $workflow['nodes'] ?? array() ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

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
		<?php
	}

	private function render_editor_screen() {
		$state = $this->get_editor_state();

		if ( empty( $state ) ) {
			echo '<p>' . esc_html__( 'Workflow not found.', 'wp-automation-engine' ) . '</p>';
			echo '<p><a href="' . esc_url( $this->get_list_url() ) . '" class="button">' . esc_html__( 'Back to workflows', 'wp-automation-engine' ) . '</a></p>';
			return;
		}

		$is_edit = 'edit' === $state['mode'];
		?>
		<div class="wpae-admin-header">
			<h1 class="wp-heading-inline"><?php echo esc_html( $is_edit ? __( 'Edit Workflow', 'wp-automation-engine' ) : __( 'Add Workflow', 'wp-automation-engine' ) ); ?></h1>
			<a href="<?php echo esc_url( $this->get_list_url() ); ?>" class="page-title-action"><?php echo esc_html__( 'Back to Workflows', 'wp-automation-engine' ); ?></a>
		</div>

		<div class="wpae-admin-layout">
			<div class="wpae-admin-main">
				<form method="post">
					<?php wp_nonce_field( 'wp_automation_engine_save_workflow' ); ?>
					<input type="hidden" name="page" value="<?php echo esc_attr( $this->plugin_name ); ?>">
					<input type="hidden" name="wp_automation_engine_workflow_mode" value="<?php echo esc_attr( $state['mode'] ); ?>">
					<input type="hidden" name="wp_automation_engine_existing_workflow_id" value="<?php echo esc_attr( $state['existing_id'] ); ?>">

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="wp-automation-engine-workflow-name"><?php echo esc_html__( 'Name', 'wp-automation-engine' ); ?></label></th>
								<td>
									<input type="text" id="wp-automation-engine-workflow-name" name="wp_automation_engine_workflow_name" class="regular-text" value="<?php echo esc_attr( $state['name'] ); ?>" required>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wp-automation-engine-workflow-id"><?php echo esc_html__( 'Workflow ID', 'wp-automation-engine' ); ?></label></th>
								<td>
									<?php if ( $is_edit ) : ?>
										<input type="text" id="wp-automation-engine-workflow-id" class="regular-text" value="<?php echo esc_attr( $state['existing_id'] ); ?>" readonly>
									<?php else : ?>
										<input type="text" id="wp-automation-engine-workflow-id" name="wp_automation_engine_workflow_id" class="regular-text" value="<?php echo esc_attr( $state['requested_id'] ); ?>">
										<p class="description"><?php echo esc_html__( 'Optional. Leave empty to generate an ID automatically.', 'wp-automation-engine' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Enabled', 'wp-automation-engine' ); ?></th>
								<td>
									<label for="wp-automation-engine-workflow-enabled">
										<input type="checkbox" id="wp-automation-engine-workflow-enabled" name="wp_automation_engine_workflow_enabled" value="1" <?php checked( $state['enabled'] ); ?>>
										<?php echo esc_html__( 'Workflow is active', 'wp-automation-engine' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wp-automation-engine-trigger-type"><?php echo esc_html__( 'Trigger Type', 'wp-automation-engine' ); ?></label></th>
								<td>
									<select id="wp-automation-engine-trigger-type" name="wp_automation_engine_trigger_type">
										<option value="action" <?php selected( $state['trigger_type'], 'action' ); ?>><?php echo esc_html__( 'WordPress Action', 'wp-automation-engine' ); ?></option>
										<option value="filter" <?php selected( $state['trigger_type'], 'filter' ); ?>><?php echo esc_html__( 'WordPress Filter', 'wp-automation-engine' ); ?></option>
										<option value="cron" <?php selected( $state['trigger_type'], 'cron' ); ?>><?php echo esc_html__( 'Cron', 'wp-automation-engine' ); ?></option>
										<option value="internal_event" <?php selected( $state['trigger_type'], 'internal_event' ); ?>><?php echo esc_html__( 'Internal Event', 'wp-automation-engine' ); ?></option>
									</select>
								</td>
							</tr>
							<tr class="wpae-trigger-row" data-trigger-types="action filter">
								<th scope="row"><label for="wp-automation-engine-trigger-hook"><?php echo esc_html__( 'Hook Name', 'wp-automation-engine' ); ?></label></th>
								<td>
									<input type="text" id="wp-automation-engine-trigger-hook" name="wp_automation_engine_trigger_hook" class="regular-text" value="<?php echo esc_attr( $state['trigger_hook'] ); ?>">
								</td>
							</tr>
							<tr class="wpae-trigger-row" data-trigger-types="cron">
								<th scope="row"><label for="wp-automation-engine-trigger-schedule"><?php echo esc_html__( 'Schedule', 'wp-automation-engine' ); ?></label></th>
								<td>
									<select id="wp-automation-engine-trigger-schedule" name="wp_automation_engine_trigger_schedule">
										<?php foreach ( wp_get_schedules() as $schedule_key => $schedule ) : ?>
											<option value="<?php echo esc_attr( $schedule_key ); ?>" <?php selected( $state['trigger_schedule'], $schedule_key ); ?>><?php echo esc_html( $schedule['display'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr class="wpae-trigger-row" data-trigger-types="internal_event">
								<th scope="row"><label for="wp-automation-engine-trigger-event"><?php echo esc_html__( 'Event Name', 'wp-automation-engine' ); ?></label></th>
								<td>
									<input type="text" id="wp-automation-engine-trigger-event" name="wp_automation_engine_trigger_event" class="regular-text" value="<?php echo esc_attr( $state['trigger_event'] ); ?>">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wp-automation-engine-variables-json"><?php echo esc_html__( 'Variables', 'wp-automation-engine' ); ?></label></th>
								<td>
									<textarea id="wp-automation-engine-variables-json" name="wp_automation_engine_workflow_variables_json" class="large-text code" rows="8"><?php echo esc_textarea( $state['variables_json'] ); ?></textarea>
									<p class="description"><?php echo esc_html__( 'Use a JSON object or array for workflow variables.', 'wp-automation-engine' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wp-automation-engine-nodes-json"><?php echo esc_html__( 'Nodes', 'wp-automation-engine' ); ?></label></th>
								<td>
									<textarea id="wp-automation-engine-nodes-json" name="wp_automation_engine_workflow_nodes_json" class="large-text code" rows="16"><?php echo esc_textarea( $state['nodes_json'] ); ?></textarea>
									<p class="description"><?php echo esc_html__( 'Nodes are stored as a JSON array. Use the schema guide on the right to build node payloads.', 'wp-automation-engine' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>

					<?php submit_button( $is_edit ? __( 'Update Workflow', 'wp-automation-engine' ) : __( 'Create Workflow', 'wp-automation-engine' ), 'primary', 'wp_automation_engine_save_workflow' ); ?>
				</form>

				<?php $this->render_node_summary( $state['nodes'] ); ?>
			</div>

			<div class="wpae-admin-sidebar">
				<?php $this->render_schema_guide(); ?>
			</div>
		</div>
		<?php
	}

	private function render_node_summary( array $nodes ) {
		?>
		<h2><?php echo esc_html__( 'Node Summary', 'wp-automation-engine' ); ?></h2>
		<?php if ( empty( $nodes ) ) : ?>
			<p><?php echo esc_html__( 'No nodes configured yet.', 'wp-automation-engine' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Node ID', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Type', 'wp-automation-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $nodes as $node ) : ?>
						<tr>
							<td><code><?php echo esc_html( isset( $node['id'] ) ? (string) $node['id'] : '' ); ?></code></td>
							<td><?php echo esc_html( isset( $node['type'] ) ? (string) $node['type'] : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private function render_schema_guide() {
		$schemas = $this->node_factory->get_schemas();
		?>
		<h2><?php echo esc_html__( 'Available Node Schemas', 'wp-automation-engine' ); ?></h2>
		<div class="wpae-schema-list">
			<?php foreach ( $schemas as $schema ) : ?>
				<div class="postbox">
					<div class="postbox-header">
						<h3 class="hndle">
							<span class="dashicons dashicons-<?php echo esc_attr( $schema['icon'] ?? 'admin-generic' ); ?>" aria-hidden="true"></span>
							<?php echo esc_html( $schema['label'] ?? ( $schema['type'] ?? '' ) ); ?>
						</h3>
					</div>
					<div class="inside">
						<p><code><?php echo esc_html( $schema['type'] ?? '' ); ?></code></p>
						<?php if ( ! empty( $schema['fields'] ) && is_array( $schema['fields'] ) ) : ?>
							<ul>
								<?php foreach ( $schema['fields'] as $field ) : ?>
									<li>
										<strong><?php echo esc_html( $field['name'] ?? '' ); ?></strong>
										<span class="description">— <?php echo esc_html( $field['type'] ?? '' ); ?></span>
										<?php if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) : ?>
											<div class="description"><?php echo esc_html( implode( ', ', $field['options'] ) ); ?></div>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function get_editor_state() {
		if ( isset( $_POST['wp_automation_engine_save_workflow'] ) ) {
			return $this->get_editor_state_from_post();
		}

		$action = $this->get_current_action();

		if ( 'edit' === $action ) {
			$workflow_id = isset( $_GET['id'] ) ? sanitize_key( wp_unslash( $_GET['id'] ) ) : '';
			$workflow    = $this->storage->get_workflow( $workflow_id );

			if ( ! $workflow ) {
				return array();
			}

			return array(
				'mode'             => 'edit',
				'existing_id'      => $workflow['id'],
				'requested_id'     => '',
				'name'             => $workflow['name'],
				'enabled'          => ! empty( $workflow['enabled'] ),
				'trigger_type'     => $workflow['trigger']['type'] ?? 'action',
				'trigger_hook'     => $workflow['trigger']['hook'] ?? '',
				'trigger_schedule' => $workflow['trigger']['schedule'] ?? 'hourly',
				'trigger_event'    => $workflow['trigger']['event'] ?? '',
				'variables_json'   => wp_json_encode( $workflow['variables'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
				'nodes_json'       => wp_json_encode( $workflow['nodes'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
				'nodes'            => is_array( $workflow['nodes'] ?? null ) ? $workflow['nodes'] : array(),
			);
		}

		return array(
			'mode'             => 'create',
			'existing_id'      => '',
			'requested_id'     => '',
			'name'             => '',
			'enabled'          => false,
			'trigger_type'     => 'action',
			'trigger_hook'     => 'init',
			'trigger_schedule' => 'hourly',
			'trigger_event'    => '',
			'variables_json'   => "{\n\n}",
			'nodes_json'       => "[]",
			'nodes'            => array(),
		);
	}

	private function get_editor_state_from_post() {
		$nodes_json = isset( $_POST['wp_automation_engine_workflow_nodes_json'] ) ? wp_unslash( $_POST['wp_automation_engine_workflow_nodes_json'] ) : '[]';
		$nodes      = json_decode( $nodes_json, true );

		return array(
			'mode'             => isset( $_POST['wp_automation_engine_workflow_mode'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_workflow_mode'] ) ) : 'create',
			'existing_id'      => isset( $_POST['wp_automation_engine_existing_workflow_id'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_existing_workflow_id'] ) ) : '',
			'requested_id'     => isset( $_POST['wp_automation_engine_workflow_id'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_workflow_id'] ) ) : '',
			'name'             => isset( $_POST['wp_automation_engine_workflow_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_automation_engine_workflow_name'] ) ) : '',
			'enabled'          => ! empty( $_POST['wp_automation_engine_workflow_enabled'] ),
			'trigger_type'     => isset( $_POST['wp_automation_engine_trigger_type'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_trigger_type'] ) ) : 'action',
			'trigger_hook'     => isset( $_POST['wp_automation_engine_trigger_hook'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_automation_engine_trigger_hook'] ) ) : '',
			'trigger_schedule' => isset( $_POST['wp_automation_engine_trigger_schedule'] ) ? sanitize_key( wp_unslash( $_POST['wp_automation_engine_trigger_schedule'] ) ) : 'hourly',
			'trigger_event'    => isset( $_POST['wp_automation_engine_trigger_event'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_automation_engine_trigger_event'] ) ) : '',
			'variables_json'   => isset( $_POST['wp_automation_engine_workflow_variables_json'] ) ? wp_unslash( $_POST['wp_automation_engine_workflow_variables_json'] ) : "{\n\n}",
			'nodes_json'       => $nodes_json,
			'nodes'            => is_array( $nodes ) ? $nodes : array(),
		);
	}

	private function get_current_action() {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';

		return in_array( $action, array( 'new', 'edit' ), true ) ? $action : 'list';
	}

	private function get_trigger_summary( array $trigger ) {
		$type = $trigger['type'] ?? '';

		switch ( $type ) {
			case 'action':
				return sprintf(
					/* translators: %s: action hook. */
					__( 'Action: %s', 'wp-automation-engine' ),
					$trigger['hook'] ?? ''
				);
			case 'filter':
				return sprintf(
					/* translators: %s: filter hook. */
					__( 'Filter: %s', 'wp-automation-engine' ),
					$trigger['hook'] ?? ''
				);
			case 'cron':
				return sprintf(
					/* translators: %s: cron schedule. */
					__( 'Cron: %s', 'wp-automation-engine' ),
					$trigger['schedule'] ?? ''
				);
			case 'internal_event':
				return sprintf(
					/* translators: %s: internal event. */
					__( 'Event: %s', 'wp-automation-engine' ),
					$trigger['event'] ?? ''
				);
			default:
				return __( 'Not configured', 'wp-automation-engine' );
		}
	}

	private function register_notice_from_query() {
		$notice = isset( $_GET['wpae_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpae_notice'] ) ) : '';

		switch ( $notice ) {
			case 'created':
				add_settings_error( $this->plugin_name, 'workflow_created', __( 'Workflow created.', 'wp-automation-engine' ), 'updated' );
				break;
			case 'updated':
				add_settings_error( $this->plugin_name, 'workflow_updated', __( 'Workflow updated.', 'wp-automation-engine' ), 'updated' );
				break;
			case 'deleted':
				add_settings_error( $this->plugin_name, 'workflow_deleted', __( 'Workflow deleted.', 'wp-automation-engine' ), 'updated' );
				break;
		}
	}

	private function get_list_url() {
		return admin_url( 'admin.php?page=' . $this->plugin_name );
	}

	private function get_new_url() {
		return admin_url( 'admin.php?page=' . $this->plugin_name . '&action=new' );
	}

	private function get_edit_url( $workflow_id ) {
		return admin_url( 'admin.php?page=' . $this->plugin_name . '&action=edit&id=' . rawurlencode( $workflow_id ) );
	}

	private function get_delete_url( $workflow_id ) {
		return wp_nonce_url(
			admin_url( 'admin.php?page=' . $this->plugin_name . '&action=delete&id=' . rawurlencode( $workflow_id ) ),
			'wp_automation_engine_delete_workflow_' . $workflow_id
		);
	}

	private function redirect_to_list( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => $this->plugin_name,
					'wpae_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function redirect_to_edit( $workflow_id, $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => $this->plugin_name,
					'action'      => 'edit',
					'id'          => $workflow_id,
					'wpae_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
