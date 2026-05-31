<?php

class WP_Automation_Context extends WPAE_Execution_Context {

	public function __construct( array $workflow, array $trigger_data = array(), array $runtime = array() ) {
		parent::__construct(
			$workflow,
			$trigger_data,
			isset( $workflow['variables'] ) && is_array( $workflow['variables'] ) ? $workflow['variables'] : array(),
			$runtime
		);
	}
}
