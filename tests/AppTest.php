<?php
/**
 * The AppTest class file.
 *
 * @package    Mazepress\Skeleton
 * @subpackage Tests
 */

declare(strict_types=1);

namespace Mazepress\Skeleton\Tests;

use WP_Mock\Tools\TestCase;
use Mazepress\Skeleton\App;
use Mazepress\Plugin\BasePlugin;
use Mazepress\Plugin\PluginInterface;
use WP_Mock;

/**
 * The AppTest class.
 */
class AppTest extends TestCase {

	/**
	 * Test class properites.
	 *
	 * @return void
	 */
	public function test_properties(): void {

		$plugin = App::instance();
		$this->assertInstanceOf( App::class, $plugin );
		$this->assertInstanceOf( BasePlugin::class, $plugin );
		$this->assertInstanceOf( PluginInterface::class, $plugin );

		// Define the regular expression for SemVer.
		$semver = '/^\d+\.\d+\.\d+(-[0-9A-Za-z-]+(\.[0-9A-Za-z-]+)*)?(\+[0-9A-Za-z-]+(\.[0-9A-Za-z-]+)*)?$/';

		$this->assertEquals( 'Skeleton', $plugin->get_name() );
		$this->assertEquals( 'skeleton', $plugin->get_slug() );
		$this->assertMatchesRegularExpression( $semver, $plugin->get_version() );
	}

	/**
	 * Test load method.
	 *
	 * @return void
	 */
	public function test_init(): void {

		$plugin = App::instance();
		$plugin->init();
		$plugin->init();
		WP_Mock::assertActionsCalled();
	}
}
