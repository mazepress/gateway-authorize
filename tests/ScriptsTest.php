<?php
/**
 * The ScriptsTest class file.
 *
 * @package    Mazepress\Skeleton
 * @subpackage Tests
 */

declare(strict_types=1);

namespace Mazepress\Skeleton\Tests;

use WP_Mock\Tools\TestCase;
use Mazepress\Skeleton\Scripts;
use Mazepress\Plugin\PluginInterface;
use Mazepress\Skeleton\Tests\Stubs\HelloWorld;
use WP_Mock;

/**
 * The ScriptsTest class.
 *
 * @group Scripts
 */
class ScriptsTest extends TestCase {

	/**
	 * Test class properites.
	 *
	 * @return void
	 */
	public function test_properties(): void {

		$package  = HelloWorld::instance();
		$instance = new Scripts( $package );

		$this->assertInstanceOf( Scripts::class, $instance->set_package( $package ) );
		$this->assertInstanceOf( PluginInterface::class, $instance->get_package() );
	}

	/**
	 * Test load function.
	 *
	 * @return void
	 */
	public function test_load(): void {

		$package  = HelloWorld::instance();
		$instance = new Scripts( $package );

		WP_Mock::passthruFunction( 'wp_enqueue_scripts' );
		WP_Mock::passthruFunction( 'admin_enqueue_scripts' );

		$instance->load();

		WP_Mock::assertActionsCalled();
	}

	/**
	 * Test enqueue_scripts function.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {

		$package  = HelloWorld::instance();
		$instance = new Scripts( $package );

		WP_Mock::passthruFunction( 'wp_enqueue_script' );
		WP_Mock::passthruFunction( 'wp_localize_script' );
		WP_Mock::passthruFunction( 'wp_enqueue_style' );
		WP_Mock::passthruFunction( 'admin_url' );

		$instance->enqueue_scripts();
		$instance->admin_enqueue_scripts();

		WP_Mock::assertActionsCalled();
	}
}
