<?php
/**
 * The AuthorizeGatewayTest class file.
 *
 * @package    Mazepress\Gateway\Authorize
 * @subpackage Tests
 */

declare(strict_types=1);

namespace Mazepress\Gateway\Authorize\Tests;

use Mockery;
use WP_Mock;
use WP_Error;
use Mazepress\Gateway\Address;
use Mazepress\Gateway\CreditCard;
use Mazepress\Gateway\Transaction;
use Mazepress\Gateway\Authorize\AuthorizeGateway;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\controller\CreateTransactionController;
use net\authorize\api\contract\v1\CreateTransactionResponse;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\contract\v1\TransactionResponseType;
use net\authorize\api\contract\v1\MessagesType;
use net\authorize\api\contract\v1\MessagesType\MessageAType;

/**
 * The AuthorizeGatewayTest class.
 */
class AuthorizeGatewayTest extends WP_Mock\Tools\TestCase {

	/**
	 * Test class properites.
	 *
	 * @return void
	 */
	public function test_properties(): void {

		$object = new AuthorizeGateway( 'public1', 'private1' );

		$this->assertEquals( 'public1', $object->get_public_key() );
		$this->assertEquals( 'private1', $object->get_private_key() );
		$this->assertFalse( $object->get_is_live() );
		$this->assertTrue( $object->get_capture() );

		$this->assertInstanceOf( AuthorizeGateway::class, $object->set_public_key( 'public2' ) );
		$this->assertEquals( 'public2', $object->get_public_key() );

		$this->assertInstanceOf( AuthorizeGateway::class, $object->set_private_key( 'private2' ) );
		$this->assertEquals( 'private2', $object->get_private_key() );

		$this->assertInstanceOf( AuthorizeGateway::class, $object->set_is_live( true ) );
		$this->assertTrue( $object->get_is_live() );

		$this->assertInstanceOf( AuthorizeGateway::class, $object->set_capture( false ) );
		$this->assertFalse( $object->get_capture() );

		$transaction_id = uniqid();
		$this->assertInstanceOf( AuthorizeGateway::class, $object->set_transaction_id( $transaction_id ) );
		$this->assertEquals( $transaction_id, $object->get_transaction_id() );

		$reference_id = uniqid();
		$this->assertInstanceOf( AuthorizeGateway::class, $object->set_reference_id( $reference_id ) );
		$this->assertEquals( $reference_id, $object->get_reference_id() );

		$object->set_is_live( false );
		$this->assertEquals( ANetEnvironment::SANDBOX, $object->get_endpoint() );
		$object->set_is_live( true );
		$this->assertEquals( ANetEnvironment::PRODUCTION, $object->get_endpoint() );
	}

	/**
	 * Test validate credentials.
	 *
	 * @return void
	 */
	public function test_validate_credentials(): void {

		$object = new AuthorizeGateway( '', '' );
		$method = $this->getInaccessibleMethod( $object, 'validate_credentials' );

		// Check for valid public_key.
		$output = $method->invoke( $object );
		$this->assertInstanceOf( \WP_Error::class, $output );
		$this->assertEquals( 'invalid_public_key', $output->get_error_code() );
		$object->set_public_key( 'public1' );

		// Check for valid private_key.
		$output = $method->invoke( $object );
		$this->assertInstanceOf( \WP_Error::class, $output );
		$this->assertEquals( 'invalid_private_key', $output->get_error_code() );
		$object->set_private_key( 'private1' );

		// Check for valid address.
		$output = $method->invoke( $object );
		$this->assertInstanceOf( \WP_Error::class, $output );
		$this->assertEquals( 'invalid_amount', $output->get_error_code() );
		$object->set_amount( 100 );

		// Check for valid card.
		$output = $method->invoke( $object );
		$this->assertInstanceOf( \WP_Error::class, $output );
		$this->assertEquals( 'invalid_card', $output->get_error_code() );
		$object->set_card( new CreditCard() );

		// Check for valid address.
		$output = $method->invoke( $object );
		$this->assertInstanceOf( \WP_Error::class, $output );
		$this->assertEquals( 'invalid_address', $output->get_error_code() );
		$object->set_address( $this->get_address() );

		// Check for valid return.
		$output = $method->invoke( $object );
		$this->assertTrue( $output );
	}

