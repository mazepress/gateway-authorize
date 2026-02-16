<?php
/**
 * The AuthorizeGatewayCustomer class file.
 *
 * @package    Mazepress\Gateway
 * @subpackage Authorize
 */

declare(strict_types=1);

namespace Mazepress\Gateway\Authorize;

use Mazepress\Gateway\Transaction;
use net\authorize\api\contract\v1\CreateCustomerPaymentProfileRequest;
use net\authorize\api\contract\v1\CreateCustomerPaymentProfileResponse;
use net\authorize\api\contract\v1\CreateCustomerProfileFromTransactionRequest;
use net\authorize\api\contract\v1\CreateCustomerProfileRequest;
use net\authorize\api\contract\v1\CreateCustomerProfileResponse;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\contract\v1\OrderType;
use net\authorize\api\contract\v1\PaymentType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\contract\v1\CustomerPaymentProfileExType;
use net\authorize\api\contract\v1\CustomerPaymentProfileType;
use net\authorize\api\contract\v1\CustomerProfileBaseType;
use net\authorize\api\contract\v1\CustomerProfilePaymentType;
use net\authorize\api\contract\v1\CustomerProfileType;
use net\authorize\api\contract\v1\DeleteCustomerPaymentProfileRequest;
use net\authorize\api\contract\v1\DeleteCustomerPaymentProfileResponse;
use net\authorize\api\contract\v1\PaymentProfileType;
use net\authorize\api\contract\v1\UpdateCustomerPaymentProfileRequest;
use net\authorize\api\contract\v1\UpdateCustomerPaymentProfileResponse;
use net\authorize\api\controller\CreateCustomerPaymentProfileController;
use net\authorize\api\controller\CreateCustomerProfileController;
use net\authorize\api\controller\CreateCustomerProfileFromTransactionController;
use net\authorize\api\controller\DeleteCustomerPaymentProfileController;
use net\authorize\api\controller\UpdateCustomerPaymentProfileController;
use WP_Error;

/**
 * The AuthorizeGatewayCustomer abstract class.
 */
class AuthorizeGatewayCustomer extends AuthorizeGateway {

	/**
	 * Process the payment. If the payment fails,
	 * it should return a WP_Error object.
	 *
	 * @param string $profile_id         The customer profile ID.
	 * @param string $payment_profile_id The payment profile ID.
	 *
	 * @return Transaction|WP_Error
	 */
	public function process_customer( string $profile_id, string $payment_profile_id ) {

		// Validate credentials.
		$validate = $this->validate_credentials();
		if ( is_wp_error( $validate ) ) {
			return $validate;
		}

		// Check for amount.
		if ( $this->get_amount() <= 0 ) {
			return new WP_Error( 'error', __( 'Invalid amount.', 'gatewayauthorize' ) );
		}

		// Check for amount.
		if ( empty( $profile_id ) || empty( $payment_profile_id ) ) {
			return new WP_Error( 'error', __( 'Invalid custoemr details.', 'gatewayauthorize' ) );
		}

		$customer_profile = ( new CustomerProfilePaymentType() )
			->setCustomerProfileId( $profile_id )
			->setPaymentProfile(
				( new PaymentProfileType() )
					->setPaymentProfileId( $payment_profile_id )
			);

		$transaction_request = ( new TransactionRequestType() )
			->setCurrencyCode( $this->get_currency_code() )
			->setAmount( $this->get_amount() )
			->setProfile( $customer_profile );

		$trans_type = $this->get_capture() ? 'authCaptureTransaction' : 'authOnlyTransaction';
		$transaction_request->setTransactionType( $trans_type );

		if ( ! empty( $this->get_invoice_id() ) ) {
			$transaction_request->setOrder(
				( new OrderType() )->setInvoiceNumber( $this->get_invoice_id() )
			);
		}

		$transaction = $this->process_transaction( $transaction_request );

		if ( ! is_wp_error( $transaction ) ) {
			$status = $this->get_capture() ? 'Paid' : 'Holding';
			$transaction->set_status( $status );
		}

		return $transaction;
	}

