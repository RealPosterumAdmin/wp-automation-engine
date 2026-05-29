<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_Save_Post_Trigger extends WPAE_Abstract_Trigger {
public function register() {
add_action( 'save_post', array( $this, 'handle' ), 20, 3 );
}

public function handle( $post_id, $post, $update ) {
if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
return;
}

$this->dispatch(
'save_post',
array(
'post_id' => (int) $post_id,
'post'    => $post,
'update'  => (bool) $update,
)
);
}
}
