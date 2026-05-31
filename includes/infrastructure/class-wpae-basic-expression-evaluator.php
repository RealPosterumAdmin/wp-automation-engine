<?php

class WPAE_Basic_Expression_Evaluator implements WPAE_Expression_Evaluator_Interface {

	public function resolve( $value, WPAE_Execution_Context $context ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $nested_value ) {
				$value[ $key ] = $this->resolve( $nested_value, $context );
			}

			return $value;
		}

		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( preg_match( '/^\{\{\s*([^}]+)\s*\}\}$/', $value, $matches ) ) {
			return $context->resolve_path( trim( $matches[1] ) );
		}

		return preg_replace_callback(
			'/\{\{\s*([^}]+)\s*\}\}/',
			static function ( $matches ) use ( $context ) {
				$resolved = $context->resolve_path( trim( $matches[1] ) );

				if ( is_scalar( $resolved ) || null === $resolved ) {
					return (string) $resolved;
				}

				return wp_json_encode( $resolved );
			},
			$value
		);
	}
}
