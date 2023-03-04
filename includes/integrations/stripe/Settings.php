<?php


namespace WPUserManager\Stripe;

use WPUM\Stripe\StripeClient;
use WPUserManager\Stripe\Controllers\Products;

class Settings {

	/**
	 * @var Connect
	 */
	protected $connect;

	/**
	 * @var Products
	 */
	protected $products;

	const APPLICATION_FEE_PERCENT = 2;

	/**
	 * @param $connect
	 */
	public function __construct( $connect ) {
		$this->connect = $connect;
	}

	public function init() {
		add_action( 'wpum_registered_settings', array( $this, 'register_settings' ) );
		add_filter( 'wpum_settings_tabs', array( $this, 'register_setting_tab' ) );
		add_action( 'update_option_wpum_settings', array( $this, 'flush_product_cache' ) );
		add_action( 'wp_ajax_wpum_stripe_connect_account_info', array( $this, 'stripe_connect_account_info_ajax_response' ) );
		add_action( 'admin_init', array( $this, 'handle_stripe_connect_disconnect' ) );
	}

	/**
	 * @param Products $products
	 */
	public function setProducts( $products ) {
		$this->products = $products;
	}

	/**
	 * Register Stripe settings
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	function register_settings( $settings ) {
		$settings['stripe'][] = array(
			'id'      => 'stripe_gateway_mode',
			'name'    => __( 'Gateway Mode', 'wp-user-manager' ),
			'type'    => 'select',
			'std'     => 'test',
			'options' => array(
				'test' => __( 'Test', 'wp-user-manager' ),
				'live' => __( 'Live', 'wp-user-manager' ),
			),
		);

		$settings['stripe'][] = array(
			'id'     => 'stripe_connect_test',
			'name'   => __( 'Connect to Stripe', 'wp-user-manager' ),
			'desc'   => __( 'Connect to your Stripe account to get started', 'wp-user-manager' ),
			'type'   => 'html',
			'html'   => sprintf( '<a href="%s"><img src="%s" style="max-width: 160px;"></a>', $this->connect->connect_url( true ), WPUM_PLUGIN_URL . 'assets/images/stripe-connect.png' ),
			'std'    => 1,
			'toggle' => array(
				array(
					'key'   => 'stripe_gateway_mode',
					'value' => 'test'
				),
				array(
					'key'   => 'test_stripe_publishable_key',
					'value' => '',
				),
				array(
					'key'   => 'test_stripe_secret_key',
					'value' => '',
				),
			),
		);

		$settings['stripe'][] = array(
			'id'     => 'stripe_disconnect_test',
			'name'   => __( 'Connection Status', 'wp-user-manager' ),
			'type'   => 'html',
			'class'  => 'button',
			'html'   => $this->render_admin_disconnect(),
			'std'    => 1,
			'toggle' => array(
				array(
					'key'   => 'stripe_gateway_mode',
					'value' => 'test'
				),
				array(
					'key'      => 'test_stripe_publishable_key',
					'value'    => '',
					'operator' => '==',
				),
				array(
					'key'      => 'test_stripe_secret_key',
					'value'    => '',
					'operator' => '==',
				),
			),
		);

		$settings['stripe'][] = array(
			'id'     => 'stripe_connect_live',
			'name'   => __( 'Connect to Stripe', 'wp-user-manager' ),
			'desc'   => __( 'Connect to your Stripe account to get started', 'wp-user-manager' ),
			'type'   => 'html',
			'html'   => sprintf( '<a href="%s"><img src="%s" style="max-width: 160px;"></a>', $this->connect->connect_url( false ), WPUM_PLUGIN_URL . 'assets/images/stripe-connect.png' ),
			'std'    => 1,
			'toggle' => array(
				array(
					'key'   => 'stripe_gateway_mode',
					'value' => 'live'
				),
				array(
					'key'   => 'live_stripe_publishable_key',
					'value' => '',
				),
				array(
					'key'   => 'live_stripe_secret_key',
					'value' => '',
				),
			),
		);

		$settings['stripe'][] = array(
			'id'     => 'stripe_disconnect_live',
			'name'   => __( 'Connection Status', 'wp-user-manager' ),
			'type'   => 'html',
			'class'  => 'button',
			'html'   => $this->render_admin_disconnect( 'live' ),
			'toggle' => array(
				array(
					'key'   => 'stripe_gateway_mode',
					'value' => 'live'
				),
				array(
					'key'      => 'live_stripe_publishable_key',
					'value'    => '',
					'operator' => '==',
				),
				array(
					'key'      => 'live_stripe_secret_key',
					'value'    => '',
					'operator' => '==',
				),
			),
		);

		// TODO remove key textboxes
		$settings['stripe'][] = array(
			'id'     => 'test_stripe_publishable_key',
			'name'   => __( 'Test Key', 'wp-user-manager' ),
			'type'   => 'hidden',
			'toggle' => array(
				'key'   => 'stripe_gateway_mode',
				'value' => 'test'
			),
		);
		$settings['stripe'][] = array(
			'id'     => 'test_stripe_secret_key',
			'name'   => __( 'Test Secret', 'wp-user-manager' ),
			'type'   => 'hidden',
			'toggle' => array(
				'key'   => 'stripe_gateway_mode',
				'value' => 'test'
			),
		);
		$settings['stripe'][] = array(
			'id'     => 'live_stripe_publishable_key',
			'name'   => __( 'Live Key', 'wp-user-manager' ),
			'type'   => 'hidden',
			'toggle' => array(
				'key'   => 'stripe_gateway_mode',
				'value' => 'live'
			),
		);
		$settings['stripe'][] = array(
			'id'     => 'live_stripe_secret_key',
			'name'   => __( 'Live Secret', 'wp-user-manager' ),
			'type'   => 'hidden',
			'toggle' => array(
				'key'   => 'stripe_gateway_mode',
				'value' => 'live'
			),
		);

		// TODO add webhook help text and link to doc
		$settings['stripe'][] = array(
			'id'     => 'test_stripe_webhook_secret',
			'name'   => __( 'Test Webhook Signing Secret', 'wp-user-manager' ),
			'type'   => 'text',
			'desc'   => 'Set up a webhook in Stripe to get the webhook signing secret, using all events for this URL:<br><code>' . WebhookEndpoint::get_webhook_url() . '</code>',
			'toggle' => array(
				array(
					'key'   => 'stripe_gateway_mode',
					'value' => 'test'
				),
				array(
					'key'      => 'test_stripe_secret_key',
					'value'    => '',
					'operator' => '==',
				),
			),
		);

		$settings['stripe'][] = array(
			'id'     => 'live_stripe_webhook_secret',
			'name'   => __( 'Live Webhook Signing Secret', 'wp-user-manager' ),
			'type'   => 'text',
			'desc'   => 'Set up a webhook in Stripe to get the webhook signing secret, using all events for this URL:<br><code>' . WebhookEndpoint::get_webhook_url() . '</code>',
			'toggle' => array(
				array(
					'key'   => 'stripe_gateway_mode',
					'value' => 'live'
				),
				array(
					'key'      => 'live_stripe_secret_key',
					'value'    => '',
					'operator' => '==',
				),
			),
		);

		$settings['stripe'][] = array(
			'id'   => 'stripe_connect_account_id',
			'name' => __( 'Stripe ID', 'wp-user-manager' ),
			'type' => 'hidden',
		);

		if ( $this->products && $this->products->totalRecurringProducts() > 1 ) {
			$settings['stripe'][] = array(
				'id'       => 'test_stripe_products',
				'name'     => __( 'Eligible Products', 'wp-user-manager' ),
				'desc'     => sprintf( 'Select the product prices users can subscribe to on the account page. This should be the same as the products defined in the <a target="_blank" href="%s">Stripe Customer Portal Subscription settings</a>.', 'https://dashboard.stripe.com/test/settings/billing/portal' ),
				'type'     => 'multiselect',
				'multiple' => true,
				'options'  => $this->products->get_plans(),
				'toggle'   => array(
					array(
						'key'   => 'stripe_gateway_mode',
						'value' => 'test'
					),
					array(
						'key'      => 'test_stripe_secret_key',
						'value'    => '',
						'operator' => '==',
					),
				),
			);

			$settings['stripe'][] = array(
				'id'       => 'live_stripe_products',
				'name'     => __( 'Eligible Products', 'wp-user-manager' ),
				'desc'     => sprintf( 'Select the product prices users can subscribe to on the account page. This should be the same as the products defined in the <a target="_blank" href="%s">Stripe Customer Portal Subscription settings</a>.', 'https://dashboard.stripe.com/settings/billing/portal' ),
				'type'     => 'multiselect',
				'multiple' => true,
				'options'  => $this->products->get_plans(),
				'toggle'   => array(
					array(
						'key'   => 'stripe_gateway_mode',
						'value' => 'live'
					),
					array(
						'key'      => 'live_stripe_secret_key',
						'value'    => '',
						'operator' => '==',
					),
				),
			);
		}

		return $settings;
	}

	/**
	 * @return string
	 */
	protected function render_admin_disconnect( $mode = 'test' ) {
		$stripe_connect_account_id = wpum_get_option( 'stripe_connect_account_id' );
		if ( ! $stripe_connect_account_id ) {
			return '';
		}

		ob_start();
		include __DIR__ . '/Views/admin-disconnect.php';

		return ob_get_clean();
	}

