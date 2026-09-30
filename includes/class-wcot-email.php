<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return; // WooCommerce not loaded yet.
}

/**
 * "Order Tracking Update" transactional email.
 * Sent manually from the order screen, or automatically when the order
 * moves to a status configured below.
 */
class WCOT_Email_Order_Tracking extends WC_Email {

	public function __construct() {
		$this->id             = 'wcot_order_tracking';
		$this->title          = __( 'Order Tracking Update', 'wc-order-tracker' );
		$this->description    = __( 'Sent to the customer when tracking information is added or updated for their order.', 'wc-order-tracker' );
		$this->customer_email = true;

		$this->template_html  = 'emails/order-tracking-update.php';
		$this->template_plain = 'emails/plain/order-tracking-update.php';
		$this->template_base  = WCOT_PLUGIN_DIR . 'templates/';

		$this->placeholders = array(
			'{order_number}' => '',
			'{order_date}'   => '',
		);

		// Fired manually from the meta box, and automatically on completed.
		add_action( 'wcot_send_tracking_email', array( $this, 'trigger' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_trigger_on_complete' ), 20, 1 );

		parent::__construct();
	}

	public function maybe_trigger_on_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_meta( '_wcot_tracking_number' ) && ! $order->get_meta( '_wcot_tracking_email_sent' ) ) {
			$this->trigger( $order_id );
		}
	}

	public function trigger( $order_id ) {
		$this->object = wc_get_order( $order_id );
		if ( ! $this->object ) {
			return;
		}

		$this->recipient = $this->object->get_billing_email();

		$this->placeholders['{order_number}'] = $this->object->get_order_number();
		$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

		if ( $sent ) {
			$this->object->update_meta_data( '_wcot_tracking_email_sent', 'yes' );
			$this->object->save();
		}
	}

	public function get_default_subject() {
		return __( 'Your order #{order_number} has shipped!', 'wc-order-tracker' );
	}

	public function get_default_heading() {
		return __( 'Your order is on its way', 'wc-order-tracker' );
	}

	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}
}
