<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Bootstrap {
protected $logger;
protected $storage;
protected $context_builder;
protected $agent;
protected $executor;
protected $triggers = array();

public function __construct() {
$this->logger          = new WPAE_Logger();
$this->storage         = new WPAE_Storage();
$this->context_builder = new WPAE_Context_Builder();
$this->agent           = new WPAE_Agent( $this->storage, $this->logger, $this->context_builder );
$this->executor        = new WPAE_Executor( $this->storage, $this->logger, $this->build_action_registry() );
$this->triggers        = $this->build_triggers();
}

public function run() {
add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedules' ) );
add_action( WPAE_Executor::HOOK, array( $this->executor, 'run' ) );

foreach ( $this->triggers as $trigger ) {
$trigger->register();
}
}

public static function activate() {
add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedules' ) );
add_option( WPAE_Storage::OPTION_KEY, array(), '', 'no' );
self::schedule_event( WPAE_Cron_Trigger::HOOK, 'wpae_every_minute' );
self::schedule_event( WPAE_Executor::HOOK, 'wpae_every_minute' );
}

public static function deactivate() {
wp_clear_scheduled_hook( WPAE_Cron_Trigger::HOOK );
wp_clear_scheduled_hook( WPAE_Executor::HOOK );
}

public static function register_cron_schedules( $schedules ) {
if ( ! isset( $schedules['wpae_every_minute'] ) ) {
$schedules['wpae_every_minute'] = array(
'interval' => MINUTE_IN_SECONDS,
'display'  => __( 'Every Minute', 'wp-automation-engine' ),
);
}

return $schedules;
}

protected static function schedule_event( $hook, $schedule ) {
if ( ! wp_next_scheduled( $hook ) ) {
wp_schedule_event( time() + MINUTE_IN_SECONDS, $schedule, $hook );
}
}

protected function build_action_registry() {
$registry = new WPAE_Action_Registry();
$registry->register( 'noop', new WPAE_Noop_Action( $this->logger ) );

return $registry;
}

protected function build_triggers() {
return array(
new WPAE_Init_Trigger( $this->agent ),
new WPAE_WP_Loaded_Trigger( $this->agent ),
new WPAE_Cron_Trigger( $this->agent ),
new WPAE_Save_Post_Trigger( $this->agent ),
new WPAE_User_Register_Trigger( $this->agent ),
);
}
}