	/**
	 * Create customer profile from transaction ID.
	 *
	 * @param bool $default Make the payment profile default.
	 *
	 * @return Customer|WP_Error
	 */
	public function create_profile( bool $default = false ) {

		// Validate credentials.
		$validate = $this->validate_credentials();
		if ( is_wp_error( $validate ) ) {
			return $validate;
		}

		$credit_card = $this->get_card_type();
		if ( empty( $credit_card ) ) {
			return new WP_Error( 'error', __( 'Invalid credit card.', 'gatewayauthorize' ) );
		}

		// Set the payment type.
		$payment = ( new PaymentType() )->setCreditCard( $credit_card );

		$billing = $this->get_address_type();
		if ( empty( $billing ) ) {
			return new WP_Error( 'error', __( 'Invalid billing address.', 'gatewayauthorize' ) );
		}

		// Create a new Customer Payment Profile object.
		$payment_profile = new CustomerPaymentProfileType();
		$payment_profile->setBillTo( $billing );
		$payment_profile->setPayment( $payment );
		$payment_profile->setDefaultPaymentProfile( $default );

		// Create a new CustomerProfileType and add the payment profile object.
		$customer_profile = new CustomerProfileType();
		$customer_profile->setEmail( $billing->getEmail() );
		$customer_profile->setpaymentProfiles( array( $payment_profile ) );

		$request = new CreateCustomerProfileRequest();
		$request->setProfile( $customer_profile );
		$request->setMerchantAuthentication(
			( new MerchantAuthenticationType() )
				->setName( $this->get_public_key() )
				->setTransactionKey( $this->get_private_key() )
		);

		$controller = $this->get_controller();

		if ( ! $controller instanceof CreateCustomerProfileController ) {
			$controller = new CreateCustomerProfileController( $request );
		}

		try {
			$response = $controller->executeWithApiResponse( $this->get_endpoint() );

			if ( ! $response instanceof CreateCustomerProfileResponse ) {
				return new WP_Error( 'error', __( 'No response received from the API.', 'gatewayauthorize' ) );
			}
		} catch ( \Exception $ex ) {
			return new WP_Error( 'error', $ex->getMessage() );
		}

		if ( 'Ok' !== $response->getMessages()->getResultCode() ) {
			return new WP_Error( 'error', $response->getMessages()->getMessage()[0]->getText() );
		}

		$profile_id          = $response->getCustomerProfileId();
		$payment_profile_ids = $response->getCustomerPaymentProfileIdList();

		if ( empty( $profile_id ) || empty( $payment_profile_ids ) ) {
			return new WP_Error( 'error', __( 'Could not create customer profile.', 'gatewayauthorize' ) );
		}

		$customer = ( new Customer() )
			->set_profile_id( $profile_id )
			->set_payment_profile_ids( $payment_profile_ids );

		return $customer;
	}

	/**
	 * Create customer profile from transaction ID.
	 *
	 * @param string $transaction_id The transaction ID.
	 * @param string $email          The customer email.
	 *
	 * @return Customer|WP_Error
	 */
	public function create_profile_from_transaction( string $transaction_id, string $email ) {

		// Validate credentials.
		$validate = $this->validate_credentials();
		if ( is_wp_error( $validate ) ) {
			return $validate;
		}

		// Check for transaction id.
		if ( empty( $transaction_id ) ) {
			return new WP_Error( 'error', __( 'Invalid transaction id.', 'gatewayauthorize' ) );
		}

		$customer_profile = new CustomerProfileBaseType();
		$customer_profile->setEmail( $email );

		$request = new CreateCustomerProfileFromTransactionRequest();
		$request->setTransId( $transaction_id );
		$request->setCustomer( $customer_profile );
		$request->setMerchantAuthentication(
			( new MerchantAuthenticationType() )
				->setName( $this->get_public_key() )
				->setTransactionKey( $this->get_private_key() )
		);

		$controller = $this->get_controller();

		if ( ! $controller instanceof CreateCustomerProfileFromTransactionController ) {
			$controller = new CreateCustomerProfileFromTransactionController( $request );
		}

		try {
			$response = $controller->executeWithApiResponse( $this->get_endpoint() );

			if ( ! $response instanceof CreateCustomerProfileResponse ) {
				return new WP_Error( 'error', __( 'No response received from the API.', 'gatewayauthorize' ) );
			}
		} catch ( \Exception $ex ) {
			return new WP_Error( 'error', $ex->getMessage() );
		}

		if ( 'Ok' !== $response->getMessages()->getResultCode() ) {
			return new WP_Error( 'error', $response->getMessages()->getMessage()[0]->getText() );
		}

		$profile_id          = $response->getCustomerProfileId();
		$payment_profile_ids = $response->getCustomerPaymentProfileIdList();

		if ( empty( $profile_id ) || empty( $payment_profile_ids ) ) {
			return new WP_Error( 'error', __( 'Could not create customer profile.', 'gatewayauthorize' ) );
		}

		$customer = ( new Customer() )
			->set_profile_id( $profile_id )
			->set_payment_profile_ids( $payment_profile_ids );

		return $customer;
	}

