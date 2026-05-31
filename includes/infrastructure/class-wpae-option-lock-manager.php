<?php

class WPAE_Option_Lock_Manager implements WPAE_Lock_Manager_Interface {

	const OPTION_NAME = 'wp_automation_engine_runtime_locks';

	public function acquire( $key, $ttl = 300 ) {
		$key   = sanitize_key( $key );
		$ttl   = max( 1, absint( $ttl ) );
		$locks = get_option( self::OPTION_NAME, array() );
		$now   = time();

		if ( ! is_array( $locks ) ) {
			$locks = array();
		}

		foreach ( $locks as $lock_key => $expires_at ) {
			if ( (int) $expires_at < $now ) {
				unset( $locks[ $lock_key ] );
			}
		}

		if ( isset( $locks[ $key ] ) ) {
			update_option( self::OPTION_NAME, $locks, false );
			return false;
		}

		$locks[ $key ] = $now + $ttl;
		update_option( self::OPTION_NAME, $locks, false );

		return true;
	}

	public function release( $key ) {
		$key   = sanitize_key( $key );
		$locks = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $locks ) || ! isset( $locks[ $key ] ) ) {
			return;
		}

		unset( $locks[ $key ] );
		update_option( self::OPTION_NAME, $locks, false );
	}
}
