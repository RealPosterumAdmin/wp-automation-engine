<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Storage {
const OPTION_KEY = 'wpae_automation_plans';
const EVENT_OPTION_KEY = 'wpae_events';

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

public function get_events() {
$events = get_option( self::EVENT_OPTION_KEY, array() );

return is_array( $events ) ? array_values( $events ) : array();
}

public function store_event( $event ) {
$events   = $this->get_events();
$events[] = $event;

return update_option( self::EVENT_OPTION_KEY, array_values( $events ), false );
}

public function mark_processing( $event_id, $plan_id = '' ) {
return $this->update_event(
$event_id,
array(
'status'    => 'processing',
'plan_id'   => (string) $plan_id,
'started_at' => time(),
)
);
}

public function mark_done( $event_id, $plan_id = '' ) {
return $this->update_event(
$event_id,
array(
'status'  => 'done',
'plan_id' => (string) $plan_id,
'done_at' => time(),
)
);
}

public function mark_failed( $event_id, $error, $plan_id = '' ) {
return $this->update_event(
$event_id,
array(
'status'  => 'failed',
'plan_id' => (string) $plan_id,
'error'   => sanitize_text_field( $error ),
'done_at' => time(),
)
);
}

protected function update_event( $event_id, $changes ) {
$events = $this->get_events();

foreach ( $events as $index => $event ) {
if ( empty( $event['id'] ) || (string) $event['id'] !== (string) $event_id ) {
continue;
}

$events[ $index ] = array_merge( $event, $changes );

return update_option( self::EVENT_OPTION_KEY, array_values( $events ), false );
}

return false;
}
}
