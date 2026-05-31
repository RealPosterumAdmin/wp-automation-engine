<?php

class WPAE_Catalog_Tree_Builder {

	public function build_tree( array $items, $id_key = 'Ref_Key', $parent_key = 'Parent_Key', $children_key = 'children' ) {
		$prepared       = array();
		$children_index = array();
		$duplicates     = 0;
		$invalid        = 0;
		$orphans        = 0;

		foreach ( $items as $item ) {
			$normalized = $this->normalize_item( $item );
			$guid       = isset( $normalized[ $id_key ] ) ? trim( (string) $normalized[ $id_key ] ) : '';

			if ( '' === $guid ) {
				++$invalid;
				continue;
			}

			if ( isset( $prepared[ $guid ] ) ) {
				++$duplicates;
				continue;
			}

			$prepared[ $guid ] = $normalized;
		}

		foreach ( $prepared as $guid => $item ) {
			$parent_guid = isset( $item[ $parent_key ] ) ? trim( (string) $item[ $parent_key ] ) : '';

			if ( '' === $parent_guid || $parent_guid === $guid ) {
				continue;
			}

			if ( ! isset( $prepared[ $parent_guid ] ) ) {
				++$orphans;
				continue;
			}

			if ( ! isset( $children_index[ $parent_guid ] ) ) {
				$children_index[ $parent_guid ] = array();
			}

			$children_index[ $parent_guid ][] = $guid;
		}

		$root_guids = array();

		foreach ( $prepared as $guid => $item ) {
			$parent_guid = isset( $item[ $parent_key ] ) ? trim( (string) $item[ $parent_key ] ) : '';

			if ( '' === $parent_guid || $parent_guid === $guid || ! isset( $prepared[ $parent_guid ] ) ) {
				$root_guids[] = $guid;
			}
		}

		$tree = array();

		foreach ( $root_guids as $guid ) {
			$tree[] = $this->build_branch( $guid, $prepared, $children_index, $children_key, array() );
		}

		return array(
			'tree'  => $tree,
			'stats' => array(
				'total'      => count( $prepared ),
				'roots'      => count( $root_guids ),
				'duplicates' => $duplicates,
				'invalid'    => $invalid,
				'orphans'    => $orphans,
			),
		);
	}

	protected function build_branch( $guid, array $prepared, array $children_index, $children_key, array $ancestry ) {
		$branch                 = $prepared[ $guid ];
		$branch[ $children_key ] = array();

		if ( in_array( $guid, $ancestry, true ) ) {
			return $branch;
		}

		$ancestry[] = $guid;

		foreach ( $children_index[ $guid ] ?? array() as $child_guid ) {
			if ( ! isset( $prepared[ $child_guid ] ) ) {
				continue;
			}

			$branch[ $children_key ][] = $this->build_branch( $child_guid, $prepared, $children_index, $children_key, $ancestry );
		}

		return $branch;
	}

	protected function normalize_item( $item ) {
		if ( is_object( $item ) ) {
			$item = wp_json_encode( $item );
			$item = false === $item ? array() : json_decode( $item, true );
		}

		return is_array( $item ) ? $item : array();
	}
}
