<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var WC_Order $order */

$tracking_number  = $order->get_meta( '_wcot_tracking_number' );
$tracking_carrier = $order->get_meta( '_wcot_tracking_carrier' );
$tracking_url     = $order->get_meta( '_wcot_tracking_url' );

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n";
echo "=======================================\n\n";

printf( esc_html__( 'Hi %s,', 'wc-order-tracker' ), esc_html( $order->get_billing_first_name() ) );
echo "\n\n";

printf(
	esc_html__( 'Order #%s is on its way. Tracking details:', 'wc-order-tracker' ),
	esc_html( $order->get_order_number() )
);
echo "\n\n";

echo esc_html__( 'Carrier:', 'wc-order-tracker' ) . ' ' . esc_html( $tracking_carrier ? $tracking_carrier : '—' ) . "\n";
echo esc_html__( 'Tracking Number:', 'wc-order-tracker' ) . ' ' . esc_html( $tracking_number ? $tracking_number : '—' ) . "\n";
if ( $tracking_url ) {
	echo esc_html__( 'Track your shipment:', 'wc-order-tracker' ) . ' ' . esc_url( $tracking_url ) . "\n";
}

echo "\n----------------------------------------\n\n";

echo wp_strip_all_tags( wc_get_email_order_items( $order, array( 'plain_text' => true ) ) );

echo "\n----------------------------------------\n\n";
echo esc_html__( 'Questions about your shipment? Just reply to this email.', 'wc-order-tracker' ) . "\n";
