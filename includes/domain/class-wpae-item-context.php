<?php

class WPAE_Item_Context {

	protected $name;
	protected $value;
	protected $previous_item;

	public function __construct( $name, $value, $previous_item ) {
		$this->name          = (string) $name;
		$this->value         = $value;
		$this->previous_item = $previous_item;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_value() {
		return $this->value;
	}

	public function get_previous_item() {
		return $this->previous_item;
	}
}
