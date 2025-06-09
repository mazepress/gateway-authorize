<?php
/**
 * The HelloWorld stub class file.
 *
 * @package    Mazepress\Skeleton
 * @subpackage Tests\Stubs
 */

namespace Mazepress\Skeleton\Tests\Stubs;

use Mazepress\Plugin\BasePlugin;
use Mazepress\Plugin\PluginInterface;

/**
 * The HelloWorld class.
 */
final class HelloWorld extends BasePlugin {

	/**
	 * The name.
	 *
	 * @var string
	 */
	const NAME = 'HelloWorld';

	/**
	 * The slug.
	 *
	 * @var string
	 */
	const SLUG = 'hello-world';

	/**
	 * The version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

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
	 * @param PluginInterface $package The package.
	 *
	 * @return void
	 */
	public static function init( PluginInterface $package = null ): void {

		// ToDo - Register all classes.
		// Call sample action.
		add_action( 'example_action', function () {} );
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
