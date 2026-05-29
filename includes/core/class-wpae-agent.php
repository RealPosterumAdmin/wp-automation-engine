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
$context = $this->context_builder->build( $trigger, $payload );
$plan    = array(
'id'        => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wpae_', true ),
'trigger'   => sanitize_key( $trigger ),
'object'    => isset( $context['object'] ) ? sanitize_key( $context['object'] ) : 'system',
'action'    => 'noop',
'timestamp' => time(),
'context'   => $context,
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
}
