<?php
/**
 * The AuthorizeGateway class file.
 *
 * @package    Mazepress\Gateway
 * @subpackage Authorize
 */

declare(strict_types=1);

namespace Mazepress\Gateway\Authorize;

use Mazepress\Gateway\Payment;
use Mazepress\Gateway\Transaction;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\CreditCardType;
use net\authorize\api\contract\v1\CustomerAddressType;
use net\authorize\api\contract\v1\CustomerDataType;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\contract\v1\OrderType;
use net\authorize\api\contract\v1\PaymentType;
use net\authorize\api\contract\v1\SettingType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\controller\CreateTransactionController;
use net\authorize\api\contract\v1\CreateTransactionResponse;
use net\authorize\api\controller\base\IApiOperation;
use WP_Error;

/**
 * The AuthorizeGateway class.
 */
class AuthorizeGateway extends Payment {

	/**
	 * The public_key.
	 *
	 * @var string
	 */
	private $public_key;

	/**
	 * The private_key.
	 *
	 * @var string
	 */
	private $private_key;

	/**
	 * The live mode flag.
	 *
	 * @var bool
	 */
	private $is_live = false;

	/**
	 * The capture mode flag.
	 *
	 * @var bool
	 */
	private $capture = true;

	/**
	 * The transaction ID.
	 *
	 * @var string
	 */
	private $transaction_id;

	/**
	 * The reference ID.
	 *
	 * @var string
	 */
	private $reference_id;

	/**
	 * The controller object.
	 *
	 * @var IApiOperation
	 */
	private $controller;

	/**
	 * Initiate class.
	 *
	 * @param string $public_key  The public key.
	 * @param string $private_key The private key.
	 * @param bool   $live        Live mode.
	 */
	public function __construct( string $public_key, string $private_key, bool $live = false ) {
		$this->set_public_key( $public_key );
		$this->set_private_key( $private_key );
		$this->set_is_live( $live );
	}