	public function register_setting_tab( $tabs ) {
		$tabs['stripe'] = __( 'Stripe', 'wp-user-manager' );

		return $tabs;
	}

	public function flush_product_cache() {
		delete_transient( 'wpum_' . $this->connect->get_gateway_mode() . '_stripe_products' );
	}

	public function stripe_connect_account_info_ajax_response() {
		$unknown_error = array(
			'message' => esc_html__( 'Unable to retrieve account information.', 'wp-user-manager' ),
		);

		// Current user can't manage settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			return wp_send_json_error( $unknown_error );
		}

		// Nonce validation, show error on fail.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpum-stripe-connect-account-information' ) ) {
			return wp_send_json_error( $unknown_error );
		}

		$account_id = isset( $_POST['account_id'] ) ? sanitize_text_field( $_POST['account_id'] ) : '';

		$mode = isset( $_POST['gateway_mode'] ) ? sanitize_text_field( $_POST['gateway_mode'] ) : 'test';

		// Provides general reconnect and disconnect action URLs.
		$reconnect_disconnect_actions = sprintf( '<a href="%s">%s</a>', esc_url( $this->connect->disconnect_url( $mode ) ), __( 'Disconnect', 'wp-user-manager' ) );

		// If connecting in Test Mode Stripe gives you the opportunity to create a
		// temporary account. Alert the user of the limitations associated with
		// this type of account.
		$dev_account_error = array(
			'message' => wp_kses(
				wpautop(
					sprintf(
						__(
						/* translators: %1$s Opening bold tag, do not translate. %2$s Closing bold tag, do not translate. */
							'You are currently connected to a %1$stemporary%2$s Stripe test account, which can only be used for testing purposes. You cannot manage this account in Stripe.',
							'wp-user-manager'
						),
						'<strong>',
						'</strong>'
					) . ' ' .
					sprintf(
						__(
						/* translators: %1$s Opening link tag, do not translate. %2$s Closing link tag, do not translate. */
							'%1$sRegister a Stripe account%2$s for full access.',
							'wp-user-manager'
						),
						'<a href="https://dashboard.stripe.com/register" target="_blank" rel="noopener noreferrer">',
						'</a>'
					) . ' ' .
					'<br /><br />' .
					sprintf(
					/* translators: %1$s Opening anchor tag for disconnecting Stripe, do not translate. %2$s Closing anchor tag, do not translate. */
						__( '%1$sDisconnect this account%2$s.', 'wp-user-manager' ),
						'<a href="' . esc_url( $this->connect->disconnect_url( $mode ) ) . '">',
						'</a>'
					)
				),
				array(
					'p'      => true,
					'strong' => true,
					'a'      => array(
						'href'   => true,
						'rel'    => true,
						'target' => true,
					)
				)
			),
			'status' => 'warning',
		);

		$secret = $this->connect->get_stripe_secret( $mode );
		if ( empty ( $secret ) ) {
			return wp_send_json_error( $unknown_error );
		}

		$stripe = new StripeClient( $secret );

		// Attempt to show account information from Stripe Connect account.
		if ( ! empty( $account_id ) ) {
			try {

				$account = $stripe->accounts->retrieve(
					$account_id
				);

				// Find the email.
				$email = isset( $account->email ) ? esc_html( $account->email ) : '';

				// Find a Display Name.
				$display_name = isset( $account->display_name ) ? esc_html( $account->display_name ) : '';

				if (
					empty( $display_name ) &&
					isset( $account->settings ) &&
					isset( $account->settings->dashboard ) &&
					isset( $account->settings->dashboard->display_name )
				) {
					$display_name = esc_html( $account->settings->dashboard->display_name );
				}

				// Unsaved/unactivated accounts do not have an email or display name.
				if ( empty( $email ) && empty( $display_name ) ) {
					return wp_send_json_success( $dev_account_error );
				}

				if ( ! empty( $display_name ) ) {
					$display_name = '<strong>' . $display_name . '</strong> &mdash; ';
				}

				if ( ! empty( $email ) ) {
					$email = $email . ' &mdash; ';
				}

				/**
				 * Filters if the Stripe Connect fee messaging should show.
				 *
				 * @since 2.8.1
				 *
				 * @param bool $show_fee_message Show fee message, or not.
				 */
				$show_fee_message = apply_filters( 'wpum_stripe_show_stripe_connect_fee_message', true );

				$fee_message = true === $show_fee_message  ? '<br>' . esc_html( sprintf( 'Pay as you go pricing: %d%% per-transaction fee + Stripe fees.', self::APPLICATION_FEE_PERCENT ), 'wp-user-manager' )  : '';

				// Return a message with name, email, and reconnect/disconnect actions.
				return wp_send_json_success(
					array(
						'message' => wpautop(
							$display_name . esc_html( $email ) . esc_html__( 'Administrator (Owner)', 'wp-user-manager' ) . $fee_message
						),
						'actions' => $reconnect_disconnect_actions,
						'status'  => 'success',
					)
				);
			} catch ( \WPUM\Stripe\Exception\AuthenticationException $e ) {
				// API keys were changed after using Stripe Connect.
				return wp_send_json_error(
					array(
						'message' => wpautop(
							esc_html__( 'The API keys provided do not match the Stripe Connect account associated with this installation. If you have manually modified these values after connecting your account, please reconnect below or update your API keys.', 'wp-user-manager' ) .
							'<br /><br />' .
							$reconnect_disconnect_actions
						),
					)
				);
			} catch ( \Exception $e ) {
				// General error.
				return wp_send_json_error( $unknown_error );
			}
			// Manual API key management.
		} else {
			$connect_button = sprintf(
				'<a href="%s" class="wpum-stripe-connect"><span>%s</span></a>',
				esc_url( $this->connect->connect_url() ),
				esc_html__( 'Connect with Stripe', 'wp-user-manager' )
			);

			$connect = esc_html__( 'It is highly recommended to Connect with Stripe for easier setup and improved security.', 'wp-user-manager' );

			// See if the keys are valid.
			try {
				$account = $stripe->accounts->retrieve();

				return wp_send_json_success(
					array(
						'message' => wpautop(
							sprintf(
							/* translators: %1$s Stripe payment mode.*/
								__( 'Your manually managed %1$s mode API keys are valid.', 'wp-user-manager' ),
								'<strong>' . $mode . '</strong>'
							) .
							'<br /><br />' .
							$connect . '<br /><br />' . $connect_button
						),
						'status'  => 'success',
					)
				);
				// Show invalid keys.
			} catch ( \Exception $e ) {
				return wp_send_json_error(
					array(
						'message' => wpautop(
							sprintf(
							/* translators: %1$s Stripe payment mode.*/
								__( 'Your manually managed %1$s mode API keys are invalid.', 'wp-user-manager' ),
								'<strong>' . $mode . '</strong>'
							) .
							'<br /><br />' .
							$connect . '<br /><br />' . $connect_button
						),
					)
				);
			}
		}
	}

	/**
	 * @return bool|void
	 */
	public function handle_stripe_connect_disconnect() {
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		if ( 'wpum-settings' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['disconnect'] ) ) {
			return;
		}

		if ( ! isset( $_GET['mode'] ) ) {
			return;
		}

		// Current user cannot handle this request, bail.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'wpum-stripe-connect-disconnect' ) ) {
			return;
		}

		$prefix = $_GET['mode'];

		$options = array(
			$prefix . '_stripe_publishable_key',
			$prefix . '_stripe_secret_key',
			$prefix . '_stripe_webhook_secret',
		);

		foreach ( $options as $option ) {
			wpum_delete_option( $option );
		}

		delete_transient( 'wpum_' . $prefix . '_stripe_products' );

		$redirect = remove_query_arg(
			array(
				'_wpnonce',
				'disconnect',
				'mode',
			)
		);

		return wp_redirect( esc_url_raw( $redirect ) );
	}
}
