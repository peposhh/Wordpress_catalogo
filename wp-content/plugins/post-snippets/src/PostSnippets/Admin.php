<?php

namespace PostSnippets;

/**
 * Post Snippets Settings.
 *
 * Class that renders out the HTML for the settings screen and contains helpful
 * methods to simply the maintainance of the admin screen.
 */
class Admin {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	protected $settings;
	/**
	 * Defines hooks and filters for admin page.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( &$this, 'menu' ) );
		add_action( 'admin_init', array( &$this, 'init' ) );
		add_action( 'current_screen', array( &$this, 'addHeaderXss' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PS_MAIN_FILE_PATH ), array( $this, 'actionLinks' ) );
		// Newsletter sign-up admin notice
		add_action( 'admin_notices', array( $this, 'admin_notice_newsletter' ) );
		// Get started admin notice
		add_action( 'admin_notices', array( $this, 'admin_notice_get_started' ) );
		// add_action( 'wp_ajax_update_post_snippets_order', array( $this, 'update_snippets_order' ) );
		add_action( 'wp_ajax_update_post_snippet_title', array( $this, 'update_post_snippet_title' ) );
		add_action( 'wp_ajax_update_post_snippet', array( $this, 'update_post_snippet' ) );
		add_action( 'wp_ajax_delete_post_snippet', array( $this, 'delete_post_snippet' ) );
		add_action( 'wp_ajax_sync_up', array( $this, 'sync_up' ) );
		add_action( 'wp_ajax_sync_list', array( $this, 'sync_list' ) );
		add_action( 'wp_ajax_sync', array( $this, 'sync' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'load_block' ) );
	}

	public function load_block() {
		wp_enqueue_script(
			'post-snippets-block',
			PS_URL . 'dist/blocks.build.js',
			array( 'wp-blocks', 'wp-editor' ),
			true
		);

		$snippets = get_option( \PostSnippets::OPTION_KEY );
		if ( isset( $snippets ) && ! empty( $snippets ) && is_array( $snippets ) ) {
			usort(
				$snippets,
				function ( $a, $b ) {
					return strcasecmp( $a['title'], $b['title'] );
				}
			);
		}
		wp_localize_script( 'post-snippets-block', 'post_snippets_s', $snippets );
	}

	// -------------------------------------------------------------------------
	// Setup
	// -------------------------------------------------------------------------
	/**
	 * Register the administration page.
	 *
	 * @return void
	 */
	public function menu() {
		$capability = $this->get_allowed_capability();

		if ( $capability == 'edit_posts' ) {
			$allowed = true;
		}

		if ( current_user_can( 'manage_options' ) || isset( $allowed ) ) {
			$optionPage = add_menu_page(
				__( 'Post Snippets', 'post-snippets' ),
				__( 'Post Snippets', 'post-snippets' ),
				$capability,
				'post-snippets',
				array( $this, 'optionsPage' ),
				PS_URL . 'assets/icon.svg'
			);
			$newsPage   = add_submenu_page(
				'post-snippets',
				__( 'News', 'post-snippets' ),
				__( 'News', 'post-snippets' ),
				$capability,
				'post-snippets-news',
				array( $this, 'newsPage' )
				// array( $this, 'overviewPage' )
			);
			/*
			$optionPage = add_submenu_page(
						__( 'Post Snippets', 'post-snippets'),
						__( 'Post Snippets', 'post-snippets'),
						$capability,
						'post-snippets',
						array ( &$this, 'optionsPage' )
					);*/
			new Help( $optionPage );
		} else {
			add_menu_page(
				__( 'Post Snippets', 'post-snippets' ),
				__( 'Post Snippets', 'post-snippets' ),
				'manage_options',
				'post-snippets',
				array( $this, 'overviewPage' ),
				PS_URL . 'assets/icon.svg'
			);
		}

	}

	/**
	 * Initialize assets for the administration page.
	 *
	 * @return void
	 */
	public function init() {
		wp_register_script(
			'post-snippets',
			plugins_url( '/assets/post-snippets.js', \PostSnippets::FILE ),
			array( 'jquery' ),
			PS_VERSION,
			true
		);
		$this->scripts();
		$this->registerSettings();
	}

