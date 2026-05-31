<?php

class WPAE_Workflow_Version {

	protected $workflow_id;
	protected $hash;

	public function __construct( $workflow_id, $hash ) {
		$this->workflow_id = (string) $workflow_id;
		$this->hash        = (string) $hash;
	}

	public static function from_workflow( WPAE_Workflow $workflow ) {
		$payload = json_encode( $workflow->to_array() );

		return new self(
			$workflow->get_id(),
			md5( false === $payload ? '' : $payload )
		);
	}

	public function get_workflow_id() {
		return $this->workflow_id;
	}

	public function get_hash() {
		return $this->hash;
	}

	public function to_array() {
		return array(
			'workflow_id' => $this->workflow_id,
			'hash'        => $this->hash,
		);
	}
}
