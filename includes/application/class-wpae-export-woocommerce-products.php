<?php

class WPAE_Export_WooCommerce_Products {

	protected $exporter;

	public function __construct( WPAE_WooCommerce_Product_Exporter $exporter ) {
		$this->exporter = $exporter;
	}

	public function export( array $options = array() ) {
		return $this->exporter->export( $options );
	}
}
