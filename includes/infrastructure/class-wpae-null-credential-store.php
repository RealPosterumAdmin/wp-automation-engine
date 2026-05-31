<?php

class WPAE_Null_Credential_Store implements WPAE_Credential_Store_Interface {

	public function get( WPAE_Credential_Reference $reference ) {
		return null;
	}
}
