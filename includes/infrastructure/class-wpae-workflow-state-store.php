<?php

class WPAE_Workflow_State_Store {

	const OPTION_NAME = 'wp_automation_engine_workflow_state';

	public function get_workflow_states( $workflow_id ) {
		$workflow_id = sanitize_key( $workflow_id );
		$states      = $this->get_states();

		if ( '' === $workflow_id || ! isset( $states[ $workflow_id ] ) || ! is_array( $states[ $workflow_id ] ) ) {
			return array();
		}

		return $states[ $workflow_id ];
	}

	public function get_state( $workflow_id, $state_key = 'default' ) {
		$workflow_id = sanitize_key( $workflow_id );
		$state_key   = sanitize_key( $state_key );
		$states      = $this->get_workflow_states( $workflow_id );

		if ( '' === $workflow_id || '' === $state_key || ! isset( $states[ $state_key ] ) || ! is_array( $states[ $state_key ] ) ) {
			return array();
		}

		return $states[ $state_key ];
	}

	public function save_state( $workflow_id, $state_key, array $state ) {
		$workflow_id = sanitize_key( $workflow_id );
		$state_key   = sanitize_key( $state_key );

		if ( '' === $workflow_id || '' === $state_key ) {
			return;
		}

		$states = $this->get_states();

		if ( ! isset( $states[ $workflow_id ] ) || ! is_array( $states[ $workflow_id ] ) ) {
			$states[ $workflow_id ] = array();
		}

		$states[ $workflow_id ][ $state_key ] = $this->normalize_value( $state );
		update_option( self::OPTION_NAME, $states, false );
	}

	public function delete_state( $workflow_id, $state_key ) {
		$workflow_id = sanitize_key( $workflow_id );
		$state_key   = sanitize_key( $state_key );
		$states      = $this->get_states();

		if ( '' === $workflow_id || '' === $state_key || ! isset( $states[ $workflow_id ][ $state_key ] ) ) {
			return;
		}

		unset( $states[ $workflow_id ][ $state_key ] );

		if ( empty( $states[ $workflow_id ] ) ) {
			unset( $states[ $workflow_id ] );
		}

		update_option( self::OPTION_NAME, $states, false );
	}

	public function delete_workflow_states( $workflow_id ) {
		$workflow_id = sanitize_key( $workflow_id );
		$states      = $this->get_states();

		if ( '' === $workflow_id || ! isset( $states[ $workflow_id ] ) ) {
			return;
		}

		unset( $states[ $workflow_id ] );
		update_option( self::OPTION_NAME, $states, false );
	}

	protected function get_states() {
		$states = get_option( self::OPTION_NAME, array() );

		return is_array( $states ) ? $states : array();
	}

	protected function normalize_value( $value ) {
		$encoded = wp_json_encode( $value );

		if ( false === $encoded ) {
			return array();
		}

		$decoded = json_decode( $encoded, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
