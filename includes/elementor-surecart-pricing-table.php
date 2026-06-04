<?php
/**
 * Elementor SureCart pricing table widget.
 *
 * @package BlueWorxEnhancements
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether Elementor and SureCart are ready for this widget.
 *
 * @return bool
 */
function blueworx_surecart_pricing_table_is_ready() {
	return class_exists( '\Elementor\Widget_Base' ) && post_type_exists( 'sc_product' ) && function_exists( 'sc_get_product' );
}

/**
 * Registers frontend assets used by the widget.
 *
 * @return void
 */
function blueworx_register_surecart_pricing_table_assets() {
	wp_register_style(
		'blueworx-surecart-pricing-table',
		BLUEWORX_ENHANCEMENTS_URL . 'assets/css/surecart-pricing-table.css',
		array(),
		blueworx_get_admin_asset_version( 'assets/css/surecart-pricing-table.css' )
	);

	wp_register_script(
		'blueworx-surecart-pricing-table',
		BLUEWORX_ENHANCEMENTS_URL . 'assets/js/surecart-pricing-table.js',
		array(),
		blueworx_get_admin_asset_version( 'assets/js/surecart-pricing-table.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'blueworx_register_surecart_pricing_table_assets' );

/**
 * Registers the Elementor widget.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 * @return void
 */
function blueworx_register_surecart_pricing_table_widget( $widgets_manager ) {
	if ( ! blueworx_surecart_pricing_table_is_ready() ) {
		return;
	}

	if ( ! class_exists( 'BlueWorx_SureCart_Pricing_Table_Widget' ) ) {
		/**
		 * SureCart pricing table widget.
		 */
		class BlueWorx_SureCart_Pricing_Table_Widget extends \Elementor\Widget_Base {

			/**
			 * Gets widget name.
			 *
			 * @return string
			 */
			public function get_name() {
				return 'blueworx_surecart_pricing_table';
			}

			/**
			 * Gets widget title.
			 *
			 * @return string
			 */
			public function get_title() {
				return __( 'SureCart Pricing Table', 'blueworx-enhancements' );
			}

			/**
			 * Gets widget icon.
			 *
			 * @return string
			 */
			public function get_icon() {
				return 'eicon-price-table';
			}

			/**
			 * Gets Elementor categories.
			 *
			 * @return array
			 */
			public function get_categories() {
				return array( 'general' );
			}

			/**
			 * Gets widget keywords.
			 *
			 * @return array
			 */
			public function get_keywords() {
				return array( 'surecart', 'pricing', 'products', 'blueworx' );
			}

			/**
			 * Gets widget style dependencies.
			 *
			 * @return array
			 */
			public function get_style_depends() {
				return array( 'blueworx-surecart-pricing-table' );
			}

			/**
			 * Gets widget script dependencies.
			 *
			 * @return array
			 */
			public function get_script_depends() {
				return array( 'blueworx-surecart-pricing-table' );
			}

			/**
			 * Registers Elementor controls.
			 *
			 * @return void
			 */
			protected function register_controls() {
				$this->start_controls_section(
					'content_section',
					array(
						'label' => __( 'Pricing Table', 'blueworx-enhancements' ),
						'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
					)
				);

				$this->add_control(
					'product_source',
					array(
						'label'   => __( 'Products', 'blueworx-enhancements' ),
						'type'    => \Elementor\Controls_Manager::SELECT,
						'default' => 'all',
						'options' => array(
							'all'      => __( 'All live products', 'blueworx-enhancements' ),
							'selected' => __( 'Selected products', 'blueworx-enhancements' ),
						),
					)
				);

				$this->add_control(
					'product_ids',
					array(
						'label'     => __( 'Choose products', 'blueworx-enhancements' ),
						'type'      => \Elementor\Controls_Manager::SELECT2,
						'multiple'  => true,
						'options'   => $this->get_product_options(),
						'condition' => array(
							'product_source' => 'selected',
						),
					)
				);

				$this->add_control(
					'price_filter',
					array(
						'label'   => __( 'Pricing', 'blueworx-enhancements' ),
						'type'    => \Elementor\Controls_Manager::SELECT,
						'default' => 'switch',
						'options' => array(
							'switch'   => __( 'Monthly / Yearly switch', 'blueworx-enhancements' ),
							'month'    => __( 'Monthly only', 'blueworx-enhancements' ),
							'year'     => __( 'Yearly only', 'blueworx-enhancements' ),
							'one_time' => __( 'One-time only', 'blueworx-enhancements' ),
						),
					)
				);

				$this->add_control(
					'button_text',
					array(
						'label'   => __( 'Button text', 'blueworx-enhancements' ),
						'type'    => \Elementor\Controls_Manager::TEXT,
						'default' => __( 'Buy Now', 'blueworx-enhancements' ),
					)
				);

				$this->add_control(
					'columns',
					array(
						'label'   => __( 'Columns', 'blueworx-enhancements' ),
						'type'    => \Elementor\Controls_Manager::SELECT,
						'default' => '3',
						'options' => array(
							'1' => __( '1', 'blueworx-enhancements' ),
							'2' => __( '2', 'blueworx-enhancements' ),
							'3' => __( '3', 'blueworx-enhancements' ),
							'4' => __( '4', 'blueworx-enhancements' ),
						),
					)
				);

				$this->add_control(
					'show_image',
					array(
						'label'        => __( 'Show image', 'blueworx-enhancements' ),
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'label_on'     => __( 'Yes', 'blueworx-enhancements' ),
						'label_off'    => __( 'No', 'blueworx-enhancements' ),
						'return_value' => 'yes',
						'default'      => 'yes',
					)
				);

				$this->add_control(
					'show_description',
					array(
						'label'        => __( 'Show description', 'blueworx-enhancements' ),
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'label_on'     => __( 'Yes', 'blueworx-enhancements' ),
						'label_off'    => __( 'No', 'blueworx-enhancements' ),
						'return_value' => 'yes',
						'default'      => 'yes',
					)
				);

				$this->end_controls_section();
			}

			/**
			 * Renders the widget.
			 *
			 * @return void
			 */
			protected function render() {
				$settings     = $this->get_settings_for_display();
				$price_filter = isset( $settings['price_filter'] ) ? sanitize_key( $settings['price_filter'] ) : 'switch';
				$items        = $this->get_pricing_items( $settings );
				$columns      = isset( $settings['columns'] ) ? absint( $settings['columns'] ) : 3;
				$columns      = max( 1, min( 4, $columns ) );
				$button_text  = ! empty( $settings['button_text'] ) ? $settings['button_text'] : __( 'Buy Now', 'blueworx-enhancements' );
				$default_cycle = $this->get_default_cycle( $items, $price_filter );

				if ( empty( $items ) ) {
					echo '<div class="blueworx-surecart-pricing-empty">' . esc_html__( 'No matching SureCart products were found.', 'blueworx-enhancements' ) . '</div>';
					return;
				}

				?>
				<div class="blueworx-surecart-pricing-table blueworx-surecart-pricing-columns-<?php echo esc_attr( (string) $columns ); ?>" data-default-cycle="<?php echo esc_attr( $default_cycle ); ?>">
					<?php if ( 'switch' === $price_filter ) : ?>
						<div class="blueworx-surecart-pricing-toggle" role="group" aria-label="<?php echo esc_attr__( 'Choose billing period', 'blueworx-enhancements' ); ?>">
							<button type="button" class="<?php echo esc_attr( 'month' === $default_cycle ? 'is-active' : '' ); ?>" data-cycle="month"><?php echo esc_html__( 'Monthly', 'blueworx-enhancements' ); ?></button>
							<button type="button" class="<?php echo esc_attr( 'year' === $default_cycle ? 'is-active' : '' ); ?>" data-cycle="year"><?php echo esc_html__( 'Yearly', 'blueworx-enhancements' ); ?></button>
						</div>
					<?php endif; ?>

					<div class="blueworx-surecart-pricing-grid">
						<?php foreach ( $items as $item ) : ?>
							<?php $active_price = $this->get_initial_price( $item['prices'], $default_cycle ); ?>
							<div class="blueworx-surecart-pricing-card" data-prices="<?php echo esc_attr( wp_json_encode( $item['prices'] ) ); ?>">
								<?php if ( ! empty( $item['image'] ) && 'yes' === $settings['show_image'] ) : ?>
									<div class="blueworx-surecart-pricing-image">
										<?php echo wp_kses_post( $item['image'] ); ?>
									</div>
								<?php endif; ?>

								<div class="blueworx-surecart-pricing-body">
									<h3><?php echo esc_html( $item['title'] ); ?></h3>

									<?php if ( ! empty( $item['description'] ) && 'yes' === $settings['show_description'] ) : ?>
										<p class="blueworx-surecart-pricing-description"><?php echo esc_html( $item['description'] ); ?></p>
									<?php endif; ?>

									<div class="blueworx-surecart-pricing-price"><?php echo esc_html( $active_price['label'] ); ?></div>

									<a class="blueworx-surecart-pricing-button" href="<?php echo esc_url( $active_price['url'] ); ?>">
										<?php echo esc_html( $button_text ); ?>
									</a>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
			}

			/**
			 * Gets product options for Elementor controls.
			 *
			 * @return array
			 */
			private function get_product_options() {
				$options = array();
				$posts   = get_posts(
					array(
						'post_type'      => 'sc_product',
						'post_status'    => 'publish',
						'posts_per_page' => 100,
						'orderby'        => 'title',
						'order'          => 'ASC',
						'fields'         => 'ids',
					)
				);

				foreach ( $posts as $post_id ) {
					$options[ $post_id ] = get_the_title( $post_id );
				}

				return $options;
			}

			/**
			 * Gets products and prices for rendering.
			 *
			 * @param array $settings Widget settings.
			 * @return array
			 */
			private function get_pricing_items( $settings ) {
				$query_args = array(
					'post_type'      => 'sc_product',
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
				);

				if ( ! empty( $settings['product_source'] ) && 'selected' === $settings['product_source'] && ! empty( $settings['product_ids'] ) ) {
					$query_args['post__in'] = array_map( 'absint', (array) $settings['product_ids'] );
					$query_args['orderby']  = 'post__in';
				} elseif ( ! empty( $settings['product_source'] ) && 'selected' === $settings['product_source'] ) {
					return $items;
				}

				$products     = new \WP_Query( $query_args );
				$items        = array();
				$price_filter = ! empty( $settings['price_filter'] ) ? sanitize_key( $settings['price_filter'] ) : 'switch';

				if ( ! $products->have_posts() ) {
					return $items;
				}

				while ( $products->have_posts() ) {
					$products->the_post();

					$post    = get_post();
					$product = sc_get_product( $post );
					$prices  = $this->get_matching_prices( $product, $price_filter );

					if ( empty( $prices ) ) {
						continue;
					}

					$items[] = array(
						'title'       => get_the_title(),
						'description' => $this->get_product_description( $product ),
						'image'       => get_the_post_thumbnail( get_the_ID(), 'medium_large' ),
						'prices'      => $prices,
					);
				}

				wp_reset_postdata();

				return $items;
			}

			/**
			 * Gets the best product description for the table.
			 *
			 * @param object $product SureCart product.
			 * @return string
			 */
			private function get_product_description( $product ) {
				$description = wp_strip_all_tags( get_the_excerpt() );

				if ( empty( $description ) ) {
					$description = wp_strip_all_tags( (string) $this->get_value( $product, 'description' ) );
				}

				return $description;
			}

			/**
			 * Gets prices that match the widget setting.
			 *
			 * @param object $product SureCart product.
			 * @param string $price_filter Price filter.
			 * @return array
			 */
			private function get_matching_prices( $product, $price_filter ) {
				$prices  = $this->normalize_prices( $this->get_product_prices( $product ) );
				$matches = array();

				foreach ( $prices as $price ) {
					$cycle = $this->get_price_cycle( $price );

					if ( 'switch' === $price_filter && ! in_array( $cycle, array( 'month', 'year' ), true ) ) {
						continue;
					}

					if ( 'switch' !== $price_filter && $cycle !== $price_filter ) {
						continue;
					}

					if ( isset( $matches[ $cycle ] ) ) {
						continue;
					}

					$matches[ $cycle ] = array(
						'label' => $this->format_price_label( $price ),
						'url'   => $this->get_checkout_url( $price['id'] ),
					);
				}

				return $matches;
			}

			/**
			 * Gets prices from the product object, with API fallback.
			 *
			 * @param object $product SureCart product.
			 * @return array
			 */
			private function get_product_prices( $product ) {
				$prices = array();

				foreach ( array( 'prices', 'current_prices' ) as $property ) {
					$value = $this->get_value( $product, $property );

					if ( ! empty( $value ) ) {
						$prices = array_merge( $prices, $this->collection_to_array( $value ) );
					}
				}

				$product_id = $this->get_value( $product, 'id' );

				if ( empty( $product_id ) || ! class_exists( '\SureCart\Models\Price' ) ) {
					return $this->unique_prices( $prices );
				}

				$cache_key = 'blueworx_surecart_prices_v2_' . md5( $product_id );
				$cached    = get_transient( $cache_key );

				if ( false === $cached ) {
					$cached = $this->get_api_prices( $product_id );
					set_transient( $cache_key, $cached, 10 * MINUTE_IN_SECONDS );
				}

				if ( is_array( $cached ) ) {
					$prices = array_merge( $prices, $cached );
				}

				return $this->unique_prices( $prices );
			}

			/**
			 * Gets all product prices from the SureCart API.
			 *
			 * @param string $product_id SureCart product ID.
			 * @return array
			 */
			private function get_api_prices( $product_id ) {
				$queries = array(
					array(
						'product_ids' => array( $product_id ),
						'archived'    => false,
					),
					array(
						'product_id' => $product_id,
						'archived'   => false,
					),
					array(
						'product'  => $product_id,
						'archived' => false,
					),
				);

				foreach ( $queries as $query ) {
					try {
						$prices = \SureCart\Models\Price::where( $query )->get();
					} catch ( \Throwable $error ) {
						$prices = array();
					}

					$prices = $this->collection_to_array( $prices );

					if ( ! empty( $prices ) ) {
						return $prices;
					}
				}

				return array();
			}

			/**
			 * Removes duplicate price objects.
			 *
			 * @param array $prices SureCart prices.
			 * @return array
			 */
			private function unique_prices( $prices ) {
				$unique = array();

				foreach ( $prices as $price ) {
					$id = $this->get_value( $price, 'id' );

					if ( empty( $id ) || isset( $unique[ $id ] ) ) {
						continue;
					}

					$unique[ $id ] = $price;
				}

				return array_values( $unique );
			}

			/**
			 * Normalizes price objects.
			 *
			 * @param array $prices SureCart prices.
			 * @return array
			 */
			private function normalize_prices( $prices ) {
				$normalized = array();

				foreach ( $prices as $price ) {
					$id = $this->get_value( $price, 'id' );

					if ( empty( $id ) ) {
						continue;
					}

					if ( $this->get_value( $price, 'archived' ) || $this->get_value( $price, 'discarded_at' ) ) {
						continue;
					}

					if ( false === $this->get_value( $price, 'current_version' ) ) {
						continue;
					}

					$normalized[] = array(
						'id'                       => $id,
						'amount'                   => $this->get_value( $price, 'amount' ),
						'currency'                 => $this->get_value( $price, 'currency' ),
						'display_amount'           => $this->get_value( $price, 'display_amount' ),
						'recurring_interval'       => $this->get_recurring_value( $price, 'interval' ),
						'recurring_interval_count' => $this->get_recurring_value( $price, 'interval_count' ),
					);
				}

				return $normalized;
			}

			/**
			 * Gets recurring values from common SureCart price shapes.
			 *
			 * @param mixed  $price Price data.
			 * @param string $key Recurring key.
			 * @return mixed
			 */
			private function get_recurring_value( $price, $key ) {
				$direct_key = 'interval' === $key ? 'recurring_interval' : 'recurring_interval_count';
				$value      = $this->get_value( $price, $direct_key );

				if ( null !== $value ) {
					return $value;
				}

				$value = $this->get_value( $price, $key );

				if ( null !== $value ) {
					return $value;
				}

				$recurring = $this->get_value( $price, 'recurring' );

				if ( null !== $recurring ) {
					return $this->get_value( $recurring, $key );
				}

				return null;
			}

			/**
			 * Gets a price cycle key.
			 *
			 * @param array $price Price data.
			 * @return string
			 */
			private function get_price_cycle( $price ) {
				$interval = ! empty( $price['recurring_interval'] ) ? sanitize_key( $price['recurring_interval'] ) : '';
				$count    = ! empty( $price['recurring_interval_count'] ) ? absint( $price['recurring_interval_count'] ) : 1;

				if ( in_array( $interval, array( 'month', 'monthly', 'months' ), true ) && 1 === $count ) {
					return 'month';
				}

				if ( in_array( $interval, array( 'year', 'yearly', 'years' ), true ) && 1 === $count ) {
					return 'year';
				}

				if ( empty( $interval ) ) {
					return 'one_time';
				}

				return $interval;
			}

			/**
			 * Formats a price label.
			 *
			 * @param array $price Price data.
			 * @return string
			 */
			private function format_price_label( $price ) {
				$label = ! empty( $price['display_amount'] ) ? $price['display_amount'] : $this->format_amount( $price );
				$cycle = $this->get_price_cycle( $price );

				if ( 'month' === $cycle ) {
					return sprintf( '%s / month', $label );
				}

				if ( 'year' === $cycle ) {
					return sprintf( '%s / year', $label );
				}

				return $label;
			}

			/**
			 * Formats an amount when SureCart has not provided display text.
			 *
			 * @param array $price Price data.
			 * @return string
			 */
			private function format_amount( $price ) {
				$amount   = isset( $price['amount'] ) ? (float) $price['amount'] / 100 : 0;
				$currency = ! empty( $price['currency'] ) ? strtolower( $price['currency'] ) : '';
				$symbols  = array(
					'usd' => '$',
					'gbp' => 'GBP ',
					'eur' => 'EUR ',
				);
				$prefix   = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : strtoupper( $currency ) . ' ';

				return $prefix . number_format_i18n( $amount, 2 );
			}

			/**
			 * Gets checkout URL for a SureCart price.
			 *
			 * @param string $price_id SureCart price ID.
			 * @return string
			 */
			private function get_checkout_url( $price_id ) {
				if ( empty( $price_id ) || ! class_exists( '\SureCart' ) || ! is_callable( array( '\SureCart', 'pages' ) ) ) {
					return '#';
				}

				try {
					$checkout_url = \SureCart::pages()->url( 'checkout' );
				} catch ( \Throwable $error ) {
					$checkout_url = '';
				}

				if ( empty( $checkout_url ) ) {
					return '#';
				}

				return add_query_arg(
					array(
						'line_items' => array(
							array(
								'price_id' => $price_id,
								'quantity' => 1,
							),
						),
					),
					$checkout_url
				);
			}

			/**
			 * Gets initial price for server output.
			 *
			 * @param array $prices Price options.
			 * @param string $price_filter Price filter.
			 * @return array
			 */
			private function get_initial_price( $prices, $default_cycle ) {
				if ( isset( $prices[ $default_cycle ] ) ) {
					return $prices[ $default_cycle ];
				}

				return reset( $prices );
			}

			/**
			 * Gets the table's starting billing cycle.
			 *
			 * @param array  $items Pricing table items.
			 * @param string $price_filter Price filter.
			 * @return string
			 */
			private function get_default_cycle( $items, $price_filter ) {
				if ( 'year' === $price_filter ) {
					return 'year';
				}

				if ( 'switch' === $price_filter && ! $this->items_have_cycle( $items, 'month' ) && $this->items_have_cycle( $items, 'year' ) ) {
					return 'year';
				}

				return 'month';
			}

			/**
			 * Checks whether any item has a billing cycle.
			 *
			 * @param array  $items Pricing table items.
			 * @param string $cycle Billing cycle.
			 * @return bool
			 */
			private function items_have_cycle( $items, $cycle ) {
				foreach ( $items as $item ) {
					if ( isset( $item['prices'][ $cycle ] ) ) {
						return true;
					}
				}

				return false;
			}

			/**
			 * Converts common collection shapes to arrays.
			 *
			 * @param mixed $collection Collection.
			 * @return array
			 */
			private function collection_to_array( $collection ) {
				if ( is_array( $collection ) ) {
					return $collection;
				}

				if ( $collection instanceof \Traversable ) {
					return iterator_to_array( $collection );
				}

				if ( is_object( $collection ) && isset( $collection->data ) ) {
					return $this->collection_to_array( $collection->data );
				}

				return array();
			}

			/**
			 * Reads values from arrays or objects.
			 *
			 * @param mixed $source Source data.
			 * @param string $key Data key.
			 * @return mixed
			 */
			private function get_value( $source, $key ) {
				if ( is_array( $source ) && array_key_exists( $key, $source ) ) {
					return $source[ $key ];
				}

				if ( is_object( $source ) && isset( $source->{$key} ) ) {
					return $source->{$key};
				}

				if ( $source instanceof \ArrayAccess && isset( $source[ $key ] ) ) {
					return $source[ $key ];
				}

				return null;
			}
		}
	}

	$widgets_manager->register( new \BlueWorx_SureCart_Pricing_Table_Widget() );
}
add_action( 'elementor/widgets/register', 'blueworx_register_surecart_pricing_table_widget' );
