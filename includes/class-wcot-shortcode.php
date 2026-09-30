<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [wc_order_tracker] — works for guests (order ID + email) and
 * for logged-in customers (pick from their own order list).
 */
class WCOT_Shortcode {

	public static function init() {
		add_shortcode( 'wc_order_tracker', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		$atts = shortcode_atts( array(
			'title' => __( 'Track Your Order', 'wc-order-tracker' ),
		), $atts, 'wc_order_tracker' );

		ob_start();

		if ( is_user_logged_in() ) {
			self::render_logged_in( $atts );
		} else {
			self::render_guest_form( $atts );
		}

		echo '<div id="wcot-result" class="mt-4"></div>';

		return ob_get_clean();
	}

	/**
	 * Guest / anonymous form: order number + billing email or phone.
	 */
	private static function render_guest_form( $atts ) {
		?>
		<div class="wcot-wrapper wcot-guest-wrapper card shadow-sm border-0">
			<div class="card-body p-4">
				<h3 class="card-title h4 mb-2"><?php echo esc_html( $atts['title'] ); ?></h3>
				<p class="wcot-form-note mb-4">
					<?php esc_html_e( 'Enter your order number and the billing email or phone used at checkout.', 'wc-order-tracker' ); ?>
				</p>

				<form id="wcot-guest-form" class="row g-3" autocomplete="off">
					<div class="wcot-field col-12 col-md-6">
						<label for="wcot_order_id" class="form-label"><?php esc_html_e( 'Order Number', 'wc-order-tracker' ); ?></label>
						<input type="text" class="form-control" id="wcot_order_id" name="order_id" placeholder="e.g. 1234" required>
					</div>
					<div class="wcot-field col-12 col-md-6">
						<label for="wcot_identifier" class="form-label"><?php esc_html_e( 'Billing Email or Phone', 'wc-order-tracker' ); ?></label>
						<input type="text" class="form-control" id="wcot_identifier" name="identifier" autocomplete="off" required>
					</div>
					<div class="wcot-actions col-12">
						<button type="submit" class="btn btn-dark px-4">
							<?php esc_html_e( 'Track Order', 'wc-order-tracker' ); ?>
						</button>
						<span class="wcot-spinner spinner-border spinner-border-sm text-dark ms-2 d-none" role="status" aria-hidden="true"></span>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Logged-in customer: list their recent orders with a "Track" button each.
	 */
	private static function render_logged_in( $atts ) {
		$customer_orders = wc_get_orders( array(
			'customer_id' => get_current_user_id(),
			'limit'       => 15,
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );
		?>
		<div class="wcot-wrapper wcot-account-wrapper card shadow-sm border-0">
			<div class="card-body p-4">
				<h3 class="card-title h4 mb-3"><?php echo esc_html( $atts['title'] ); ?></h3>

				<?php if ( empty( $customer_orders ) ) : ?>
					<div class="alert alert-secondary mb-0">
						<?php esc_html_e( "You don't have any orders yet.", 'wc-order-tracker' ); ?>
					</div>
				<?php else : ?>
					<div class="table-responsive">
						<table class="table align-middle">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Order', 'wc-order-tracker' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Date', 'wc-order-tracker' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'wc-order-tracker' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Total', 'wc-order-tracker' ); ?></th>
									<th scope="col" class="text-end"><?php esc_html_e( 'Action', 'wc-order-tracker' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $customer_orders as $order ) : ?>
								<tr>
									<td>#<?php echo esc_html( $order->get_order_number() ); ?></td>
									<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
									<td>
										<span class="badge rounded-pill text-bg-light border">
											<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
										</span>
									</td>
									<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
									<td class="text-end">
										<button
											type="button"
											class="btn btn-outline-dark btn-sm wcot-track-btn"
											data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
										>
											<?php esc_html_e( 'Track', 'wc-order-tracker' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
