<?php
/**
 * The Scripts class file.
 *
 * @package Mazepress\Skeleton
 */

declare(strict_types=1);

namespace Mazepress\Skeleton;

use Mazepress\Plugin\PluginInterface;

/**
 * The Scripts class.
 */
class Scripts {

	/**
	 * The package.
	 *
	 * @var PluginInterface $package
	 */
	private $package;

	/**
	 * Initiate class.
	 *
	 * @param PluginInterface $package The package.
	 */
	public function __construct( PluginInterface $package ) {
		$this->set_package( $package );
	}

	/**
	 * Enque scripts and style.
	 *
	 * @return void
	 */
	public function load(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {

		wp_enqueue_script( 'jquery' );

		wp_enqueue_script(
			'skeleton',
			$this->get_package()->get_url() . 'assets/js/main.min.js',
			array( 'jquery' ),
			$this->get_package()->get_version(),
			true
		);

		wp_localize_script(
			'skeleton',
			'skeleton',
			array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) )
		);

		wp_enqueue_style(
			'skeleton',
			$this->get_package()->get_url() . 'assets/css/style.min.css',
			array(),
			$this->get_package()->get_version(),
		);

		wp_enqueue_style(
			'skeleton-custom',
			$this->get_package()->get_url() . '/assets/css/custom.css',
			array( 'skeleton' ),
			$this->get_package()->get_version(),
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {

		wp_enqueue_style(
			'skeleton-admin',
			$this->get_package()->get_url() . 'assets/css/admin.min.css',
			array(),
			$this->get_package()->get_version(),
		);
	}

	/**
	 * Get the package.
	 *
	 * @return PluginInterface|null
	 */
	public function get_package(): ?PluginInterface {
		return $this->package;
	}

	/**
	 * Set the package.
	 *
	 * @param PluginInterface $package The package.
	 *
	 * @return self
	 */
	public function set_package( PluginInterface $package ): self {
		$this->package = $package;
		return $this;
	}
}
