=== Kovatz WooCommerce Order Tracker ===
Requires at least: 6.0
Requires PHP: 7.4
Requires plugins: woocommerce

A front-end order tracking form for WooCommerce, usable with or without a customer login, plus a custom branded "Order Tracking Update" email.

== Installation ==

1. Zip already provided — in wp-admin go to Plugins > Add New > Upload Plugin, choose wc-order-tracker.zip, and click Install Now, then Activate.
   (Or unzip and upload the `wc-order-tracker` folder to `/wp-content/plugins/` via FTP.)
2. Make sure WooCommerce is active.
3. Create/edit any page and add the shortcode:

   [wc_order_tracker]

   Optional attribute:
   [wc_order_tracker title="Where's My Order?"]

== How it works ==

Guests: see a form asking for Order Number + Billing Email or Phone. On submit,
an AJAX request verifies the value matches the order's billing email or phone,
then renders a Bootstrap progress timeline, full order details, and (if set)
the carrier / tracking number / tracking link.

Logged-in customers: instead of the form, they see a table of their own
recent orders with a "Track" button per order — no email re-entry needed,
since ownership is verified against their account.

== Adding tracking info (admin) ==

Open any WooCommerce order (Edit screen). A "Shipment Tracking" box appears
in the sidebar with:
- Carrier
- Tracking Number
- Tracking URL
- A checkbox: "Send tracking update email to customer on save"

Checking that box and updating the order fires the custom
"Order Tracking Update" email (found under WooCommerce > Settings > Emails,
listed as "Order Tracking Update", fully editable there like any other
WooCommerce email — subject, heading, colors via the standard WooCommerce
email style settings).

When a tracking URL is saved, the plugin also adds a "Track Your Shipment"
link to standard customer order emails. Plain-text emails include the URL as
text. Administrator emails and the custom tracking update email are excluded.

The email is also sent automatically the first time an order with a saved
tracking number is marked "Completed" (and won't be sent twice for the
same order).

== Design ==

- Front-end form/timeline/order table use Bootstrap 5 (loaded from jsDelivr
  CDN) — if your theme already includes Bootstrap, remove the enqueue in
  wc-order-tracker.php to avoid loading it twice.
- The email re-uses WooCommerce's own email header/footer/order-details
  hooks, so it automatically matches your store's configured email colors,
  logo and footer text, with an added tracking-details card.

== Notes ==

- The "Shipped" step is a virtual timeline step for display purposes only;
  WooCommerce doesn't ship with a native "shipped" order status. If your
  store uses a plugin that adds a real "shipped" status, update the
  $status_map array in includes/class-wcot-ajax.php to point that status
  key at 'shipped'.
- All AJAX requests are protected with a nonce, and email/order-ID matching
  prevents guests from viewing orders that aren't theirs.
