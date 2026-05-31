<?php

class WPAE_Node_Definition {

	protected $id;
	protected $type;
	protected $config;

	public function __construct( $id, $type, array $config = array() ) {
		$this->id     = (string) $id;
		$this->type   = (string) $type;
		$this->config = $config;
	}

	public static function from_array( array $definition ) {
		return new self(
			$definition['id'] ?? '',
			$definition['type'] ?? '',
			isset( $definition['config'] ) && is_array( $definition['config'] ) ? $definition['config'] : array()
		);
	}

	public function to_array() {
		return array(
			'id'     => $this->id,
			'type'   => $this->type,
			'config' => $this->config,
		);
	}
}
