<?php

interface WPAE_Lock_Manager_Interface {

	public function acquire( $key, $ttl = 300 );

	public function release( $key );
}
