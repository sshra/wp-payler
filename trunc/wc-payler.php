<?php
/*
Plugin Name: Integrate Payler with Woocommerce
Plugin URI:
Description: Allows you to use Payler (payler.com) payment gateway with the WooCommerce plugin.
Version: 1.3
Requires at least: 3.1.0
Tags: payler, payler integration, woocommerce payments, woocommerce gateway
Author: Korol Yuriy aka Shra <to@shra.ru>
Author URI: https://shra.ru
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Add roubles in currencies
 */
function payler_rub_currency_symbol($currency_symbol, $currency)
{
	if ($currency == "RUB") {
		$currency_symbol = 'р.';
	}
	return $currency_symbol;
}

function payler_rub_currency($currencies)
{
	$currencies['RUB'] = __('Russian Roubles');
	return $currencies;
}

add_filter('woocommerce_currency_symbol', 'payler_rub_currency_symbol', 10, 2);
add_filter('woocommerce_currencies', 'payler_rub_currency', 10, 1);

/* Add a custom payment class to WC */

add_action('plugins_loaded', 'woocommerce_payler', 0);

function woocommerce_payler()
{

	if (!class_exists('WC_Payment_Gateway')) {
		return; // if the WC payment gateway class is not available, do nothing
	}

	require_once __DIR__ . '/classes/payler-gateway.class.php';

	/**
	 * Add the gateway to WooCommerce
	 **/
	function add_payler_gateway($methods)
	{
		$methods[] = 'WC_PAYLER';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_payler_gateway');
}
