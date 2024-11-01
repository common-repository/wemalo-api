<?php

/*
Plugin Name: Wemalo Connect
Plugin URI: http://help.wemalo.com/wordpress-wemalo-api-2/
Description: Wemalo API is being used for Shops using WooCommerce to communicate with Wemalo
Version: 2.1.28
Author: 4e software solution GmbH
Author URI: https://www.4e-software.de/
Update Server: http://www.wemalo.com/schnittstellen/wemalo-plugin
Min WP Version: 6.6.0
Max WP Version: 6.6.2
License: GPL2

WC requires at least: 3.0.0
WC tested up to: 9.3

Wemalo API is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
Wemalo API is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with Wemalo Plugin. If not, see http://www.gnu.org/licenses/gpl.txt.
*/

define("WEMALO_BASE", plugin_dir_url(__FILE__));

//register rest handler, actions and filters
require_once __DIR__ . '/api/class-wemaloapihandler.php';
require_once __DIR__ . '/api/handler/class-wemalopluginorders.php';
require_once __DIR__ . '/pages/class-wemalopluginconfig.php';
require_once __DIR__ .'/plugin/class-wemaloapiinstaller.php';
require_once __DIR__ .'/plugin/class-wemaloapiregisterorder.php';
require_once __DIR__ . '/plugin/class-wemaloapiregisterproduct.php';

$installer = new WemaloAPIInstaller();
$installer->register_events();
$regOrder = new WemaloAPIRegisterOrder();
$regOrder->register_events();
$regProduct = new WemaloAPIRegisterProduct();
$regProduct->register_events();
$handler = new WemaloAPIHandler();
$handler->register_events();

if(!function_exists('wemalo_plugin_user')){
    function wemalo_plugin_user() {
        $user = new WemaloPluginConfig();
        $user->build_markup();
    }
}


//create tables on activation
register_activation_hook( __FILE__, 'wemalo_activation' );

/**
 * called for activating wemalo
 */
function wemalo_activation() {
	$installer = new WemaloAPIInstaller();
	$installer->set_up();
}

register_deactivation_hook(__FILE__, 'wemalo_deactivation');

/**
 * called when plugin is being deactivated
 */
function wemalo_deactivation() {
	$installer = new WemaloAPIInstaller();
	$installer->tear_down();
}

function wemalo_register_plugin_styles() {

	wp_register_style( 'wemalo', plugins_url( 'css/wemaloplugin-style.css', __FILE__ ) , [] , wemalo_css_js_suffix() );
	wp_enqueue_style( 'wemalo' );
}

function wemalo_register_plugin_scripts() {
	// make sure jQuery is loaded BEFORE our script as we are using it
	wp_register_script( 'wemalo-order', plugins_url( 'js/wemalo.order.js', __FILE__ ), array( 'jquery' ) , wemalo_css_js_suffix() );
	wp_enqueue_script( 'wemalo-order' );
	wp_localize_script('wemalo-order', 'wemaloOrder', array('ajax_url' => admin_url('admin-ajax.php')));
}

// load JS and CSS files on admin pages only
add_action('admin_enqueue_scripts', 'wemalo_register_plugin_styles' );
add_action('admin_enqueue_scripts', 'wemalo_register_plugin_scripts' );

function wemalo_autoload($class) {
	if ($class === "WemaloInterface") {
		require_once __DIR__ .'/plugin/class-wemalointerface.php';
	}
}

function wemalo_css_js_suffix() {
    $result = '';
    $pluginInfo = get_plugin_data( __FILE__, $markup = false, $translate = false);
    if (is_array($pluginInfo) && isset($pluginInfo['Version'])) {
        $result = $pluginInfo['Version'];
    }
    return $result;
}

spl_autoload_register('wemalo_autoload');