	/**
	 * Process the payment. If the payment fails,
	 * it should return a WP_Error object.
	 *
	 * @return Transaction|WP_Error
	 */
	public function process() {

		// Validate credentials.
		$validate = $this->validate_credentials();
		if ( is_wp_error( $validate ) ) {
			return $validate;
		}

		// Check for amount.
		if ( $this->get_amount() <= 0 ) {
			return new WP_Error( 'error', __( 'Invalid amount.', 'gatewayauthorize' ) );
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

		$transaction_request = ( new TransactionRequestType() )
			->setCurrencyCode( $this->get_currency_code() )
			->setAmount( $this->get_amount() )
			->setPayment( $payment )
			->setBillTo( $billing );

		$trans_type = $this->get_capture() ? 'authCaptureTransaction' : 'authOnlyTransaction';
		$transaction_request->setTransactionType( $trans_type );

		// Set the customer.
		$transaction_request->setCustomer( ( new CustomerDataType() )->setEmail( $billing->getEmail() ) );

		if ( ! empty( $this->get_invoice_id() ) ) {
			$transaction_request->setOrder(
				( new OrderType() )
					->setInvoiceNumber( $this->get_invoice_id() )
					->setDescription( (string) $this->get_description() )
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
	 * Capture the previously holding payment.
	 *
	 * @return Transaction|WP_Error
	 */
	public function capture() {

		// Check the transaction ID.
		if ( empty( $this->get_transaction_id() ) ) {
			return new WP_Error( 'error', __( 'Invalid transaction ID.', 'gatewayauthorize' ) );
		}

		$transaction_request = ( new TransactionRequestType() )
			->setTransactionType( 'priorAuthCaptureTransaction' )
			->setRefTransId( $this->get_transaction_id() );

		$transaction = $this->process_transaction( $transaction_request );

		if ( ! is_wp_error( $transaction ) ) {
			$transaction->set_status( 'Paid' );
		}

		return $transaction;
	}

	/**
	 * Process the transaction request.
	 *
	 * @param TransactionRequestType $transaction_request The transaction request.
	 *
	 * @return Transaction|WP_Error
	 */
	public function process_transaction( TransactionRequestType $transaction_request ) {

		$transaction_request->addToTransactionSettings(
			( new SettingType() )
				->setSettingName( 'duplicateWindow' )
				->setSettingValue( '60' )
		);

		$request = new CreateTransactionRequest();
		$request->setTransactionRequest( $transaction_request );
		$request->setMerchantAuthentication(
			( new MerchantAuthenticationType() )
				->setName( $this->get_public_key() )
				->setTransactionKey( $this->get_private_key() )
		);

		if ( ! empty( $this->get_reference_id() ) ) {
			$request->setRefId( $this->get_reference_id() );
		}

		$controller = $this->get_controller();

		if ( empty( $controller ) || ! $controller instanceof CreateTransactionController ) {
			$controller = new CreateTransactionController( $request );
		}

		try {
			$response = $controller->executeWithApiResponse( $this->get_endpoint() );

			if ( ! $response instanceof CreateTransactionResponse ) {
				return new WP_Error( 'error', __( 'No response received from the API.', 'gatewayauthorize' ) );
			}

			$tresponse = $response->getTransactionResponse();

		} catch ( \Exception $ex ) {
			return new WP_Error( 'error', $ex->getMessage() );
		}

		if ( 'Ok' !== $response->getMessages()->getResultCode() ) {

			$message = ! empty( $tresponse->getErrors() )
				? $tresponse->getErrors()[0]->getErrorText()
				: $response->getMessages()->getMessage()[0]->getText();

			return new WP_Error( 'error', $message );
		}

		if ( 1 !== (int) $tresponse->getResponseCode() ) {
			return new WP_Error( 'error', __( 'Failed processing the payment!', 'gatewayauthorize' ) );
		}

		$transaction = ( new Transaction() )
			->set_transaction_id( $tresponse->getTransId() )
			->set_code( $tresponse->getResponseCode() )
			->set_message( $tresponse->getMessages()[0]->getDescription() );

		return $transaction;
	}

	/**
	 * Valdiate the minimum requirements.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_credentials() {

		// Check the public key.
		if ( empty( $this->get_public_key() ) ) {
			return new WP_Error( 'error', __( 'Invalid public key.', 'gatewayauthorize' ) );
		}

		// Check the private key.
		if ( empty( $this->get_private_key() ) ) {
			return new WP_Error( 'error', __( 'Invalid private key.', 'gatewayauthorize' ) );
		}

		return true;
	}

	/**
	 * Get the card type object.
	 *
	 * @return CreditCardType|null
	 */
	public function get_card_type(): ?CreditCardType {

		if (
			empty( $this->get_card() ) ||
			empty( $this->get_card()->get_number() ) ||
			empty( $this->get_card()->get_expiry_month() ) ||
			empty( $this->get_card()->get_expiry_year() )
		) {
			return null;
		}

		$card = $this->get_card();

		$card_type = new CreditCardType();
		$card_type->setCardNumber( $card->get_number() );
		$card_type->setCardCode( $card->get_cvv() );
		$card_type->setExpirationDate( $card->get_expiry_year() . '-' . $card->get_expiry_month() );

		return $card_type;
	}

	/**
	 * Get the address type object.
	 *
	 * @return CustomerAddressType|null
	 */
	public function get_address_type(): ?CustomerAddressType {

		// Check for address.
		if (
			empty( $this->get_address() ) ||
			empty( $this->get_address()->get_first_name() ) ||
			empty( $this->get_address()->get_last_name() ) ||
			empty( $this->get_address()->get_email() )
		) {
			return null;
		}

		$address      = $this->get_address();
		$full_address = (string) $address->get_address1();

		if ( ! empty( $address->get_address2() ) ) {
			$full_address .= ! empty( $full_address ) ? ', ' . $address->get_address2() : $address->get_address2();
		}

		$address_type = new CustomerAddressType();
		$address_type->setFirstName( $address->get_first_name() );
		$address_type->setLastName( $address->get_last_name() );
		$address_type->setEmail( $address->get_email() );
		$address_type->setPhoneNumber( (string) $address->get_phone() );
		$address_type->setAddress( $full_address );
		$address_type->setState( (string) $address->get_state() );
		$address_type->setZip( (string) $address->get_zip() );
		$address_type->setCountry( (string) $address->get_country_code() );

		return $address_type;
	}

	/**
	 * Get the public key.
	 *
	 * @return string|null
	 */
	public function get_public_key(): ?string {
		return $this->public_key;
	}

	/**
	 * Set the public key.
	 *
	 * @param string $public_key The public key.
	 *
	 * @return self
	 */
	public function set_public_key( string $public_key ): self {
		$this->public_key = $public_key;
		return $this;
	}

	/**
	 * Get the private key.
	 *
	 * @return string|null
	 */
	public function get_private_key(): ?string {
		return $this->private_key;
	}

	/**
	 * Set the private key.
	 *
	 * @param string $private_key The private key.
	 *
	 * @return self
	 */
	public function set_private_key( string $private_key ): self {
		$this->private_key = $private_key;
		return $this;
	}

	/**
	 * Get the live mode.
	 *
	 * @return bool
	 */
	public function get_is_live(): bool {
		return $this->is_live;
	}

	/**
	 * Set the live mode.
	 *
	 * @param bool $live The live mode.
	 *
	 * @return self
	 */
	public function set_is_live( bool $live ): self {
		$this->is_live = $live;
		return $this;
	}

	/**
	 * Get the capture mode.
	 *
	 * @return bool
	 */
	public function get_capture(): bool {
		return $this->capture;
	}

	/**
	 * Set the capture mode.
	 *
	 * @param bool $capture The capture mode.
	 *
	 * @return self
	 */
	public function set_capture( bool $capture ): self {
		$this->capture = $capture;
		return $this;
	}

	/**
	 * Get the transaction ID.
	 *
	 * @return string|null
	 */
	public function get_transaction_id(): ?string {
		return $this->transaction_id;
	}

	/**
	 * Set the transaction ID.
	 *
	 * @param string $transaction_id The transaction ID.
	 *
	 * @return self
	 */
	public function set_transaction_id( string $transaction_id ): self {
		$this->transaction_id = $transaction_id;
		return $this;
	}

	/**
	 * Get the reference ID.
	 *
	 * @return string|null
	 */
	public function get_reference_id(): ?string {
		return $this->reference_id;
	}

	/**
	 * Set the reference ID.
	 *
	 * @param string $reference_id The reference ID.
	 *
	 * @return self
	 */
	public function set_reference_id( string $reference_id ): self {
		$this->reference_id = $reference_id;
		return $this;
	}

	/**
	 * Get the endpoint.
	 *
	 * @return string
	 */
	public function get_endpoint(): string {
		return $this->is_live ? ANetEnvironment::PRODUCTION : ANetEnvironment::SANDBOX;
	}

	/**
	 * Get the controller.
	 *
	 * @return IApiOperation|null
	 */
	public function get_controller(): ?IApiOperation {
		return $this->controller;
	}

	/**
	 * Set the controller.
	 *
	 * @param IApiOperation $controller The controller.
	 *
	 * @return self
	 */
	public function set_controller( IApiOperation $controller ): self {
		$this->controller = $controller;
		return $this;
	}
}