	/**
	 * Enqueue scripts to be loaded.
	 *
	 * @return void
	 */
	public function scripts() {
		// Localize the strings in the script
		$translation_array = array(
			'invalid_shortcode'    => __( 'Invalid shortcode name', 'post-snippets' ),
			'save_title_nonce'     => wp_create_nonce( 'ps-save-title-nonce' ),
			'update_snippet_nonce' => wp_create_nonce( 'ps-update-snippet-nonce' ),
		);
		wp_localize_script( 'post-snippets', 'post_snippets', $translation_array );
		// Add CSS for Pro features page
		$features_style_url = plugins_url( '/assets/features.css', \PostSnippets::FILE );
		wp_register_style(
			'post-snippets-icons',
			$features_style_url,
			array(),
			PS_VERSION
		);
		wp_enqueue_style( 'post-snippets-icons' );
		// Add CSS for icons
		$features_style_url = plugins_url( '/assets/icons.css', \PostSnippets::FILE );
		wp_register_style(
			'post-snippets-features',
			$features_style_url,
			array(),
			PS_VERSION
		);
		wp_enqueue_style( 'post-snippets-features' );
		// Add CSS for newsletter opt-in
		$features_style_url = plugins_url( '/assets/newsletter.css', \PostSnippets::FILE );
		wp_register_style(
			'post-snippets-newsletter',
			$features_style_url,
			array(),
			PS_VERSION
		);
		wp_enqueue_style( 'post-snippets-newsletter' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'underscore' );
		wp_enqueue_script( 'post-snippets' );
	}

	/**
	 * Add X-XSS-Protection header.
	 *
	 * Newer versions of Chrome does not allow form tags to be submitted in the
	 * forms. This header disables that functionlity on the Post Snippets admin
	 * screen only.
	 */
	public function addHeaderXss( $current_screen ) {
		if ( $current_screen->base == 'settings_page_post-snippets/post-snippets' ) {
			header( 'X-XSS-Protection: 0' );
		}
	}

	/**
	 * Quick link to the Post Snippets Settings page from the Plugins page.
	 *
	 * @param array $links Array of all plugin links
	 *
	 * @return array $links Array with all the plugin's action links
	 */
	public function actionLinks( $links ) {
		$links[] = '<a href="' . PS_MAIN_PAGE_URL . '">' . __( 'Settings', 'post-snippets' ) . '</a>';
		return $links;
	}

