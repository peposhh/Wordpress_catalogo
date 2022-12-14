<?php

/**
 * Post Snippets
 *
 * @package PS
 * @author  Postsnippets <support@wpexperts.io>
 * @license GPL-2.0+
 * @link    https://www.postsnippets.com
 *
 * @wordpress-plugin
 * Plugin Name: Post Snippets
 * Plugin URI: https://www.postsnippets.com
 * Description: Create a library of reusable content and insert it into your posts and pages. Navigate to "Settings > Post Snippets" to get started.
 * Version: 3.1.7
 * Author: Postsnippets
 * Author URI: https://www.postsnippets.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: post-snippets
 * Domain Path: /lang
 */

if ( ! function_exists( 'postsnippets_fs' ) ) {
	// Create a helper function for easy SDK access.
	function postsnippets_fs() {
		global  $postsnippets_fs;

		if ( ! isset( $postsnippets_fs ) ) {
			// Include Freemius SDK.
			include_once dirname( __FILE__ ) . '/freemius/start.php';
			$postsnippets_fs = fs_dynamic_init(
				array(
					'id'             => '1576',
					'slug'           => 'post-snippets',
					'type'           => 'plugin',
					'public_key'     => 'pk_58a2ec84c44485a459aae07bfaf5f',
					'is_premium'     => false,
					'has_addons'     => false,
					'has_paid_plans' => true,
					'trial'          => array(
						'days'               => 14,
						'is_require_payment' => true,
					),
					'menu'           => array(
						'slug'    => 'post-snippets',
						'contact' => false,
						'support' => false,
					),
					'is_live'        => true,
				)
			);
		}

		return $postsnippets_fs;
	}

	// Init Freemius.
	postsnippets_fs();
	// Signal that SDK was initiated.
	do_action( 'postsnippets_fs_loaded' );
	function postsnippets_fs_settings_url() {
		return admin_url( 'admin.php?page=post-snippets' );
	}

	// postsnippets_fs()->add_filter( 'connect_url', 'postsnippets_fs_settings_url' );
	// postsnippets_fs()->add_filter( 'after_skip_url', 'postsnippets_fs_settings_url' );
	// postsnippets_fs()->add_filter( 'after_connect_url', 'postsnippets_fs_settings_url' );
	// postsnippets_fs()->add_filter( 'after_pending_connect_url', 'postsnippets_fs_settings_url' );
	/**
	 * Only show Post Snippets submenu when PS is open, not on all Settings pages
	 */
	function postsnippets_show_submenu( $is_visible ) {
		if ( isset( $_REQUEST['page'] ) ) {
			if ( $is_visible && false !== strpos( sanitize_key( $_REQUEST['page'] ), 'post-snippets' ) ) {
				return true;
			}
		}
		return false;
	}

	postsnippets_fs()->add_filter(
		'is_submenu_visible',
		'postsnippets_show_submenu',
		10,
		2
	);
	if ( ! defined( 'PS_MAIN_FILE' ) ) {
		define( 'PS_MAIN_FILE', basename( __FILE__ ) );
	}
	if ( ! defined( 'PS_VERSION' ) ) {
		define( 'PS_VERSION', '3.1.7' );
	}
	if ( ! defined( 'PS_MAIN_FILE_PATH' ) ) {
		define( 'PS_MAIN_FILE_PATH', __FILE__ );
	}
	if ( ! defined( 'PS_DIRECTORY' ) ) {
		define( 'PS_DIRECTORY', plugin_basename( dirname( __FILE__ ) ) );
	}
	if ( ! defined( 'PS_PATH' ) ) {
		define( 'PS_PATH', plugin_dir_path( __FILE__ ) );
	}
	if ( ! defined( 'PS_URL' ) ) {
		define( 'PS_URL', plugins_url( '', __FILE__ ) . '/' );
	}
	if ( ! defined( 'PS_MAIN_PAGE_URL' ) ) {
		define( 'PS_MAIN_PAGE_URL', esc_url( admin_url( 'admin.php?page=post-snippets' ) ) );
	}
	class PostSnippets {

		/**
		 * Holds the plugin instance
		 */
		private static $instance = false;
		/**
         * Define plugin constants
         */
		const  MIN_PHP_VERSION = '5.3.0';
		const  MIN_WP_VERSION  = '3.3';
		const  SETTINGS        = 'post_snippets';
		const  OPTION_KEY      = 'post_snippets_options';
		const  USER_META_KEY   = 'post_snippets';
		const  FILE            = __FILE__;
		/**
		 * Singleton class
		 */
		public static function getInstance() {
			if ( ! self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Initializes the plugin.
		 */
		private function __construct() {
			if ( ! $this->testHost() ) {
				return;
			}
			load_plugin_textdomain( 'post-snippets', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
			add_action( 'after_setup_theme', array( &$this, 'phpExecState' ) );
			new \PostSnippets\Admin();
			new \PostSnippets\WPEditor();
			new \PostSnippets\Shortcode();
		}

		/**
		 * PSR-0 compliant autoloader to load classes as needed.
		 *
		 * @param string $classname
		 *
		 * @return void
		 */
		public static function autoload( $className ) {
			if ( __CLASS__ !== mb_substr( $className, 0, strlen( __CLASS__ ) ) ) {
				return;
			}
			$className = ltrim( $className, '\\' );
			$fileName  = '';
			$namespace = '';

			if ( $lastNsPos = strrpos( $className, '\\' ) ) {
				$namespace = substr( $className, 0, $lastNsPos );
				$className = substr( $className, $lastNsPos + 1 );
				$fileName  = str_replace( '\\', DIRECTORY_SEPARATOR, $namespace );
				$fileName .= DIRECTORY_SEPARATOR;
			}

			$fileName .= str_replace( '_', DIRECTORY_SEPARATOR, $className );
			include 'src' . DIRECTORY_SEPARATOR . $fileName . '.php';
		}

		// -------------------------------------------------------------------------
		// Helpers
		// -------------------------------------------------------------------------
		/**
		 * Allow snippets to be retrieved directly from PHP.
		 *
		 * @since Post Snippets 1.8.9.1
		 *
		 * @param string       $name      The name of the snippet to retrieve
		 * @param string|array $variables The variables to pass to the snippet,
		 *                                formatted as a query string or an associative array.
		 *
		 * @return string  The Snippet
		 */
		public static function getSnippet( $name, $variables = '' ) {
			$snippets 		= get_option( self::OPTION_KEY, array() );
			$snippet_count 	= count( $snippets );
			for ( $i = 0;  $i < $snippet_count;  $i++ ) {

				if ( $snippets[ $i ]['title'] == $name ) {
					if ( ! is_array( $variables ) ) {
						parse_str( htmlspecialchars_decode( $variables ), $variables );
					}
					$snippet = $snippets[ $i ]['snippet'];
					$var_arr = explode( ',', $snippets[ $i ]['vars'] );
					if ( ! empty( $var_arr[0] ) ) {
						$var_arr_count = count( $var_arr );
						for ( $j = 0;  $j < $var_arr_count;  $j++ ) {
							$snippet = str_replace( '{' . $var_arr[ $j ] . '}', $variables[ $var_arr[ $j ] ], $snippet );
						}
					}
					break;
				}
			}
			return do_shortcode( $snippet );
		}

		// -------------------------------------------------------------------------
		// Environment Checks
		// -------------------------------------------------------------------------
		/**
		 * Checks PHP and WordPress versions.
		 */
		private function testHost() {
			// Check if PHP is too old

			if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
				// Display notice
				add_action( 'admin_notices', array( &$this, 'phpVersionError' ) );
				return false;
			}

			// Check if WordPress is too old
			global  $wp_version;

			if ( version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
				add_action( 'admin_notices', array( &$this, 'wpVersionError' ) );
				return false;
			}

			return true;
		}

		/**
		 * Displays a warning when installed on an old PHP version.
		 */
		public function phpVersionError() {
			echo '<div class="error"><p><strong>';
			printf(
				'Error: %3$s requires PHP version %1$s or greater.<br/>' . 'Your installed PHP version: %2$s',
				esc_html( self::MIN_PHP_VERSION ),
				esc_html( PHP_VERSION ),
				esc_html( $this->getPluginName() )
			);
			echo '</strong></p></div>';
		}

		/**
		 * Displays a warning when installed in an old WordPress version.
		 */
		public function wpVersionError() {
			echo '<div class="error"><p><strong>';
			printf( 'Error: %2$s requires WordPress version %1$s or greater.', esc_html( self::MIN_WP_VERSION ), esc_html( $this->getPluginName() ) );
			echo '</strong></p></div>';
		}

		/**
		 * Get the name of this plugin.
		 *
		 * @return string The plugin name.
		 */
		private function getPluginName() {
			$data = get_plugin_data( self::FILE );
			return $data['Name'];
		}

		// -------------------------------------------------------------------------
		// Deprecated methods
		// -------------------------------------------------------------------------
		/**
		 * Allow plugins to disable the PHP Code execution feature with a filter.
		 * Deprecated: Use the POST_SNIPPETS_DISABLE_PHP global constant to disable
		 * PHP instead.
		 *
		 * @see        http://wordpress.org/extend/plugins/post-snippets/faq/
		 * @since      2.1
		 * @deprecated 2.3
		 */
		public function phpExecState() {
			$filter = apply_filters( 'post_snippets_php_execution_enabled', true );

			if ( $filter == false && ! defined( 'POST_SNIPPETS_DISABLE_PHP' ) ) {
				_deprecated_function( 'post_snippets_php_execution_enabled', '2.3', 'define(\'POST_SNIPPETS_DISABLE_PHP\', true);' );
				define( 'POST_SNIPPETS_DISABLE_PHP', true );
			}

		}

	}
	add_action( 'plugins_loaded', array( 'PostSnippets', 'getInstance' ) );
	/**
	 * Load all of the necessary class files for the plugin
	 */
	spl_autoload_register( 'PostSnippets::autoload' );
}
