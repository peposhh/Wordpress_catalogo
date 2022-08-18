<?php
namespace PostSnippets;

/**
 * Post Snippets I/O.
 *
 * Class to handle import and export of Snippets.
 */
class ImportExport {

	protected static $FILE_ZIP;
	const FILE_CFG = 'post-snippets-export.cfg';

	private $downloadUrl;

	/**
	 * Export Snippets.
	 *
	 * Check if an export file shall be created, or if a download url should be
	 * pushed to the footer. Also checks for old export files laying around and
	 * deletes them (for security).
	 *
	 * @return void
	 */
	public function exportSnippets() {
		self::set_export_name();
		if ( isset( $_POST['postsnippets_export'] ) ) {
			check_admin_referer( 'pspro_export_snippets', 'pspro_export_snippets_nonce' );
			if( !current_user_can( 'manage_options' ) ){				
				return;
			}
			$url = $this->createExportFile();
			if ( $url ) {
				$this->downloadUrl = $url;
				add_action(
					'admin_footer',
					array( &$this, 'psnippetsFooter' ),
					10000
				);
			} else {
				echo esc_html__( 'Error: ', 'post-snippets' ) . esc_url( $url );
			}
		} else {
			// Check if there is any old export files to delete
			$dir        = wp_upload_dir();
			$upload_dir = $dir['basedir'] . '/';
			chdir( $upload_dir );
			if ( file_exists( './' . self::$FILE_ZIP ) ) {
				unlink( './' . self::$FILE_ZIP );
			}
		}
	}

