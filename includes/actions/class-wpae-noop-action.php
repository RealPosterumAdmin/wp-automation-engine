<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Noop_Action implements WPAE_Action_Interface {
protected $logger;

public function __construct( $logger ) {
$this->logger = $logger;
}

public function execute( $plan ) {
$this->logger->log(
'Выполнено действие noop',
array(
'plan_id' => isset( $plan['id'] ) ? $plan['id'] : '',
'trigger' => isset( $plan['trigger'] ) ? $plan['trigger'] : '',
'object'  => isset( $plan['object'] ) ? $plan['object'] : '',
)
);
}
}
