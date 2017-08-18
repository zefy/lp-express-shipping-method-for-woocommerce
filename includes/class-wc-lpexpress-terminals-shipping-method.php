<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


class WC_LPExpress_Terminals_Shipping_Method extends WC_Shipping_Method {

	/**
	 * LP Express terminals JSON list URL
	 *
	 * @var string
	 */
	public $terminals_url = 'https://www.lpexpress.lt/index.php?cl=terminals&fnc=getTerminals';

	/**
	 * Terminals list array
	 *
	 * @var mixed
	 */
	public $terminals = false;


	/**
	 * Constructor for your shipping class
	 *
	 * @param int $instance_id Shipping method instance.
	 */
	public function __construct( $instance_id = 0 ) {
		// Add terminal selection dropdown and save it
		add_action( 'woocommerce_review_order_after_shipping',                 array( $this, 'review_order_after_shipping' ) );
		add_action( 'woocommerce_checkout_update_order_meta',                  array( $this, 'checkout_save_order_terminal_id_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_review',                array( $this, 'checkout_save_session_terminal_id' ), 10, 1 );

		// Checkout validation
		add_action( 'woocommerce_after_checkout_validation',                   array( $this, 'validate_user_selected_terminal' ), 10, 1 );

		// Show selected terminal in order and emails
		add_action( 'woocommerce_order_details_after_order_table',             array( $this, 'show_selected_terminal' ), 10, 1 );
		add_action( 'woocommerce_email_after_order_table',                     array( $this, 'show_selected_terminal' ), 15, 1 );

		// Show selected terminal in admin order review
		if( is_admin() ) {
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'show_selected_terminal' ), 20 );
		}

		// Meta and input field name
		$this->id                 = 'lpexpress_terminals';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( '"LP Express" parcel terminals', 'lp-express-shipping-method-for-woocommerce' );
		$this->method_description = __( 'Shipping to "LP Express" self-service parcel terminals', 'lp-express-shipping-method-for-woocommerce' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->field_name = apply_filters( 'wc_shipping_'. $this->id .'_terminals_field_name', 'wc_'. $this->id .'_info' );

		$this->init();

	}

	/**
	 * Initialize LP Express terminals
	 *
	 * @return void
	 */
	public function init() {
		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title            = $this->get_option( 'title' );
		$this->tax_status       = $this->get_option( 'tax_status', 'none' );
		$this->cost             = $this->get_option( 'cost', 0 );
		$this->free_shipping    = $this->get_option( 'free_shipping', 0 );

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Define settings field for this shipping method
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title' => array(
				'title'         => __( 'Title', 'lp-express-shipping-method-for-woocommerce' ),
				'type'          => 'text',
				'description'   => __( 'Title to be display on site', 'lp-express-shipping-method-for-woocommerce' ),
				'default'       => __( '"LP Express" parcel terminals', 'lp-express-shipping-method-for-woocommerce' ),
				'desc_tip'      => true,
			),

			'cost' => array(
				'title'         => __( 'Cost', 'lp-express-shipping-method-for-woocommerce' ),
				'type'          => 'number',
				'description'   => __( 'Shipping price', 'lp-express-shipping-method-for-woocommerce' ),
				'placeholder'   => '0',
				'default'       => 0,
				'desc_tip'      => true,
			),

			'free_shipping'     => array(
				'title'         => __( 'Free shipping', 'lp-express-shipping-method-for-woocommerce' ),
				'type'          => 'number',
				'description'   => __( 'Free shipping if price greater than (0 - turned off)', 'lp-express-shipping-method-for-woocommerce' ),
				'placeholder'   => '0',
				'default'       => 0,
				'desc_tip'      => true,
			),

