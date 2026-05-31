<?php

class WP_Automation_i18n {

	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'wp-automation-engine',
			false,
			dirname( plugin_basename( dirname( __FILE__ ) ) ) . '/languages/'
		);
	}
}
