<?php
/**
 * WooCommerce Try On Frontend.
 *
 * @package WooCommerce_Try_On
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Tryloom_Frontend Class.
 */
class Tryloom_Frontend
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// Add try-on button to product page.
		$button_placement = get_option('tryloom_button_placement', 'default');
		if ('default' === $button_placement) {
			add_action('woocommerce_after_add_to_cart_button', array($this, 'add_try_on_button'));
		}

		// Register shortcode.
		add_shortcode('tryloom', array($this, 'try_on_shortcode'));
		add_shortcode('tryloom_popup', array($this, 'try_on_popup_shortcode'));

		// Enqueue frontend scripts and styles.
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

		// Add try-on popup to footer.
		add_action('wp_footer', array($this, 'add_try_on_popup'));

		// Add try-on tab to user account.
		add_filter('woocommerce_account_menu_items', array($this, 'add_try_on_account_menu_item'));
		add_action('woocommerce_account_try-on_endpoint', array($this, 'try_on_account_content'));
		add_action('init', array($this, 'add_try_on_endpoint'));
		// Make sure the endpoint works with query vars
		add_filter('query_vars', array($this, 'add_try_on_query_vars'));

		// Handle AJAX requests.
		add_action('wp_ajax_tryloom_upload_photo', array($this, 'ajax_upload_photo'));
		add_action('wp_ajax_nopriv_tryloom_upload_photo', array($this, 'ajax_upload_photo'));
		add_action('wp_ajax_tryloom_generate', array($this, 'ajax_generate_try_on'));
		add_action('wp_ajax_nopriv_tryloom_generate', array($this, 'ajax_generate_try_on'));
		add_action('wp_ajax_tryloom_delete_photo', array($this, 'ajax_delete_photo'));
		add_action('wp_ajax_tryloom_set_default_photo', array($this, 'ajax_set_default_photo'));
		add_action('wp_ajax_tryloom_delete_history', array($this, 'ajax_delete_history'));
		add_action('wp_ajax_tryloom_delete_all_history', array($this, 'ajax_delete_all_history'));
		add_action('wp_ajax_tryloom_upload_account_photo', array($this, 'ajax_upload_account_photo'));
		add_action('wp_ajax_tryloom_get_variations', array($this, 'ajax_get_variations'));
		add_action('wp_ajax_nopriv_tryloom_get_variations', array($this, 'ajax_get_variations'));
		add_action('wp_ajax_tryloom_get_product', array($this, 'ajax_get_product'));
		add_action('wp_ajax_nopriv_tryloom_get_product', array($this, 'ajax_get_product'));

		// Invalidate variations cache when product is updated.
		add_action('save_post_product', array($this, 'invalidate_variations_cache'), 10, 1);
		add_action('woocommerce_save_product_variation', array($this, 'invalidate_parent_variations_cache'), 10, 2);

		// Invalidate variations cache on stock change.
		add_action('woocommerce_product_set_stock', array($this, 'invalidate_cache_on_stock_change'), 10, 1);
		add_action('woocommerce_variation_set_stock', array($this, 'invalidate_cache_on_stock_change'), 10, 1);

		// Hide TryLoom images from Media Library (admin only).
		if (is_admin()) {
			add_filter('ajax_query_attachments_args', array($this, 'exclude_try_on_images_from_media_library'));
			add_action('pre_get_posts', array($this, 'exclude_try_on_images_from_media_library_query'));
		}

		// Add theme body class for dark mode CSS cascade
		add_filter('body_class', array($this, 'add_theme_body_class'));
	}

	/**
	 * Add theme body class for dark mode.
	 */
	public function add_theme_body_class($classes)
	{
		if ('yes' === get_option('tryloom_enabled', 'yes')) {
			$theme = get_option('tryloom_theme_color', 'light');
			$classes[] = 'tryloom-theme-' . esc_attr($theme);
		}
		return $classes;
	}

	/**
	 * Add try-on endpoint.
	 */
	public function add_try_on_endpoint()
	{
		add_rewrite_endpoint('try-on', EP_ROOT | EP_PAGES);
	}

	/**
	 * Add try-on query vars.
	 *
	 * @param array $vars Query variables.
	 * @return array
	 */
	public function add_try_on_query_vars($vars)
	{
		$vars[] = 'try-on';
		return $vars;
	}

	/**
	 * Add try-on account menu item.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function add_try_on_account_menu_item($items)
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			return $items;
		}

		// Check if try-on history/tab is enabled
		if ('yes' !== get_option('tryloom_enable_history', 'yes')) {
			return $items;
		}

		// Add try-on tab as the third menu item.
		// First, extract the first two items
		$first_items = array_slice($items, 0, 2, true);
		// Then get the rest of the items
		$remaining_items = array_slice($items, 2, null, true);
		// Insert the try-on item between them
		$items = array_merge($first_items, array('try-on' => __('Try On', 'tryloom')), $remaining_items);

		return $items;
	}

	/**
	 * Add try-on button to product page.
	 */
	public function add_try_on_button()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			return;
		}

		// Check if subscription ended
		$subscription_ended = get_option('tryloom_subscription_ended', 'no');
		if ('yes' === $subscription_ended) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		global $product;

		// Check if product exists.
		if (!$product) {
			return;
		}

		// Check if product is in allowed categories.
		$allowed_categories = get_option('tryloom_allowed_categories', array());
		if (!empty($allowed_categories)) {
			$product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
			$intersection = array_intersect($allowed_categories, $product_categories);
			if (empty($intersection)) {
				return;
			}
		}

		// Check if user role is allowed.
		$allowed_user_roles = get_option('tryloom_allowed_user_roles', array('customer'));
		$current_user_roles = wp_get_current_user()->roles;

		// If guest is allowed or user has an allowed role.
		if (in_array('guest', $allowed_user_roles, true) || (is_user_logged_in() && array_intersect($current_user_roles, $allowed_user_roles))) {
			// Get settings.
			$primary_color = get_option('tryloom_primary_color', '#552FBC');

			// Get Add to Cart button classes.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			// Use your own prefix
			$button_classes = apply_filters('tryloom_product_single_add_to_cart_button_classes', 'button alt wp-element-button');

			// Output button.
			?>
			<button type="button" class="<?php echo esc_attr($button_classes); ?> tryloom-button"
				data-product-id="<?php echo esc_attr($product->get_id()); ?>">
				<?php esc_html_e('Virtual Try On', 'tryloom'); ?>
			</button>
			<?php
		}
	}

	/**
	 * Try-on shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function try_on_shortcode($atts)
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			return '';
		}

		// Check if subscription ended
		$subscription_ended = get_option('tryloom_subscription_ended', 'no');
		if ('yes' === $subscription_ended) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'product_id' => 0,
			),
			$atts,
			'tryloom'
		);

		// Get product ID.
		$product_id = absint($atts['product_id']);
		if (!$product_id) {
			global $product;
			// Check if $product is a valid WooCommerce product object.
			if ($product && is_a($product, 'WC_Product')) {
				$product_id = $product->get_id();
			} elseif (is_product()) {
				// Try to get product ID from the current post if on a product page.
				$product_id = get_the_ID();
			}
		}

		// Check if product ID is valid.
		if (!$product_id) {
			return '';
		}

		// Get product.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		$product = wc_get_product($product_id);
		if (!$product || !$product->is_in_stock()) {
			return '';
		}

		// Check if product is in allowed categories.
		$allowed_categories = get_option('tryloom_allowed_categories', array());
		if (!empty($allowed_categories)) {
			$product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
			$intersection = array_intersect($allowed_categories, $product_categories);
			if (empty($intersection)) {
				return '';
			}
		}

		// Check if user role is allowed.
		$allowed_user_roles = get_option('tryloom_allowed_user_roles', array('customer'));
		$current_user_roles = wp_get_current_user()->roles;

		// If guest is allowed or user has an allowed role.
		if (!in_array('guest', $allowed_user_roles, true) && is_user_logged_in() && !array_intersect($current_user_roles, $allowed_user_roles)) {
			return '';
		}

		// Get settings.
		$primary_color = get_option('tryloom_primary_color', '#552FBC');

		// Get Add to Cart button classes.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$button_classes = apply_filters('tryloom_product_single_add_to_cart_button_classes', 'button alt wp-element-button');

		// Output button.
		ob_start();
		?>
		<button type="button" class="<?php echo esc_attr($button_classes); ?> tryloom-button"
			data-product-id="<?php echo esc_attr($product_id); ?>">
			<?php esc_html_e('Virtual Try On', 'tryloom'); ?>
		</button>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add try-on popup to footer.
	 */
	public function add_try_on_popup()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			return;
		}

		// Only render popup on product pages or account pages.
		if (!is_product() && !is_account_page()) {
			return;
		}

		// Check if subscription ended
		$subscription_ended = get_option('tryloom_subscription_ended', 'no');
		if ('yes' === $subscription_ended) {
			return;
		}

		// Check if user role is allowed.
		$allowed_user_roles = get_option('tryloom_allowed_user_roles', array('customer'));
		$current_user_roles = wp_get_current_user()->roles;

		// If guest is allowed or user has an allowed role.
		if (!in_array('guest', $allowed_user_roles, true) && is_user_logged_in() && !array_intersect($current_user_roles, $allowed_user_roles)) {
			return;
		}

		// Get settings.
		$theme_color = get_option('tryloom_theme_color', 'light');
		$primary_color = get_option('tryloom_primary_color', '#552FBC');
		$save_photos = get_option('tryloom_save_photos', 'yes');
		$watermark = get_option('tryloom_brand_watermark', '');

		// Check if user has a default photo.
		$default_photo_url = '';
		if (is_user_logged_in()) {
			$user_id = get_current_user_id();
			global $wpdb;
			$table_name = $wpdb->prefix . 'tryloom_user_photos';
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
			$default_photo = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . esc_sql($table_name) . ' WHERE user_id = %d AND is_default = 1 ORDER BY manually_set_default DESC, created_at DESC LIMIT 1',
					$user_id
				)
			);

			if ($default_photo) {
				$default_photo_url = $default_photo->image_url;
			}
		}

		// Include popup template.
		include TRYLOOM_PLUGIN_DIR . 'templates/try-on-popup.php';
	}

	/**
	 * Try-on popup shortcode.
	 *
	 * @return string
	 */
	public function try_on_popup_shortcode()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			return '';
		}

		// Ensure scripts are enqueued.
		if (!wp_script_is('tryloom-frontend', 'enqueued')) {
			$this->enqueue_scripts();
		}

		// Fetch default photo URL for template (same logic as add_try_on_popup).
		$default_photo_url = '';
		if (is_user_logged_in()) {
			$user_id = get_current_user_id();
			global $wpdb;
			$table_name = $wpdb->prefix . 'tryloom_user_photos';
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
			$default_photo = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT image_url FROM ' . esc_sql($table_name) . ' WHERE user_id = %d AND is_default = 1 ORDER BY manually_set_default DESC, created_at DESC LIMIT 1',
					$user_id
				)
			);
			if ($default_photo) {
				$default_photo_url = $default_photo->image_url;
			}
		}

		ob_start();
		include TRYLOOM_PLUGIN_DIR . 'templates/try-on-popup.php';
		return ob_get_clean();
	}

	/**
	 * Display try-on account content.
	 */
	public function try_on_account_content()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			return;
		}

		// Check if user is logged in.
		if (!is_user_logged_in()) {
			return;
		}

		$user_id = get_current_user_id();

		// Get user photos.
		global $wpdb;
		$table_name = $wpdb->prefix . 'tryloom_user_photos';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$user_photos = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . esc_sql($table_name) . ' WHERE user_id = %d ORDER BY is_default DESC, created_at DESC',
				$user_id
			)
		);

		// Get try-on history (only if history is enabled).
		$try_on_history = array();
		$enable_history = get_option('tryloom_enable_history', 'yes');

		if ('yes' === $enable_history) {
			$table_name = $wpdb->prefix . 'tryloom_history';
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
			$try_on_history = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . esc_sql($table_name) . ' WHERE user_id = %d ORDER BY created_at DESC LIMIT 20',
					$user_id
				)
			);
		}

		// Display content.
		?>
		<div class="tryloom-account">
			<?php if ('yes' === $enable_history): ?>
				<h2><?php esc_html_e('Try On History', 'tryloom'); ?></h2>

				<div class="tryloom-account-history">
					<?php if (!empty($try_on_history)): ?>
						<div class="tryloom-history-actions">
							<button class="button tryloom-delete-all-history">
								<?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-trash.php'; ?>
								<?php esc_html_e('Delete All History', 'tryloom'); ?>
							</button>
						</div>

						<table class="tryloom-history-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Date', 'tryloom'); ?></th>
									<th><?php esc_html_e('Product', 'tryloom'); ?></th>
									<th><?php esc_html_e('Image', 'tryloom'); ?></th>
									<th><?php esc_html_e('Actions', 'tryloom'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($try_on_history as $history): ?>
									<?php
									$product = wc_get_product($history->product_id);
									if (!$product) {
										continue;
									}
									?>
									<tr>
										<td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($history->created_at))); ?>
										</td>
										<td>
											<a href="<?php echo esc_url(get_permalink($history->product_id)); ?>">
												<?php echo esc_html($product->get_name()); ?>
											</a>
										</td>
										<td>
											<a href="<?php echo esc_url($history->generated_image_url); ?>" target="_blank">
												<img src="<?php echo esc_url($history->generated_image_url); ?>"
													alt="<?php esc_attr_e('Try On Result', 'tryloom'); ?>" width="50" height="50" />
											</a>
										</td>
										<td>
											<a href="<?php echo esc_url($history->generated_image_url); ?>" download class="button">
												<?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-download.php'; ?>
											</a>
											<a href="<?php echo esc_url(get_permalink($history->product_id)); ?>" class="button">
												<?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-redo.php'; ?>
											</a>
											<button class="button tryloom-delete-history" data-id="<?php echo esc_attr($history->id); ?>">
												<?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-trash.php'; ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else: ?>
						<p><?php esc_html_e('You have no try-on history.', 'tryloom'); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function enqueue_scripts()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			return;
		}

		// Only enqueue on product pages or account pages.
		if (!is_product() && !is_account_page()) {
			return;
		}

		// Enqueue Font Awesome.


		// Enqueue styles.
		wp_enqueue_style(
			'tryloom-frontend',
			TRYLOOM_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			TRYLOOM_VERSION
		);

		// Add custom CSS.
		$custom_popup_css = get_option('tryloom_custom_popup_css', '');
		$custom_button_css = get_option('tryloom_custom_button_css', '');
		$custom_account_css = get_option('tryloom_custom_account_css', '');
		// Build CSS string
		$raw_css = $custom_popup_css . "\n" . $custom_button_css . "\n" . $custom_account_css;

		if (!empty(trim($raw_css))) {
			// Escaping LATE: wp_strip_all_tags called directly inside the output function
			wp_add_inline_style('tryloom-frontend', wp_strip_all_tags($raw_css));
		}

		// Inject dynamic primary color via CSS variable at :root level
		$primary_color = get_option('tryloom_primary_color', '');
		if (!empty($primary_color)) {
			$root_css = ":root { --tryloom-primary-color: " . esc_attr($primary_color) . "; }";
			$root_css .= "\n.tryloom-button { background-color: " . esc_attr($primary_color) . " !important; }";
			wp_add_inline_style('tryloom-frontend', $root_css);
		}

		// Enqueue scripts.
		wp_enqueue_script(
			'tryloom-frontend',
			TRYLOOM_PLUGIN_URL . 'assets/js/frontend.js',
			array('jquery'),
			TRYLOOM_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'tryloom-frontend',
			'tryloom_params',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('tryloom'),
				'primary_color' => get_option('tryloom_primary_color', ''),
				'show_popup_errors' => get_option('tryloom_show_popup_errors', 'no') === 'yes',
				'save_photos_setting' => get_option('tryloom_save_photos', 'yes'),
				'hide_variations' => get_option('tryloom_hide_variations', 'no') === 'yes',
				'plugin_url' => TRYLOOM_PLUGIN_URL,
				'store_name' => get_bloginfo('name'),
				'i18n' => array(
					'error' => __('An error occurred. Please try again.', 'tryloom'),
					'upload_image' => __('Please upload an image first.', 'tryloom'),
					'select_variant' => __('Please select a product variant.', 'tryloom'),
					'no_history' => __('You haven\'t tried on any products yet.', 'tryloom'),
					'click_or_drag' => __('Click or drag to upload', 'tryloom'),
					'remove_image' => __('Remove image', 'tryloom'),
					'upload_your_photo' => __('Upload your photo', 'tryloom'),
					'drag_and_drop' => __('or drag and drop here.', 'tryloom'),
					'loading_variations' => __('Loading variations...', 'tryloom'),
					'loading_step_1' => __('Measuring your virtual avatar...', 'tryloom'),
					'loading_step_2' => __('Selecting the fabric...', 'tryloom'),
					'loading_step_3' => __('Stitching the garment...', 'tryloom'),
					'loading_step_4' => __('Applying lighting and shadows...', 'tryloom'),
					'loading_step_5' => __('Finalizing your look...', 'tryloom'),
					'loading_step_6' => __('Almost there...', 'tryloom'),
					'success_message' => __('Your look is ready!', 'tryloom'),
				),
			)
		);
	}

	/**
	 * Get file path from URL using WordPress upload directory.
	 *
	 * @param string $image_url The URL of the image.
	 * @return string|false File path on success, false on failure.
	 */
	private function get_file_path_from_url($image_url)
	{
		if (empty($image_url)) {
			return false;
		}

		// Try to get attachment ID and file path
		$attachment_id = attachment_url_to_postid($image_url);
		if ($attachment_id) {
			$file_path = get_attached_file($attachment_id);
			if ($file_path && file_exists($file_path)) {
				return $file_path;
			}
		}

		// Try to extract path from uploads directory URL
		$upload_dir = wp_upload_dir();
		$upload_base_url = $upload_dir['baseurl'];
		$upload_base_dir = $upload_dir['basedir'];

		// Check if URL is within uploads directory
		if (strpos($image_url, $upload_base_url) === 0) {
			$relative_path = str_replace($upload_base_url, '', $image_url);
			// Remove query string if present
			$relative_path = strtok($relative_path, '?');
			$file_path = $upload_base_dir . $relative_path;
			if (file_exists($file_path)) {
				return $file_path;
			}
		}

		return false;
	}

	/**
	 * Create custom directory for try-on images with date-based subfolders.
	 *
	 * @return array Array with 'path' and 'url' keys for the custom directory.
	 */
	private function create_custom_directory()
	{
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'] . '/tryloom';
		$base_url = $upload_dir['baseurl'] . '/tryloom';

		// Create base directory if it doesn't exist
		if (!file_exists($base_dir)) {
			wp_mkdir_p($base_dir);
			$this->create_directory_protection($base_dir);
		}

		// Create date-based subfolder (YYYY/MM) to prevent folder bloat
		$year = gmdate('Y');
		$month = gmdate('m');
		$date_dir = $base_dir . '/' . $year . '/' . $month;
		$date_url = $base_url . '/' . $year . '/' . $month;

		if (!file_exists($date_dir)) {
			wp_mkdir_p($date_dir);
			// Add silence file to year folder
			$this->create_directory_protection($base_dir . '/' . $year);
			// Add silence file to month folder
			$this->create_directory_protection($date_dir);
		}

		return array(
			'path' => $date_dir,
			'url' => $date_url,
		);
	}

	/**
	 * Create index.php to prevent directory listing.
	 *
	 * @param string $dir_path Directory path.
	 */
	private function create_directory_protection($dir_path)
	{
		// Create index.php to prevent directory listing (Silence is golden)
		$index_file = $dir_path . '/index.php';
		if (!file_exists($index_file)) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Simple file creation
			file_put_contents($index_file, "<?php\n// Silence is golden.");
		}
		// Note: No .htaccess is created. Security relies on UUID filenames.
	}

	/**
	 * Generate a secure random filename (32-char hex string).
	 *
	 * @param string $extension File extension (e.g., 'png').
	 * @return string Random filename like 'a3f8d1b6c4e29a7b1f3e5d8c2a4b6e9f.png'
	 */
	private function generate_secure_filename($extension = 'png')
	{
		// Generate 16 random bytes = 32 hex characters
		if (function_exists('random_bytes')) {
			$random = bin2hex(random_bytes(16));
		} else {
			// Fallback for older PHP versions
			$random = wp_generate_password(32, false, false);
		}
		return strtolower($random) . '.' . $extension;
	}

	/**
	 * Handle AJAX request to upload photo.
	 */
	public function ajax_upload_photo()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			wp_send_json_error(array('message' => __('Try-on feature is disabled.', 'tryloom')));
		}

		// Check nonce.
		if (!check_ajax_referer('tryloom', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid nonce.', 'tryloom')));
		}

		// Check if file is uploaded.
		if (!isset($_FILES['image'])) {
			wp_send_json_error(array('message' => __('No image file uploaded.', 'tryloom')));
		}

		// Check if we should save photos to server
		$save_to_server = ('yes' === get_option('tryloom_save_photos', 'yes'));

		// Handle file upload manually to avoid Media Library
		if (!function_exists('wp_handle_upload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$file = $_FILES['image']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file['name'] = sanitize_file_name($file['name']);

		// Validate file type
		$file_type = wp_check_filetype($file['name']);
		$allowed_types = array('jpg', 'jpeg', 'png', 'webp');
		if (!in_array($file_type['ext'], $allowed_types)) {
			wp_send_json_error(array('message' => __('Invalid file type. Allowed types: jpg, jpeg, png, webp', 'tryloom')));
		}

		// Strict MIME type check to prevent masked files
		if (function_exists('mime_content_type')) {
			$mime_type = mime_content_type($file['tmp_name']);
			$allowed_mimes = array('image/jpeg', 'image/png', 'image/webp');
			if (!in_array($mime_type, $allowed_mimes)) {
				wp_send_json_error(array('message' => __('Invalid file content. Must be a valid image.', 'tryloom')));
			}
		}

		// Size Check: Limit to ~11.8MB ( 5 * 1536 * 1536 )
		// This protects memory usage during later processing
		$max_size_bytes = 5 * 1536 * 1536;
		if ($file['size'] > $max_size_bytes) {
			/* translators: %s: Maximum file size */
			wp_send_json_error(array('message' => sprintf(__('File too large. Maximum size is %s.', 'tryloom'), size_format($max_size_bytes))));
		}

		// Get user ID early for filename
		$user_id = get_current_user_id();

		// Use custom directory
		$upload_result = $this->create_custom_directory();
		$upload_dir = $upload_result['path'];
		$upload_dir_url = $upload_result['url'];

		// Create a protected filename pattern: tryloom-{userid}-upload-{timestamp}.{ext}
		$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
		$protected_filename = 'tryloom-' . $user_id . '-upload-' . time() . '.' . $file_ext;
		$new_file = $upload_dir . '/' . $protected_filename;

		// Move uploaded file
		// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
		if (move_uploaded_file($file['tmp_name'], $new_file)) {
			// Build direct URL - the random filename and date-based path provide security
			$file_url = $upload_dir_url . '/' . $protected_filename;
		} else {
			wp_send_json_error(array('message' => __('Failed to save uploaded file.', 'tryloom')));
		}

		// Strict Single Photo Policy & Database Saving
		// $user_id already defined above
		if ($user_id) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'tryloom_user_photos';

			// Always delete ANY existing photo for this user first (Strict Replacement)
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for user photos
			$existing_photo = $wpdb->get_row($wpdb->prepare(
				"SELECT id, attachment_id, image_url FROM " . esc_sql($table_name) . " WHERE user_id = %d",
				$user_id
			));

			if ($existing_photo) {
				// Delete legacy attachment if needed
				if (!empty($existing_photo->attachment_id)) {
					wp_delete_attachment((int) $existing_photo->attachment_id, true);
				}
				// Delete physical file of the PREVIOUS photo
				if (!empty($existing_photo->image_url)) {
					$old_file_path = $this->get_file_path_from_url($existing_photo->image_url);
					// Important: Don't delete the NEW file we just uploaded if URLs happen to collide (unlikely due to wp_unique_filename)
					if ($old_file_path && file_exists($old_file_path) && $old_file_path !== $new_file) {
						if (!function_exists('wp_delete_file')) {
							require_once ABSPATH . 'wp-admin/includes/file.php';
						}
						wp_delete_file($old_file_path);
					}
				}
				// Delete from DB
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for user photos
				$wpdb->delete($table_name, array('id' => $existing_photo->id));
			}

			// Only save the NEW photo to DB if requested or forced
			if ($save_to_server) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$table_name,
					array(
						'user_id' => $user_id,
						'attachment_id' => 0,
						'image_url' => $file_url,
						'is_default' => 1,
						'manually_set_default' => 0,
						'created_at' => current_time('mysql'),
						'last_used' => current_time('mysql'),
					),
					array('%d', '%d', '%s', '%d', '%d', '%s', '%s')
				);
			}
		}

		wp_send_json_success(array(
			'file_url' => $file_url,
			'saved_to_server' => $save_to_server
		));
	}



	/**
	 * Handle AJAX request to generate try-on image.
	 */
	public function ajax_generate_try_on()
	{
		// Increase time limit for API call (image processing happens on Google's servers)
		if (function_exists('set_time_limit')) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for API timeout
			@set_time_limit(60);
		}

		try {
			// Check if try-on is enabled.
			if ('yes' !== get_option('tryloom_enabled', 'yes')) {
				wp_send_json_error(array('message' => __('Try-on feature is disabled.', 'tryloom')));
			}

			// Check nonce.
			if (!check_ajax_referer('tryloom', 'nonce', false)) {
				wp_send_json_error(array('message' => __('Invalid nonce.', 'tryloom')));
			}

			// Check if required parameters are set.
			if (!isset($_POST['product_id'])) {
				wp_send_json_error(array('message' => __('Missing product ID.', 'tryloom')));
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
			$using_default_photo = isset($_POST['using_default_photo']) && 'yes' === sanitize_text_field(wp_unslash($_POST['using_default_photo']));
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
			$uploaded_file_url = isset($_POST['uploaded_file_url']) ? sanitize_text_field(wp_unslash($_POST['uploaded_file_url'])) : '';
			$using_uploaded_file = !empty($uploaded_file_url);
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
			$save_photo = isset($_POST['save_photo']) ? sanitize_text_field(wp_unslash($_POST['save_photo'])) : 'no';

			if (!$using_default_photo && !$using_uploaded_file) {
				wp_send_json_error(array('message' => __('Missing image file.', 'tryloom')));
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
			$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
			$variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;

			// Check if product exists.
			$product = wc_get_product($product_id);
			if (!$product) {
				wp_send_json_error(array('message' => __('Invalid product.', 'tryloom')));
			}

			// Check if variation exists if provided.
			$variation = null;
			if ($variation_id > 0) {
				$variation = wc_get_product($variation_id);
				if (!$variation || $variation->get_parent_id() !== $product_id) {
					wp_send_json_error(array('message' => __('Invalid variation.', 'tryloom')));
				}
			}

			// Check if user has reached generation limit.
			$user_id = get_current_user_id();
			$user = get_userdata($user_id);

			// 1. Get global default limit
			$generation_limit = absint(get_option('tryloom_generation_limit', 10));

			// 2. Check for Role-Based Overrides
			$role_limits = get_option('tryloom_role_limits', array());

			if (!empty($role_limits) && $user && !empty($user->roles)) {
				$highest_role_limit = -1; // Keep track of the highest limit found

				foreach ($user->roles as $role) {
					if (isset($role_limits[$role])) {
						$role_limit = absint($role_limits[$role]);
						if ($role_limit > $highest_role_limit) {
							$highest_role_limit = $role_limit;
						}
					}
				}

				// If we found a valid override, apply it
				if ($highest_role_limit >= 0) {
					$generation_limit = $highest_role_limit;
				}
			}

			$time_period = get_option('tryloom_time_period', 'hour');

			if ($generation_limit > 0) {
				// Get current usage from user meta
				$usage_count = (int) get_user_meta($user_id, 'tryloom_usage_count', true);
				$last_reset = get_user_meta($user_id, 'tryloom_last_reset_date', true);

				// Calculate what the date/time string should be for the current period
				$current_period_identifier = '';
				$tz = wp_timezone();
				$dt = new DateTime('now', $tz);

				switch ($time_period) {
					case 'hour':
						// E.g. "2026-02-20 10" (resets at the top of each hour)
						$current_period_identifier = wp_date('Y-m-d H');
						$dt->modify('+1 hour')->setTime((int)$dt->format('H'), 0, 0);
						break;
					case 'day':
						// E.g. "2026-02-20" (resets at local midnight)
						$current_period_identifier = wp_date('Y-m-d');
						$dt->modify('+1 day')->setTime(0, 0, 0);
						break;
					case 'week':
						// E.g. "2026-08" (Year and week number)
						$current_period_identifier = wp_date('Y-W');
						$dt->modify('next monday')->setTime(0, 0, 0);
						break;
					case 'month':
						// E.g. "2026-02"
						$current_period_identifier = wp_date('Y-m');
						$dt->modify('first day of next month')->setTime(0, 0, 0);
						break;
					default:
						$current_period_identifier = wp_date('Y-m-d H');
						$dt->modify('+1 hour')->setTime((int)$dt->format('H'), 0, 0);
				}
				$reset_time_iso = $dt->format('c');

				// If the period identifier doesn't match the last reset, reset the counter
				if ($last_reset !== $current_period_identifier) {
					$usage_count = 0;
					update_user_meta($user_id, 'tryloom_last_reset_date', $current_period_identifier);
					update_user_meta($user_id, 'tryloom_usage_count', $usage_count);
				}

				if ($usage_count >= $generation_limit) {
					$upsell_url = get_option('tryloom_limit_upsell_url', '');
					wp_send_json_error(array(
						'message' => __('You have reached your generation limit.', 'tryloom'),
						'error_code' => 'limit_exceeded',
						'reset_time' => $reset_time_iso,
						'upsell_url' => $upsell_url
					));
				}
			}

			// Check if using default photo.
			$user_photo_url = '';

			if ($using_default_photo) {
				// Get default photo URL.
				global $wpdb;
				$table_name = $wpdb->prefix . 'tryloom_user_photos';
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
				$default_photo = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM ' . esc_sql($table_name) . ' WHERE user_id = %d AND is_default = 1 LIMIT 1',
						$user_id
					)
				);

				if (!$default_photo) {
					wp_send_json_error(array('message' => __('No default photo found.', 'tryloom')));
				}

				$user_photo_url = $default_photo->image_url;
			} elseif ($using_uploaded_file) {
				$user_photo_url = esc_url_raw($uploaded_file_url);
			}

			// Check if subscription ended
			$subscription_ended = get_option('tryloom_subscription_ended', 'no');
			if ('yes' === $subscription_ended) {
				wp_send_json_error(array('message' => __('Your subscription has ended. Please renew to continue using this feature.', 'tryloom')));
			}

			// Prepare API request.
			$api = new Tryloom_API();
			$request_data = array(
				'product_id' => $product_id,
				'variation_id' => $variation_id,
				'user_photo' => $user_photo_url,
			);

			// Send request to API.
			$api_response = $api->send_request('generate', $request_data);

			if (is_wp_error($api_response)) {
				$error_code = $api_response->get_error_code();

				// Handle specific error codes
				if ('free_trial_ended' === $error_code) {
					wp_send_json_error(array('message' => __('Your free trial ended. Please buy a subscription to continue use this feature.', 'tryloom')));
				}

				wp_send_json_error(array('message' => $api_response->get_error_message()));
			}

			if (!isset($api_response['success']) || !$api_response['success'] || !isset($api_response['data']['image_base64']) || empty($api_response['data']['image_base64'])) {
				wp_send_json_error(array('message' => __('Invalid API response or missing image data.', 'tryloom')));
			}

			$image_base64 = $api_response['data']['image_base64'];
			$image_data = base64_decode($image_base64);

			if (false === $image_data) {
				wp_send_json_error(array('message' => __('Failed to decode generated image.', 'tryloom')));
			}

			// Create a secure random filename (32-char hex for unguessable URLs)
			$filename = $this->generate_secure_filename('png');

			// Create custom directory with date-based subfolders
			$custom_result = $this->create_custom_directory();
			$custom_dir = $custom_result['path'];
			$custom_dir_url = $custom_result['url'];

			// Initialize filesystem API if needed.
			global $wp_filesystem;
			if (empty($wp_filesystem)) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if (empty($wp_filesystem)) {
				wp_send_json_error(array('message' => __('Failed to initialize filesystem.', 'tryloom')));
			}

			// Save the image to custom directory via WP_Filesystem.
			$file_path = $custom_dir . '/' . $filename;
			$file_saved = $wp_filesystem->put_contents($file_path, $image_data, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);

			// Verify the file was saved successfully
			if (false === $file_saved || !file_exists($file_path)) {
				wp_send_json_error(array('message' => __('Failed to save generated image.', 'tryloom')));
			}

			// Verify file is readable
			if (!is_readable($file_path)) {
				wp_send_json_error(array('message' => __('Generated image file is not readable.', 'tryloom')));
			}

			// Build direct URL - the 32-char random filename IS the security (unguessable)
			$generated_image_url = $custom_dir_url . '/' . $filename;

			// Save try-on history and create attachment based on history setting.
			$enable_history = get_option('tryloom_enable_history', 'yes');
			$attachment_id = 0;

			if ('yes' === $enable_history) {
				// Create an attachment for the media library only if history is enabled.
				$attachment = array(
					'guid' => $generated_image_url,
					'post_mime_type' => 'image/png',
					// translators: %s: Product name.
					'post_title' => sprintf(__('Try On for %s', 'tryloom'), $product->get_name()),
					'post_content' => '',
					'post_status' => 'inherit',
				);

				$attachment_id = wp_insert_attachment($attachment, $file_path, 0, true);
				if (!is_wp_error($attachment_id)) {
					// Mark this as a try-on image
					update_post_meta($attachment_id, '_tryloom_image', 'yes');

					require_once ABSPATH . 'wp-admin/includes/image.php';
					$attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
					wp_update_attachment_metadata($attachment_id, $attachment_data);
				}

				// Save to history.
				global $wpdb;
				$table_name = $wpdb->prefix . 'tryloom_history';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Using $wpdb->insert() with prepared format strings
				$wpdb->insert(
					$table_name,
					array(
						'user_id' => $user_id,
						'product_id' => $product_id,
						'variation_id' => $variation_id,
						'user_image_url' => $user_photo_url,
						'generated_image_url' => $generated_image_url,
						'created_at' => current_time('mysql'),
					),
					array(
						'%d',
						'%d',
						'%d',
						'%s',
						'%s',
						'%s',
					)
				);
			} else {
				// If history is disabled, schedule deletion of generated image after 5 minutes.
				// The file exists but no attachment is created in media library.
				wp_schedule_single_event(time() + (5 * 60), 'tryloom_delete_generated_image', array($generated_image_url, $attachment_id));
			}

			// Check if we should delete the user photo.
			if (!$using_default_photo) {
				$save_photos_setting = get_option('tryloom_save_photos', 'yes');

				if ('no' === $save_photos_setting) {
					// Delete the file using WordPress upload directory.
					$file_path = $this->get_file_path_from_url($user_photo_url);
					if ($file_path && file_exists($file_path)) {
						wp_delete_file($file_path);
					}

					// Delete the attachment.
					$attachment_id = attachment_url_to_postid($user_photo_url);
					if ($attachment_id) {
						wp_delete_attachment($attachment_id, true);
					}
				}
			}

			// Log if enabled.
			if ('yes' === get_option('tryloom_enable_logging', 'no')) {
				$log_message = sprintf(
					'Try-on generated for user %d, product %d, variation %d. Generated image: %s',
					$user_id,
					$product_id,
					$variation_id,
					$generated_image_url
				);
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log('[WooCommerce Try On] ' . $log_message);
			}

			// Increment usage limit after a successful generation
			if ($generation_limit > 0) {
				$current_usage = (int) get_user_meta($user_id, 'tryloom_usage_count', true);
				update_user_meta($user_id, 'tryloom_usage_count', $current_usage + 1);
			}

			// Generate custom filename.
			$store_name = sanitize_title(get_bloginfo('name'));
			$user = wp_get_current_user();
			$username = $user->user_login ? sanitize_title($user->user_login) : 'guest';
			$unique_number = time() . '-' . wp_rand(1000, 9999);
			$filename = $store_name . '-try-on-by-toolteek-for-' . $username . '-' . $unique_number . '.png';

			// Return success response.
			wp_send_json_success(
				array(
					'image_url' => $generated_image_url,
					'filename' => $filename,
					'product_id' => $product_id,
					'variation_id' => $variation_id,
				)
			);

		} catch (Throwable $e) {
			// Log the error
			if ('yes' === get_option('tryloom_enable_logging', 'no')) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Controlled by logging setting
				error_log('[WooCommerce Try On] Critical Error in Generation: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
			}

			wp_send_json_error(array(
				'message' => __('An internal error occurred. Please try again.', 'tryloom'),
				'debug' => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : ''
			));
		}
	}

	/**
	 * Handle AJAX request to delete user photo.
	 */
	public function ajax_delete_photo()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			wp_send_json_error(array('message' => __('Try-on feature is disabled.', 'tryloom')));
		}

		// Check nonce.
		if (!check_ajax_referer('tryloom', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid nonce.', 'tryloom')));
		}

		// Check if user is logged in.
		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => __('You must be logged in to delete photos.', 'tryloom')));
		}

		// Check if photo ID is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		if (!isset($_POST['photo_id'])) {
			wp_send_json_error(array('message' => __('Missing photo ID.', 'tryloom')));
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		$photo_id = isset($_POST['photo_id']) ? intval($_POST['photo_id']) : 0;
		if (!$photo_id) {
			wp_send_json_error(array('message' => __('Invalid photo ID.', 'tryloom')));
		}
		$user_id = get_current_user_id();

		// Get photo.
		global $wpdb;
		$table_name = $wpdb->prefix . 'tryloom_user_photos';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$photo = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . esc_sql($table_name) . ' WHERE id = %d AND user_id = %d',
				$photo_id,
				$user_id
			)
		);

		if (!$photo) {
			wp_send_json_error(array('message' => __('Photo not found or you do not have permission to delete it.', 'tryloom')));
		}

		// Delete photo from database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->delete() with prepared format strings
		$wpdb->delete(
			$table_name,
			array(
				'id' => $photo_id,
			),
			array(
				'%d',
			)
		);

		// Delete the file using WordPress upload directory.
		$file_path = $this->get_file_path_from_url($photo->image_url);
		if ($file_path && file_exists($file_path)) {
			if (!function_exists('wp_delete_file')) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			wp_delete_file($file_path);
		}

		// Delete the attachment.
		$attachment_id = attachment_url_to_postid($photo->image_url);
		if ($attachment_id) {
			wp_delete_attachment($attachment_id, true);
		}

		// If this was the default photo, set another photo as default if available.
		if ($photo->is_default) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
			$new_default = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . esc_sql($table_name) . ' WHERE user_id = %d ORDER BY created_at DESC LIMIT 1',
					$user_id
				)
			);

			if ($new_default) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->update() with prepared format strings
				$wpdb->update(
					$table_name,
					array(
						'is_default' => 1,
					),
					array(
						'id' => $new_default->id,
					),
					array(
						'%d',
					),
					array(
						'%d',
					)
				);
			}
		}

		// Return success response.
		wp_send_json_success();
	}

	/**
	 * Handle AJAX request to set default photo.
	 */
	public function ajax_set_default_photo()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			wp_send_json_error(array('message' => __('Try-on feature is disabled.', 'tryloom')));
		}

		// Check nonce.
		if (!check_ajax_referer('tryloom', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid nonce.', 'tryloom')));
		}

		// Check if user is logged in.
		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => __('You must be logged in to set default photo.', 'tryloom')));
		}

		// Check if photo ID is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		if (!isset($_POST['photo_id'])) {
			wp_send_json_error(array('message' => __('Missing photo ID.', 'tryloom')));
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		$photo_id = isset($_POST['photo_id']) ? intval($_POST['photo_id']) : 0;
		if (!$photo_id) {
			wp_send_json_error(array('message' => __('Invalid photo ID.', 'tryloom')));
		}
		$user_id = get_current_user_id();

		// Get photo.
		global $wpdb;
		$table_name = $wpdb->prefix . 'tryloom_user_photos';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$photo = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . esc_sql($table_name) . ' WHERE id = %d AND user_id = %d',
				$photo_id,
				$user_id
			)
		);

		if (!$photo) {
			wp_send_json_error(array('message' => __('Photo not found or you do not have permission to set it as default.', 'tryloom')));
		}

		// Reset all photos to non-default.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->update() with prepared format strings
		$wpdb->update(
			$table_name,
			array(
				'is_default' => 0,
				'manually_set_default' => 0,
			),
			array('user_id' => $user_id),
			array('%d', '%d'),
			array('%d')
		);

		// Set the selected photo as default and mark as manually set.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->update() with prepared format strings
		$wpdb->update(
			$table_name,
			array(
				'is_default' => 1,
				'manually_set_default' => 1,
			),
			array(
				'id' => $photo_id,
			),
			array('%d', '%d'),
			array('%d')
		);

		// Return success response.
		wp_send_json_success();
	}

	/**
	 * Handle AJAX request to delete history item.
	 */
	public function ajax_delete_history()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			wp_send_json_error(array('message' => __('Try-on feature is disabled.', 'tryloom')));
		}

		// Check nonce.
		if (!check_ajax_referer('tryloom', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid nonce.', 'tryloom')));
		}

		// Check if user is logged in.
		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => __('You must be logged in to delete history.', 'tryloom')));
		}

		// Check if history ID is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		if (!isset($_POST['history_id'])) {
			wp_send_json_error(array('message' => __('Missing history ID.', 'tryloom')));
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		$history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : 0;
		$user_id = get_current_user_id();

		// Delete from database.
		global $wpdb;
		$table_name = $wpdb->prefix . 'tryloom_history';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->delete() with prepared format strings
		$wpdb->delete(
			$table_name,
			array(
				'id' => $history_id,
				'user_id' => $user_id,
			),
			array('%d', '%d')
		);

		wp_send_json_success();
	}

	/**
	 * Handle AJAX request to delete all history.
	 */
	public function ajax_delete_all_history()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			wp_send_json_error(array('message' => __('Try-on feature is disabled.', 'tryloom')));
		}

		// Check nonce.
		if (!check_ajax_referer('tryloom', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid nonce.', 'tryloom')));
		}

		// Check if user is logged in.
		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => __('You must be logged in to delete history.', 'tryloom')));
		}

		$user_id = get_current_user_id();

		// Get all user's history to delete associated images.
		global $wpdb;
		$table_name = $wpdb->prefix . 'tryloom_history';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$history_records = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . esc_sql($table_name) . ' WHERE user_id = %d',
				$user_id
			)
		);

		// Delete each generated image from media library.
		foreach ($history_records as $record) {
			if (!empty($record->generated_image_url)) {
				$attachment_id = attachment_url_to_postid($record->generated_image_url);
				if ($attachment_id) {
					wp_delete_attachment($attachment_id, true);
				}

				// Delete from filesystem using WordPress upload directory
				$file_path = $this->get_file_path_from_url($record->generated_image_url);
				if ($file_path && file_exists($file_path)) {
					if (!function_exists('wp_delete_file')) {
						require_once ABSPATH . 'wp-admin/includes/file.php';
					}
					wp_delete_file($file_path);
				}
			}
		}

		// Delete all user's history from database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->delete() with prepared format strings
		$wpdb->delete(
			$table_name,
			array('user_id' => $user_id),
			array('%d')
		);

		wp_send_json_success();
	}

	/**
	 * Handle AJAX request to upload account photo.
	 */
	public function ajax_upload_account_photo()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			wp_send_json_error(array('message' => __('Try-on feature is disabled.', 'tryloom')));
		}

		// Check nonce.
		if (!check_ajax_referer('tryloom', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid nonce.', 'tryloom')));
		}

		// Check if user is logged in.
		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => __('You must be logged in.', 'tryloom')));
		}

		// Check if file is uploaded.
		if (!isset($_FILES['image'])) {
			wp_send_json_error(array('message' => __('No image file uploaded.', 'tryloom')));
		}

		$set_as_default = isset($_POST['set_as_default']) && 'yes' === $_POST['set_as_default'];
		$user_id = get_current_user_id();

		// Handle file upload.
		if (!function_exists('wp_handle_upload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$upload_overrides = array('test_form' => false);
		$file = wp_handle_upload($_FILES['image'], $upload_overrides);

		if (isset($file['error'])) {
			wp_send_json_error(array('message' => $file['error']));
		}

		$file_url = $file['url'];

		// Save to media library.
		$attachment = array(
			'post_mime_type' => $file['type'],
			'post_title' => isset($_FILES['image']['name']) ? sanitize_file_name($_FILES['image']['name']) : '',
			'post_content' => '',
			'post_status' => 'inherit',
		);

		$attachment_id = wp_insert_attachment($attachment, $file['file']);

		if (!is_wp_error($attachment_id)) {
			// Mark this as a try-on image
			update_post_meta($attachment_id, '_tryloom_image', 'yes');

			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attachment_data = wp_generate_attachment_metadata($attachment_id, $file['file']);
			wp_update_attachment_metadata($attachment_id, $attachment_data);
		}

		// Save to database.
		global $wpdb;
		$table_name = $wpdb->prefix . 'tryloom_user_photos';
		$old_attachment_id = null;

		// If setting as default, delete old permanent default and unset all defaults.
		if ($set_as_default) {
			// Get old permanent default to delete it.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
			$old_permanent_default = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . esc_sql($table_name) . ' WHERE user_id = %d AND is_default = 1 AND manually_set_default = 1 LIMIT 1',
					$user_id
				)
			);

			if ($old_permanent_default) {
				$old_attachment_id = $old_permanent_default->attachment_id;
				// Delete old permanent default from database.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->delete() with prepared format strings
				$wpdb->delete(
					$table_name,
					array('id' => $old_permanent_default->id),
					array('%d')
				);
			}

			// Unset all defaults.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->update() with prepared format strings
			$wpdb->update(
				$table_name,
				array(
					'is_default' => 0,
					'manually_set_default' => 0,
				),
				array('user_id' => $user_id),
				array('%d', '%d'),
				array('%d')
			);

			// Delete old attachment from media library.
			if ($old_attachment_id) {
				wp_delete_attachment($old_attachment_id, true);
			}
		}

		// Insert photo.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Using $wpdb->insert() with prepared format strings
		$wpdb->insert(
			$table_name,
			array(
				'user_id' => $user_id,
				'attachment_id' => $attachment_id,
				'image_url' => $file_url,
				'is_default' => $set_as_default ? 1 : 0,
				'manually_set_default' => $set_as_default ? 1 : 0,
				'created_at' => current_time('mysql'),
				'last_used' => current_time('mysql'),
			),
			array('%d', '%d', '%s', '%d', '%d', '%s', '%s')
		);

		wp_send_json_success(
			array(
				'photo_id' => $wpdb->insert_id,
				'image_url' => $file_url,
			)
		);
	}

	/**
	 * Handle AJAX request to get product variations.
	 */
	public function ajax_get_variations()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			wp_send_json_error(array('message' => __('Try-on feature is disabled.', 'tryloom')));
		}

		// Check nonce.
		if (!check_ajax_referer('tryloom', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid nonce.', 'tryloom')));
		}

		// Check if product ID is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		if (!isset($_POST['product_id'])) {
			wp_send_json_error(array('message' => __('Missing product ID.', 'tryloom')));
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

		// Try to get cached variations first (1 hour cache).
		$cache_key = 'tryloom_variations_' . $product_id;
		$cached_variations = get_transient($cache_key);
		$variations_to_send = array();

		if (false !== $cached_variations && is_array($cached_variations)) {
			// Hydrate the dynamic prices from cache
			foreach ($cached_variations as $cached_var) {
				$variation = wc_get_product($cached_var['variation_id']);
				if (!$variation) {
					continue;
				}
				$variations_to_send[] = array(
					'variation_id' => $cached_var['variation_id'],
					'variation_description' => wc_get_formatted_variation($variation, true, true, false),
					'image' => $cached_var['image'],
					'price_html' => $variation->get_price_html(),
					'attributes' => $cached_var['attributes'],
				);
			}
			wp_send_json_success(array('variations' => $variations_to_send));
			return;
		}

		$product = wc_get_product($product_id);

		if (!$product || !$product->is_type('variable')) {
			wp_send_json_success(array('variations' => array()));
			return;
		}

		$variations_to_cache = array();
		$seen_images = array();

		foreach ($product->get_available_variations() as $variation_data) {
			$variation = wc_get_product($variation_data['variation_id']);
			if (!$variation) {
				continue;
			}

			// Feature 3: Visual variation deduplication filtering (by color/image)
			$image_url = isset($variation_data['image']['url']) ? $variation_data['image']['url'] : (isset($variation_data['image']['src']) ? $variation_data['image']['src'] : '');

			if (!empty($image_url)) {
				if (in_array($image_url, $seen_images, true)) {
					continue;
				}
				$seen_images[] = $image_url;
			}

			// Store raw immutable data in cache
			$variations_to_cache[] = array(
				'variation_id' => $variation_data['variation_id'],
				'image' => $variation_data['image'],
				'attributes' => $variation_data['attributes'],
			);

			// Store formatted dynamic data for immediate response
			$variations_to_send[] = array(
				'variation_id' => $variation_data['variation_id'],
				'variation_description' => wc_get_formatted_variation($variation, true, true, false),
				'image' => $variation_data['image'],
				'price_html' => $variation->get_price_html(),
				'attributes' => $variation_data['attributes'],
			);
		}

		// Cache the raw immutable variations for 1 hour.
		set_transient($cache_key, $variations_to_cache, HOUR_IN_SECONDS);

		wp_send_json_success(array('variations' => $variations_to_send));
	}

	/**
	 * Invalidate variations cache when a product is saved.
	 *
	 * @param int $product_id Product ID.
	 */
	public function invalidate_variations_cache($product_id)
	{
		delete_transient('tryloom_variations_' . $product_id);
	}

	/**
	 * Invalidate parent product's variations cache when stock is updated.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function invalidate_cache_on_stock_change($product)
	{
		if ($product && is_a($product, 'WC_Product')) {
			$product_id = $product->get_id();
			if ($product->is_type('variation')) {
				$parent_id = $product->get_parent_id();
				if ($parent_id) {
					delete_transient('tryloom_variations_' . $parent_id);
				}
			} else {
				delete_transient('tryloom_variations_' . $product_id);
			}
		}
	}

	public function invalidate_parent_variations_cache($variation_id, $loop = 0)
	{
		$variation = wc_get_product($variation_id);
		if ($variation && $variation->is_type('variation')) {
			$parent_id = $variation->get_parent_id();
			if ($parent_id) {
				delete_transient('tryloom_variations_' . $parent_id);
			}
		}
	}

	/**
	 * Handle AJAX request to get product.
	 */
	public function ajax_get_product()
	{
		// Check if try-on is enabled.
		if ('yes' !== get_option('tryloom_enabled', 'yes')) {
			wp_send_json_error(array('message' => __('Try-on feature is disabled.', 'tryloom')));
		}

		// Nonce check (added for security)
		if (!check_ajax_referer('tryloom', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid nonce.', 'tryloom')));
		}

		// Check product ID.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		if (!isset($_POST['product_id'])) {
			wp_send_json_error(array('message' => __('Product ID is required.', 'tryloom')));
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
		$product = wc_get_product($product_id);

		if (!$product) {
			wp_send_json_error(array('message' => __('Product not found.', 'tryloom')));
		}

		$product_data = array(
			'id' => $product->get_id(),
			'name' => $product->get_name(),
			'price' => $product->get_price(),
			'price_html' => $product->get_price_html(),
			'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
		);

		wp_send_json_success($product_data);
	}

	/**
	 * Exclude try-on images from media library AJAX queries.
	 *
	 * @param array $query Query arguments.
	 * @return array
	 */
	public function exclude_try_on_images_from_media_library($query)
	{
		// This filter is specifically for media library AJAX queries
		// No need to check get_current_screen() as it returns null during AJAX

		// Exclude attachments with meta key indicating they are try-on images
		if (!isset($query['meta_query'])) {
			$query['meta_query'] = array();
		}

		$query['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key' => '_tryloom_image',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key' => '_tryloom_image',
				'value' => 'yes',
				'compare' => '!=',
			),
		);
		return $query;
	}

	/**
	 * Exclude try-on images from media library queries.
	 *
	 * @param WP_Query $query Query object.
	 * @return WP_Query
	 */
	public function exclude_try_on_images_from_media_library_query($query)
	{
		// Only affect admin media library queries
		if (!is_admin() || !$query->is_main_query()) {
			return $query;
		}

		global $pagenow;
		if ('upload.php' !== $pagenow) {
			return $query;
		}

		// Exclude attachments with meta key indicating they are try-on images
		$meta_query = $query->get('meta_query');
		if (!is_array($meta_query)) {
			$meta_query = array();
		}
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key' => '_tryloom_image',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key' => '_tryloom_image',
				'value' => 'yes',
				'compare' => '!=',
			),
		);
		$query->set('meta_query', $meta_query);
		return $query;
	}
}