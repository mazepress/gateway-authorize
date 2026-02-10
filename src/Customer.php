<?php
/**
 * The Customer class file.
 *
 * @package    Mazepress\Gateway
 * @subpackage Authorize
 */

declare(strict_types=1);

namespace Mazepress\Gateway\Authorize;

/**
 * The Customer class.
 */
class Customer {

	/**
	 * The profile ID.
	 *
	 * @var string
	 */
	private $profile_id;

	/**
	 * The payment profile IDs.
	 *
	 * @var string[]
	 */
	private $payment_profile_ids = array();

	/**
	 * Get profile ID.
	 *
	 * @return string|null
	 */
	public function get_profile_id(): ?string {
		return $this->profile_id;
	}

	/**
	 * Set profile ID.
	 *
	 * @param string $profile_id The profile ID.
	 *
	 * @return self
	 */
	public function set_profile_id( string $profile_id ): self {
		$this->profile_id = $profile_id;
		return $this;
	}

	/**
	 * Get payment profile IDs.
	 *
	 * @return string[]
	 */
	public function get_payment_profile_ids(): array {
		return $this->payment_profile_ids;
	}

	/**
	 * Set payment profile IDs.
	 *
	 * @param string[] $payment_profile_ids The payment profile IDs.
	 *
	 * @return self
	 */
	public function set_payment_profile_ids( array $payment_profile_ids ): self {
		$this->payment_profile_ids = $payment_profile_ids;
		return $this;
	}

	/**
	 * Append payment profile IDs.
	 *
	 * @param string $profile_id The payment profile ID.
	 *
	 * @return self
	 */
	public function append_payment_profile_ids( string $profile_id ): self {

		if ( ! empty( $profile_id ) && ! in_array( $profile_id, $this->payment_profile_ids, true ) ) {
			$this->payment_profile_ids[] = $profile_id;
		}

		return $this;
	}
}
