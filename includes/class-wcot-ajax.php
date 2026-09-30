<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the front-end AJAX lookup for both guests and logged-in users.
 */
class WCOT_Ajax {

	public static function init() {
		add_action( 'wp_ajax_wcot_track_order', array( __CLASS__, 'track_order' ) );
		add_action( 'wp_ajax_nopriv_wcot_track_order', array( __CLASS__, 'track_order' ) );
	}

	public static function track_order() {
		check_ajax_referer( 'wcot_track_order', 'nonce' );

		$order_number = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
		$order_id     = absint( $order_number );
		$identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';

		if ( '' === $order_number ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid order number.', 'wc-order-tracker' ) ) );
		}

		$order = wc_get_order( $order_id );

		if ( $order instanceof WC_Order && (string) $order->get_order_number() !== $order_number ) {
			$order = null;
		}

		if ( ! $order instanceof WC_Order ) {
			$order_ids = WC_Data_Store::load( 'order' )->search_orders( $order_number );

			foreach ( $order_ids as $candidate_id ) {
				$candidate = wc_get_order( $candidate_id );

				if ( $candidate instanceof WC_Order && (string) $candidate->get_order_number() === $order_number ) {
					$order = $candidate;
					break;
				}
			}
		}

		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'We could not find that order.', 'wc-order-tracker' ) ) );
		}

		$is_owner = is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();

		if ( ! $is_owner ) {
			// Guests (or users looking up someone else's order) must verify with a billing contact.
			$email_matches = strtolower( $identifier ) === strtolower( $order->get_billing_email() );
			$phone_digits  = preg_replace( '/\D+/', '', $identifier );
			$order_digits  = preg_replace( '/\D+/', '', $order->get_billing_phone() );
			$phone_digits  = preg_replace( '/^88/', '', $phone_digits );
			$order_digits  = preg_replace( '/^88/', '', $order_digits );
			$phone_matches = '' !== $phone_digits && $phone_digits === $order_digits;

			if ( '' === $identifier || ( ! $email_matches && ! $phone_matches ) ) {
				wp_send_json_error( array( 'message' => __( 'Order number and billing email or phone do not match our records.', 'wc-order-tracker' ) ) );
			}
		}

		wp_send_json_success( array(
			'html' => self::render_timeline( $order ),
		) );
	}

	/**
	 * Builds the Bootstrap order-status timeline + summary markup.
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	public static function render_timeline( $order ) {
		$steps = array(
			'pending'    => __( 'Order Placed', 'wc-order-tracker' ),
			'processing' => __( 'Processing', 'wc-order-tracker' ),
			'shipped'    => __( 'Shipped', 'wc-order-tracker' ),
			'completed'  => __( 'Delivered', 'wc-order-tracker' ),
		);

		$current_status = $order->get_status();

		// Map less common statuses onto the nearest visible step.
		$status_map = array(
			'on-hold'    => 'pending',
			'processing' => 'processing',
			'shipped'    => 'shipped',
			'completed'  => 'completed',
			'refunded'   => 'completed',
			'cancelled'  => 'pending',
			'failed'     => 'pending',
		);
		$active_key = isset( $status_map[ $current_status ] ) ? $status_map[ $current_status ] : 'pending';
		$keys       = array_keys( $steps );
		$active_idx = array_search( $active_key, $keys, true );

		$tracking_number = $order->get_meta( '_wcot_tracking_number' );
		$tracking_carrier = $order->get_meta( '_wcot_tracking_carrier' );
		$tracking_url    = $order->get_meta( '_wcot_tracking_url' );

		$cancelled_or_failed = in_array( $current_status, array( 'cancelled', 'failed', 'refunded' ), true );

		ob_start();
		?>
		<div class="wcot-result wcot-order-result card shadow-sm border-0">
			<div class="card-body p-4">
				<div class="wcot-order-header d-flex flex-wrap justify-content-between align-items-center mb-4">
					<div>
						<h4 class="mb-1">
							<?php
							/* translators: %s: order number */
							printf( esc_html__( 'Order #%s', 'wc-order-tracker' ), esc_html( $order->get_order_number() ) );
							?>
						</h4>
						<div class="text-muted small">
							<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
						</div>
					</div>
					<span class="badge rounded-pill text-bg-<?php echo $cancelled_or_failed ? 'danger' : 'dark'; ?> px-3 py-2">
						<?php echo esc_html( wc_get_order_status_name( $current_status ) ); ?>
					</span>
				</div>

				<?php if ( $cancelled_or_failed ) : ?>
					<div class="alert alert-warning">
						<?php esc_html_e( 'This order is not currently in an active fulfillment state.', 'wc-order-tracker' ); ?>
					</div>
				<?php else : ?>
					<div class="wcot-timeline d-flex justify-content-between position-relative mb-4">
						<?php foreach ( $keys as $i => $key ) :
							$is_done   = $i <= $active_idx;
							$is_active = $i === $active_idx;
							?>
							<div class="wcot-step text-center flex-fill<?php echo $is_done ? ' is-done' : ''; ?><?php echo $is_active ? ' is-active' : ''; ?>">
								<div class="wcot-dot mx-auto mb-2"></div>
								<div class="small fw-semibold"><?php echo esc_html( $steps[ $key ] ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $tracking_number ) : ?>
					<div class="wcot-tracking-box border rounded p-3 mb-4 bg-light">
						<div class="row g-2 align-items-center">
							<div class="col-12 col-md-8">
								<div class="small text-muted"><?php esc_html_e( 'Carrier', 'wc-order-tracker' ); ?></div>
								<div class="fw-semibold mb-2"><?php echo esc_html( $tracking_carrier ? $tracking_carrier : '—' ); ?></div>
								<div class="small text-muted"><?php esc_html_e( 'Tracking Number', 'wc-order-tracker' ); ?></div>
								<div class="fw-semibold"><?php echo esc_html( $tracking_number ); ?></div>
							</div>
							<div class="col-12 col-md-4 text-md-end">
								<?php if ( $tracking_url ) : ?>
									<a href="<?php echo esc_url( $tracking_url ); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-dark btn-sm">
										<?php esc_html_e( 'Track Shipment', 'wc-order-tracker' ); ?>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<h6 class="mb-2"><?php esc_html_e( 'Items', 'wc-order-tracker' ); ?></h6>
				<ul class="list-group list-group-flush mb-3">
					<?php foreach ( $order->get_items() as $item ) : ?>
						<li class="list-group-item d-flex justify-content-between px-0">
							<span><?php echo esc_html( $item->get_name() ); ?> × <?php echo esc_html( $item->get_quantity() ); ?></span>
							<span><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>

				<table class="table table-sm mb-4">
					<tbody>
						<tr><th scope="row"><?php esc_html_e( 'Subtotal', 'wc-order-tracker' ); ?></th><td class="text-end"><?php echo wp_kses_post( $order->get_subtotal_to_display() ); ?></td></tr>
						<?php if ( $order->get_coupon_codes() ) : ?>
							<tr><th scope="row"><?php esc_html_e( 'Coupons', 'wc-order-tracker' ); ?></th><td class="text-end"><?php echo esc_html( implode( ', ', $order->get_coupon_codes() ) ); ?></td></tr>
						<?php endif; ?>
						<?php if ( $order->get_discount_total() > 0 ) : ?>
							<tr><th scope="row"><?php esc_html_e( 'Discount', 'wc-order-tracker' ); ?></th><td class="text-end"><?php echo wp_kses_post( $order->get_discount_to_display() ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $order->get_fees() as $fee ) : ?>
							<tr><th scope="row"><?php echo esc_html( $fee->get_name() ); ?></th><td class="text-end"><?php echo wp_kses_post( wc_price( $fee->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?></td></tr>
						<?php endforeach; ?>
						<?php if ( $order->get_shipping_total() > 0 || $order->get_shipping_method() ) : ?>
							<tr><th scope="row"><?php echo esc_html( $order->get_shipping_method() ? $order->get_shipping_method() : __( 'Shipping', 'wc-order-tracker' ) ); ?></th><td class="text-end"><?php echo wp_kses_post( $order->get_shipping_to_display() ); ?></td></tr>
						<?php endif; ?>
						<?php if ( $order->get_total_tax() > 0 ) : ?>
							<tr><th scope="row"><?php esc_html_e( 'Tax', 'wc-order-tracker' ); ?></th><td class="text-end"><?php echo wp_kses_post( wc_price( $order->get_total_tax(), array( 'currency' => $order->get_currency() ) ) ); ?></td></tr>
						<?php endif; ?>
						<tr class="border-top"><th scope="row"><?php esc_html_e( 'Total', 'wc-order-tracker' ); ?></th><td class="text-end fw-semibold"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td></tr>
					</tbody>
				</table>

				<h6 class="mb-2"><?php esc_html_e( 'Billing Details', 'wc-order-tracker' ); ?></h6>
				<div class="mb-4">
					<?php if ( $order->get_formatted_billing_address() ) : ?>
						<div><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></div>
					<?php endif; ?>
					<?php if ( $order->get_billing_email() ) : ?><div><?php echo esc_html( $order->get_billing_email() ); ?></div><?php endif; ?>
					<?php if ( $order->get_billing_phone() ) : ?><div><?php echo esc_html( $order->get_billing_phone() ); ?></div><?php endif; ?>
				</div>

				<?php if ( $order->get_formatted_shipping_address() ) : ?>
					<h6 class="mb-2"><?php esc_html_e( 'Shipping Address', 'wc-order-tracker' ); ?></h6>
					<div class="mb-4"><?php echo wp_kses_post( $order->get_formatted_shipping_address() ); ?></div>
				<?php endif; ?>

				<?php if ( $order->get_payment_method_title() ) : ?>
					<div class="small text-muted"><?php esc_html_e( 'Payment Method', 'wc-order-tracker' ); ?>: <?php echo esc_html( $order->get_payment_method_title() ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
