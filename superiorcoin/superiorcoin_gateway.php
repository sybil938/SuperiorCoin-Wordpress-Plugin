<?php
/**
 * Plugin Name: SuperiorCoin Woocommerce Payment Gateway
 * Plugin URI: http://superior-coin.com
 * Description: This plugin supports SuperiorCoin payment in Woocommerce (Supports only USD $Currency at the moment).
 * Version: 1.0
 * Author: SuperiorCoin Team
 * Author URI: http://superior-coin.com
 */

// !Important: This Code Prevents Public User To Directly Access Your PHP Files Through URL.
if (!defined('ABSPATH')) {
    exit; 
}

// Include SuperiorCoin Gateway Class & As A Payment Gateway With WooCommerce
add_action('plugins_loaded', 'superiorcoin_init', 0);
function superiorcoin_init()
{
    // If class does not exist [WooCommerce Not Installed] then return NULL
    if ( !class_exists('WC_Payment_Gateway') ) {
        return;
    }

    // Include Superior Gateway Class
    include_once('include/superiorcoin_payments.php');
    require_once('library.php');

    // Add To WooCommerce
    add_filter('woocommerce_payment_gateways', 'superiorcoin_gateway');
    function superiorcoin_gateway($methods)
    {
        $methods[] = 'superiorcoin_gateway';

        return $methods;

    }
}

// Add Custom Link
// The url will be http://yourworpress/wp-admin/admin.php?=wc-settings&tab=checkout
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'superiorcoin_payment');
function superiorcoin_payment($links) {

    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'superiorcoin_payment') . '</a>',
    );

    return array_merge($plugin_links, $links);
}

// Add Admin Menu
add_action('admin_menu', 'superiorcoin_create_menu');
function superiorcoin_create_menu()
{
    add_menu_page(
        __('SuperiorCoin', 'textdomain'),
        'SuperiorCoin',
        'manage_options',
        'admin.php?page=wc-settings&tab=checkout&section=superiorcoin_gateway',
        '',
        plugins_url('superiorcoin/assets/icon.png'),
        57 // Position on menu, woocommerce has 55.5, products has 55.6

    );
}