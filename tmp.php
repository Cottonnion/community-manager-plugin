<?php
/**
 * Astra Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra Child
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ASTRA_CHILD_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {
	wp_enqueue_style( 'astra-child-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_ASTRA_CHILD_VERSION, 'all' );
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

// We will change the logo.
function my_login_logo() { ?>
	<style>
		#login h1 a, .login h1 a {
			background-image: url(<?php echo esc_url( wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' )[0] ); ?>);
			height:200px;
			width:200px;
			background-size: 200px 200px;
			background-repeat: no-repeat;          			
			text-indent: 0px;
			color: #1e2657;
			margin-bottom: 0px;
			font-size: 0;
		}
	</style>
<?php }
add_action( 'login_enqueue_scripts', 'my_login_logo' );

function jh_block_wp_admin() {
    if ( is_admin() && ! current_user_can( 'administrator' ) && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		$url = wc_get_page_permalink( 'myaccount' );
		wp_safe_redirect( $url );
        exit;
    }
}
add_action( 'admin_init', 'jh_block_wp_admin' );

function custom_query_vars_filter( $vars ){
	$vars[] = 'ref';
	return $vars;
}
add_filter( 'query_vars', 'custom_query_vars_filter' );

add_filter( 'auto_update_plugin', '__return_false' );
add_filter( 'auto_update_theme', '__return_false' );

function my_admin_bar_render() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
}
add_action( 'wp_before_admin_bar_render', 'my_admin_bar_render' );

// This is the feed for the main course product page. It draws from an ACF repeater field in the product.
// The number of sections is determined by the number given in the repeater.
function jh_return_product_feed(){
	$this_state_name = get_query_var( 'course_state' );	
	$state = get_term_by( 'name', $this_state_name, 'course_state' );
	$acf_post_id = 'course_state_' . $state->term_id;
	$sections = get_field( 'course_sections', $acf_post_id );
	$return_string = "";
	
	// We need to provide an anchor link to the bottom section from the bottom of each preceding section
	$last_section = count( $sections ) - 1;
	foreach( $sections as $this_section ){
		// First build the headings
		$return_string .= "<div class='header_anchor' id='" . $this_section['header_anchor'] . "'></div>";
		$return_string .= "<div class='header-wrapper'>";
		$return_string .= "<h2 class='main-collection-header'>" . $this_section['header_name'] . "</h2>";
		$return_string .= "</div>";
		if( $this_section['course_sub_sections'] ){
			foreach( $this_section['course_sub_sections'] as $this_sub_section ){
				if( $this_sub_section['sub_section_header'] ){
					// Now we build the query and loop through each resulting product.
					$args = array(
						'post_type'             => 'product',
						'post_status'           => 'publish',
						'posts_per_page'        => -1,
						'tax_query'             => array(
							'relation'			=> 'AND',
							array(
								'taxonomy'		=> 'course_state',
								'field'			=> 'slug',
								'terms'			=> array( $this_state_name ),
							),
							array(
								'relation'		=> 'OR',
								array(
									'operator'		=> 'IN',
									'taxonomy'      => 'license_holder',
									'field'         => 'term_id',
									'terms'         => $this_sub_section['sub_section_licenses'],
								),
								array(
									'operator'		=> 'NOT IN',
									'taxonomy'      => 'license_holder',
									'field'         => 'term_id',
								),
							),
							array(
								'relation'		=> 'OR',
								array(
									'operator'		=> 'IN',
									'taxonomy'      => 'product_cat',
									'field'         => 'term_id',
									'terms'         => $this_sub_section['sub_section_categories'],
								),
								array(
									'operator'		=> 'NOT IN',
									'taxonomy'      => 'product_cat',
									'field'         => 'term_id',
								),
							),
						),
						'orderby'				=> array(
							'menu_order'		=> 'DESC',
							'title'				=> 'ASC',
						),
					);
					$query = new WP_Query( $args );	
					$return_string .= "<div class='sub-header-wrapper'>";
					$return_string .= "<h2 class='sub-collection-header'>" . $this_sub_section['sub_section_header'] . " (" . $query->found_posts . ")</h2>";
					$return_string .= "</div>";
					if( $query->have_posts() ){
						while( $query->have_posts() ){
							$query->the_post();
							$this_id = get_the_ID();
							
							$course_id = get_post_meta( $this_id, '_related_course', true ); // Hours may vary by state. This field is stored in the COURSE not the product.
							$this_state = $this_state_name;
							$all_state_info = get_field( 'course_info_by_state', $course_id[0] );
							if( is_array($all_state_info) ){
								foreach( $all_state_info as $this_state_info ){
									$state_slug = $this_state_info['course_state']->slug;
									if( $state_slug == $this_state ){
										$this_hours = $this_state_info['state_ce_hours'];
									}
								}
							} else {
								$this_hours = get_post_meta( $this_id, 'hours', true );							
							}
				
							$return_string .= "<div class='product-wrapper'>";

							$product_url = get_post_permalink() . "?state=" . $this_state_name . "&ref=products";
							$return_string .= "<div class='product-title'><a href='" . $product_url . "'>" . get_the_title() . "</a></div>";

							$return_string .= "<div class='product-hours'>" . $this_hours . " Hours</div>";
							$this_product = wc_get_product( $this_id );
// 							$add_to_cart_url = $this_product->add_to_cart_url();
							$add_to_cart_url = jh_get_add_to_cart_url( $this_id );
							$product_price = $this_product->get_price();

							$return_string .= "<div class='product-buy'>";
							$return_string .= "<div class='product-price'>$" . $product_price . "</div>";
				
// 							$return_string .= "<a rel='nofollow' class='product_type_simple add_to_cart_button ajax_add_to_cart' data-product_id='" . $this_id . "' href='" . $add_to_cart_url . "'><div class='product-cart'><i class='fas fa-shopping-cart'></i> Add to Cart</div></a>";
							$return_string .= "<a href='" . $add_to_cart_url . "'><div class='product-cart'><i class='fas fa-shopping-cart'></i> Add to Cart</div></a>";
							
							$return_string .= "</div>";
							$return_string .= "</div>";
						}
					}
				}		
			}
		}		
// 		if(($this_section['section_licenses']) === false){
// 			$this_section['section_licenses'] = array();
// 		}
// 	$return_string .= "<a class='anchor-link' href='#" . $sections[$last_section]['header_anchor'] . "'>>> Go to all courses for " . $this_section['header_name'] . " to build your package.</a>";
	}
	$version = time();
	wp_enqueue_style( 'product-feed', get_stylesheet_directory_uri() . '/css/product-feed.css', '', $version );
	
	return $return_string;
}
add_shortcode( 'jh_product_feed', 'jh_return_product_feed' );

function jh_return_back_to_courses_button(){
	$return_page = get_query_var( 'ref' );
	$this_state_name = get_query_var( 'state' );
	if( $return_page == 'products' ){
		$to_page = 'course_state';
		$return_link = get_site_url() . '/' . $to_page . '/' . $this_state_name ;
		$return_button = "<span class='return-button'><a href='" . $return_link . "'><i class='fas fa-arrow-left'></i> Add Another Course</a></span>";
	} else if( is_numeric( $return_page ) ) {
		$return_link = get_post_permalink( $return_page ) . '?ref=products&state=' . $this_state_name;
		$return_button = "<span class='return-button'><a href='" . $return_link . "'><i class='fas fa-arrow-left'></i> Return to Package</a></span>";
	} else {
		$return_link = get_site_url() . '/continuing-education/';
		$return_button = "<span class='return-button'><a href='" . $return_link . "'><i class='fas fa-arrow-left'></i> Add Another Course</a></span>";
	}
	return $return_button;
}
add_shortcode( 'jh_back_to_courses_button', 'jh_return_back_to_courses_button' );

function jh_go_to_cart_button(){
	$return_page = get_query_var( 'ref' );

	$this_state_name = get_query_var( 'state' );
	$return_link = wc_get_cart_url() . '?state=' . $this_state_name . '&ref=products';

	$return_button = "<span class='go-to-cart-button'><a href='" . $return_link . "'><i class='fas fa-shopping-cart'></i> Go to Cart</a></span>";
	return $return_button;	
}
add_shortcode( 'jh_cart_button', 'jh_go_to_cart_button' );

function jh_return_add_to_cart_url(){
	$this_product = get_the_ID();
	$url = jh_get_add_to_cart_url( $this_product );
	$button = "<div class='jh-add-to-cart'><a href='" .$url. "'>Add to Cart</a></div>";
	return $button;
}
add_shortcode( 'jh_add_to_cart_url', 'jh_return_add_to_cart_url' );

function jh_get_add_to_cart_url( $product_id ){
	$product_array = get_field( 'included_products', $product_id );
	$url = wc_get_cart_url() . "?add-to-cart=";
	if( has_term('packages','product_cat') ){
		$counter = 0;
		foreach( $product_array as $this_id ){
			$counter > 0 ? $url .= "," . $this_id : $url .= $this_id;
			$counter++;
		}
	} else {
		$url .= $product_id;
	}
	
	$this_state_name = get_query_var( 'course_state' );	
	if( !$this_state_name ){
		$this_state_name = get_query_var( 'state' );	
	}
	$url .= "&state=" . $this_state_name;
	
	$this_ref = get_query_var( 'ref' );
	if( !$this_ref ){
		$url .= "&ref=products";
	} else {
		$url .= "&ref=" . $this_ref;
	}
	
	return $url;
}

// This shortcode is added to a state's metadata
function jh_return_sales_page_navigation(){
	$this_state_name = get_query_var( 'course_state' );	
	$state = get_term_by( 'name', $this_state_name, 'course_state' );
	$acf_post_id = 'course_state_' . $state->term_id;
	$sections = get_field( 'course_sections', $acf_post_id );

	$return_string = "<div style='margin-top: 5px;'>";
	foreach( $sections as $this_section ){
		$return_string .= "<a class='anchor-link' href='#" . $this_section['header_anchor'] . "'><div class='button-to-section'>" . $this_section['header_name'] . "</div></a>";
	}
	$return_string .= "</div>";

	wp_enqueue_style( 'section-feed', get_stylesheet_directory_uri() . '/css/section-feed.css' );
	$version = time();
	wp_enqueue_script( 'jh-smooth-scroll', get_stylesheet_directory_uri() . '/js/smooth-scroll.js', 'jquery', $version, true );
	return $return_string;
}
add_shortcode( 'jh_sales_page_navigation', 'jh_return_sales_page_navigation' );

// This makes the editing of a course_state term page more usable. By default, WP does 800px width, which is too narrow.
function jh_fix_width(){
	wp_enqueue_style( 'edit-tags-mod', get_stylesheet_directory_uri() . '/css/edit-tags-mod.css' );
}
add_filter( 'load-edit-tags.php', 'jh_fix_width' );

// Work here is on packages, including the "build your own package" feature.

// Add the credit hours to the description of each item in the cart.
function jh_add_hours( $cart_item, $cart_item_key ){
	$hours = get_post_meta( $cart_item['product_id'], 'hours', true );
	$categories = $cart_item['data']->category_ids;
	if( in_array( 272, $categories ) || in_array( 342, $categories ) ){
		echo "<div style='margin-left: 15px'>" . $hours . " CE Hours</div>";
	}
}
add_action( 'woocommerce_after_cart_item_name', 'jh_add_hours', 10, 2 );

function jh_show_ce_calculations(){
	$this_state_name = get_query_var( 'state' );	
	$cart = WC()->cart;
	$hours = jh_get_total_cart_hours( $cart );
	echo "<div>There are currently <strong>" . $hours . "</strong> CE hours in your cart.</div>";
	setlocale(LC_MONETARY, 'en_US.UTF-8');
	if( $this_state_name == 'arkansas' || $this_state_name == 'maryland' || $this_state_name == 'pennsylvania' ){
		if( $hours >= 24 ){
			echo "<div>A 24-hour plus CE package costs $30. Never pay more than $30 for CE (plus applicable fees).</div>";
		}
	} else {
		if( $hours <= 10 ){
			echo "<div>With 10 CE hours or fewer, you will never pay more than $20 for CE (plus applicable fees).</div>";
		} else if( $hours > 10 && $hours < 20 ){
			if( $this_state_name == 'kansas' ){
				echo "<div>In Kansas, with fewer than 20 CE hours, you will never pay more than $39.95 for CE (plus applicable fees).</div>";
			} else if( $this_state_name == 'texas' ) {
				echo "<div>In Texas, a 12-hour package costs no more than $25.</div>";
			} else {
				echo "<div>With fewer than 20 CE hours, you will never pay more than $40 for CE (plus applicable fees).</div>";
			}
		} else if ( $hours >= 20 && $hours < 24 ){
			$discount = jh_get_ce_discount( $hours );
			echo "<div>20- to 23-hour CE packages cost $40 (plus applicable fees).</div>";
			echo "<div>Your current discount is <strong>" . money_format( '%.2n', $discount ) . "</strong></div>";
		} else if ( $hours >=24 ){
			$discount = jh_get_ce_discount( $hours );
			echo "<div>A 24-hour plus CE package costs $50. Never pay more than $50 for CE (plus applicable fees).</div>";
			echo "<div>Your current discount is <strong>" . money_format( '%.2n', $discount ) . "</strong></div>";
		}
	}
}
add_action( 'woocommerce_cart_totals_before_order_total', 'jh_show_ce_calculations' );

// Add another fee: Filing Fee, which is state-based
// Checkout bug fix by Rodolfo Melogli 09/Mar/2023

add_action( 'template_redirect', 'bbloomer_add_state_to_session' );
function bbloomer_add_state_to_session() {
    if ( isset( $_GET['state'] ) ) {
        WC()->session->set( 'getstate', esc_attr( $_GET['state'] ) );
    }
}

function jh_set_cart_filing_fee( $cart ){

	$this_state_name = WC()->session->get( 'getstate' );
	if ( ! $this_state_name ) return;

	$the_state = get_term_by( 'slug', $this_state_name, 'course_state' );
	$acf_post_id = 'course_state_' . $the_state->term_id;

	$fee_charged = get_field( 'completion_filing_fee', $acf_post_id );
	if($fee_charged['fee_amount'] > 0){
		$fee_name = "Filing Fee ($" . $fee_charged['fee_amount'] . " per " . $fee_charged['fee_charged_per'] . ")";
		if( $fee_charged['fee_charged_per'] == 'credit' ){
			$hours = jh_get_total_cart_hours( $cart );
			$fee_total = $hours * $fee_charged['fee_amount'];
		} else if ( $fee_charged['fee_charged_per'] == 'course' ){
			$courses = count($cart->cart_contents);
			$fee_total = $courses * $fee_charged['fee_amount'];
		}
		$cart->add_fee( $fee_name, $fee_total, true, 'standard' );
	}

}
add_action('woocommerce_cart_calculate_fees', 'jh_set_cart_filing_fee', 11, 1 );


// Discount: Depending on the number of hours, reduce the cost.
function jh_set_cart_discount( $cart ){
	$coupons = $cart->get_coupons();
// 	var_dump($coupons);
	$ce_term_ids = array( 272 );
	$coupon_applies_to_cart = false;
	foreach( $coupons as $this_coupon_code => $this_coupon_object ){
	 	$included_cats = $this_coupon_object->get_product_categories();
		foreach( $included_cats as $this_cat ){
			if( in_array( $this_cat, $ce_term_ids ) ){
				$coupon_applies_to_cart = true;
				break;
			}
		}	
	}
	if( ! $coupon_applies_to_cart ){
		$hours = jh_get_total_cart_hours( $cart );
		$discount = jh_get_ce_discount( $hours );
		$cart->add_fee( 'CE Package Discount', $discount, true, 'standard' );
	} else {
		// Hide the coupon discount, which is actually $0
		echo '<style>
				.cart-discount span.woocommerce-Price-amount.amount {
					display: none;
				}
				a.woocommerce-remove-coupon{
					display: none;
				}
			</style>';
		$hours = jh_get_total_cart_hours( $cart );
		$discount = jh_get_ce_coupon_discount( $hours );
		$cart->add_fee( 'CE Total Discount', $discount, true, 'standard' );
	}
}
add_action('woocommerce_cart_calculate_fees', 'jh_set_cart_discount', 10, 1 );

// This is not good. It actually replaces the coupon amount, which is 0% and should always be.
// Thus, all CE coupons must be the same. AND there are no coupon categories. Perhaps the name could indicate the discount.
function jh_get_ce_coupon_discount( $hours ){
	$this_state_name = WC()->session->get( 'getstate' );
	if ( ! $this_state_name ) return;
	$cart = WC()->cart;
	$sub = jh_get_total_ce_cost( $cart );
	if( $hours <= 10 ){
		if( $sub > 10 ){
			$discount = 10 - $sub;
		} else {
			$discount = -$sub/2;
		}
	} else if( $hours > 10 && $hours < 24 ){
		if( $sub > 40 ){
			$discount = 20 - $sub;
		} else {
			$discount = -$sub/2;
		}
	} else if( $hours >= 24 ){
			$discount = 25 - $sub;
	}
	return $discount;
}

function jh_get_ce_discount( $hours ){
	$this_state_name = WC()->session->get( 'getstate' );
	if ( ! $this_state_name ) return;

// 	$this_state_name = get_query_var( 'state' );	
	$cart = WC()->cart;
	$sub = jh_get_total_ce_cost( $cart );
	if( $this_state_name == 'arkansas' || $this_state_name == 'maryland' || $this_state_name == 'pennsylvania' ){
		if( $hours >= 24 ){
			$discount = 30 - $sub;
		}
	} else {
		if( $hours <= 10 ){
			if( $sub > 20 ){
				$discount = 20 - $sub;
			} else {
				$discount = 0;
			}
		} else if( $hours > 10 && $hours < 20 ){
			if( $this_state_name == 'kansas' ){
				if( $sub > 39.95 ){
					$discount = 39.95 - $sub;
				} else {
					$discount = 0;
				}			
			} else if ( $this_state_name == 'texas' ) {
				if( $sub > 25 ){
					$discount = 25 - $sub;
				} else {
					$discount = 0;
				}
			} else {
				if( $sub > 40 ){
					$discount = 40 - $sub;
				} else {
					$discount = 0;
				}
			}
		} else if( $hours >= 20 && $hours < 24 ){
			$discount = 40 - $sub;
		} else if( $hours >= 24 ){
			$discount = 50 - $sub;
		}
	}
	return $discount;
}

function jh_get_total_cart_hours( $cart ){
	$total_hours = 0;
	foreach( $cart->get_cart() as $cart_item_key => $cart_item ){
		$cart_product = $cart_item['data'];
// 		$hours = get_post_meta( $cart_item['product_id'], 'hours', true );
		$prod_id = $cart_product->get_id();
		$hours = get_post_meta( $prod_id, 'hours', true );
// 		$categories = $cart_item['data']->category_ids;
		$categories = $cart_product->get_category_ids();
		if( in_array( 272, $categories ) || in_array( 342, $categories ) ){
			$total_hours += $hours;	
		}
	}
	return $total_hours;
}

function jh_get_total_ce_cost( $cart ){
	$total_price = 0;
	foreach( $cart->get_cart() as $cart_item_key => $cart_item ){
// 		var_dump($cart_item);
		$cart_product = $cart_item['data'];
// 		$categories = $cart_item['data']->category_ids;
		$categories = $cart_product->get_category_ids();
// 		$price = $cart_item['data']->price;
		$price = $cart_product->get_price();
		if( in_array( 272, $categories ) || in_array( 342, $categories ) ){
			$total_price += $price;	
		}
	}
	return $total_price;
}

// List the course products within a package from our ACF field "Included Products"
function jh_return_list_package_products(){
	$this_package_product = get_the_ID();
	$all_course_products = get_field( 'included_products', $this_package_product );
	$id_product_name = array();
	foreach( $all_course_products as $this_product ){
		$id_product_name[$this_product] = get_the_title( $this_product );
	}
	asort( $id_product_name );
// 	var_dump($id_product_name);
	$this_state_name = get_query_var( 'state' );
	$return_string = '<ul>';
	foreach( $id_product_name as $this_product_id => $this_product_name ){
		$course_id = get_post_meta( $this_product_id, '_related_course', true ); // Hours may vary by state. This field is stored in the COURSE not the product.
		$this_state = $this_state_name;
		$all_state_info = get_field( 'course_info_by_state', $course_id[0] );
		if( is_array($all_state_info) ){
			foreach( $all_state_info as $this_state_info ){
				$state_slug = $this_state_info['course_state']->slug;
				if( $state_slug == $this_state ){
					$this_hours = $this_state_info['state_ce_hours'];
				}
			}
		} else {
			$this_hours = get_post_meta( $this_product, 'hours', true );							
		}
// 		$hours_data = get_field( 'hours', $this_product_id );
		if( $this_hours == 1 ){
			$hours = $this_hours . ' hour';
		} else {
			$hours = $this_hours . ' hours';
		}
		$return_string .= '<li><strong><a href="' . get_post_permalink( $this_product_id ) . '?state=' . $this_state_name . '&ref=' . $this_package_product . '">' . $this_product_name . '</a> (' . $hours . ')</strong></li>';
	}
	$return_string .= '</ul>';
	return $return_string;
}
add_shortcode( 'jh_list_package_products', 'jh_return_list_package_products' );

function jh_can_take_assessment(){
	$page_id = get_the_id();
	$course_id = get_post_meta( $page_id, 'course-id', true );
// 	$course_id = get_post_meta( $page_id );
	$this_user = get_current_user_id();
	$student_courses = learndash_user_get_enrolled_courses( $this_user );
	if( in_array( $course_id, $student_courses ) ){
		return true;
	}
}




function woocommerce_maybe_add_multiple_products_to_cart() {
  // Make sure WC is installed, and add-to-cart qauery arg exists, and contains at least one comma.
  if ( ! class_exists( 'WC_Form_Handler' ) || empty( $_REQUEST['add-to-cart'] ) || false === strpos( $_REQUEST['add-to-cart'], ',' ) ) {
      return;
  }

  remove_action( 'wp_loaded', array( 'WC_Form_Handler', 'add_to_cart_action' ), 20 );

  $product_ids = explode( ',', $_REQUEST['add-to-cart'] );
  $count       = count( $product_ids );
  $number      = 0;

  foreach ( $product_ids as $product_id ) {
      if ( ++$number === $count ) {
          // Ok, final item, let's send it back to woocommerce's add_to_cart_action method for handling.
          $_REQUEST['add-to-cart'] = $product_id;

          return WC_Form_Handler::add_to_cart_action();
      }

      $product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $product_id ) );
      $was_added_to_cart = false;

      $adding_to_cart    = wc_get_product( $product_id );

      if ( ! $adding_to_cart ) {
          continue;
      }

      if ( $adding_to_cart->is_type( 'simple' ) ) {

          // quantity applies to all products atm
          $quantity          = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( $_REQUEST['quantity'] );
          $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

          if ( $passed_validation && false !== WC()->cart->add_to_cart( $product_id, $quantity ) ) {
              wc_add_to_cart_message( array( $product_id => $quantity ), true );
          }

      } else {

          $variation_id       = empty( $_REQUEST['variation_id'] ) ? '' : absint( wp_unslash( $_REQUEST['variation_id'] ) );
          $quantity           = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( wp_unslash( $_REQUEST['quantity'] ) ); // WPCS: sanitization ok.
          $missing_attributes = array();
          $variations         = array();
          $adding_to_cart     = wc_get_product( $product_id );

          if ( ! $adding_to_cart ) {
            continue;
          }

          // If the $product_id was in fact a variation ID, update the variables.
          if ( $adding_to_cart->is_type( 'variation' ) ) {
            $variation_id   = $product_id;
            $product_id     = $adding_to_cart->get_parent_id();
            $adding_to_cart = wc_get_product( $product_id );

            if ( ! $adding_to_cart ) {
              continue;
            }
          }

          // Gather posted attributes.
          $posted_attributes = array();

          foreach ( $adding_to_cart->get_attributes() as $attribute ) {
            if ( ! $attribute['is_variation'] ) {
              continue;
            }
            $attribute_key = 'attribute_' . sanitize_title( $attribute['name'] );

            if ( isset( $_REQUEST[ $attribute_key ] ) ) {
              if ( $attribute['is_taxonomy'] ) {
                // Don't use wc_clean as it destroys sanitized characters.
                $value = sanitize_title( wp_unslash( $_REQUEST[ $attribute_key ] ) );
              } else {
                $value = html_entity_decode( wc_clean( wp_unslash( $_REQUEST[ $attribute_key ] ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ); // WPCS: sanitization ok.
              }

              $posted_attributes[ $attribute_key ] = $value;
            }
          }

          // If no variation ID is set, attempt to get a variation ID from posted attributes.
          if ( empty( $variation_id ) ) {
            $data_store   = WC_Data_Store::load( 'product' );
            $variation_id = $data_store->find_matching_product_variation( $adding_to_cart, $posted_attributes );
          }

          // Do we have a variation ID?
          if ( empty( $variation_id ) ) {
            throw new Exception( __( 'Please choose product options&hellip;', 'woocommerce' ) );
          }

          // Check the data we have is valid.
          $variation_data = wc_get_product_variation_attributes( $variation_id );

          foreach ( $adding_to_cart->get_attributes() as $attribute ) {
            if ( ! $attribute['is_variation'] ) {
              continue;
            }

            // Get valid value from variation data.
            $attribute_key = 'attribute_' . sanitize_title( $attribute['name'] );
            $valid_value   = isset( $variation_data[ $attribute_key ] ) ? $variation_data[ $attribute_key ]: '';

            /**
             * If the attribute value was posted, check if it's valid.
             *
             * If no attribute was posted, only error if the variation has an 'any' attribute which requires a value.
             */
            if ( isset( $posted_attributes[ $attribute_key ] ) ) {
              $value = $posted_attributes[ $attribute_key ];

              // Allow if valid or show error.
              if ( $valid_value === $value ) {
                $variations[ $attribute_key ] = $value;
              } elseif ( '' === $valid_value && in_array( $value, $attribute->get_slugs() ) ) {
                // If valid values are empty, this is an 'any' variation so get all possible values.
                $variations[ $attribute_key ] = $value;
              } else {
                throw new Exception( sprintf( __( 'Invalid value posted for %s', 'woocommerce' ), wc_attribute_label( $attribute['name'] ) ) );
              }
            } elseif ( '' === $valid_value ) {
              $missing_attributes[] = wc_attribute_label( $attribute['name'] );
            }
          }
          if ( ! empty( $missing_attributes ) ) {
            throw new Exception( sprintf( _n( '%s is a required field', '%s are required fields', count( $missing_attributes ), 'woocommerce' ), wc_format_list_of_items( $missing_attributes ) ) );
          }

        $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );

        if ( $passed_validation && false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations ) ) {
          wc_add_to_cart_message( array( $product_id => $quantity ), true );
        }
      }
  }
}
add_action( 'wp_loaded', 'woocommerce_maybe_add_multiple_products_to_cart', 15 );

