<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Cron_Trigger extends WPAE_Abstract_Trigger {
const HOOK = 'wpae_cron_trigger_event';

public function register() {
add_action( self::HOOK, array( $this, 'handle' ) );
}

public function handle() {
$this->dispatch( 'cron' );
}
}
