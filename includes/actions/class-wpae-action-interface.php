<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

interface WPAE_Action_Interface {
public function execute( $plan );
}
