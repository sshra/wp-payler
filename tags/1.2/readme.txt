=== Integrate Payler with Woocommerce ===
Contributors: shra
Donate link: https://yoomoney.ru/to/410011969010464
Tags: payler, payler integration, woocommerce payments, woocommerce gateway
Requires at least: 3.1.0
Tested up to: 6.1
Stable tag: 1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The plugin allows you to connect payments via Payler (https://payler.com) to your woocommerce based e-shop.

== Description ==

Plugin allows to use Payler for Woocommerce to receive payments from your customers. It also generates fiscal checks via the Payler API.

Read more details in the installation section about initial plugin configuring.

== Installation ==

To install this plugin:

1. Download plugin
2. Extract and copy plugins files to /wp-content/plugins/wc-payler directory
3. Activate it (enter to /wp_admin, then choose plugins page, press activate plugin)
4. Go to /wp-admin/admin.php?page=wc-settings&tab=checkout&section=payler and provide necessary settings.
5. Don't forgot provide settings on payler side: https://my.payler.com/settings/payment.
CALLBACK URL should have format like next (replace YOURWEBSITE.NET by your sitename):
http://YOURWEBSITE.NET/?wc-api=wc_payler&order_id={order_id}
PAYMENT TYPE select as 'doublestaged'
6. Enjoy, I hope :)

== Changelog ==

= 1.2 = NDS cals fixes. Check for API updates.
= 1.1 = Shipping is separate line in fiscal checks.
= 1.0 = Added support of fiscal checks via payler API. Some small changes and fixes.
= 0.2 = Code refactoring
= 0.1 = Initial version based on code of Sergey Khodko (https://vk.com/tranceiteasy).

== Upgrade Notice ==

No special notes here are for upgrade. Install and enjoy.
