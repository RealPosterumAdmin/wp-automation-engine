<?php

class WPAE_Condition_Group {

	protected $definition;

	public function __construct( array $definition ) {
		$this->definition = $definition;
	}

	public function to_array() {
		return $this->definition;
	}
}
