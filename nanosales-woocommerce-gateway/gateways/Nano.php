<?php
namespace NanoSales\WooCommerce\Gateways;

use NanoSales\WooCommerce\WC_NANOSALES;

if (!defined('ABSPATH')) {
	exit;
}

final class Nano extends \WC_Payment_Gateway {
	/**
	 * Default values
	 *
	 * @var array
	 */
	public static $defaults = array(
		'server_url' => 'http://localhost:13380',
		'auto_settlement' => 'yes',
		'nano_price' => 7,
	);

	public function __construct() {
		$this->id                 = 'nanosales';
		$this->title              = \__('Pay with Nano', 'nanosales-woocommerce-gateway');
		$this->method_title       = $this->title;
		$this->method_description = \__('Pay using your Nano cryptocurrency wallet.', 'nanosales-woocommerce-gateway');

		$this->initFormFields();

		// wordpress call
		$this->init_settings();

		$this->enabled = true;

		\add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'processAdminOptions']);
		\add_action('woocommerce_thankyou_' . $this->id, [$this, 'showQrCode']);
		\add_action('woocommerce_api_' . $this->id, [$this, 'webhook']);
		\add_action('admin_head', [$this, 'getOrderSettlementButtonCss']);
		\add_filter('woocommerce_admin_order_actions', [$this, 'addOrderSettlementButton']);
	}

	/**
	 * Define the fields on gateway settings page and set the defaults when the plugin is installed
	 *
	 * @return void
	 */
	public function initFormFields() {
		$currency_code = strtoupper(\get_woocommerce_currency());

		$fields = [
			'server_url' => [
				'title' => \__('Server URL', 'nanosales-woocommerce-gateway'),
				'type' => 'title',
				'description' => 'Location of your nanosales server',
			],
			'auto_settlement' => [
				'type' => 'checkbox',
				'title' => \__('Enable Auto-Settlement', 'nanosales-woocommerce-gateway'),
				'label' => \__('Settle the payment immediately', 'nanosales-woocommerce-gateway'),
				'description' => \__('. Otherwise you will need to capture the payment going to: WooCommerce -> Orders.', 'nanosales-woocommerce-gateway'),
				'desc_tip' => true,
			],
			'nano_price' => [
				'title' => sprintf(\__('Nano price in %s'), $currency_code),
				'type' => 'number',
				'placeholder' => \__('In 2021 the price was 7 USD', 'nanosales-woocommerce-gateway'),
				'description' => sprintf(\__('How many %s for a Nano you want to charge?', 'nanosales-woocommerce-gateway'), $currency_code),
				'desc_tip' => true,
			]
		];

		$this->form_fields = \apply_filters('nanosales_settings_form_fields', $fields);

		$this->inject_defaults();
	}

	/**
	 * Inject the default data
	 *
	 * @return void
	 */
	private function inject_defaults(){
		foreach($this->form_fields as $field => &$properties){
			if (!isset(self::$defaults[$field])) {
				continue;
			}

			$properties['default'] = self::$defaults[$field];
		}
	}

	/**
	 * Fetches a single setting from the gateway settings if found, otherwise it returns an optional default value
	 *
	 * @param  string $name    The setting name to fetch
	 * @param  mixed  $default The default value in case setting is not present
	 * @return mixed
	 */
	public function getConfigOrDefault($name, $default=null) {
		if ( ! isset($this->settings[$name]) || empty($this->settings[$name])) {
			return $default;
		}

		return $this->settings[$name];
	}

	public function payment_fields() {
		echo \wp_kses_post(\wpautop(\wptexturize($this->method_description)));
	}

	/**
	 * Process Payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($orderId) {
		$order = \wc_get_order($orderId);

		$success = [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
		];

		if ($order->get_total() == 0) {
			$order->payment_complete('{}');
			\WC()->cart->empty_cart();
			return $success;
		}

		$callback = \site_url('wc-api/' . $this->id . '?order_id=' . $orderId);
		$amount = ($order->get_total() * $this->getConfigOrDefault('nano_price', 7)) . 'e+30';

		$backend = $this->getBackendUrl();
		$payload = '{"callback":"'.$callback.'","amount":'.$amount.'}';

		$response = json_decode(\wp_remote_post($backend, [
			'body' => $payload
		])['body']);

		if ($response->paid) {
			$order->update_status('on-hold');
			if (!$this->getConfigOrDefault('auto_settlement')) {
				return [];
			}

			$this->settle($orderId);
			return [];
		}

		$order->set_transaction_id(json_encode($response));
		\WC()->cart->empty_cart();

        return $success;
	}

	public function webhook() {
		$order = \wc_get_order($_GET['order_id']);
		$info = json_decode($order->get_transaction_id());
		if (!isset($info->address))
			return;

		$backend = $this->getBackendUrl().'/'.$info->address;

		$response = json_decode(\wp_remote_get($backend, [
			'timeout' => 300
		])['body']);

		if (!$response->paid)
			return;

		$order->update_status('on-hold');

		if (!$this->getConfigOrDefault('auto_settlement')) {
			return;
		}

		$this->settle($_GET['order_id']);
	}

	public function settle($orderId) {
		$order = \wc_get_order($orderId);
		$info = json_decode($order->get_transaction_id());
		$backend = $this->getBackendUrl().'/'.$info->address;

		$http = \_wp_http_get_object();
		$http->request($backend, [
			'method' => 'DELETE',
			'timeout' => 300
		]);

		$order->payment_complete('{}');
	}

	/**
	 *
	 * @param array    $actions
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function addOrderSettlementButton($actions, $order) {
		if ($order->get_status() !== 'on-hold'
			|| $order->get_payment_method() !== $this->id
			|| !\current_user_can( 'administrator' ) ) {
			return $actions;
		}

		$callback = \site_url('wc-api/' . $this->id . '?order_id=' . $order->get_id());

		$actions['nanosales_settle'] = array(
			'url'    => $callback,
			'name'   => \__( 'Settle payment to main wallet', 'nanosales-woocommerce-gateway' ),
			'action' => 'view settle',
		);

		return $actions;
	}

	public function getOrderSettlementButtonCss() {
		echo '<style>.view.settle::after { font-family: Dashicons; content: "\f18e" !important; }</style>';
	}

	/**
	 * The order received page
	 *
	 * @param  \WC_Order $order
	 * @return void
	 */
	public function showQrCode($order) {
		$info = json_decode($order->get_transaction_id());

		$data = [
			'address' => $info->address,
			'url' => $info->url,
			'amount' => ($order->get_total() * $this->getConfigOrDefault('nano_price', 7)),
		];

		\wc_get_template(
			'payment.php',
			$data,
			'woocommerce/nanosales',
			WC_NANOSALES::DIR.'/templates'
		);

		\wp_enqueue_script('nanosales_woocommerce_clipboard', \plugins_url( 'assets/js/clipboard.min.js', WC_NANOSALES::DIR, false, true ) );
		\wp_enqueue_script('nanosales_woocommerce_qrcode', \plugins_url( 'assets/js/qrcode.min.js', WC_NANOSALES::DIR, false, true ) );
		\wp_enqueue_script('nanosales_woocommerce_order_received', \plugins_url( 'assets/js/order-received.js', WC_NANOSALES::DIR, false, true ) );
	}

	private function getBackendUrl() {
		return rtrim($this->getConfigOrDefault('server_url'), '/').'/sales';
	}
}
