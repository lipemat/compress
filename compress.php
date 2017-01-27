<?php
/**
 * Plugin Name: Compress
 * Description: Combine and Compress CSS and JS
 * Author: Mat Lipe
 * Author URI: https://matlipe.com
 * Version: 1.0
 *
 */
 // Block direct requests
if ( !defined('ABSPATH') )
    die('-1');

/**
 * Load all the plugin files and initialize appropriately
 *
 * @return void
 */
if ( !function_exists('compress_load') ) { // play nice
	function compress_load() {
		require_once('classes/Compress_Option.php');
		require_once('classes/Compress.php');
		require_once('classes/Compress_WP_Scripts.php');
		require_once('classes/Compress_WP_Styles.php');
		require_once('classes/Compress_Admin.php');
		add_action('plugins_loaded', array('Compress', 'init'));
		add_action('plugins_loaded', array('Compress_Admin', 'init'));
	}

	// Fire it up!
	compress_load();
}


function compress_flush() {
	$shrinker = Compress::get_instance();
	$shrinker->flush_cache();
}