	/**
	 * Test process payment.
	 *
	 * @return void
	 */
	public function test_process_failure(): void {

		$object = new AuthorizeGateway( '', '', false );
		$output = $object->process();
		$this->assertInstanceOf( \WP_Error::class, $output );
	}

	/**
	 * Test process payment.
	 *
	 * @return void
	 */
	public function test_process_exception(): void {

		$object = new AuthorizeGateway( 'public1', 'private1', false );
		$object->set_amount( 100 );
		$object->set_reference_id( uniqid() );
		$object->set_card( new CreditCard() );
		$object->set_address( $this->get_address() );

		$client  = Mockery::mock( CreateTransactionController::class );
		$message = 'An error occurred!';

		// @phpstan-ignore-next-line
		$client->shouldReceive( 'executeWithApiResponse' )
			->once()
			->andThrow( new \Exception( $message ) );

		// @phpstan-ignore-next-line
		$object->set_client( $client );

		$output = $object->process();
		$this->assertInstanceOf( \WP_Error::class, $output );
		$this->assertEquals( 'error', $output->get_error_code() );
		$this->assertEquals( $message, $output->get_error_message() );
	}

	/**
	 * Test process payment.
	 *
	 * @return void
	 */
	public function test_process_error1(): void {

		$object = new AuthorizeGateway( 'public1', 'private1', false );
		$object->set_amount( 100 );
		$object->set_card( new CreditCard() );
		$object->set_address( $this->get_address() );

		$client = Mockery::mock( CreateTransactionController::class );

		// @phpstan-ignore-next-line
		$client->shouldReceive( 'executeWithApiResponse' )
			->once()
			->andReturn( null );

		// @phpstan-ignore-next-line
		$object->set_client( $client );

		$output = $object->process();
		$this->assertInstanceOf( \WP_Error::class, $output );
		$this->assertEquals( 'error', $output->get_error_code() );
		$this->assertEquals( 'No response received from the API.', $output->get_error_message() );
	}

	/**
	 * Test process payment.
	 *
	 * @return void
	 */
	public function test_process_error2(): void {

		$object = new AuthorizeGateway( 'public1', 'private1', false );
		$object->set_amount( 100 );
		$object->set_card( new CreditCard() );
		$object->set_address( $this->get_address() );

		$client   = Mockery::mock( CreateTransactionController::class );
		$response = new CreateTransactionResponse();

		$tresponse = new TransactionResponseType();
		$response->setTransactionResponse( $tresponse );

		$message  = ( new MessageAType() )
			->setText( 'Invalid TransactionResponse!' );
		$messages = ( new MessagesType() )
			->setResultCode( 'Error' )
			->setMessage( array( $message ) );
		$response->setMessages( $messages );

		// @phpstan-ignore-next-line
		$client->shouldReceive( 'executeWithApiResponse' )
			->once()
			->andReturn( $response );

		// @phpstan-ignore-next-line
		$object->set_client( $client );

		$output = $object->process();
		$this->assertInstanceOf( \WP_Error::class, $output );
		$this->assertEquals( 'error', $output->get_error_code() );
		$this->assertEquals( $message->getText(), $output->get_error_message() );
	}

	/**
	 * Test process payment.
	 *
	 * @return void
	 */
	public function test_process_error3(): void {

		$object = new AuthorizeGateway( 'public1', 'private1', false );
		$object->set_amount( 100 );
		$object->set_card( new CreditCard() );
		$object->set_address( $this->get_address() );

		$client   = Mockery::mock( CreateTransactionController::class );
		$response = new CreateTransactionResponse();

		$tresponse = new TransactionResponseType();
		$response->setTransactionResponse( $tresponse );

		$message  = ( new MessageAType() )
			->setText( 'Failed processing the payment!' );
		$messages = ( new MessagesType() )
			->setResultCode( 'Ok' )
			->setMessage( array( $message ) );
		$response->setMessages( $messages );

		// @phpstan-ignore-next-line
		$client->shouldReceive( 'executeWithApiResponse' )
			->once()
			->andReturn( $response );

		// @phpstan-ignore-next-line
		$object->set_client( $client );

		$output = $object->process();
		$this->assertInstanceOf( \WP_Error::class, $output );
		$this->assertEquals( 'error', $output->get_error_code() );
		$this->assertEquals( $message->getText(), $output->get_error_message() );
	}