function bbloomer_redirect_checkout_add_cart( $url, $product ) {
	$cat_ids = $product->get_category_ids();
	if( in_array( 332, $cat_ids ) ){
		return wc_get_checkout_url();
	} // else if( in_array( 583, $cat_ids ) || in_array( 581, $cat_ids ) || in_array( 582, $cat_ids ) ) {
// 		$site_url = get_site_url();
// 		$queries = '/checkouts/xactimate-checkout/';
// 		$xactimate_checkout = $site_url . $queries;
// 		return $xactimate_checkout;
// 	}
}
add_filter( 'woocommerce_add_to_cart_redirect', 'bbloomer_redirect_checkout_add_cart', 30, 2 );

// 
// function jh_import_from_gf_user_meta(){
// 	if( current_user_can( 'manage_options' ) ){
// 		$entries = GFAPI::get_entries( 1, array(), array(), array( 'offset' => 2200, 'page_size' => 100 ) );
// // 		var_dump($entries);
// 		foreach($entries as $this_entry){
// 			update_user_meta( $this_entry['created_by'], 'birth_city', $this_entry['3'] );
// 			update_user_meta( $this_entry['created_by'], 'mothers_maiden_name', $this_entry['4'] );
// 			update_user_meta( $this_entry['created_by'], 'favorite_color', $this_entry['5'] );
// 			update_user_meta( $this_entry['created_by'], 'favorite_ice_cream', $this_entry['6'] );
// 			update_user_meta( $this_entry['created_by'], 'motorcycle', $this_entry['7'] );
// 			update_user_meta( $this_entry['created_by'], 'hawaii', $this_entry['8'] );
// 			update_user_meta( $this_entry['created_by'], 'been_to_florida', $this_entry['9'] );
// 			update_user_meta( $this_entry['created_by'], 'military', $this_entry['11'] );
// 			update_user_meta( $this_entry['created_by'], 'birth_month', $this_entry['10'] );
// 			var_dump($this_entry['created_by']);
// 		}
// 	}
// }
// add_shortcode( 'jh_import_user_meta', 'jh_import_from_gf_user_meta' );

