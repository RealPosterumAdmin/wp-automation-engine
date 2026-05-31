<?php

class WPAE_Trigger_Definition {

	protected $type;
	protected $config;

	public function __construct( $type, array $config = array() ) {
		$this->type   = (string) $type;
		$this->config = $config;
	}

	public static function from_array( array $definition ) {
		$type = $definition['type'] ?? '';
		unset( $definition['type'] );

		return new self( $type, $definition );
	}

	public function get_type() {
		return $this->type;
	}

	public function get_config() {
		return $this->config;
	}

	public function to_array() {
		return array_merge(
			array(
				'type' => $this->type,
			),
			$this->config
		);
	}
}
