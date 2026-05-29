<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Init_Trigger extends WPAE_Abstract_Trigger {
public function register() {
add_action( 'init', array( $this, 'handle' ), 20 );
}

public function handle() {
$this->dispatch( 'init' );
}
}
