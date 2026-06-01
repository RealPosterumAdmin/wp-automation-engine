<?php

class WPAE_Sync_OneC_Product {

	protected $product_synchronizer;

	public function __construct( WPAE_WooCommerce_Product_Synchronizer $product_synchronizer ) {
		$this->product_synchronizer = $product_synchronizer;
	}

	public function sync( array $product_data, array $options = array() ) {
		return $this->product_synchronizer->synchronize( $product_data, $options );
	}
}
