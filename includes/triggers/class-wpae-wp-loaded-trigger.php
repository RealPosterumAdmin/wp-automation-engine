<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_WP_Loaded_Trigger extends WPAE_Abstract_Trigger {
public function register() {
add_action( 'wp_loaded', array( $this, 'handle' ), 20 );
}

public function handle() {
$this->dispatch( 'wp_loaded' );
}
}
