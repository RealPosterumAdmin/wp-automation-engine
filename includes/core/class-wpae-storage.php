<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Storage {
const OPTION_KEY = 'wpae_automation_plans';

public function get_plans() {
$plans = get_option( self::OPTION_KEY, array() );

return is_array( $plans ) ? array_values( $plans ) : array();
}

public function add_plan( $plan ) {
$plans   = $this->get_plans();
$plans[] = $plan;

return $this->set_plans( $plans );
}

public function set_plans( $plans ) {
return update_option( self::OPTION_KEY, array_values( $plans ), false );
}
}