	/**
	 * Create customer payment profile from profile ID.
	 *
	 * @param string $profile_id The profile ID.
	 * @param bool   $default    Mark as default payment profile.
	 *
	 * @return Customer|WP_Error
	 */
	public function create_payment_profile( string $profile_id, bool $default = false ) {

		// Validate credentials.
		$validate = $this->validate_credentials();
		if ( is_wp_error( $validate ) ) {
			return $validate;
		}

		// Check for profile id.
		if ( empty( $profile_id ) ) {
			return new WP_Error( 'error', __( 'Invalid profile id.', 'gatewayauthorize' ) );
		}

		$credit_card = $this->get_card_type();
		if ( empty( $credit_card ) ) {
			return new WP_Error( 'error', __( 'Invalid credit card.', 'gatewayauthorize' ) );
		}

		// Set the payment type.
		$payment = ( new PaymentType() )->setCreditCard( $credit_card );

		$billing = $this->get_address_type();
		if ( empty( $billing ) ) {
			return new WP_Error( 'error', __( 'Invalid billing address.', 'gatewayauthorize' ) );
		}

		// Create a new Customer Payment Profile object.
		$payment_profile = new CustomerPaymentProfileType();
		$payment_profile->setCustomerType( 'individual' );
		$payment_profile->setBillTo( $billing );
		$payment_profile->setPayment( $payment );
		$payment_profile->setDefaultPaymentProfile( $default );

		$request = new CreateCustomerPaymentProfileRequest();
		$request->setCustomerProfileId( $profile_id );
		$request->setPaymentProfile( $payment_profile );
		$request->setValidationMode( 'liveMode' );
		$request->setMerchantAuthentication(
			( new MerchantAuthenticationType() )
				->setName( $this->get_public_key() )
				->setTransactionKey( $this->get_private_key() )
		);

		$controller = $this->get_controller();

		if ( ! $controller instanceof CreateCustomerPaymentProfileController ) {
			$controller = new CreateCustomerPaymentProfileController( $request );
		}

		try {
			$response = $controller->executeWithApiResponse( $this->get_endpoint() );

			if ( ! $response instanceof CreateCustomerPaymentProfileResponse ) {
				return new WP_Error( 'error', __( 'No response received from the API.', 'gatewayauthorize' ) );
			}
		} catch ( \Exception $ex ) {
			return new WP_Error( 'error', $ex->getMessage() );
		}

		if ( 'Ok' !== $response->getMessages()->getResultCode() ) {
			return new WP_Error( 'error', $response->getMessages()->getMessage()[0]->getText() );
		}

		$profile_id         = $response->getCustomerProfileId();
		$payment_profile_id = $response->getCustomerPaymentProfileId();

		if ( empty( $profile_id ) || empty( $payment_profile_id ) ) {
			return new WP_Error( 'error', __( 'Could not create customer payment profile.', 'gatewayauthorize' ) );
		}

		$customer = ( new Customer() )
			->set_profile_id( $profile_id )
			->set_payment_profile_id( $payment_profile_id );

		return $customer;
	}

