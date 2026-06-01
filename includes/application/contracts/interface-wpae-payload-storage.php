<?php

interface WPAE_Payload_Storage_Interface {

	public function save( $data, array $metadata = array() );

	public function read( $payload_id );

	public function read_batch( $payload_id, $offset = 0, $limit = 50, $source = '' );

	public function update_status( $payload_id, $status, array $attributes = array() );

	public function archive( $payload_id );

	public function delete( $payload_id );
}
