<?php

class WPAE_Payload_Reference {

	protected $id;
	protected $key;
	protected $status;
	protected $source;
	protected $total_items;
	protected $file;
	protected $created_at;
	protected $updated_at;
	protected $metadata;

	public function __construct( $id, $key, $status, $source, $total_items, $file, $created_at, $updated_at, array $metadata = array() ) {
		$this->id         = (string) $id;
		$this->key        = (string) $key;
		$this->status     = (string) $status;
		$this->source     = (string) $source;
		$this->total_items = (int) $total_items;
		$this->file       = (string) $file;
		$this->created_at = (string) $created_at;
		$this->updated_at = (string) $updated_at;
		$this->metadata   = $metadata;
	}

	public static function from_array( array $payload ) {
		return new self(
			$payload['id'] ?? '',
			$payload['key'] ?? '',
			$payload['status'] ?? 'ready',
			$payload['source'] ?? '',
			isset( $payload['total_items'] ) ? (int) $payload['total_items'] : 0,
			$payload['file'] ?? '',
			$payload['created_at'] ?? '',
			$payload['updated_at'] ?? '',
			isset( $payload['metadata'] ) && is_array( $payload['metadata'] ) ? $payload['metadata'] : array()
		);
	}

	public function to_array() {
		return array(
			'id'          => $this->id,
			'key'         => $this->key,
			'status'      => $this->status,
			'source'      => $this->source,
			'total_items' => $this->total_items,
			'file'        => $this->file,
			'created_at'  => $this->created_at,
			'updated_at'  => $this->updated_at,
			'metadata'    => $this->metadata,
		);
	}
}
