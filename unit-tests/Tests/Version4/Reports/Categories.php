<?php
/**
 * Reports Categories REST API Test
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
 * Class Categories
 */
class Categories extends AbstractReportsTest {

	/**
	 * Endpoints.
	 *
	 * @var string
	 */
	protected $endpoint = '/wc/v4/reports/categories';

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
	 * Test getting reports.
	 *
	 * @since 3.5.0
	 */
	public function test_get_reports() {
		// Populate all of the data.
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( 25 );
		$product->save();

		$order = OrderHelper::create_order( 1, $product );
		$order->set_status( 'completed' );
		$order->set_total( 100 ); // $25 x 4.
		$order->save();

		QueueHelper::run_all_pending();

		$uncategorized_term = get_term_by( 'slug', 'uncategorized', 'product_cat' );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', $this->endpoint ) );
		$reports  = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 1, count( $reports ) );

		$category_report = reset( $reports );

		$this->assertEquals( $uncategorized_term->term_id, $category_report['category_id'] );
		$this->assertEquals( 4, $category_report['items_sold'] );
		$this->assertEquals( 1, $category_report['orders_count'] );
		$this->assertEquals( 1, $category_report['products_count'] );
		$this->assertArrayHasKey( '_links', $category_report );
		$this->assertArrayHasKey( 'category', $category_report['_links'] );
	}

	/**
	 * Test getting reports with the `categories` param.
	 *
	 * @since 3.5.0
	 */
	public function test_get_reports_categories_param() {
		// Populate all of the data.
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( 25 );
		$product->save();

		$order = OrderHelper::create_order( 1, $product );
		$order->set_status( 'completed' );
		$order->set_total( 100 ); // $25 x 4.
		$order->save();

		// Populate all of the data.
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Product 2' );
		$product->set_regular_price( 100 );
		$second_category_id = wp_create_category( 'Second Category' );
		$product->set_category_ids( array( $second_category_id ) );
		$product->save();

		QueueHelper::run_all_pending();

		$uncategorized_term = get_term_by( 'slug', 'uncategorized', 'product_cat' );

		$request = new WP_REST_Request( 'GET', $this->endpoint );
		$request->set_query_params(
			array(
				'categories' => $uncategorized_term->term_id . ',' . $second_category_id,
			)
		);
		$response = $this->server->dispatch( $request );
		$reports  = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 2, count( $reports ) );

		$category_report = reset( $reports );

		$this->assertEquals( $second_category_id, $category_report['category_id'] );
		$this->assertEquals( 0, $category_report['items_sold'] );
		$this->assertEquals( 0, $category_report['orders_count'] );
		$this->assertEquals( 0, $category_report['products_count'] );
		$this->assertArrayHasKey( '_links', $category_report );
		$this->assertArrayHasKey( 'category', $category_report['_links'] );

		$category_report = next( $reports );

		$this->assertEquals( $uncategorized_term->term_id, $category_report['category_id'] );
		$this->assertEquals( 4, $category_report['items_sold'] );
		$this->assertEquals( 1, $category_report['orders_count'] );
		$this->assertEquals( 1, $category_report['products_count'] );
		$this->assertArrayHasKey( '_links', $category_report );
		$this->assertArrayHasKey( 'category', $category_report['_links'] );
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
	 * Test reports schema.
	 *
	 * @since 3.5.0
	 */
	public function test_reports_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 6, count( $properties ) );
		$this->assertArrayHasKey( 'category_id', $properties );
		$this->assertArrayHasKey( 'items_sold', $properties );
		$this->assertArrayHasKey( 'net_revenue', $properties );
		$this->assertArrayHasKey( 'orders_count', $properties );
		$this->assertArrayHasKey( 'products_count', $properties );
		$this->assertArrayHasKey( 'extended_info', $properties );
	}
}
