<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
exit;
}

delete_option( 'wpae_automation_plans' );
wp_clear_scheduled_hook( 'wpae_cron_trigger_event' );
wp_clear_scheduled_hook( 'wpae_executor_run' );
