<?php

interface WP_Automation_Node {

	public function get_type();

	public function get_schema();

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor );
}