			'tax_status' => array(
				'title'         => __( 'Tax status', 'lp-express-shipping-method-for-woocommerce' ),
				'type'          => 'select',
				'class'         => 'wc-enhanced-select',
				'default'       => 'none',
				'options'       => array(
					'taxable'   => __( 'Taxable', 'lp-express-shipping-method-for-woocommerce' ),
					'none'      => _x( 'None', 'Tax status', 'lp-express-shipping-method-for-woocommerce' ),
				),
			),

		);

	}

	/**
	 * Fetches locations and stores them to cache
	 *
	 * @return array Terminals
	 */
	public function get_terminals( ) {
		// Fetch terminals from cache
		$terminals_cache = $this->get_terminals_cache();

		if( $terminals_cache !== null ) {
			return $terminals_cache;
		}

		$terminals_json  = file_get_contents( $this->terminals_url );
		$terminals_json  = json_decode( $terminals_json );

		$locations       = array();

		foreach( $terminals_json as $key => $location ) {
			if( $location->nfqactive == 1 ) {
				$locations[$location->city][] = (object) array(
					'place_id'   => $location->oxid,
					'zipcode'    => $location->zip,
					'name'       => $location->name,
					'city'       => $location->city,
					'address'    => $location->address,
					'comment'    => $location->comment
				);
			}
		}

		// Save cache
		$this->save_terminals_cache( $locations );

		return $locations;
	}

	/**
	 * Fetch terminals cache
	 *
	 * @return array Terminals
	 */
	public function get_terminals_cache() {
		// Check if terminals are already loaded
		if( $this->terminals !== FALSE ) {
			return $this->terminals;
		}

		// Fetch transient cache
		$terminals_transient = get_transient( $this->id . '_cache' );

		// Check if terminals transient exists
		if ( $terminals_transient ) {
			// Return cached terminals
			return $terminals_transient;
		}
		else {
			return NULL;
		}
	}

	/**
	 * Save terminals to cache (transient)
	 *
	 * @param  array $terminals Terminals array
	 *
	 * @return void
	 */
	public function save_terminals_cache( $terminals ) {
		// Save terminals to "cache" for 24 hours
		set_transient( $this->id . '_cache', $terminals, 86400 );
	}

	/**
	 * This function is used to calculate the shipping cost
	 * We will make it free if possible.
	 *
	 * @access public
	 * @uses WC_Shipping_Method::add_rate()
	 * @param mixed $package
	 *
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		$rate = array(
			'id' => $this->id,
			'label' => $this->title,
			'cost' => $this->cost,
			'taxes' => TRUE
		);

		if( $this->free_shipping > 0 && $package['contents_cost'] >= $this->free_shipping) {
			$rate['cost'] = 0;
		}

		if( $this->tax_status == 'none' ) {
			$rate['taxes'] = FALSE;
		}

		// Register the rate
		$this->add_rate( $rate );
	}

	/**
	 * Adds dropdown selection of terminals right after shipping in checkout
	 *
	 * @return void
	 */
	function review_order_after_shipping() {
		// Ignore instances created by shipping zones due to duplications
		if( $this->instance_id ) {
			return;
		}

		// Hide terminals when no other shipping methods available and this method was last selected
		if( ! $this->is_shipping_method_available() ) {
			return;
		}

		// Get currently selected shipping methods
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		// Check if ours is one of the selected methods
		if( ! empty( $chosen_shipping_methods ) && in_array( $this->id, $chosen_shipping_methods ) ) {
			// Get selected terminal
			$selected_terminal   = WC()->session->get( $this->id );

			// Set data for terminals template
			$template_data = array(
				'terminals'  => $this->get_terminals(),
				'field_name' => $this->field_name,
				'field_id'   => $this->id,
				'selected'   => $selected_terminal ? $selected_terminal : ''
			);

			// Allow to do some activity before terminals
			do_action( $this->id . '_before_terminals' );

			// Get terminals template
			wc_get_template( 'checkout/'. $this->id .'.php', $template_data );

			// Allow to do some activity after terminals
			do_action( $this->id . '_after_terminals' );
		}
	}

	/**
	 * Checking if current shipping method available for cart
	 *
	 * @return bool
	 */
	public function is_shipping_method_available() {
		$packages = WC()->shipping()->get_packages();
		foreach ( $packages as $i => $package ) {
			if( isset( $package['rates'][$this->id] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validates user submitted terminal
	 *
	 * @param  array $posted Checkout data
	 *
	 * @return void
	 */
	public function validate_user_selected_terminal( $posted ) {
		// Preferred error text
		$error = __( 'Please select a parcel terminal', 'lp-express-shipping-method-for-woocommerce' );

		// Get currently selected shipping methods
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		// Check if this shipping method is selected
		if( ! empty( $chosen_shipping_methods ) && ! in_array( $this->id, $chosen_shipping_methods ) ) {
			// Stop validation if it isn't
			return;
		}

		// Be sure shipping method was posted
		if( ! isset( $posted['shipping_method'] ) || ! is_array( $posted['shipping_method'] ) ) {
			wc_add_notice( $error, 'error' );
			return;
		}

		// Check if it's correct shipping method
		if( ! in_array( $this->id, $posted['shipping_method'] ) ) {
			wc_add_notice( $error, 'error' );
			return;
		}

		// Check if our field was submitted
		if( ! isset( $_POST[ $this->field_name ] ) || '' == $_POST[ $this->field_name ] || NULL == $_POST[ $this->field_name ] ) {
			wc_add_notice( $error, 'error' );
			return;
		}

		// Finally check if this terminal id exists
		if( NULL == $this->get_terminal_info( $_POST[ $this->field_name ] ) ) {
			wc_add_notice( $error, 'error' );
			return;
		}
	}

	/**
	 * Saves selected terminal to order meta
	 *
	 * @param  integer $order_id Order ID
	 * @param  array   $posted   WooCommerce posted data
	 *
	 * @return void
	 */
	public function checkout_save_order_terminal_id_meta( $order_id, $posted ) {
		if( isset( $_POST[ $this->field_name ] ) ) {
			update_post_meta( $order_id, $this->field_name, $_POST[ $this->field_name ] );
		}
	}

	/**
	 * Saves selected terminal in session whilst order review updates
	 *
	 * @param  string $post_data Posted data
	 *
	 * @return void
	 */
	public function checkout_save_session_terminal_id( $post_data ) {
		parse_str( $post_data, $posted );

		if( isset( $posted[ $this->field_name ] ) ) {
			WC()->session->set( $this->field_name, $posted[ $this->field_name ] );
		}
	}

	/**
	 * Get selected terminal ID from order meta
	 *
	 * @param  integer $order_id Order ID
	 *
	 * @return integer           Selected terminal ID
	 */
	public function get_order_terminal_id( $order_id ) {
		return (int) get_post_meta( $order_id, $this->field_name, TRUE );
	}

	/**
	 * Returns terminal info by it's ID
	 *
	 * @TODO: Possibility to change terminal name formatting by admin
	 *
	 * @param  integer $place_id Terminal ID
	 *
	 * @return object            Terminal's info
	 */
	public function get_terminal_info( $place_id ) {
		$terminals = $this->get_terminals();

		foreach( $terminals as $terminal_group ) {
			foreach( $terminal_group as $terminal ) {
				if ( intval( $terminal->place_id ) === intval( $place_id ) ) {
					return $terminal;
				}
			}
		}

		return NULL;
	}

	/**
	 * Outputs user selected LP EXPRESS terminal in different locations (admin screen, email, orders)
	 *
	 * @param  mixed $order Order (ID or WC_Order)
	 *
	 * @return void
	 */
	public function show_selected_terminal( $order ) {
		// Create order instance if needed
		if( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		// Store order ID
		$this->order_id = $order->get_id();

		// Check if the order has our shipping method
		if( $order->has_shipping_method( $this->id ) ) {
			// Fetch selected terminal ID
			$terminal_id    = $this->get_order_terminal_id( $this->order_id );
			$terminal       = $this->get_terminal_info( $terminal_id );

			$template_data  = (array) $terminal;

			// Output selected terminal
			if( current_filter() == 'woocommerce_order_details_after_order_table' ) {
				wc_get_template( 'order/'. $this->id .'.php', $template_data );
			} elseif( current_filter() == 'woocommerce_email_after_order_table' ) {
				// Prevent duplication when instance created by shipping zones
				if( $this->instance_id > 0 ) {
					return;
				}

				wc_get_template( 'email/'. $this->id .'.php', $template_data );
			} else {
				wc_get_template( 'order/admin_'. $this->id .'.php', $template_data );
			}
		}
	}
}