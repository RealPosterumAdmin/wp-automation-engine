<?php

class WP_Automation_Condition_Evaluator {

	public function evaluate( array $condition, WP_Automation_Context $context ) {
		if ( isset( $condition['conditions'] ) && is_array( $condition['conditions'] ) ) {
			$operator = strtoupper( isset( $condition['operator'] ) ? $condition['operator'] : 'AND' );
			$results  = array();

			foreach ( $condition['conditions'] as $child_condition ) {
				$results[] = $this->evaluate( $child_condition, $context );
			}

			return 'OR' === $operator
				? in_array( true, $results, true )
				: ! in_array( false, $results, true );
		}

		$left       = $this->resolve_operand( $condition['left'] ?? null, $context );
		$right      = $this->resolve_operand( $condition['right'] ?? null, $context );
		$comparison = isset( $condition['comparison'] ) ? $condition['comparison'] : '==';

		switch ( $comparison ) {
			case '!=':
				return $left != $right;
			case '>':
				return $left > $right;
			case '<':
				return $left < $right;
			case '>=':
				return $left >= $right;
			case '<=':
				return $left <= $right;
			case 'contains':
				if ( is_array( $left ) ) {
					return in_array( $right, $left, true );
				}

				return false !== strpos( (string) $left, (string) $right );
			case 'empty':
				return empty( $left );
			case 'not_empty':
				return ! empty( $left );
			case '==':
			default:
				return $left == $right;
		}
	}

	private function resolve_operand( $operand, WP_Automation_Context $context ) {
		if ( ! is_array( $operand ) ) {
			return $operand;
		}

		if ( 'path' === ( $operand['type'] ?? 'value' ) ) {
			return $context->resolve_path( $operand['value'] ?? '' );
		}

		return $operand['value'] ?? null;
	}
}
