<?php

class WPAE_Step_Run {

	protected $data;

	public function __construct( array $data ) {
		$this->data = $data;
	}

	public function to_array() {
		return $this->data;
	}
}
