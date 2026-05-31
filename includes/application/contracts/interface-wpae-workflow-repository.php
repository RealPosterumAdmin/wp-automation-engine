<?php

interface WPAE_Workflow_Repository_Interface {

	public function find( $workflow_id );

	public function all();
}
