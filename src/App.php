<?php
/**
 * The App class file.
 *
 * @package Mazepress\Skeleton
 */

declare(strict_types=1);

namespace Mazepress\Skeleton;

use Mazepress\Plugin\BasePlugin;
use Mazepress\Skeleton\Packages;
use Mazepress\Skeleton\Scripts;

/**
 * The App class.
 */
final class App extends BasePlugin {

	/**
	 * The name.
	 *
	 * @var string
	 */
	const NAME = 'Skeleton';

	/**
	 * The slug.
	 *
	 * @var string
	 */
	const SLUG = 'skeleton';

	/**
	 * The version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Loaded init function.
	 *
	 * @var bool $loaded
	 */
	private static $loaded = false;

	/**
	 * Instance for this class.
	 *
	 * @var self|null $instance
	 */
	private static $instance;

	/**
	 * If an instance exists, returns it. If not, creates one and retuns it.
	 *
	 * @return self
	 */
	public static function instance(): self {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the package features.
	 *
	 * @return void
	 */
	public static function init(): void {

		// Prevent duplicate call.
		if ( self::$loaded ) {
			return;
		}

		// Load the dependent packages.
		( new Packages( self::$instance ) )->load();

		// Enque scripts and style.
		( new Scripts( self::$instance ) )->load();

		// ToDo - Register all classes.

		// Flag init loaded.
		self::$loaded = true;
	}

	/**
	 * Get the package name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return self::NAME;
	}

	/**
	 * Get the package slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return self::SLUG;
	}

	/**
	 * Get the package version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return self::VERSION;
	}

	/**
	 * Prevent initiate.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}
}
