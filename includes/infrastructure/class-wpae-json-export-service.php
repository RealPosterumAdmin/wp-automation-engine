<?php

class WPAE_JSON_Export_Service {

	public function export( $workflow_id, $filename, $data ) {
		$workflow_id = sanitize_key( $workflow_id );
		$filename    = sanitize_file_name( (string) $filename );

		if ( '' === $workflow_id ) {
			return new WP_Error( 'wpae_export_workflow_required', __( 'Не удалось определить сценарий для экспорта.', 'wp-automation-engine' ) );
		}

		if ( '' === $filename ) {
			$filename = 'export-' . gmdate( 'Ymd-His' ) . '.json';
		}

		if ( '.json' !== strtolower( substr( $filename, -5 ) ) ) {
			$filename .= '.json';
		}

		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'wpae_export_upload_dir_error', __( 'Не удалось получить директорию uploads для экспорта.', 'wp-automation-engine' ) );
		}

		$directory = trailingslashit( $upload_dir['basedir'] ) . 'wpae-exports/' . $workflow_id;

		if ( ! wp_mkdir_p( $directory ) ) {
			return new WP_Error( 'wpae_export_directory_error', __( 'Не удалось создать директорию для экспорта JSON.', 'wp-automation-engine' ) );
		}

		$file_path = trailingslashit( $directory ) . $filename;
		$encoded   = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false === $encoded ) {
			return new WP_Error( 'wpae_export_encoding_error', __( 'Не удалось преобразовать данные в JSON для экспорта.', 'wp-automation-engine' ) );
		}

		$bytes_written = @file_put_contents( $file_path, $encoded );

		if ( false === $bytes_written ) {
			return new WP_Error( 'wpae_export_write_error', __( 'Не удалось записать файл экспорта JSON.', 'wp-automation-engine' ) );
		}

		return array(
			'filename'     => $filename,
			'path'         => $file_path,
			'url'          => trailingslashit( $upload_dir['baseurl'] ) . 'wpae-exports/' . rawurlencode( $workflow_id ) . '/' . rawurlencode( $filename ),
			'size'         => filesize( $file_path ),
			'generated_at' => current_time( 'mysql', true ),
		);
	}
}
