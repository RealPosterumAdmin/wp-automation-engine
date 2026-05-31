<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
exit;
}

delete_option( 'wp_automation_engine_workflows' );
delete_option( 'wp_automation_engine_execution_log' );
delete_option( 'wpae_automation_plans' );
delete_option( 'wpae_events' );
wp_clear_scheduled_hook( 'wp_automation_engine_run_cron_workflow' );
wp_clear_scheduled_hook( 'wpae_cron_trigger_event' );
wp_clear_scheduled_hook( 'wpae_kernel_tick' );
wp_clear_scheduled_hook( 'wpae_executor_run' );
