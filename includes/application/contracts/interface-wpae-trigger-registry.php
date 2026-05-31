<?php

interface WPAE_Trigger_Registry_Interface {

	public function register( WPAE_Trigger_Definition $definition );

	public function all();
}
