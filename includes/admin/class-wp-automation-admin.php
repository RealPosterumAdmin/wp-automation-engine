<?php

class WP_Automation_Admin {

	protected $plugin_name;
	protected $version;
	protected $storage;
	protected $node_factory;
	protected $kernel;
	protected $state_store;

	public function __construct( $plugin_name, $version, WP_Automation_Storage $storage, WP_Automation_Node_Factory $node_factory, WP_Automation_Kernel $kernel ) {
		$this->plugin_name  = $plugin_name;
		$this->version      = $version;
		$this->storage      = $storage;
		$this->node_factory = $node_factory;
		$this->kernel       = $kernel;
		$this->state_store  = new WPAE_Workflow_State_Store();
	}

	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'Движок автоматизации', 'wp-automation-engine' ),
			__( 'Движок автоматизации', 'wp-automation-engine' ),
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

		wp_localize_script(
			'wp-automation-engine-admin',
			'wpaeAdminConfig',
			array(
				'schemas' => $this->node_factory->get_schemas(),
			)
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
		$this->handle_run_request();
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
			$this->state_store->delete_workflow_states( $workflow_id );
			$this->redirect_to_list( 'deleted' );
		}
	}

	private function handle_run_request() {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'run' !== $action ) {
			return;
		}

		$workflow_id = isset( $_GET['id'] ) ? sanitize_key( wp_unslash( $_GET['id'] ) ) : '';

		if ( '' === $workflow_id ) {
			return;
		}

		check_admin_referer( 'wp_automation_engine_run_workflow_' . $workflow_id );

		$result = $this->kernel->run_manual_workflow(
			$workflow_id,
			array(
				'source'  => 'admin',
				'user_id' => get_current_user_id(),
			)
		);

		if ( is_wp_error( $result ) ) {
			add_settings_error( $this->plugin_name, 'workflow_run_error', $result->get_error_message(), 'error' );
			return;
		}

		$this->redirect_to_edit( $workflow_id, 'run_started' );
	}

	private function format_schema_field_options( array $options ) {
		$labels = array();

		foreach ( $options as $option ) {
			if ( is_array( $option ) ) {
				$labels[] = isset( $option['label'] ) ? (string) $option['label'] : (string) ( $option['value'] ?? '' );
				continue;
			}

			$labels[] = (string) $option;
		}

		return implode( ', ', array_filter( $labels ) );
	}

	private function get_log_status_label( $status ) {
		switch ( $status ) {
			case 'started':
				return __( 'Запуск', 'wp-automation-engine' );
			case 'success':
				return __( 'Успех', 'wp-automation-engine' );
			case 'error':
				return __( 'Ошибка', 'wp-automation-engine' );
			case 'info':
				return __( 'Инфо', 'wp-automation-engine' );
			case 'skipped':
				return __( 'Пропущено', 'wp-automation-engine' );
			default:
				return (string) $status;
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
				__( 'Не удалось сохранить сценарий.', 'wp-automation-engine' ),
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
			return new WP_Error( 'missing_name', __( 'Укажите название сценария.', 'wp-automation-engine' ) );
		}

		$allowed_trigger_types = array( 'action', 'filter', 'cron', 'internal_event', 'manual' );

		if ( ! in_array( $trigger_type, $allowed_trigger_types, true ) ) {
			return new WP_Error( 'invalid_trigger_type', __( 'Указан некорректный тип триггера.', 'wp-automation-engine' ) );
		}

		if ( 'edit' === $mode && '' === $existing_id ) {
			return new WP_Error( 'missing_workflow_id', __( 'Не найден ID сценария.', 'wp-automation-engine' ) );
		}

		if ( 'create' === $mode && '' !== $requested_id && $this->storage->workflow_exists( $requested_id ) ) {
			return new WP_Error( 'duplicate_workflow_id', __( 'Сценарий с таким ID уже существует.', 'wp-automation-engine' ) );
		}

		if ( in_array( $trigger_type, array( 'action', 'filter' ), true ) && '' === $trigger_hook ) {
			return new WP_Error( 'missing_trigger_hook', __( 'Для action и filter нужно указать имя хука.', 'wp-automation-engine' ) );
		}

		if ( 'cron' === $trigger_type && '' === $trigger_schedule ) {
			return new WP_Error( 'missing_trigger_schedule', __( 'Для cron нужно указать расписание.', 'wp-automation-engine' ) );
		}

		if ( 'internal_event' === $trigger_type && '' === $trigger_event ) {
			return new WP_Error( 'missing_trigger_event', __( 'Для внутреннего события нужно указать имя события.', 'wp-automation-engine' ) );
		}

		$variables = $this->decode_json_field( $variables_json_raw, __( 'Переменные сценария должны быть корректным объектом или массивом JSON.', 'wp-automation-engine' ) );

		if ( is_wp_error( $variables ) ) {
			return $variables;
		}

		$nodes = $this->decode_json_field( $nodes_json_raw, __( 'Узлы сценария должны быть корректным массивом JSON.', 'wp-automation-engine' ) );

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
		$runs      = array_reverse( $this->storage->get_runs() );
		?>
		<div class="wpae-admin-header">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Сценарии', 'wp-automation-engine' ); ?></h1>
			<a href="<?php echo esc_url( $this->get_new_url() ); ?>" class="page-title-action"><?php echo esc_html__( 'Добавить сценарий', 'wp-automation-engine' ); ?></a>
		</div>
		<p><?php echo esc_html__( 'Редактируйте сценарии через визуальный интерфейс. JSON обновляется автоматически и сохраняется как внутренний формат.', 'wp-automation-engine' ); ?></p>

		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Название', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'ID', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Триггер', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Активен', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Узлы', 'wp-automation-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $workflows ) ) : ?>
					<tr>
						<td colspan="5"><?php echo esc_html__( 'Сценарии пока не созданы.', 'wp-automation-engine' ); ?></td>
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
									<span class="edit"><a href="<?php echo esc_url( $this->get_edit_url( $workflow['id'] ) ); ?>"><?php echo esc_html__( 'Редактировать', 'wp-automation-engine' ); ?></a></span>
									<span class="view"> | <a href="<?php echo esc_url( $this->get_run_url( $workflow['id'] ) ); ?>"><?php echo esc_html__( 'Запустить', 'wp-automation-engine' ); ?></a></span>
									<span class="delete"> | <a href="<?php echo esc_url( $this->get_delete_url( $workflow['id'] ) ); ?>"><?php echo esc_html__( 'Удалить', 'wp-automation-engine' ); ?></a></span>
								</div>
							</td>
							<td><code><?php echo esc_html( $workflow['id'] ); ?></code></td>
							<td><?php echo esc_html( $this->get_trigger_summary( $workflow['trigger'] ?? array() ) ); ?></td>
							<td>
								<span class="dashicons <?php echo ! empty( $workflow['enabled'] ) ? esc_attr( 'dashicons-yes-alt wpae-status-enabled' ) : esc_attr( 'dashicons-dismiss wpae-status-disabled' ); ?>" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php echo ! empty( $workflow['enabled'] ) ? esc_html__( 'Активен', 'wp-automation-engine' ) : esc_html__( 'Отключен', 'wp-automation-engine' ); ?></span>
							</td>
							<td><?php echo esc_html( count( $workflow['nodes'] ?? array() ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<h2><?php echo esc_html__( 'Последние выполнения', 'wp-automation-engine' ); ?></h2>
		<?php if ( empty( $runs ) ) : ?>
			<p><?php echo esc_html__( 'История запусков пока пуста.', 'wp-automation-engine' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Run ID', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Сценарий', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Статус', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Триггер', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Запущен', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Завершен', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Шагов', 'wp-automation-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $runs as $run ) : ?>
						<tr>
							<td><code><?php echo esc_html( $run['id'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $run['workflow_name'] ?? ( $run['workflow_id'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( $this->get_log_status_label( $run['status'] ?? '' ) ); ?></td>
							<td><code><?php echo esc_html( wp_json_encode( $run['trigger_data'] ?? array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></code></td>
							<td><?php echo esc_html( $run['started_at'] ?? '' ); ?></td>
							<td><?php echo esc_html( $run['finished_at'] ?? '' ); ?></td>
							<td><?php echo esc_html( count( $run['steps'] ?? array() ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php echo esc_html__( 'Состояние синхронизации', 'wp-automation-engine' ); ?></h2>
		<?php $this->render_workflow_state_table(); ?>

		<h2><?php echo esc_html__( 'Последние записи логов', 'wp-automation-engine' ); ?></h2>
		<?php if ( empty( $logs ) ) : ?>
			<p><?php echo esc_html__( 'Выполнений сценариев пока нет.', 'wp-automation-engine' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Время', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Сценарий', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Узел', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Статус', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Сообщение', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Контекст', 'wp-automation-engine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['time'] ?? '' ); ?></td>
							<td><?php echo esc_html( $log['workflow_id'] ?? '' ); ?></td>
							<td><?php echo esc_html( $log['node_id'] ?? '' ); ?></td>
							<td><?php echo esc_html( $this->get_log_status_label( $log['status'] ?? '' ) ); ?></td>
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
			echo '<p>' . esc_html__( 'Сценарий не найден.', 'wp-automation-engine' ) . '</p>';
			echo '<p><a href="' . esc_url( $this->get_list_url() ) . '" class="button">' . esc_html__( 'Назад к сценариям', 'wp-automation-engine' ) . '</a></p>';
			return;
		}

		$is_edit = 'edit' === $state['mode'];
		?>
		<div class="wpae-admin-header">
			<h1 class="wp-heading-inline"><?php echo esc_html( $is_edit ? __( 'Редактирование сценария', 'wp-automation-engine' ) : __( 'Новый сценарий', 'wp-automation-engine' ) ); ?></h1>
			<a href="<?php echo esc_url( $this->get_list_url() ); ?>" class="page-title-action"><?php echo esc_html__( 'Назад к сценариям', 'wp-automation-engine' ); ?></a>
			<?php if ( $is_edit ) : ?>
				<a href="<?php echo esc_url( $this->get_run_url( $state['existing_id'] ) ); ?>" class="page-title-action"><?php echo esc_html__( 'Запустить сейчас', 'wp-automation-engine' ); ?></a>
			<?php endif; ?>
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
								<th scope="row"><label for="wp-automation-engine-workflow-name"><?php echo esc_html__( 'Название', 'wp-automation-engine' ); ?></label></th>
								<td>
									<input type="text" id="wp-automation-engine-workflow-name" name="wp_automation_engine_workflow_name" class="regular-text" value="<?php echo esc_attr( $state['name'] ); ?>" required>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wp-automation-engine-workflow-id"><?php echo esc_html__( 'ID сценария', 'wp-automation-engine' ); ?></label></th>
								<td>
									<?php if ( $is_edit ) : ?>
										<input type="text" id="wp-automation-engine-workflow-id" class="regular-text" value="<?php echo esc_attr( $state['existing_id'] ); ?>" readonly>
									<?php else : ?>
										<input type="text" id="wp-automation-engine-workflow-id" name="wp_automation_engine_workflow_id" class="regular-text" value="<?php echo esc_attr( $state['requested_id'] ); ?>">
										<p class="description"><?php echo esc_html__( 'Необязательно. Оставьте пустым, чтобы ID создался автоматически.', 'wp-automation-engine' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Активность', 'wp-automation-engine' ); ?></th>
								<td>
									<label for="wp-automation-engine-workflow-enabled">
										<input type="checkbox" id="wp-automation-engine-workflow-enabled" name="wp_automation_engine_workflow_enabled" value="1" <?php checked( $state['enabled'] ); ?>>
										<?php echo esc_html__( 'Сценарий активен', 'wp-automation-engine' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wp-automation-engine-trigger-type"><?php echo esc_html__( 'Тип триггера', 'wp-automation-engine' ); ?></label></th>
								<td>
									<select id="wp-automation-engine-trigger-type" name="wp_automation_engine_trigger_type">
										<option value="action" <?php selected( $state['trigger_type'], 'action' ); ?>><?php echo esc_html__( 'Action WordPress', 'wp-automation-engine' ); ?></option>
										<option value="filter" <?php selected( $state['trigger_type'], 'filter' ); ?>><?php echo esc_html__( 'Filter WordPress', 'wp-automation-engine' ); ?></option>
										<option value="cron" <?php selected( $state['trigger_type'], 'cron' ); ?>><?php echo esc_html__( 'Cron', 'wp-automation-engine' ); ?></option>
										<option value="internal_event" <?php selected( $state['trigger_type'], 'internal_event' ); ?>><?php echo esc_html__( 'Внутреннее событие', 'wp-automation-engine' ); ?></option>
										<option value="manual" <?php selected( $state['trigger_type'], 'manual' ); ?>><?php echo esc_html__( 'Ручной запуск', 'wp-automation-engine' ); ?></option>
									</select>
								</td>
							</tr>
							<tr class="wpae-trigger-row" data-trigger-types="action filter">
								<th scope="row"><label for="wp-automation-engine-trigger-hook"><?php echo esc_html__( 'Имя хука', 'wp-automation-engine' ); ?></label></th>
								<td>
									<input type="text" id="wp-automation-engine-trigger-hook" name="wp_automation_engine_trigger_hook" class="regular-text" value="<?php echo esc_attr( $state['trigger_hook'] ); ?>">
								</td>
							</tr>
							<tr class="wpae-trigger-row" data-trigger-types="cron">
								<th scope="row"><label for="wp-automation-engine-trigger-schedule"><?php echo esc_html__( 'Расписание', 'wp-automation-engine' ); ?></label></th>
								<td>
									<select id="wp-automation-engine-trigger-schedule" name="wp_automation_engine_trigger_schedule">
										<?php foreach ( wp_get_schedules() as $schedule_key => $schedule ) : ?>
											<option value="<?php echo esc_attr( $schedule_key ); ?>" <?php selected( $state['trigger_schedule'], $schedule_key ); ?>><?php echo esc_html( $schedule['display'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr class="wpae-trigger-row" data-trigger-types="internal_event">
								<th scope="row"><label for="wp-automation-engine-trigger-event"><?php echo esc_html__( 'Имя события', 'wp-automation-engine' ); ?></label></th>
								<td>
									<input type="text" id="wp-automation-engine-trigger-event" name="wp_automation_engine_trigger_event" class="regular-text" value="<?php echo esc_attr( $state['trigger_event'] ); ?>">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wp-automation-engine-variables-json"><?php echo esc_html__( 'Переменные', 'wp-automation-engine' ); ?></label></th>
								<td>
									<div id="wpae-variable-editor" class="wpae-data-root" data-target-input="wp-automation-engine-variables-json"></div>
									<p class="description"><?php echo esc_html__( 'Редактор меняет структуру переменных визуально, а JSON обновляется автоматически.', 'wp-automation-engine' ); ?></p>
									<textarea id="wp-automation-engine-variables-json" name="wp_automation_engine_workflow_variables_json" class="wpae-json-storage-field" rows="8"><?php echo esc_textarea( $state['variables_json'] ); ?></textarea>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wp-automation-engine-nodes-json"><?php echo esc_html__( 'Узлы', 'wp-automation-engine' ); ?></label></th>
								<td>
									<div id="wpae-node-editor" class="wpae-node-editor" data-target-input="wp-automation-engine-nodes-json"></div>
									<p class="description"><?php echo esc_html__( 'Редактор управляет JSON узлов автоматически. Логика выполнения и интерфейс остаются независимыми.', 'wp-automation-engine' ); ?></p>
									<textarea id="wp-automation-engine-nodes-json" name="wp_automation_engine_workflow_nodes_json" class="wpae-json-storage-field" rows="16"><?php echo esc_textarea( $state['nodes_json'] ); ?></textarea>
									<noscript>
										<p class="description"><?php echo esc_html__( 'Для визуального редактора нужен JavaScript.', 'wp-automation-engine' ); ?></p>
									</noscript>
								</td>
							</tr>
						</tbody>
					</table>

					<?php submit_button( $is_edit ? __( 'Сохранить сценарий', 'wp-automation-engine' ) : __( 'Создать сценарий', 'wp-automation-engine' ), 'primary', 'wp_automation_engine_save_workflow' ); ?>
				</form>

				<?php $this->render_node_summary( $state['nodes'] ); ?>
				<?php if ( $is_edit ) : ?>
					<?php $this->render_workflow_state_table( $state['existing_id'] ); ?>
				<?php endif; ?>
			</div>

			<div class="wpae-admin-sidebar">
				<?php $this->render_schema_guide(); ?>
			</div>
		</div>
		<?php
	}

	private function render_node_summary( array $nodes ) {
		?>
		<h2><?php echo esc_html__( 'Краткий список узлов', 'wp-automation-engine' ); ?></h2>
		<?php if ( empty( $nodes ) ) : ?>
			<p><?php echo esc_html__( 'Узлы пока не добавлены.', 'wp-automation-engine' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'ID узла', 'wp-automation-engine' ); ?></th>
						<th><?php echo esc_html__( 'Тип', 'wp-automation-engine' ); ?></th>
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
		<h2><?php echo esc_html__( 'Доступные схемы узлов', 'wp-automation-engine' ); ?></h2>
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
										<strong><?php echo esc_html( $this->get_schema_field_label( $field ) ); ?></strong>
										<span class="description">— <?php echo esc_html( $this->get_field_type_label( $field['type'] ?? '' ) ); ?></span>
										<?php if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) : ?>
											<div class="description"><?php echo esc_html( $this->format_schema_field_options( $field['options'] ) ); ?></div>
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

	private function render_workflow_state_table( $workflow_id = '' ) {
		$workflow_ids = array();

		if ( '' !== $workflow_id ) {
			$workflow_ids[] = $workflow_id;
		} else {
			foreach ( $this->storage->get_workflows() as $workflow ) {
				$workflow_ids[] = $workflow['id'];
			}
		}

		$rows = array();

		foreach ( array_unique( array_filter( $workflow_ids ) ) as $current_workflow_id ) {
			foreach ( $this->state_store->get_workflow_states( $current_workflow_id ) as $state_key => $state ) {
				if ( ! is_array( $state ) ) {
					continue;
				}

				$rows[] = array(
					'workflow_id' => $current_workflow_id,
					'state_key'   => $state_key,
					'state'       => $state,
				);
			}
		}

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Сохраненное состояние синхронизации пока отсутствует.', 'wp-automation-engine' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Сценарий', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Ключ состояния', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Обновлено', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Статус', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Прогресс', 'wp-automation-engine' ); ?></th>
					<th><?php echo esc_html__( 'Экспорт', 'wp-automation-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php $state = $row['state']; ?>
					<tr>
						<td><?php echo esc_html( $row['workflow_id'] ); ?></td>
						<td><code><?php echo esc_html( $row['state_key'] ); ?></code></td>
						<td><?php echo esc_html( $state['updated_at'] ?? '' ); ?></td>
						<td>
							<?php
							if ( isset( $state['finished'] ) ) {
								echo esc_html( ! empty( $state['finished'] ) ? __( 'Завершено', 'wp-automation-engine' ) : __( 'В процессе', 'wp-automation-engine' ) );
							} elseif ( 'export' === ( $state['type'] ?? '' ) ) {
								echo esc_html__( 'Экспорт создан', 'wp-automation-engine' );
							} else {
								echo esc_html__( 'Сохранено', 'wp-automation-engine' );
							}
							?>
						</td>
						<td>
							<?php
							if ( isset( $state['total'] ) ) {
								echo esc_html(
									sprintf(
										/* translators: 1: processed items, 2: total items. */
										__( '%1$d из %2$d', 'wp-automation-engine' ),
										isset( $state['processed'] ) ? absint( $state['processed'] ) : 0,
										absint( $state['total'] )
									)
								);
							}
							?>
						</td>
						<td>
							<?php if ( ! empty( $state['file']['url'] ) ) : ?>
								<a href="<?php echo esc_url( $state['file']['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $state['file']['filename'] ?? __( 'Скачать JSON', 'wp-automation-engine' ) ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function get_schema_field_label( array $field ) {
		if ( ! empty( $field['label'] ) ) {
			return (string) $field['label'];
		}

		return (string) ( $field['name'] ?? '' );
	}

	private function get_field_type_label( $type ) {
		switch ( $type ) {
			case 'text':
				return __( 'Текст', 'wp-automation-engine' );
			case 'path':
				return __( 'Путь', 'wp-automation-engine' );
			case 'select':
				return __( 'Список', 'wp-automation-engine' );
			case 'mixed':
				return __( 'Любое значение', 'wp-automation-engine' );
			case 'object':
				return __( 'Объект или массив', 'wp-automation-engine' );
			case 'condition':
				return __( 'Условие', 'wp-automation-engine' );
			case 'nodes':
				return __( 'Вложенные узлы', 'wp-automation-engine' );
			default:
				return (string) $type;
		}
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
				'variables_json'   => wp_json_encode( $workflow['variables'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'nodes_json'       => wp_json_encode( $workflow['nodes'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
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
					__( 'Action-хук: %s', 'wp-automation-engine' ),
					$trigger['hook'] ?? ''
				);
			case 'filter':
				return sprintf(
					/* translators: %s: filter hook. */
					__( 'Filter-хук: %s', 'wp-automation-engine' ),
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
					__( 'Событие: %s', 'wp-automation-engine' ),
					$trigger['event'] ?? ''
				);
			case 'manual':
				return __( 'Ручной запуск', 'wp-automation-engine' );
			default:
				return __( 'Не настроен', 'wp-automation-engine' );
		}
	}

	private function register_notice_from_query() {
		$notice = isset( $_GET['wpae_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpae_notice'] ) ) : '';

		switch ( $notice ) {
			case 'created':
				add_settings_error( $this->plugin_name, 'workflow_created', __( 'Сценарий создан.', 'wp-automation-engine' ), 'updated' );
				break;
			case 'updated':
				add_settings_error( $this->plugin_name, 'workflow_updated', __( 'Сценарий сохранен.', 'wp-automation-engine' ), 'updated' );
				break;
			case 'deleted':
				add_settings_error( $this->plugin_name, 'workflow_deleted', __( 'Сценарий удален.', 'wp-automation-engine' ), 'updated' );
				break;
			case 'run_started':
				add_settings_error( $this->plugin_name, 'workflow_run_started', __( 'Сценарий поставлен на запуск.', 'wp-automation-engine' ), 'updated' );
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

	private function get_run_url( $workflow_id ) {
		return wp_nonce_url(
			admin_url( 'admin.php?page=' . $this->plugin_name . '&action=run&id=' . rawurlencode( $workflow_id ) ),
			'wp_automation_engine_run_workflow_' . $workflow_id
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
