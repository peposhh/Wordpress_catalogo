<?php
/**
 * Plugin Name: Animated Text Block
 * Description: Apply animation on any text.
 * Version: 1.0.4
 * Author: bPlugins LLC
 * Author URI: http://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: animated-text
 */

// ABS PATH
if ( !defined( 'ABSPATH' ) ) { exit; }

// Constant
define( 'ATB_PLUGIN_VERSION', isset($_SERVER['HTTP_HOST']) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '1.0.4' );
define( 'ATB_ASSETS_DIR', plugin_dir_url( __FILE__ ) . 'assets/' );

// Animated Text Block
class ATBAnimatedTextBlock{
	function __construct(){
		add_action( 'enqueue_block_assets', [$this, 'enqueueBlockAssets'] );
		add_action( 'init', [$this, 'onInit'] );
	}

	function enqueueBlockAssets(){
		wp_enqueue_style( 'animate', ATB_ASSETS_DIR . 'css/animate.min.css', [], '4.1.1' );
		wp_enqueue_script( 'textillate', ATB_ASSETS_DIR . 'js/jquery.textillate.min.js', [ 'jquery' ], ATB_PLUGIN_VERSION, true );
	}

	function onInit() {
		wp_register_style( 'atb-animated-text-editor-style', plugins_url( 'dist/editor.css', __FILE__ ), [ 'wp-edit-blocks' ], ATB_PLUGIN_VERSION ); // Backend Style
		wp_register_style( 'atb-animated-text-style', plugins_url( 'dist/style.css', __FILE__ ), [ 'wp-editor' ], ATB_PLUGIN_VERSION ); // Both Style

		register_block_type( __DIR__, [
			'editor_style'		=> 'atb-animated-text-editor-style',
			'style'				=> 'atb-animated-text-style',
			'render_callback'	=> [$this, 'render']
		] ); // Register Block

		wp_set_script_translations( 'atb-animated-text-editor-script', 'animated-text', plugin_dir_path( __FILE__ ) . 'languages' ); // Translate
	}

	function render( $attributes ){
		extract( $attributes );

		$className = $className ?? '';
		$atbBlockClassName = 'wp-block-atb-animated-text ' . $className . ' align' . $align;

		ob_start(); ?>
		<div class='<?php echo esc_attr( $atbBlockClassName ); ?>' id='atbAnimatedText-<?php echo esc_attr( $cId ) ?>' data-attributes='<?php echo esc_attr( wp_json_encode( $attributes ) ); ?>'></div>

		<?php return ob_get_clean();
	} // Render
}
new ATBAnimatedTextBlock;