	/**
	 * Test process payment.
	 *
	 * @return void
	 */
	public function test_process_success(): void {

		$object = new AuthorizeGateway( 'public1', 'private1', false );
		$object->set_amount( 100 );
		$object->set_card( new CreditCard() );
		$object->set_address( $this->get_address() );
		$object->set_invoice_id( uniqid() );

		$transid  = uniqid();
		$client   = Mockery::mock( CreateTransactionController::class );
		$response = new CreateTransactionResponse();

		$tmessage  = ( new \net\authorize\api\contract\v1\TransactionResponseType\MessagesAType\MessageAType() )
			->setDescription( 'Approved payment!' );
		$tresponse = ( new TransactionResponseType() )
			->setResponseCode( '1' )
			->setTransId( $transid )
			->setMessages( array( $tmessage ) );
		$response->setTransactionResponse( $tresponse );

		$messages = ( new MessagesType() )
			->setResultCode( 'Ok' );
		$response->setMessages( $messages );

		// @phpstan-ignore-next-line
		$client->shouldReceive( 'executeWithApiResponse' )
			->once()
			->andReturn( $response );

		// @phpstan-ignore-next-line
		$object->set_client( $client );

		$output = $object->process();
		$this->assertInstanceOf( Transaction::class, $output );
		$this->assertEquals( $transid, $output->get_transaction_id() );
		$this->assertEquals( 'Paid', $output->get_status() );
	}

	/**
	 * Test process payment.
	 *
	 * @return void
	 */
	public function test_capture_error(): void {

		$object = new AuthorizeGateway( 'public1', 'private1', false );

		$output = $object->capture();
		$this->assertInstanceOf( \WP_Error::class, $output );
		$this->assertEquals( 'error', $output->get_error_code() );
		$this->assertEquals( 'Invalid transaction ID.', $output->get_error_message() );
	}

	/**
	 * Test process payment.
	 *
	 * @return void
	 */
	public function test_capture_success(): void {

		$object = new AuthorizeGateway( 'public1', 'private1', false );
		$object->set_transaction_id( uniqid() );

		$transid  = uniqid();
		$client   = Mockery::mock( CreateTransactionController::class );
		$response = new CreateTransactionResponse();

		$tmessage  = ( new \net\authorize\api\contract\v1\TransactionResponseType\MessagesAType\MessageAType() )
			->setDescription( 'Approved payment!' );
		$tresponse = ( new TransactionResponseType() )
			->setResponseCode( '1' )
			->setTransId( $transid )
			->setMessages( array( $tmessage ) );
		$response->setTransactionResponse( $tresponse );

		$messages = ( new MessagesType() )
			->setResultCode( 'Ok' );
		$response->setMessages( $messages );

		// @phpstan-ignore-next-line
		$client->shouldReceive( 'executeWithApiResponse' )
			->once()
			->andReturn( $response );

		// @phpstan-ignore-next-line
		$object->set_client( $client );

		$output = $object->capture();
		$this->assertInstanceOf( Transaction::class, $output );
		$this->assertEquals( $transid, $output->get_transaction_id() );
		$this->assertEquals( 'Paid', $output->get_status() );
	}

	/**
	 * Get the dummy address
	 *
	 * @return Address
	 */
	private function get_address(): Address {

		$address = ( new Address() )
			->set_first_name( 'First' )
			->set_last_name( 'Last' )
			->set_email( 'firstlast@example.com' )
			->set_address1( 'Second street' )
			->set_address2( 'Down town' );

		return $address;
	}
}