	/**
	 * Handles uploading of post snippets archive and import the snippets.
	 *
	 * @uses   wp_handle_upload() in wp-admin/includes/file.php
	 * @return string HTML to handle the import
	 */
	public function importSnippets() {
		$import =
		'<br/><br/><strong>' .
		esc_html__( 'Import', 'post-snippets' ) .
		'</strong><br/>';
		if ( ! isset( $_FILES['postsnippets_import_file'] )
			|| empty( $_FILES['postsnippets_import_file'] )
		) {
			$import .=
			'<p>' . esc_html__( 'Import snippets from a post-snippets-export.zip file. Imported sinppets will get added at the bottom.', 'post-snippets' ) .
			'</p>';
			$import .=
			'<p>' . esc_html__( 'Please make sure no snippets have duplicate titles.', 'post-snippets' ) .
			'</p>';
			$import .= '<form method="post" enctype="multipart/form-data">';
			$import .= '<input type="file" name="postsnippets_import_file"/>';
			$import .= '<input type="hidden" name="action" value="wp_handle_upload"/>';
			$import .=
			'<input type="submit" class="button" value="' .
			__( 'Import Snippets', 'post-snippets' ) . '"/>';
			$import .= wp_nonce_field( 'pspro_import_snippets', 'pspro_import_snippets_nonce', false, false );
			$import .= '</form>';
		} else {
			check_admin_referer( 'pspro_import_snippets', 'pspro_import_snippets_nonce' );

			if( !current_user_can( 'manage_options' ) ){
				
				$import .=
					'<p><strong>' .
					esc_html__( 'Only Admins can import snippets', 'post-snippets' ) .
					'</strong></p>';

				return $import;
			}

			$file = wp_handle_upload( $_FILES['postsnippets_import_file'] );

			if ( isset( $file['file'] ) && ! is_wp_error( $file ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
				$zip        = new \PclZip( $file['file'] );
				$dir        = wp_upload_dir();
				$upload_dir = $dir['basedir'] . '/';
				chdir( $upload_dir );
				$unzipped = $zip->extract();

				if ( $unzipped[0]['stored_filename'] == self::FILE_CFG
					&& $unzipped[0]['status'] == 'ok'
				) {
					// Delete the uploaded archive
					unlink( $file['file'] );

					$snippets = file_get_contents(
						$upload_dir . self::FILE_CFG
					);

					if ( $snippets ) {
						$snippets = apply_filters(
							'post_snippets_import',
							$snippets
						);

						$imported_snippets = maybe_unserialize( $snippets );
						$current_snippets  = get_option( \PostSnippets::OPTION_KEY );

						if ( empty( $current_snippets ) ) {
							$current_snippets = array();
						}

						$all_snippets          = $current_snippets;
						$duplicate_title_exist = false;
						$empty_title           = false;
						$duplicate_title       = '';
						foreach ( $imported_snippets as $snippet ) {
							foreach ( $snippet as $key => $value ) {
								$snippet[ $key ] = sanitize_text_field( $snippet[ $key ] );
							}
							if ( empty( $snippet['title'] ) ) {
								$empty_title = true;
								break;
							}
							// $snippet['title'] = sanitize_text_field( $snippet['title'] );
							$all_snippets[] = $snippet;
							foreach ( $current_snippets as $current ) {
								if ( $current['title'] == $snippet['title'] ) {
									$duplicate_title       = $snippet['title'];
									$duplicate_title_exist = true;
									break;
								}
							}
						}

						if ( $empty_title ) {
							$import .=
							'<p><strong>' .
							esc_html__( 'Snippets cannot have empty Titles', 'post-snippets' ) .
							'</strong></p>';
						} elseif ( $duplicate_title_exist == false ) {
							update_option( \PostSnippets::OPTION_KEY, $all_snippets );
							$import .=
							'<p><strong>' .
							esc_html__( 'Snippets successfully imported.', 'post-snippets' ) .
							'</strong></p>';
						} else {
							$import .=
							'<p><strong>' .
							esc_html__( 'All the snippets should have unique titles. The snippet in your import file with title "' . $duplicate_title . '" already exists in your site.', 'post-snippets' ) .
							'</strong></p>';
						}
					}

					// Delete the snippet file
					unlink( './' . self::FILE_CFG );

				} else {
					$import .=
					'<p><strong>' .
					esc_html__( 'Snippets could not be imported:', 'post-snippets' ) .
					' ' .
					esc_html__( 'Unzipping failed.', 'post-snippets' ) .
					'</strong></p>';
				}
			} else {
				if ( $file['error'] || is_wp_error( $file ) ) {
					$import .=
					'<p><strong>' .
					esc_html__( 'Snippets could not be imported:', 'post-snippets' ) .
					' ' .
					$file['error'] . '</strong></p>';
				} else {
					$import .=
					'<p><strong>' .
					esc_html__( 'Snippets could not be imported:', 'post-snippets' ) .
					' ' .
					esc_html__( 'Upload failed.', 'post-snippets' ) .
					'</strong></p>';
				}
			}
		}
		return $import;
	}

	/**
	 * Create a zipped filed containing all Post Snippets, for export.
	 *
	 * @return string URL to the exported snippets
	 */
	private function createExportFile() {
		$snippets   = maybe_serialize( get_option( \PostSnippets::OPTION_KEY ) );
		$snippets   = apply_filters( 'post_snippets_export', $snippets );
		$dir        = wp_upload_dir();
		$upload_dir = $dir['basedir'] . '/';
		$upload_url = $dir['baseurl'] . '/';

		// Open a file stream and write the serialized options to it.
		if ( ! $handle = fopen( $upload_dir . './' . self::FILE_CFG, 'w' ) ) {
			die();
		}
		if ( ! fwrite( $handle, $snippets ) ) {
			die();
		}
		fclose( $handle );

		// Create a zip archive
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		chdir( $upload_dir );
		$zip    = new \PclZip( './' . self::$FILE_ZIP );
		$zipped = $zip->create( './' . self::FILE_CFG );

		// Delete the snippet file
		unlink( './' . self::FILE_CFG );

		if ( ! $zipped ) {
			return false;
		}

		return $upload_url . './' . self::$FILE_ZIP;
	}

	/**
	 * Set export file name
	 */
	public static function set_export_name() {
		$date_part      = date( 'Y-m-d' );
		self::$FILE_ZIP = "post-snippets-export-{$date_part}.zip";
	}

	/**
	 * Generates the javascript to trigger the download of the file.
	 *
	 * @return void
	 */
	public function psnippetsFooter() {
		echo '<script type="text/javascript">
                    document.location = \'' . esc_js( $this->downloadUrl ) . '\';
                </script>';
		// echo $export;
	}
}
