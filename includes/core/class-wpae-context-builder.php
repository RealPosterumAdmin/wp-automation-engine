<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Context_Builder {
public function build( $trigger, $payload = array() ) {
$context = array(
'object'  => 'system',
'runtime' => array(
'site_url'        => home_url( '/' ),
'current_blog_id' => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0,
'current_user_id' => (int) get_current_user_id(),
'is_admin'        => is_admin(),
'request_uri'     => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
),
'trigger_data' => array(),
);

switch ( $trigger ) {
case 'save_post':
$context['object']       = 'post';
$context['trigger_data'] = $this->build_post_data( $payload );
break;
case 'user_register':
$context['object']       = 'user';
$context['trigger_data'] = $this->build_user_data( $payload );
break;
case 'cron':
$context['trigger_data'] = array(
'schedule' => 'wpae_every_minute',
'time'     => time(),
);
break;
default:
$context['trigger_data'] = array(
'time' => time(),
);
break;
}

return $context;
}

protected function build_post_data( $payload ) {
$data = array(
'post_id'   => isset( $payload['post_id'] ) ? (int) $payload['post_id'] : 0,
'post_type' => '',
'post_name' => '',
'update'    => ! empty( $payload['update'] ),
);

if ( isset( $payload['post'] ) && $payload['post'] instanceof WP_Post ) {
$data['post_type'] = sanitize_key( $payload['post']->post_type );
$data['post_name'] = sanitize_text_field( $payload['post']->post_title );
}

return $data;
}

protected function build_user_data( $payload ) {
$user_id = isset( $payload['user_id'] ) ? (int) $payload['user_id'] : 0;
$user    = $user_id ? get_userdata( $user_id ) : false;

return array(
'user_id'    => $user_id,
'user_login' => $user instanceof WP_User ? sanitize_user( $user->user_login, true ) : '',
'roles'      => $user instanceof WP_User ? array_values( array_map( 'sanitize_key', $user->roles ) ) : array(),
);
}
}
