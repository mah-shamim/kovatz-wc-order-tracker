<?php
/**
 * Order Tracking Update email (HTML).
 * Overridable by copying to yourtheme/woocommerce/emails/order-tracking-update.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var WC_Order $order */

do_action( 'woocommerce_email_header', $email_heading, $email );

$tracking_number  = $order->get_meta( '_wcot_tracking_number' );
$tracking_carrier = $order->get_meta( '_wcot_tracking_carrier' );
$tracking_url     = $order->get_meta( '_wcot_tracking_url' );
?>

<p style="font-size:15px; line-height:1.6; margin:0 0 16px;">
	<?php
	printf(
		/* translators: %s: customer first name */
		esc_html__( 'Hi %s,', 'wc-order-tracker' ),
		esc_html( $order->get_billing_first_name() )
	);
	?>
</p>

<p style="font-size:15px; line-height:1.6; margin:0 0 24px;">
	<?php
	printf(
		/* translators: %s: order number */
		esc_html__( 'Great news — order #%s is on its way. Here are your tracking details:', 'wc-order-tracker' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<!-- Tracking card -->
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px; border:1px solid #e5e5e5; border-radius:6px; background-color:#f8f9fa;">
	<tr>
		<td style="padding:20px 24px;">
			<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
				<tr>
					<td style="font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6c757d; padding-bottom:4px;">
						<?php esc_html_e( 'Carrier', 'wc-order-tracker' ); ?>
					</td>
					<td style="font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6c757d; padding-bottom:4px; text-align:right;">
						<?php esc_html_e( 'Tracking Number', 'wc-order-tracker' ); ?>
					</td>
				</tr>
				<tr>
					<td style="font-size:16px; font-weight:600; color:#212529;">
						<?php echo esc_html( $tracking_carrier ? $tracking_carrier : '—' ); ?>
					</td>
					<td style="font-size:16px; font-weight:600; color:#212529; text-align:right;">
						<?php echo esc_html( $tracking_number ? $tracking_number : '—' ); ?>
					</td>
				</tr>
			</table>

			<?php if ( $tracking_url ) : ?>
			<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-top:20px;">
				<tr>
					<td align="center">
						<a href="<?php echo esc_url( $tracking_url ); ?>"
							style="display:inline-block; padding:12px 28px; background-color:#212529; color:#ffffff; text-decoration:none; border-radius:4px; font-size:14px; font-weight:600;">
							<?php esc_html_e( 'Track Your Shipment', 'wc-order-tracker' ); ?>
						</a>
					</td>
				</tr>
			</table>
			<?php endif; ?>
		</td>
	</tr>
</table>

<h3 style="font-size:15px; margin:0 0 12px;"><?php esc_html_e( 'Order Summary', 'wc-order-tracker' ); ?></h3>

<?php
/*
 * WooCommerce core template — renders the order items table, totals and
 * (optionally) customer/billing details using the theme's own email styles.
 */
do_action( 'woocommerce_email_order_details', $order, false, false, $email );
do_action( 'woocommerce_email_order_meta', $order, false, false, $email );
do_action( 'woocommerce_email_customer_details', $order, false, false, $email );
?>

<p style="font-size:14px; color:#6c757d; margin-top:24px;">
	<?php esc_html_e( 'If you have any questions about your shipment, just reply to this email — we are happy to help.', 'wc-order-tracker' ); ?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
