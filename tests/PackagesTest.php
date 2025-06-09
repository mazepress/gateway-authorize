<?php
/**
 * The Packages test class file.
 *
 * @package    Mazepress\Skeleton
 * @subpackage Tests
 */

declare(strict_types=1);

namespace Mazepress\Skeleton\Tests;

use WP_Mock\Tools\TestCase;
use Mazepress\Skeleton\App;
use Mazepress\Skeleton\Packages;
use Mazepress\Plugin\PackageInterface;
use Mazepress\Plugin\PluginInterface;
use WP_Mock;

/**
 * The Packages test class.
 *
 * @group Packages
 */
class PackagesTest extends TestCase {

	/**
	 * Test class properites.
	 *
	 * @return void
	 */
	public function test_properties(): void {

		$plugin = App::instance();
		$this->assertInstanceOf( PluginInterface::class, $plugin );

		$packages = new Packages( $plugin );
		$this->assertInstanceOf( PackageInterface::class, $packages );
		$this->assertInstanceOf( PluginInterface::class, $packages->get_package() );
		$this->assertInstanceOf( Packages::class, $packages->set_package( $plugin ) );
	}

	/**
	 * Test error message.
	 *
	 * @return void
	 */
	public function test_get_package_missing_message(): void {

		$plugin   = App::instance();
		$packages = new Packages( $plugin );
		$message  = 'The package PackageOne is missing';

		WP_Mock::userFunction( 'wp_sprintf' )
			->once()
			->andReturn( $message );

		$this->assertEquals( $message, $packages->get_package_missing_message( 'PackageOne', 'HelloWorld' ) );
	}

	/**
	 * Test load method.
	 *
	 * @return void
	 */
	public function test_load(): void {

		$plugin   = App::instance();
		$packages = new Packages( $plugin );

		$packs = array(
			'HelloWorld' => 'Mazepress\\Skeleton\\Tests\\Stubs\\HelloWorld',
		);

		$this->assertInstanceOf( Packages::class, $packages->set_packages( $packs ) );
		$this->assertEquals( $packs, $packages->get_packages() );

		WP_Mock::expectActionAdded( 'example_action', \WP_Mock\Functions::type( 'callable' ) );
		$packages->load();
		WP_Mock::assertActionsCalled();
	}

	/**
	 * Test load method.
	 *
	 * @return void
	 */
	public function test_load_error(): void {

		$plugin   = App::instance();
		$packages = new Packages( $plugin );

		$packs = array(
			'PackageOne' => 'Mazepress\\Skeleton\\Tests\\Stubs\\PackageOne',
		);

		$this->assertInstanceOf( Packages::class, $packages->set_packages( $packs ) );
		$this->assertEquals( $packs, $packages->get_packages() );

		WP_Mock::userFunction( 'wp_sprintf' )->once()->andReturn( 'The package not found' );
		WP_Mock::expectActionAdded( 'admin_notices', \WP_Mock\Functions::type( 'callable' ) );
		$packages->load();
		WP_Mock::assertActionsCalled();
	}
}