	/**
	 * Update customer payment profile
	 *
	 * @param string $profile_id         The customer profile ID.
	 * @param string $payment_profile_id The payment profile ID.
	 * @param bool   $default            Mark as default payment profile.
	 *
	 * @return true|WP_Error
	 */
	public function update_payment_profile( string $profile_id, string $payment_profile_id, bool $default = false ) {

		// Check for amount.
		if ( empty( $profile_id ) || empty( $payment_profile_id ) ) {
			return new WP_Error( 'error', __( 'Invalid custoemr details.', 'gatewayauthorize' ) );
		}

		$credit_card = $this->get_card_type();
		if ( empty( $credit_card ) ) {
			return new WP_Error( 'error', __( 'Invalid credit card.', 'gatewayauthorize' ) );
		}

		// Set the payment type.
		$payment = ( new PaymentType() )->setCreditCard( $credit_card );

		$billing = $this->get_address_type();
		if ( empty( $billing ) ) {
			return new WP_Error( 'error', __( 'Invalid billing address.', 'gatewayauthorize' ) );
		}

		$payment_profile = new CustomerPaymentProfileExType();
		$payment_profile->setCustomerPaymentProfileId( $payment_profile_id );
		$payment_profile->setBillTo( $billing );
		$payment_profile->setPayment( $payment );
		$payment_profile->setDefaultPaymentProfile( $default );

		$request = new UpdateCustomerPaymentProfileRequest();
		$request->setCustomerProfileId( $profile_id );
		$request->setPaymentProfile( $payment_profile );
		$request->setValidationMode( 'liveMode' );
		$request->setMerchantAuthentication(
			( new MerchantAuthenticationType() )
				->setName( $this->get_public_key() )
				->setTransactionKey( $this->get_private_key() )
		);

		$controller = $this->get_controller();

		if ( ! $controller instanceof UpdateCustomerPaymentProfileController ) {
			$controller = new UpdateCustomerPaymentProfileController( $request );
		}

		try {
			$response = $controller->executeWithApiResponse( $this->get_endpoint() );

			if ( ! $response instanceof UpdateCustomerPaymentProfileResponse ) {
				return new WP_Error( 'error', __( 'No response received from the API.', 'gatewayauthorize' ) );
			}
		} catch ( \Exception $ex ) {
			return new WP_Error( 'error', $ex->getMessage() );
		}

		if ( 'Ok' !== $response->getMessages()->getResultCode() ) {
			return new WP_Error( 'error', $response->getMessages()->getMessage()[0]->getText() );
		}

		return true;
	}

	/**
	 * Delete customer payment profile
	 *
	 * @param string $profile_id         The customer profile ID.
	 * @param string $payment_profile_id The payment profile ID.
	 *
	 * @return true|WP_Error
	 */
	public function delete_payment_profile( string $profile_id, string $payment_profile_id ) {

		// Check for amount.
		if ( empty( $profile_id ) || empty( $payment_profile_id ) ) {
			return new WP_Error( 'error', __( 'Invalid custoemr details.', 'gatewayauthorize' ) );
		}

		$request = new DeleteCustomerPaymentProfileRequest();
		$request->setCustomerProfileId( $profile_id );
		$request->setCustomerPaymentProfileId( $payment_profile_id );
		$request->setMerchantAuthentication(
			( new MerchantAuthenticationType() )
				->setName( $this->get_public_key() )
				->setTransactionKey( $this->get_private_key() )
		);

		$controller = $this->get_controller();

		if ( ! $controller instanceof DeleteCustomerPaymentProfileController ) {
			$controller = new DeleteCustomerPaymentProfileController( $request );
		}

		try {
			$response = $controller->executeWithApiResponse( $this->get_endpoint() );

			if ( ! $response instanceof DeleteCustomerPaymentProfileResponse ) {
				return new WP_Error( 'error', __( 'No response received from the API.', 'gatewayauthorize' ) );
			}
		} catch ( \Exception $ex ) {
			return new WP_Error( 'error', $ex->getMessage() );
		}

		if ( 'Ok' !== $response->getMessages()->getResultCode() ) {
			return new WP_Error( 'error', $response->getMessages()->getMessage()[0]->getText() );
		}

		return true;
	}
}