function jh_return_remote_content(){
	
	?>
	<form method="post">
		<label for="fl-link"><strong>FL Licensee Detail Link:</strong> Enter Agent's unique URL.</label><br />
		<input type="text" id="fl-link" name="fl-link" style="width: 400px; padding: 4px;">
		<input type="submit" value="Get Agent Info">
	</form>
	<?php
	
	$link = $_POST['fl-link'];
	
	if( $link ){
	
		echo '<strong>Agent Link:</strong> ' . $link . '<br />';

		$dom = new DOMDocument();
		$url = 'https://licenseesearch.fldfs.com/Licensee/320447';
		$request = wp_remote_get( $url );
		$dom->loadHTML(wp_remote_retrieve_body($request));
		$all_text = strval( $dom->textContent );
	
		$name_matches = array();
		preg_match('/Full Name:\s+([A-Za-z0-9,\'-]+\s)+/', $all_text, $name_matches);
		$full_name_text = $name_matches[0];
		$full_name_matches = array();
		preg_match( '/Full Name:\s+([A-Za-z0-9,\'\s-]+)/', $full_name_text, $full_name_matches);
		$full_name = substr( $full_name_matches[1], 0, -1 );
		echo '<strong>Full Name:</strong> ' . $full_name . '<br />';
	
		$license_matches = array();
		preg_match('/License\s#:\s+([A-Za-z0-9]+)/', $all_text, $license_matches);
		$license = $license_matches[1];
		echo '<strong>License:</strong> ' . $license . '<br />';

		$npn_matches = array();
		preg_match('/NPN\s#:\s+(\d+)/', $all_text, $npn_matches);
		$npn = $npn_matches[1];
		echo '<strong>NPN:</strong> ' . $npn . '<br />';

		$ce_due_date_matches = array();
		preg_match('/CE Due Date:\s+(\d+\/\d+\/\d+)/', $all_text, $ce_due_date_matches);
		$ce_due_date = $ce_due_date_matches[1];
		echo '<strong>CE Due Date:</strong> ' . $ce_due_date . '<br />';

		$hours_required_matches = array();
		preg_match('/Number of Hours Required:\s+(\d+)/', $all_text, $hours_required_matches);
		$hours_required = $hours_required_matches[1];
		echo '<strong>Number of CE Hours Required:</strong> ' . $hours_required . '<br />';

		$hours_completed_matches = array();
		preg_match('/Number of Hours Completed:\s+(\d+)/', $all_text, $hours_completed_matches);
		$hours_completed = $hours_completed_matches[1];
		echo '<strong>Number of CE Hours Completed:</strong> ' . $hours_completed . '<br />';

		$ce_status_matches = array();
		preg_match('/Continuing Education Status:\s+([A-Za-z]+\s)+/', $all_text, $ce_status_matches);
		$full_status_text = $ce_status_matches[0];
		$full_status_matches = array();
		preg_match('/Continuing Education Status:\s+([A-Za-z\s]+)/', $full_status_text, $full_status_matches);
	// 	var_dump($full_status_matches);
		$ce_status = substr( $full_status_matches[1], 0, -1 );
		echo '<strong>CE Status:</strong> ' . $ce_status . '<br />';
	
	}

// 	var_dump( $all_text );
}
add_shortcode( 'jh_remote_content', 'jh_return_remote_content' );

