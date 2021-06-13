<?php
/**
 * Plugin Name: Nano Payment Gateway for WooCommerce
 * Plugin URI: https://github.com/SparK-Cruz/nanosales-woocommerce-gateway
 * Description: Accept the Nano cryptocurrency in your checkout page and join the digital revolution.
 * Author: Spark
 * Author URI: https://github.com/SparK-Cruz
 * Version: 1.0.0
 * License: CC0-1.0
 * Text Domain: nanosales-woocommerce-gateway
 * Domain Path: /languages
 *
 * @package WC_NANOSALES
 */
namespace NanoSales\WooCommerce;

if ( ! defined('ABSPATH') ) {
	exit;
}
if ( class_exists('WC_NANOSALES') ) {
	return;
}

require __DIR__ . '/vendor/autoload.php';

class WC_NANOSALES {
	const DIR = __DIR__;

	/**
	 * Singleton holder.
	 *
	 * @var WC_NANOSALES
	 */
	protected static $instance;

	/**
	 * The singleton acessor.
	 *
	 * @return WC_NANOSALES
	 */
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		\add_filter( 'woocommerce_payment_gateways', [$this, 'addGateways'] );
	}

	/**
	 * Add the gateways to WooCommerce.
	 *
	 * @param  array $methods WooCommerce payment methods.
	 * @return array
	 */
	public function addGateways($methods) {
		$methods[] = 'NanoSales\WooCommerce\Gateways\Nano';
		return $methods;
	}
}

\add_action('plugins_loaded', ['NanoSales\WooCommerce\WC_NANOSALES', 'getInstance']);
