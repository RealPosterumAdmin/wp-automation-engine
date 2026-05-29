<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Agent {
protected $storage;
protected $logger;
protected $context_builder;

public function __construct( $storage, $logger, $context_builder ) {
$this->storage         = $storage;
$this->logger          = $logger;
$this->context_builder = $context_builder;
}

public function handle_trigger( $trigger, $payload = array() ) {
$plan = $this->build_plan_from_event(
array(
'id'        => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wpae_event_', true ),
'trigger'   => $trigger,
'payload'   => $payload,
'timestamp' => time(),
)
);

$this->storage->add_plan( $plan );
$this->logger->log(
'План сохранён',
array(
'plan_id' => $plan['id'],
'trigger' => $plan['trigger'],
'object'  => $plan['object'],
)
);
}

public function build_plan_from_event( $event ) {
$trigger = isset( $event['trigger'] ) ? sanitize_key( $event['trigger'] ) : '';
$payload = isset( $event['payload'] ) && is_array( $event['payload'] ) ? $event['payload'] : array();
$context = $this->context_builder->build( $trigger, $payload );

return array(
'id'         => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wpae_plan_', true ),
'event_id'   => isset( $event['id'] ) ? sanitize_text_field( (string) $event['id'] ) : '',
'trigger'    => $trigger,
'object'     => isset( $context['object'] ) ? sanitize_key( $context['object'] ) : 'system',
'action'     => 'noop',
'status'     => 'pending',
'payload'    => $payload,
'timestamp'  => isset( $event['timestamp'] ) ? (int) $event['timestamp'] : time(),
'created_at' => time(),
'context'    => $context,
);
}
}
