<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Kernel {
const HOOK = 'wpae_kernel_tick';

protected $storage;
protected $agent;
protected $executor;
protected $logger;

public function __construct( $storage, $agent, $executor, $logger ) {
$this->storage  = $storage;
$this->agent    = $agent;
$this->executor = $executor;
$this->logger   = $logger;
}

public function register() {
add_action( 'init', array( $this, 'capture_event' ), 20 );
add_action( 'wp_loaded', array( $this, 'capture_event' ), 20 );
add_action( 'save_post', array( $this, 'capture_event' ), 20, 3 );
add_action( 'user_register', array( $this, 'capture_event' ), 20, 1 );
add_action( WPAE_Cron_Trigger::HOOK, array( $this, 'capture_event' ), 20 );
add_action( self::HOOK, array( $this, 'dispatch' ) );
}

public function capture_event() {
$hook    = current_filter();
$trigger = $this->normalize_trigger( $hook );
$args    = func_get_args();

if ( '' === $trigger ) {
return;
}

$event = array(
'id'         => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wpae_event_', true ),
'trigger'    => $trigger,
'payload'    => $this->build_payload( $trigger, $args ),
'timestamp'  => time(),
'status'     => 'captured',
'source_hook' => sanitize_key( $hook ),
);

$this->storage->store_event( $event );
$this->logger->log(
'Событие захвачено',
array(
'event_id' => $event['id'],
'trigger'  => $event['trigger'],
)
);

$this->queue_dispatch();
}

public function dispatch() {
$this->logger->log( 'Запущен kernel dispatch' );

$events = $this->storage->get_events();

if ( empty( $events ) ) {
$this->logger->log( 'Нет событий для обработки' );
return;
}

foreach ( $events as $event ) {
if ( ! $this->is_processable_event( $event ) ) {
continue;
}

$plan_id = '';

try {
$plan    = $this->agent->build_plan_from_event( $event );
$plan_id = isset( $plan['id'] ) ? $plan['id'] : '';

$this->storage->mark_processing( $event['id'], $plan_id );

if ( ! $this->executor->run_plan( $plan ) ) {
throw new \RuntimeException( __( 'Plan execution returned false.', 'wp-automation-engine' ) );
}

$this->storage->mark_done( $event['id'], $plan_id );
$this->logger->log(
'Событие выполнено успешно',
array(
'event_id' => $event['id'],
'plan_id'  => $plan_id,
'trigger'  => isset( $event['trigger'] ) ? $event['trigger'] : '',
)
);
} catch ( Throwable $throwable ) {
$this->storage->mark_failed( $event['id'], $throwable->getMessage(), $plan_id );
$this->logger->log(
'Ошибка выполнения события',
array(
'event_id' => isset( $event['id'] ) ? $event['id'] : '',
'plan_id'  => $plan_id,
'error'    => $throwable->getMessage(),
)
);
}
}
}

protected function queue_dispatch() {
if ( ! wp_next_scheduled( self::HOOK ) ) {
wp_schedule_single_event( time() + 5, self::HOOK );
}
}

protected function normalize_trigger( $hook ) {
$hook = sanitize_key( (string) $hook );

if ( WPAE_Cron_Trigger::HOOK === $hook ) {
return 'cron';
}

return $hook;
}

protected function is_processable_event( $event ) {
return isset( $event['id'], $event['status'] ) && 'captured' === $event['status'];
}

protected function build_payload( $trigger, $args ) {
switch ( $trigger ) {
case 'save_post':
$post = isset( $args[1] ) && $args[1] instanceof WP_Post ? $args[1] : null;

return array(
'post_id'   => isset( $args[0] ) ? (int) $args[0] : 0,
'post_type' => $post instanceof WP_Post ? sanitize_key( $post->post_type ) : '',
'post_name' => $post instanceof WP_Post ? sanitize_text_field( $post->post_title ) : '',
'update'    => isset( $args[2] ) ? (bool) $args[2] : false,
);
case 'user_register':
return array(
'user_id' => isset( $args[0] ) ? (int) $args[0] : 0,
);
default:
return array();
}
}
}
