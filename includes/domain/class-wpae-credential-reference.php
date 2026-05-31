<?php

class WPAE_Credential_Reference {

	protected $key;

	public function __construct( $key ) {
		$this->key = (string) $key;
	}

	public function get_key() {
		return $this->key;
	}
}
