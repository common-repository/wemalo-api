=== Wemalo Connect ===
Contributors: wemalo
Tags: wemalo, lagerverwaltung, warehouse management, fulfillment
Requires at least: 6.0
Tested up to: 6.6.2
Requires PHP: 8.2
Stable tag: 2.1.28
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Wemalo API provides a link between your WooCommerce shop and the Wemalo warehouse management system.

== Description ==
This plugin is used for transmission between a Woocommerce-based Wordpress shopsystem and the WEMALO warehouse management system.

If you install Wemalo API, WEMALO is able to get updates from shopsystem like product changes and sales orders. It\'s capable of managing your stocks and provides ways for handling returns.

= Feature list =
* REST-Api to get shop information
* connected to wemalo-connect
* adding new fields to products
* adding new statuses to orders
* provides returns workflow

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the \'Plugins\' screen in WordPress
3. Use the Settings->Wemalo screen to configure the plugin
4. Enter your WEMALO-CONNECT access token (you\'ll get it from your Wemalo contact)
5. Pick&Pack !

== Frequently Asked Questions ==
= Kann das Plugin gefahrlos von 1.4.x auf 2.x aktualisiert werden? =
Bitte wenden Sie sich dafür zunächst an Ihren Ansprechpartner. Die Version 2.x unterstützt eine andere Schnittstelle, weshalb Sie dafür erst freigeschaltet werden müssen.

= Bleibt der Authkey bei Aktualisierung von 1.4.x auf 2.x gleich? =
Nein, für die Version 2.x ist ein Account in wemalo-connect notwendig. Dazu wird auch ein neuer Schlüssel benötigt.

== Changelog ==
= 2.1.28 =
Date: 03.10.2024
* Bug-Fixes
* Plugin cleanup

= 2.1.27 =
Date: 30.09.2024
* Bug-Fixes

= 2.1.25 =
Date: 24.08.2023
* Bug-Fix / internal exception handling.

== Changelog ==
= 2.1.24 =
Date: 15.03.2023
* Remove Product Description

= 2.1.23 =
Date: 02.11.2022
* Bug-Fix / Tracking number / dispatcher was missed in some orders
* Bug-Fix / using dynamic table prefix

= 2.1.22 =
Date: 22.03.2022
* Bug-Fix / setting 0 product stocks which is delivers from wemalo

= 2.1.21 =
Date: 11.11.2021
* Bug-Fix / handle product dimension and weight with the correct unit type

= 2.1.20 =
Date: 26.10.2021
* Bug-Fix / product name
* Bug-Fix / missing tracking number in the order

= 2.1.19 =
Date: 16.07.2021
* Correct tracking info

== Changelog ==
= 2.1.18 =
Date: 30.03.2021
* Security review

= 2.1.17 =
Date: 13.11.2020
* split tracking number webhook route

= 2.1.16 =
Date: 19.06.2020
* use add multiple products for product variants
* fix the issue by cancelling the orders
* solve the loading issue caused by internal call

= 2.1.15.1 =
Date: 19.02.2020
* translation bug fix
* status change bug fix

= 2.1.15 =
Date: 17.12.2019
* adding tracking number
* setting up a cronjob to install webhooks
* checking the webhooks limits
* fix bug

= 2.1.14 =
Date: 01.04.2019
* WE/WA checkbox
* adding lot/sku to returns booked
* fix bug

= 2.1.13 =
Date: 06.03.2019
* Batch Charge as Info im WA-Scan
* setting default priotity
* listing all selected shipping methods
* adding stock to product call

= 2.1.12 =
Date: 21.06.2018
* resetting webhooks and diplaying webhook token on configuration page
* loading current order status from connect when webhook was triggered
* compare file extension while uploading case intensitive

= 2.1.11 =
Date: 18.05.2018
* URL to connect adjusted due to newest DSGVO
* fix: typo when transmitting additional stock information corrected

= 2.1.10 =
Date: 14.04.2018
* displaying not reservable quantities on product pages
* fix: displaying priority corrected if saved as array

= 2.1.9 =
Date: 23.03.2018
* added a new option for specifiying field name for order category (e.g. B2B)

= 2.1.8 =
Date: 01.03.2018
* added a new option field for setting the custom field key for parent order ids
* possibility to set a key for skipping parent order check in wemalo

= 2.1.7 =
Date: 26.02.2018
* showing skipping serial number check independently of order status (exception: announced returns)
* fix: avoiding notices

= 2.1.6 =
Date: 13.02.2018
* when checking unreserved orders, we\'ll now using pagination to load only 60 orders at once
* fix: uploading documents from orders view

= 2.1.5 =
Date: 09.02.2018
* registered a hook for detecting if an order was changed to return announced programmatically

= 2.1.4 =
Date: 07.02.2018
* added version number to css and javascript files
* stock information extended
* announce return button won\'t be displayed anymore

= 2.1.3 =
Date: 23.01.2018
* option for skipping serial number check while scanning returns added to wemalo meta box
* serial number field renamed
* taking parent order id (meta field parent_order_id) if set when announcing returns
* don\'t show an alert if an error occurs while loading dispatcher profiles
* calculating total quantity and total reserved, displaying on order position if item is on stock and showing serial number accordingly
* don\'t transmit orders without positions

= 2.1.2 =
Date: 17.01.2018
* displaying available quantities in order positions
* allowing changing back from fulfillment blocked to fulfillment if order was finally packed
* fix: transmitting order updates optimized

= 2.1.1 =
Date: 15.01.2018
* new column for fulfillment blocked added to order overview
* icon for fulfillment blocked added to order overview
* fix: loading scripts and css optimized

= 2.1.0 =
Date: 12.01.2018
* loading available dispatcher profiles and transmitting selected profile to wemalo
* added an interface for accessing some functions from outside of wemalo plugin quite easily
* new routes for checking orders in status processing
* new order status fulfillment blocked introduced
* retransmitting orders and setting order to fulfillment blocked implemented
* saving a flag if celebrity was set
* registered to newly introduced wemalo connect order status update webhook
* uploading documents in orders

= 2.0.8 =
Date: 06.01.2018
* fix: avoiding php errors/warnings if order position price was not set/product weight was not set
* fix: don\'t accessing order id directly when loading orders meta data

= 2.0.7 =
Date: 04.01.2018
* get shop name from options
* transmit weight in g instead of kg
* loading plugin information via rest call
* fix: position quantity field was renamed in latest woocommerce version

= 2.0.6 =
Date: 20.12.2017
* don\'t transmit orders if flag order_not_paid is set
* priority field added to orders (added in a wemalo order box)
* custom fields added to order view (download timestamp, priority and reason of partially reserved)

= 2.0.5 =
Date: 14.12.2017
* handling of html characters in product master data optimized (when transmitting data to Wemalo)
* sales prices added to order positions (when transmitting positions to Wemalo)
* transmit orders even if download timestamp was already set
* set to fulfillment in case of additional order status (e.g. in picking or packed)
* adding update date to stock table
* legacy files removed

= 2.0.4 =
Date: 10.12.2017
* added a hook after payment has been completed
* refactoring when setting tracking number and carrier added to custom fields

= 2.0.3 =
Date: 04.12.2017
* added a new hook for detecting orders changed to processing

= 2.0.2 =
Date: 28.11.2017
* supporting WooCommerce Product Bundle

= 2.0.1 =
Date: 24.11.2017
* matching order positions by sku instead of post id

= 2.0.0 =
Date: 19.10.2017
* using wordpress api structure
* connecting against wemalo-connect directly
* product and order structure modified and aligned to wemalo-connect specification
* additional stock information are being transmitted to woocommerce

= 1.4.5 =
Date: 11.10.2017
* setting tracking number and carrier to wp lister amazon/ebay

= 1.4.4 =
* new call for getting an order by id added
* checkbox for setting an order as blocked added
* refactoring

= 1.4.3 =
* etd added as new field to orders
* notices added as new field to orders. Will be read when loading orders and announced returns.

= 1.4.2 =
* bootstrap removed
* linking to wp-load.php

= 1.4.1 =
* ignoring virtual products when downloading orders

= 1.4.0 =
Date: 10.07.2017
* setting alternative stock
* added a new field for storing ean
* new order status introduced to cancel an order by Wemalo

= 1.3.3 =
* return reason added to return shipment table
* getting product master data from private products as well

= 1.3.2 =
* supporting WooCommerce 2.6.14 and >= 3.0
* avoiding usage of WC_Order and WC_Product
* check whether additioanl order status already exists before inserting

= 1.3.1 =
* added max results to call for getting return shipments
* removed unused call
* added order prices and orders payment method

= 1.3.0 =
Date: 12.04.2017
* added new status return announced and return received
* booked return shipment items will be transmitted back to WooCommerce

= 1.2.1 =
* Reading gtin from product meta array as ean

= 1.2.0 =
Date: 30.01.2017
* Setting total stock provided

= 1.1.0 =
Date: 13.12.2016
* Adding tracking numbers as order notes

= 1.0.4 =
* Setting a meta key in orders after download

= 1.0.0 =
Date: 22.03.2016
* First major release

== Upgrade Notice ==
If upgrading from <2.0 to >= 2.0, please deactivate and delete the current version first.
Afterwards, follow the steps in chapter installation.