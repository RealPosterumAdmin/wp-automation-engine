<?php

interface WP_Automation_Node {

	public function execute( array $node, WP_Automation_Context $context, WP_Automation_Executor $executor );
}
