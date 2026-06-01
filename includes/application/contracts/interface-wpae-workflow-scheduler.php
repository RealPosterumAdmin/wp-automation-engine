<?php

interface WPAE_Workflow_Scheduler_Interface {

	public function schedule_single( $workflow_id, array $trigger_data = array(), $timestamp = 0 );
}
