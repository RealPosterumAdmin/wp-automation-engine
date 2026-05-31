<?php

interface WPAE_Expression_Evaluator_Interface {

	public function resolve( $value, WPAE_Execution_Context $context );
}
