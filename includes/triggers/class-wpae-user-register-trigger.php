<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WPAE_User_Register_Trigger extends WPAE_Abstract_Trigger {
public function register() {
add_action( 'user_register', array( $this, 'handle' ), 20, 1 );
}

public function handle( $user_id ) {
$this->dispatch(
'user_register',
array(
'user_id' => (int) $user_id,
)
);
}
}