function jh_return_account_endpoint(){
	$u = is_wc_endpoint_url('orders');
	var_dump($u);
}
add_shortcode( 'jh_account_endpoint', 'jh_return_account_endpoint' );

function jh_return_law_ethics_incomplete(){
	$old_l_e_course = 155802; // 2024 course to be removed from
	$new_l_e_course = 195805; // 2025 course to be added to
	$all_2024_l_e_users = learndash_get_users_for_course( $old_l_e_course );
	$course_name = get_the_title($old_l_e_course);
	echo $course_name . '<br />';
// 	$test_array = array( 7157 );
// 	var_dump($all_2024_l_e_users->results);
	foreach( $all_2024_l_e_users->results as $this_user ){
// 	foreach( $test_array as $this_user ){
		$status = learndash_course_status( $old_l_e_course, $this_user, true );
// 		var_dump($status);
		if( $status != 'completed' ){
			$user_info = get_userdata( $this_user );
			$add = ld_update_course_access( $this_user, $new_l_e_course, false );
			$remove = ld_update_course_access( $this_user, $old_l_e_course, true );
			if($add == true){
				$str = $user_info->user_email . '<br />';
				echo $str;
			}
		}
	}
}
// add_shortcode( 'jh_law_ethics_incomplete', 'jh_return_law_ethics_incomplete' );



