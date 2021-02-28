<?php
/*
 * Plugin Name: Sparco Payment Gateway for WooCommerce 
 * Plugin URI: https://mundia.me/woocommerce/plugins/sparco-payment-gateway-plugin.html
 * Description: Accept Mobile Money and Credit Card payments.
 * Version: 1.0.0
 * Author: Mundia Mwala
 * Author URI: http://mundia.me
 * Developer: Mundia Mwala
 * Developer URI: http://twitter.com/glidematrix
 * text-domain: sparco-gateway
*/


/**
 * Class WC_Gateway_Sparco file.
 *
 * @package WooCommerce\Sparco
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

if (! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;


add_action('plugins_loaded', 'sparco_payment_init', 11);

function sparco_payment_init(){
    if(class_exists('WC_Payment_Gateway')){

		require plugin_dir_path(__FILE__) . '/includes/class-wc-sparco-payment-gateway.php';
		require plugin_dir_path(__FILE__) . '/includes/class-sparco-signature.php';

	}
}

add_filter('woocommerce_payment_gateways', 'add_to_woo_sparco_payment_gateway');

function add_to_woo_sparco_payment_gateway($gateways)
{
    $gateways[] = 'WC_Gateway_Sparco';

    return $gateways;
}