	// -------------------------------------------------------------------------
	// Handle form submissions
	// -------------------------------------------------------------------------
	/**
	 * Add New Snippet.
	 */
	private function add() {

		if ( isset( $_POST['add-snippet'] ) && isset( $_POST['update_snippets_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['update_snippets_nonce'] ), 'update_snippets' ) && current_user_can( 'manage_options' ) ) {
			$snippets = get_option( \PostSnippets::OPTION_KEY );
			if ( empty( $snippets ) ) {
				$snippets = array();
			}
			array_push(
				$snippets,
				array(
					'title'       => 'Untitled',
					'vars'        => '',
					'description' => '',
					'shortcode'   => false,
					'php'         => false,
					'wptexturize' => false,
					'snippet'     => '',
				)
			);
			update_option( \PostSnippets::OPTION_KEY, $snippets );
			$this->message( __( 'A snippet named Untitled has been added.', 'post-snippets' ) );
		}

	}

	public function sync_list() {

		if ( isset( $_POST['sync_down'] ) && wp_verify_nonce( sanitize_key( $_POST['sync_down'] ), 'sync_down' ) && current_user_can( 'manage_options' ) ) {

			if ( postsnippets_fs()->can_use_premium_code() ) {
				$license = postsnippets_fs()->_get_license();

				if ( $license != null ) {
					$id           = $license->id;
					$secret       = $license->secret_key;
					$query        = array();
					$query['lid'] = $id;
					$query['s']   = $secret;
					$arg          = array(
						'method'      => 'POST',
						'body'        => ( $query ),
						'data_format' => 'body',
					);
					$response     = wp_remote_post( 'https://cloud.postsnippets.com/wp-content/themes/twentytwenty/ps-cloud/list.php', $arg );
					$body         = wp_remote_retrieve_body( $response );
					// $http_code = wp_remote_retrieve_response_code( $response );

					$response = json_decode( $body );
					// $ch = curl_init();
					// curl_setopt( $ch, CURLOPT_URL, "https://api.greentreelabs.net/postsnippets/list" );
					// curl_setopt( $ch, CURLOPT_POST, 1 );
					// curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $query ) );
					// curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					// $server_output = curl_exec( $ch );
					// $response = json_decode( $server_output );
					wp_send_json_success( $response );
				}
			}
		} else {
			wp_send_json_error();
		}

	}

	public function sync() {
		if ( isset( $_POST['sync_up'] ) && wp_verify_nonce( sanitize_key( $_POST['sync_up'] ), 'sync_up' ) && current_user_can( 'manage_options' ) ) {
            if ( ! isset( $_POST['ids'] ) ) {
                wp_send_json_error();
                return;
            }
			$ids = explode( ',', sanitize_text_field( wp_unslash( $_POST['ids'] ) ) );

			foreach ( $ids as $id ) {
				if ( ! is_numeric( $id ) ) {
					wp_send_json_error();
					return;
				}
			}

			if ( count( $ids ) == 0 ) {
				wp_send_json_error();
				return;
			}

			$snippets = get_option( \PostSnippets::OPTION_KEY );

			if ( postsnippets_fs()->can_use_premium_code() ) {
				$license = postsnippets_fs()->_get_license();

				if ( $license != null ) {
					$id           = $license->id;
					$secret       = $license->secret_key;
					$query        = array();
					$query['lid'] = $id;
					$query['s']   = $secret;
					$arg          = array(
						'method'      => 'POST',
						'body'        => ( $query ),
						'data_format' => 'body',
					);
					$response     = wp_remote_post( 'https://cloud.postsnippets.com/wp-content/themes/twentytwenty/ps-cloud/list.php', $arg );
					$body         = wp_remote_retrieve_body( $response );

					// $ch = curl_init();
					// curl_setopt( $ch, CURLOPT_URL, "https://api.greentreelabs.net/postsnippets/list" );
					// curl_setopt( $ch, CURLOPT_POST, 1 );
					// curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $query ) );
					// curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					// $server_output = curl_exec( $ch );

					$response       = json_decode( $body, true );
					$saved_snippets = $response['data'];
					$i              = 0;
					$updated        = array();
					foreach ( $snippets as &$snippet ) {
						foreach ( $saved_snippets as $saved ) {

							if ( $snippet['title'] == $saved['title'] && in_array( $saved['id'], $ids ) ) {
								$snippet                = $saved;
								$options                = json_decode( $saved['options'], true );
								$snippet['php']         = ( $options['php'] ? '1' : '0' );
								$snippet['shortcode']   = ( $options['shortcode'] ? '1' : '0' );
								$snippet['wptexturize'] = ( $options['wptexturize'] ? '1' : '0' );
								if ( defined( 'POST_SNIPPETS_DISABLE_PHP' ) ) {
									$snippet['php'] = '0';
								}
								$updated[] = $snippet['title'];
							}
						}
					}
					foreach ( $saved_snippets as $saved ) {
						if ( ! in_array( $saved['id'], $ids ) ) {
							continue;
						}

						if ( ! in_array( $saved['title'], $updated ) ) {
							$snippet                = $saved;
							$options                = json_decode( $saved['options'], true );
							$snippet['php']         = ( $options['php'] ? '1' : '0' );
							$snippet['shortcode']   = ( $options['shortcode'] ? '1' : '0' );
							$snippet['wptexturize'] = ( $options['wptexturize'] ? '1' : '0' );
							if ( defined( 'POST_SNIPPETS_DISABLE_PHP' ) ) {
								$snippet['php'] = '0';
							}
							$snippets[] = $snippet;
						}
					}
					update_option( \PostSnippets::OPTION_KEY, $snippets );
					wp_send_json_success();
				}
			}
		} else {
			wp_send_json_error();
		}

	}

	public function sync_up() {

		if ( isset( $_POST['sync_up'] ) && wp_verify_nonce( sanitize_key( $_POST['sync_up'] ), 'sync_up' ) && current_user_can( 'manage_options' ) ) {
			$snippets = get_option( \PostSnippets::OPTION_KEY );

			if ( empty( $snippets ) || ! isset( $_POST['key'] ) ) {
				wp_send_json_error( __( 'Nothing selected to save.', 'post-snippets' ) );
				return;
			}

			if ( postsnippets_fs()->can_use_premium_code() ) {
				$license = postsnippets_fs()->_get_license();

				if ( $license != null ) {
					$id       = $license->id;
					$secret   = $license->secret_key;
					$snippets = get_option( \PostSnippets::OPTION_KEY );

					if ( empty( $snippets ) || ! isset( $_POST['key'] ) ) {
						wp_send_json_error( __( 'Nothing selected to save.', 'post-snippets' ) );
						return;
					}

					$toSave      = sanitize_key( $_POST['key'] );
					$newsnippets = array();
					$data        = null;
					foreach ( $snippets as $key => $snippet ) {

						if ( $key == $toSave ) {
							$data = $snippet;
							break;
						}
					}

					if ( $data == null ) {
						wp_send_json_error( __( "Snippet {$key} not found", 'post-snippets' ) );
						return;
					}

                    if ( ! isset( $_POST['update'] ) ) {
                        wp_send_json_error( __( 'Update not defined.', 'post-snippets' ) );
                        return;
                    }

					$query                = array();
					$query['update']      = sanitize_text_field( wp_unslash( $_POST['update'] ) );
					$query['lid']         = $id;
					$query['s']           = $secret;
					$query['title']       = $snippet['title'];
					$query['description'] = $snippet['description'];
					$query['vars']        = $snippet['vars'];
					$query['snippet']     = $snippet['snippet'];
					$query['options']     = wp_json_encode(
						array(
							'shortcode'   => isset( $snippet['shortcode'] ) && $snippet['shortcode'] == '1',
							'php'         => isset( $snippet['php'] ) && $snippet['php'] == '1',
							'wptexturize' => isset( $snippet['wptexturize'] ) && $snippet['wptexturize'] == '1',
						)
					);

					$arg      = array(
						'method'      => 'POST',
						'body'        => ( $query ),
						'data_format' => 'body',
					);
					$response = wp_remote_post( 'https://cloud.postsnippets.com/wp-content/themes/twentytwenty/ps-cloud/list.php', $arg );
					$body     = wp_remote_retrieve_body( $response );

					// $ch = curl_init();
					// curl_setopt( $ch, CURLOPT_URL, "https://api.greentreelabs.net/postsnippets/save" );
					// curl_setopt( $ch, CURLOPT_POST, 1 );
					// curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $query ) );
					// curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					// $server_output = curl_exec( $ch );
					// curl_close( $ch );

					$response = json_decode( $body );

					if ( isset( $response->code ) && $response->code != '1' ) {
						$msg = '?';
						if ( isset( $response->code ) ) {
							$msg = $response->data;
						}
						wp_send_json_error( $response );
					} else {
						wp_send_json_success();
					}
				} else {
					wp_send_json_error( 'enter-license' );
					return;
				}
			} else {
				wp_send_json_error( 'license' );
				return;
			}
		}

	}

	/**
	 * Delete snippet via ajax
	 */
	public function delete_post_snippet() {

		if ( isset( $_POST['delete_snippet'] ) && wp_verify_nonce( sanitize_key( $_POST['delete_snippet'] ), 'delete_snippet' ) && current_user_can( 'manage_options' ) ) {
			$snippets = get_option( \PostSnippets::OPTION_KEY );

			if ( empty( $snippets ) || ! isset( $_POST['key'] ) ) {
				wp_send_json_error( __( 'Nothing selected to delete.', 'post-snippets' ) );
				return;
			}

			$delete      = sanitize_key( $_POST['key'] );
			$newsnippets = array();
			foreach ( $snippets as $key => $snippet ) {
				if ( $key != $delete ) {
					array_push( $newsnippets, $snippet );
				}
			}
			update_option( \PostSnippets::OPTION_KEY, $newsnippets );
			wp_send_json_success();
		} else {
			wp_send_json_error( __( 'Security check failed.', 'post-snippets' ) );
		}

		wp_die();
	}

	/**
	 * Delete Snippet/s.
	 */
	private function delete() {

		if ( isset( $_POST['delete-snippets'] ) && isset( $_POST['update_snippets_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['update_snippets_nonce'] ), 'update_snippets' ) && current_user_can( 'manage_options' ) ) {
			$snippets = get_option( \PostSnippets::OPTION_KEY );

			if ( empty( $snippets ) || ! isset( $_POST['checked'] ) ) {
				$this->message( __( 'Nothing selected to delete.', 'post-snippets' ) );
				return;
			}

			$delete      = array_map( 'sanitize_text_field', wp_unslash( $_POST['checked'] ) );
			$newsnippets = array();
			foreach ( $snippets as $key => $snippet ) {
				if ( in_array( $key, $delete ) == false ) {
					array_push( $newsnippets, $snippet );
				}
			}
			update_option( \PostSnippets::OPTION_KEY, $newsnippets );
			$this->message( __( 'Selected snippets have been deleted.', 'post-snippets' ) );
		}

	}

	/**
	 * Update Snippet/s.
	 */
	private function update() {
		if ( isset( $_POST['update-snippets'] ) && isset( $_POST['update_snippets_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['update_snippets_nonce'] ), 'update_snippets' ) && current_user_can( 'manage_options' ) ) {
			$default            = array(
				'title'       => '',
				'rtf'         => '0',
				'snippet'     => '',
				'description' => '',
				'vars'        => '',
				'shortcode'   => '0',
				'php'         => '0',
				'wptexturize' => '0',
			);
			$formatted_snippets = array();

			if ( ! empty( $_POST['snippets'] ) ) {
				$snippets = array_map( 'sanitize_text_field', wp_unslash( $_POST['snippets'] ) );
				$i        = 0;
				foreach ( $snippets as $snippet ) {
					if ( empty( $snippets ) ) {
						continue;
					}
					$snippet          = wp_parse_args( $snippet, $default );
					$snippet['title'] = str_replace( ' ', '', $snippet['title'] );
					if ( defined( 'POST_SNIPPETS_DISABLE_PHP' ) ) {
						$snippet['php'] = '0';
					}
					$snippet['snippet']       = wp_specialchars_decode( trim( stripslashes( $snippet['snippet'] ) ), ENT_NOQUOTES );
					$snippet['description']   = wp_specialchars_decode( trim( stripslashes( $snippet['description'] ) ), ENT_NOQUOTES );
					$formatted_snippets[ $i ] = $snippet;
					$i++;
				}
			}

			update_option( \PostSnippets::OPTION_KEY, $formatted_snippets );
			$this->message( __( 'Snippets have been updated.', 'post-snippets' ) );
		}

	}

	/**
	 * Update User Option.
	 *
	 * Sets the per user option for the read-only overview page.
	 *
	 * @since   Post Snippets 1.9.7
	 */
	private function setUserOptions() {

		if ( isset( $_POST['post_snippets_user_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['post_snippets_user_nonce'] ), 'post_snippets_user_options' ) && current_user_can( 'manage_options' ) ) {
			$id     = get_current_user_id();
			$render = ( isset( $_POST['render'] ) ? true : false );
			update_user_meta( $id, \PostSnippets::USER_META_KEY, $render );
		}

	}

	/**
	 * Get User Option.
	 *
	 * Gets the per user option for the read-only overview page.
	 *
	 * @since   Post Snippets 1.9.7
	 * @return  boolean If overview should be rendered on output or not
	 */
	private function getUserOptions() {
		$id      = get_current_user_id();
		$options = get_user_meta( $id, \PostSnippets::USER_META_KEY, true );
		return $options;
	}

	// -------------------------------------------------------------------------
	// HTML generation for option pages
	// -------------------------------------------------------------------------
	/**
	 * Display Flashing Message.
	 *
	 * @param   string $message Message to display to the user.
	 */
	private function message( $message ) {
		if ( $message ) {
			echo "<div class='updated'><p><strong>" .esc_html( $message ). "</strong></p></div>";
		}
	}

	public function newsPage() {
		$plugins = array(
			array(
				'name'        => 'myCred',
				'description' => 'myCred makes it simple to create a loyalty program or gamify your website so that you can increase the average customer value with less marketing effort.',
				'image'       => 'myCred.png',
				'url'         => 'https://wordpress.org/plugins/mycred',
			),
			array(
				'name'        => 'WP Contact Slider',
				'description' => 'WP contact slider is simple contact slider to display contactform7, Gravity forms, Wp Forms, Caldera forms, Constant Contact Forms or display random text or HTML.',
				'image'       => 'ContactSlider.png',
				'url'         => 'https://wordpress.org/plugins/wp-contact-slider/',
			),
			array(
				'name'        => 'Wholesale For WooCommerce',
				'description' => 'Sign up wholesale customers and display wholesale prices based on multiple wholesale user roles on your existing WooCommerce store',
				'image'       => 'WholesaleForWoocommerce.png',
				'url'         => 'https://woocommerce.com/products/wholesale-for-woocommerce/',
			),
			array(
				'name'        => 'WooCommerce Product Disclaimer',
				'description' => 'Woocommerce extension where you can set some products to accept terms and conditions before adding product to cart.            ',
				'image'       => 'ProductDisclaimer.png',
				'url'         => 'https://wordpress.org/plugins/woo-product-disclaimer/',
			),
		);
		include PS_PATH . '/views/admin_news.php';
	}

	/**
	 * Creates the snippets administration page.
	 *
	 * For users with manage_options capability (admin, super admin).
	 *
	 * @since   Post Snippets 1.8.8
	 */
	public function optionsPage() {
		// Handle Form Submits
		$this->add();
		$this->delete();
		$this->update();
		// Header
		echo '
        <!-- Create a header in the default WordPress \'wrap\' container -->
        <div class="wrap">
            <div id="icon-plugins" class="icon32"></div>
            <h2>';
		echo esc_html__( 'Post Snippets', 'post-snippets' );
		echo '</h2>';
		// Tabs
		$active_tab = ( isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'snippets' ); // phpcs:ignore
		$base_url   = '?page=post-snippets&amp;tab=';
		$tabs       = array(
			'snippets' => __( 'Manage Snippets', 'post-snippets' ),
			'options'  => __( 'Options', 'post-snippets' ),
			'tools'    => __( 'Import/Export', 'post-snippets' ),
		);
		if ( postsnippets_fs()->is_not_paying() ) {
			$tabs['upgrade'] = __( 'Upgrade', 'post-snippets' );
		}
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab => $title ) {
			$active = ( $active_tab == $tab ? ' nav-tab-active' : '' );
			$link   = $base_url . $tab;
			if ( $tab == 'upgrade' ) {
				$link = postsnippets_fs()->get_upgrade_url();
			}
			echo "<a href='" . esc_url( $link ) . "' class='nav-tab " . esc_attr( $active ) . ' tab-' . esc_attr( $tab ) . "'>" . esc_html( $title ) . '</a>';
		}
		echo '</h2>';
		// Tab content

		if ( $active_tab == 'snippets' ) {
			$this->tabSnippets();
		} elseif ( $active_tab == 'options' ) {
			$this->tabOptions();
		} elseif ( $active_tab == 'tools' ) {
			$this->tabTools();
		} else {
			$this->tabFeatures();
		}

		// Close it
		echo '</div>';
	}

	/**
	 * Tab to Manage Snippets.
	 *
	 * @since   Post Snippets 2.0
	 */
	private function tabSnippets() {
		echo '<p class="description post-snippets-documentation-note">';
		esc_html_e( 'Click \'Help\' in the top right for the documentation!', 'post-snippets' );
		echo '</p>';
		$data = array();
		echo View::render( 'admin_snippets', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Tab to set options for the plugin.
	 *
	 * @return void
	 */
	private function tabOptions() {
		echo '<p class="description post-snippets-documentation-note">';
		esc_html_e( 'Click \'Help\' in the top right for the documentation!', 'post-snippets' );
		echo '</p>';
		$data = array();
		echo View::render( 'admin_options', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Tab for Import/Export
	 *
	 * @since   Post Snippets 2.0
	 */
	private function tabTools() {
		echo '<p class="description post-snippets-documentation-note">';
		esc_html_e( 'Click \'Help\' in the top right for the documentation!', 'post-snippets' );
		echo '</p>';
		$ie = new ImportExport();
		// Create header and export html form
		printf( '<h3>%s</h3>', esc_html__( 'Import/Export', 'post-snippets' ) );
		printf( '<h4>%s</h4>', esc_html__( 'Export', 'post-snippets' ) );
		echo '<form method="post">';
		echo '<p>';
		esc_html_e( 'Export your snippets for backup or to import them on another site.', 'post-snippets' );
		echo '</p>';
		printf( "<input type='submit' class='button' name='postsnippets_export' value='%s' />", esc_html__( 'Export Snippets', 'post-snippets' ) );
        wp_nonce_field( 'pspro_export_snippets', 'pspro_export_snippets_nonce', false );
		echo '</form>';
		// Export logic, and import html form and logic
		$ie->exportSnippets();
		echo $ie->importSnippets(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Tab for Pro features
	 *
	 * @since   Post Snippets 2.5.4
	 */
	private function tabFeatures() {
		$features = new Features();
		echo $features->showFeatures(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Creates a read-only overview page.
	 *
	 * For users with edit_posts capability but without manage_options
	 * capability.
	 *
	 * @since   Post Snippets 1.9.7
	 */
	public function overviewPage() {
		// Header
		echo '<div class="wrap">';
		echo '<h2>Post Snippets</h2>';
		echo '<p>';
		esc_html_e( 'This is an overview of all snippets defined for this site. These snippets are inserted into posts from the post editor using the Post Snippets button. You can choose to see the snippets here as-is or as they are actually rendered on the website. Enabling rendered snippets for this overview might look strange if the snippet have dependencies on variables, CSS or other parameters only available on the frontend. If that is the case it is recommended to keep this option disabled.', 'post-snippets' );
		echo '</p>';
		// Form
		$this->setUserOptions();
		$render = $this->getUserOptions();
		echo '<form method="post" action="">';
		wp_nonce_field( 'post_snippets_user_options', 'post_snippets_user_nonce' );
		$this->checkbox( __( 'Display rendered snippets', 'post-snippets' ), 'render', $render );
		$this->submit( 'update-post-snippets-user', __( 'Update', 'post-snippets' ) );
		echo '</form>';
		// Snippet List
		$snippets = get_option( \PostSnippets::OPTION_KEY );
		if ( ! empty( $snippets ) ) {
			foreach ( $snippets as $key => $snippet ) {
				echo "<hr style='border: none;border-top:1px dashed #aaa; margin:24px 0;' />";
				echo '<h3>' . esc_html( $snippet['title'] );
				if ( $snippet['description'] ) {
					echo "<span class='description'> " . esc_html( $snippet['description'] ) . '</span>';
				}
				echo '</h3>';
				if ( $snippet['vars'] ) {
					printf( '<strong>%s:</strong> ' . esc_html( $snippet['vars'] ) . '<br/>', esc_html__( 'Variables', 'post-snippets' ) );
				}
				// echo "<strong>Variables:</strong> {$snippet['vars']}<br/>";
				$options = array();
				if ( $snippet['shortcode'] ) {
					array_push( $options, 'Shortcode' );
				}
				if ( $snippet['php'] ) {
					array_push( $options, 'PHP' );
				}
				if ( $snippet['wptexturize'] ) {
					array_push( $options, 'wptexturize' );
				}
				if ( $options ) {
					printf( '<strong>%s:</strong> %s<br/>', esc_html__( 'Options', 'post-snippets' ), esc_html( implode( ', ', $options ) ) );
				}
				printf( '<br/><strong>%s:</strong><br/>', esc_html__( 'Snippet', 'post-snippets' ) );

				if ( $render ) {
					echo do_shortcode( $snippet['snippet'] );
				} else {
					echo '<code>';
					echo esc_html( $snippet['snippet'] );
					echo '</code>';
				}
			}
		}
		// Close
		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// Register and callbacks for the options tab
	// -------------------------------------------------------------------------
	/**
	 * Register settings for the options tab.
	 *
	 * @return void
	 */
	protected function registerSettings() {
		$this->settings = get_option( \PostSnippets::SETTINGS );
		register_setting( \PostSnippets::SETTINGS, \PostSnippets::SETTINGS );
		add_settings_section(
			'general_section',
			__( 'General', 'post-snippets' ),
			null,
			'post-snippets'
		);
		add_settings_field(
			'exclude_from_custom_editors',
			__( 'Exclude from Custom Editors', 'post-snippets' ),
			array( $this, 'cbExcludeFromCustomEditors' ),
			'post-snippets',
			'general_section',
			array(
				'id'          => 'exclude_from_custom_editors',
				'label_for'   => 'exclude_from_custom_editors',
				'description' => __( 'Checking this only includes Post Snippets on standard WordPress post editing screens.', 'post-snippets' ),
			)
		);
	}

	/**
	 * Callback for HTML generator for exlusion of custom editors.
	 *
	 * @param  array $args
	 *
	 * @return  void
	 */
	public function cbExcludeFromCustomEditors( $args ) {
		$checked = ( isset( $this->settings[ $args['id'] ] ) ? $this->settings[ $args['id'] ] : false );
		echo "<input type='checkbox' id='" . esc_attr( $args['id'] ) . "' ";
		echo "name='" . esc_attr( \PostSnippets::SETTINGS . '[' . $args['id'] . ']' ) . "'" . "value='1' ";
		if ( $checked ) {
			echo 'checked ';
		}
		echo ' />';
		echo "<span class='description'>" . esc_html( $args['description'] ) . '</span>';
	}

	// -------------------------------------------------------------------------
	// HTML and Form element methods for Snippets form
	// -------------------------------------------------------------------------
	/**
	 * Checkbox.
	 *
	 * Renders the HTML for an input checkbox.
	 *
	 * @param   string  $label The label rendered to screen
	 * @param   string  $name The unique name and id to identify the input
	 * @param   boolean $checked If the input is checked or not
	 *
	 * @return  void
	 */
	public static function checkbox( $label, $name, $checked ) {
		echo '<label for="' . esc_attr( $name ) . '">';
		printf( '<input type="checkbox" name="%1$s" id="%1$s" value="true"', esc_attr( $name ) );
		if ( $checked ) {
			echo ' checked';
		}
		echo ' />';
		echo esc_html( $label ) . '</label><br/>';
	}

	/**
	 * Submit.
	 *
	 * Renders the HTML for a submit button.
	 *
	 * @since   Post Snippets 1.9.7
	 *
	 * @param   string  $name The name that identifies the button on submit
	 * @param   string  $label The label rendered on the button
	 * @param   string  $class Optional. Button class. Default: button-primary
	 * @param   boolean $wrap Optional. Wrap in a submit div. Default: true
	 *
	 * @return  void
	 */
	public static function submit(
		$name,
		$label,
		$class = 'button-primary',
		$wrap = true
	) {

		if ( $wrap ) {

			echo "<div class=\"submit\"><input type=submit name='" . esc_attr( $name ) . "' value='" . esc_attr( $label ) . "' class='" . esc_attr( $class ) . "' />&nbsp;&nbsp;&nbsp;</div>";
			return;

		}

		echo "<input type=submit name='" . esc_attr( $name ) . "' value='" . esc_attr( $label ) . "' class='" . esc_attr( $class ) . "' />&nbsp;&nbsp;&nbsp;";

	}

	/**
	 *
	 * Show newsletter opt-in, only in Post Snippets.
	 * Not on Pro features tab/page.
	 * Not when user selected to Hide opt-in.
	 *
	 * @since   2.5.4
	 */
	public function admin_notice_newsletter() {
		// Hide newsletter opt-in if option is true
		if ( get_option( 'ps_hide_admin_notice_newsletter' ) == true ) {
			return;
		}
		// Set option if "hide" button click detected (custom querystring value set to 1).

		if ( ! empty( $_REQUEST['ps-dismiss-newsletter-nag'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option( 'ps_hide_admin_notice_newsletter', true );
			return;
		}

		// Show newsletter notice.

		if ( get_current_screen()->id == 'settings_page_post-snippets/post-snippets' ) {
			$active_tab = ( isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'snippets' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $active_tab != 'features' ) {
				include_once PS_PATH . '/views/admin_notice_newsletter.php';
			}
		}

	}

	/**
	 *
	 * Show 'Get started' admin notice', everywhere.
	 * Not when user already clicked or dismissed.
	 *
	 * @since   2.5.4
	 */
	public function admin_notice_get_started() {
		// Hide newsletter opt-in if option is true
		if ( get_option( 'ps_hide_admin_notice_get_started' ) == true ) {
			return;
		}
		// Set option if "hide" button click detected (custom query string value set to 1).

		if ( ! empty( $_REQUEST['ps-dismiss-get-started-nag'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option( 'ps_hide_admin_notice_get_started', true );
			return;
		}

		// Show newsletter notice.

		if ( strpos( get_current_screen()->id, '/post-snippets' ) == false ) {
			$active_tab = ( isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'snippets' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $active_tab != 'features' ) {
				include_once PS_PATH . '/views/admin_notice_get_started.php';
			}
		}

	}

	/**
	 * Save Updated sorting
	 */
    /*
	public function update_snippets_order() {
		if ( empty( $_POST['order'] ) || ! is_array( $_POST['order'] ) ) {
			wp_send_json_error( 'order data not received' );
		}
		$capability = $this->get_allowed_capability();
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( 'permission denied' );
		}
		$orders   = array_map( 'sanitize_key', array_map( 'intval', $_POST['order'] ) );
		$snippets = get_option( 'post_snippets_options', array() );
		if ( empty( $snippets ) ) {
			wp_send_json_error( 'snippets empty' );
		}
		$updated_order = array();
		foreach ( $orders as $order ) {
			if ( isset( $snippets[ $order ] ) ) {
				$updated_order[] = $snippets[ $order ];
			}
		}
		update_option( 'post_snippets_options', $updated_order );
		wp_send_json_success( 'success' );
	}
    */

	/**
	 * Save Updated title
	 */
	public function update_post_snippet_title() {
		if ( ! isset( $_POST['key'] ) || empty( $_POST['title'] ) ) {
			wp_send_json_error();
		}
		if ( ! isset( $_POST['save_title_nonce'] ) || !wp_verify_nonce( sanitize_key( $_POST['save_title_nonce'] ), 'ps-save-title-nonce' ) || !current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$capability = $this->get_allowed_capability();
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( 'permission denied' );
		}
		$pkey     = intval( sanitize_text_field( wp_unslash( $_POST['key'] ) ) );
		$title    = sanitize_text_field( wp_unslash( $_POST['title'] ) );
		$snippets = get_option( 'post_snippets_options', array() );
		if ( empty( $snippets ) ) {
			wp_send_json_error();
		}

		if ( isset( $snippets[ $pkey ] ) && $snippets[ $pkey ]['title'] == $title ) {

			wp_send_json_error( __( 'You have not made any changes to the snippet title.', 'post-snippets' ) );
		}

		foreach ( $snippets as $key => $snippet ) {
			if ( $snippet['title'] == $title ) {
				wp_send_json_error( __( 'Duplicate title is not allowed. Please use different title for each snippets.', 'post-snippets' ) );
			}
		}

		if ( isset( $snippets[ $pkey ] ) ) {
			$snippets[ $pkey ]['title'] = $title;
			update_option( 'post_snippets_options', $snippets );
		}

		wp_send_json_success( $title );
	}

	public function update_post_snippet() {
		if ( ! isset( $_POST['key'] ) ) {
			wp_send_json_error();
		}
		if ( ! isset( $_POST['update_snippet_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['update_snippet_nonce'] ), 'ps-update-snippet-nonce' ) || !current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$capability = $this->get_allowed_capability();
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( 'permission denied' );
		}
		$key      = intval( sanitize_text_field( wp_unslash ( $_POST['key'] ) ) );
		$snippets = get_option( 'post_snippets_options', array() );
		if ( empty( $snippets ) ) {
			wp_send_json_error();
		}
		$title = '';
		if ( isset( $_POST['title'] ) ) {
			$title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
		}

		if ( isset( $snippets[ $key ] ) ) {
			$snippets[ $key ]['description'] = stripslashes( sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ) );
			$data = $_POST;
			$snippet = stripslashes( wp_unslash( $data['snippet'] ?? '' ) );
			// this variable contains php/js code. which need to be saved in database and executed later.
			// so we cannot use any sanitization function here.
			$snippets[ $key ]['snippet']     = $snippet ;

			$snippets[ $key ]['vars']        = stripslashes( sanitize_text_field(  wp_unslash( $_POST['vars'] ?? '' ) ) );
			$snippets[ $key ]['shortcode']   = stripslashes( sanitize_text_field(  wp_unslash( $_POST['shortcode'] ?? '' ) ) );
			$snippets[ $key ]['php']         = stripslashes( sanitize_text_field(  wp_unslash( $_POST['php'] ?? '' ) ) );
			$snippets[ $key ]['wptexturize'] = stripslashes( sanitize_text_field(  wp_unslash( $_POST['wptexturize'] ?? '' ) ) );
			if ( ! empty( $title ) ) {
				$snippets[ $key ]['title'] = $title;
			}
			update_option( 'post_snippets_options', $snippets );
		}

		wp_send_json_success( $title );
	}

	public function get_allowed_capability() {
		$capability = 'manage_options';

		if ( defined( 'POST_SNIPPETS_ALLOW_EDIT_POSTS' ) && current_user_can( 'edit_posts' ) ) {
			$capability = 'edit_posts';
		}

		return $capability;
	}

}
