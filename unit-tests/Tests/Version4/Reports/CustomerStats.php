<?php
/**
 * Reports Customers Stats REST API Test
 *
 * @package WooCommerce\Tests\API
 * @since 3.5.0
 */

namespace WooCommerce\RestApi\UnitTests\Tests\Version4\Reports;

defined( 'ABSPATH' ) || exit;

use \WooCommerce\RestApi\UnitTests\AbstractReportsTest;
use \WP_REST_Request;
use \WooCommerce\RestApi\UnitTests\Helpers\OrderHelper;
use \WooCommerce\RestApi\UnitTests\Helpers\QueueHelper;
use \WooCommerce\RestApi\UnitTests\Helpers\CustomerHelper;

/**
 * Reports Customers Stats REST API Test Class
 *
 * @package WooCommerce\Tests\API
 * @since 3.5.0
 */
class CustomerStats extends AbstractReportsTest {
	/**
	 * Endpoint.
	 *
	 * @var string
	 */
	protected $endpoint = '/wc/v4/reports/customers/stats';

	/**
	 * Test route registration.
	 *
	 * @since 3.5.0
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( $this->endpoint, $routes );
	}

	/**
	 * Test reports schema.
	 *
	 * @since 3.5.0
	 */
	public function test_reports_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertCount( 1, $properties );
		$this->assertArrayHasKey( 'totals', $properties );
		$this->assertCount( 4, $properties['totals']['properties'] );
		$this->assertArrayHasKey( 'customers_count', $properties['totals']['properties'] );
		$this->assertArrayHasKey( 'avg_orders_count', $properties['totals']['properties'] );
		$this->assertArrayHasKey( 'avg_total_spend', $properties['totals']['properties'] );
		$this->assertArrayHasKey( 'avg_avg_order_value', $properties['totals']['properties'] );
	}

	/**
	 * Test getting reports without valid permissions.
	 *
	 * @since 3.5.0
	 */
	public function test_get_reports_without_permission() {
		wp_set_current_user( 0 );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', $this->endpoint ) );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test getting reports.
	 *
	 * @since 3.5.0
	 */
	public function test_get_reports() {
		$test_customers = array();

		// Create 10 test customers.
		for ( $i = 1; $i <= 10; $i++ ) {
			$test_customers[] = CustomerHelper::create_customer( "customer{$i}", 'password', "customer{$i}@example.com" );
		}
		// One differing name.
		$test_customers[2]->set_first_name( 'Jeff' );
		$test_customers[2]->save();

		// Create a test product for use in an order.
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( 25 );
		$product->save();

		// Place some test orders.
		$order = OrderHelper::create_order( $test_customers[0]->get_id(), $product );
		$order->set_status( 'completed' );
		$order->set_total( 100 );
		$order->save();

		$order = OrderHelper::create_order( $test_customers[0]->get_id(), $product );
		$order->set_status( 'completed' );
		$order->set_total( 234 );
		$order->save();

		$order = OrderHelper::create_order( $test_customers[1]->get_id(), $product );
		$order->set_status( 'completed' );
		$order->set_total( 55 );
		$order->save();

		$order = OrderHelper::create_order( $test_customers[2]->get_id(), $product );
		$order->set_status( 'completed' );
		$order->set_total( 9.12 );
		$order->save();

		QueueHelper::run_all_pending();

		$request  = new WP_REST_Request( 'GET', $this->endpoint );
		$response = $this->server->dispatch( $request );
		$reports  = $response->get_data();
		$headers  = $response->get_headers();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 10, $reports['totals']->customers_count );
		$this->assertEquals( 1.333, round( $reports['totals']->avg_orders_count, 3 ) );
		$this->assertEquals( 132.707, round( $reports['totals']->avg_total_spend, 3 ) );
		$this->assertEquals( 77.04, $reports['totals']->avg_avg_order_value );

		// Test name parameter (case with no matches).
		$request->set_query_params(
			array(
				'search' => 'Nota Customername',
			)
		);
		$response = $this->server->dispatch( $request );
		$reports  = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 0, $reports['totals']->customers_count );
		$this->assertEquals( 0, $reports['totals']->avg_orders_count );
		$this->assertEquals( 0, $reports['totals']->avg_total_spend );
		$this->assertEquals( 0, $reports['totals']->avg_avg_order_value );

		// Test name and last_order parameters.
		$request->set_query_params(
			array(
				'search'           => 'Jeff',
				'last_order_after' => date( 'Y-m-d' ) . 'T00:00:00Z',
			)
		);
		$response = $this->server->dispatch( $request );
		$reports  = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 1, $reports['totals']->customers_count );
		$this->assertEquals( 1, $reports['totals']->avg_orders_count );
		$this->assertEquals( 9.12, $reports['totals']->avg_total_spend );
		$this->assertEquals( 9.12, $reports['totals']->avg_avg_order_value );
	}
}
