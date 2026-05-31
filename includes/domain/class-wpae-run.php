<?php

class WPAE_Run {

	protected $id;
	protected $workflow_id;
	protected $workflow_name;
	protected $workflow_version;
	protected $status;
	protected $trigger_data;
	protected $steps;
	protected $started_at;
	protected $finished_at;
	protected $context_snapshot;
	protected $error_message;

	public function __construct( array $data ) {
		$this->id               = (string) ( $data['id'] ?? '' );
		$this->workflow_id      = (string) ( $data['workflow_id'] ?? '' );
		$this->workflow_name    = (string) ( $data['workflow_name'] ?? '' );
		$this->workflow_version = (string) ( $data['workflow_version'] ?? '' );
		$this->status           = (string) ( $data['status'] ?? 'pending' );
		$this->trigger_data     = isset( $data['trigger_data'] ) && is_array( $data['trigger_data'] ) ? $data['trigger_data'] : array();
		$this->steps            = isset( $data['steps'] ) && is_array( $data['steps'] ) ? $data['steps'] : array();
		$this->started_at       = (string) ( $data['started_at'] ?? '' );
		$this->finished_at      = (string) ( $data['finished_at'] ?? '' );
		$this->context_snapshot = isset( $data['context_snapshot'] ) && is_array( $data['context_snapshot'] ) ? $data['context_snapshot'] : array();
		$this->error_message    = (string) ( $data['error_message'] ?? '' );
	}

	public static function start( WPAE_Workflow $workflow, WPAE_Workflow_Version $version, array $trigger_data, $started_at ) {
		try {
			$suffix = bin2hex( random_bytes( 5 ) );
		} catch ( Exception $exception ) {
			$suffix = uniqid();
		}

		return new self(
			array(
				'id'               => 'run_' . strtolower( $suffix ),
				'workflow_id'      => $workflow->get_id(),
				'workflow_name'    => $workflow->get_name(),
				'workflow_version' => $version->get_hash(),
				'status'           => 'running',
				'trigger_data'     => $trigger_data,
				'steps'            => array(),
				'started_at'       => (string) $started_at,
				'finished_at'      => '',
				'context_snapshot' => array(),
				'error_message'    => '',
			)
		);
	}

	public function get_id() {
		return $this->id;
	}

	public function to_array() {
		return array(
			'id'               => $this->id,
			'workflow_id'      => $this->workflow_id,
			'workflow_name'    => $this->workflow_name,
			'workflow_version' => $this->workflow_version,
			'status'           => $this->status,
			'trigger_data'     => $this->trigger_data,
			'steps'            => $this->steps,
			'started_at'       => $this->started_at,
			'finished_at'      => $this->finished_at,
			'context_snapshot' => $this->context_snapshot,
			'error_message'    => $this->error_message,
		);
	}
}
