<?php
/**
 * A custom WordPress plugin.
 *
 * @package  Mazepress\Skeleton
 * @internal This file is used only when running this package as a standalone plugin.
 *
 * @wordpress-plugin
 * Plugin Name:         Skeleton
 * Version:             1.0.0
 * Description:         A custom WordPress Skeleton plugin.
 * Plugin URI:          https://github.com/mazepress/skeleton
 * Author:              Mazepress
 * Author URI:          https://github.com/mazepress
 * License:             MIT
 * License URI:         https://opensource.org/licenses/MIT
 * Text Domain:         skeleton
 * Domain Path:         /languages
 * Requires PHP:        7.4
 * Requires at least:   5.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Autoload plugin packages.
 *
 * The package autoloader includes version information which prevents classes in this plugin
 * conflicting with other package classes.
 *
 * If the autoloader is not present, display admin notice.
 */
if ( ! is_readable( __DIR__ . '/vendor/autoload_packages.php' ) ) {
	// Show autoloader warning.
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%1$s</p></div>',
				wp_sprintf(
					/* translators: 1: Plugin name */
					esc_html__(
						'The installation of %1$s plugin is incomplete or some required files are missing!',
						'skeleton'
					),
					'<code>Skeleton</code>',
				)
			);
		}
	);
} else {
	// Load the autoloder.
	require_once __DIR__ . '/vendor/autoload_packages.php';

	// Register and load the plugin.
	add_action(
		'plugins_loaded',
		function () {
			skeleton()
				->set_path( plugin_dir_path( __FILE__ ) )
				->set_url( plugin_dir_url( __FILE__ ) )
				->init();
		}
	);
}

/**
 * Get the plugin main class instance.
 *
 * @return \Mazepress\Skeleton\App
 */
function skeleton(): \Mazepress\Skeleton\App {
	return \Mazepress\Skeleton\App::instance();
}
