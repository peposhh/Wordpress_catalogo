<?php
/**
* Plugin Name: Remove built by storefront
* Plugin URI: https://glassofcode.com/
* Description: Just a very basic plugin to remove built by storefront credit when you use storefront theme
* Version: 1.0
* Author: Glassofcode
* Author URI: http://glassofcode.com/
* 
**/
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * Check if storefront theme is active
 **/
$theme = wp_get_theme(); // gets the current theme

if ( 'Storefront' == $theme->name || 'Storefront' == $theme->parent_theme ) {
    
    add_action( 'init', 'removebuiltstrfrnt_remove_footer_credit', 10 );

	function removebuiltstrfrnt_remove_footer_credit () {
	    add_filter('storefront_credit_link', '__return_false');
	}
	
}