// Trying to Selectize the LD categories in the wp admin questions page.
// function jh_selectize_ld_categories() {
// 	$this_post_type = get_post_type();
// 	if( $this_post_type == 'sfwd-question' ){
// 		echo '<link
// 			rel="stylesheet"
// 			href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/css/selectize.default.min.css"
// 			integrity="sha512-pTaEn+6gF1IeWv3W1+7X7eM60TFu/agjgoHmYhAfLEU8Phuf6JKiiE8YmsNC0aCgQv4192s4Vai8YZ6VNM6vyQ=="
// 			crossorigin="anonymous"
// 			referrerpolicy="no-referrer"
// 		/>
// 		<script
// 			src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/js/selectize.min.js"
// 			integrity="sha512-IOebNkvA/HZjMM7MxL0NYeLYEalloZ8ckak+NDtOViP7oiYzG5vn6WVXyrJDiJPhl4yRdmNAG49iuLmhkUdVsQ=="
// 			crossorigin="anonymous"
// 			referrerpolicy="no-referrer"
// 		> 
// 		jQuery("#learndash_question_category_proquiz select").selectize();
// 		</script>';
// 		
// 	}
// }
// add_action('admin_footer', 'jh_selectize_ld_categories');


function jh_selectize_ld_categories( $hook_suffix ){
    $cpt = 'sfwd-question';

    if( in_array($hook_suffix, array('post.php', 'post-new.php') ) ){
        $screen = get_current_screen();

        if( is_object( $screen ) && $cpt == $screen->post_type ){
			
			$version = time();
            wp_enqueue_script( 'jh_selectize_cats-script', 'https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/js/selectize.min.js', 'jquery', $version, true );
            wp_enqueue_style( 'jh_selectize_cats-style', 'https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/css/selectize.default.min.css' );
			wp_enqueue_script( 'jh_selectize_cats', get_stylesheet_directory_uri() . '/js/selectize.js', 'jquery', $version, true );
			
        }
    }
}

add_action( 'admin_enqueue_scripts', 'jh_selectize_ld_categories');