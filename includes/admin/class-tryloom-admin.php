<?php
/**
 * TryLoom Admin.
 *
 * @package TryLoom
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Tryloom_Admin Class.
 */
class Tryloom_Admin
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// Add admin menu.
		add_action('admin_menu', array($this, 'add_admin_menu'));

		// Register settings.
		add_action('admin_init', array($this, 'register_settings'));

		// Handle clear all history.
		add_action('admin_post_tryloom_clear_all_history', array($this, 'clear_all_history'));

		// Handle delete user photos.
		add_action('admin_post_tryloom_delete_user_photos', array($this, 'delete_user_photos'));

		// Add settings link.
		add_filter('plugin_action_links_' . TRYLOOM_PLUGIN_BASENAME, array($this, 'add_settings_link'));

		// Add admin scripts and styles.
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

		// Add dashboard widget for statistics.
		add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));

		// Add admin notices.
		add_action('admin_notices', array($this, 'admin_notices'));

		// AJAX handler for async subscription status check (Bug 4: prevents admin freeze).
		add_action('wp_ajax_tryloom_check_subscription', array($this, 'ajax_check_subscription'));
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu()
	{
		add_menu_page(
			__('Try On Settings', 'tryloom'),
			__('TryLoom', 'tryloom'),
			'manage_options',
			'tryloom-settings',
			array($this, 'settings_page'),
			plugin_dir_url(__FILE__) . '/icon.png',
			30
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings()
	{
		// Register settings sections.
		add_settings_section(
			'tryloom_general_section',
			__('General Settings', 'tryloom'),
			array($this, 'general_section_callback'),
			'tryloom-settings-general'
		);

		add_settings_section(
			'tryloom_appearance_section',
			__('Appearance Settings', 'tryloom'),
			array($this, 'appearance_section_callback'),
			'tryloom-settings-appearance'
		);

		add_settings_section(
			'tryloom_access_section',
			__('Access & Limits', 'tryloom'),
			array($this, 'access_section_callback'),
			'tryloom-settings-access'
		);

		add_settings_section(
			'tryloom_privacy_section',
			__('Privacy & User', 'tryloom'),
			array($this, 'privacy_section_callback'),
			'tryloom-settings-privacy'
		);

		add_settings_section(
			'tryloom_advanced_section',
			__('Advanced Settings', 'tryloom'),
			array($this, 'advanced_section_callback'),
			'tryloom-settings-advanced'
		);

		// General Tab Fields
		register_setting('tryloom-settings-group-general', 'tryloom_platform_key', array('sanitize_callback' => array($this, 'sanitize_platform_key'), 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-general', 'tryloom_enabled', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-general', 'tryloom_try_on_method', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-general', 'tryloom_button_placement', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-general', 'tryloom_allowed_categories', array('sanitize_callback' => array($this, 'sanitize_array'), 'capability' => 'manage_options'));

		// Appearance Tab Fields
		register_setting('tryloom-settings-group-appearance', 'tryloom_theme_color', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-appearance', 'tryloom_primary_color', array('sanitize_callback' => 'sanitize_hex_color', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-appearance', 'tryloom_retry_button', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-appearance', 'tryloom_hide_variations', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-appearance', 'tryloom_custom_button_css', array('sanitize_callback' => 'wp_strip_all_tags', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-appearance', 'tryloom_custom_popup_css', array('sanitize_callback' => 'wp_strip_all_tags', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-appearance', 'tryloom_custom_account_css', array('sanitize_callback' => 'wp_strip_all_tags', 'capability' => 'manage_options'));

		// Access Tab Fields
		register_setting('tryloom-settings-group-access', 'tryloom_allowed_user_roles', array('sanitize_callback' => array($this, 'sanitize_array'), 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-access', 'tryloom_generation_limit', array('sanitize_callback' => 'absint', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-access', 'tryloom_time_period', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-access', 'tryloom_role_limits', array('sanitize_callback' => array($this, 'sanitize_role_limits_array'), 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-access', 'tryloom_limit_upsell_url', array('sanitize_callback' => 'esc_url_raw', 'capability' => 'manage_options'));

		// Privacy Tab Fields
		register_setting('tryloom-settings-group-privacy', 'tryloom_save_photos', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-privacy', 'tryloom_delete_photos_days', array('sanitize_callback' => 'absint', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-privacy', 'tryloom_enable_history', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));

		// Advanced Tab Fields
		register_setting('tryloom-settings-group-advanced', 'tryloom_enable_logging', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-advanced', 'tryloom_show_popup_errors', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-advanced', 'tryloom_admin_user_roles', array('sanitize_callback' => array($this, 'sanitize_array'), 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-advanced', 'tryloom_remove_data_on_delete', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-advanced', 'tryloom_turnstile_enabled', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-advanced', 'tryloom_turnstile_site_key', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));
		register_setting('tryloom-settings-group-advanced', 'tryloom_turnstile_secret_key', array('sanitize_callback' => 'sanitize_text_field', 'capability' => 'manage_options'));

		// General Settings Field Callbacks
		add_settings_field('tryloom_platform_key', __('Platform Key', 'tryloom'), array($this, 'platform_key_callback'), 'tryloom-settings-general', 'tryloom_general_section');
		add_settings_field('tryloom_enabled', __('Enable TryLoom', 'tryloom'), array($this, 'enabled_callback'), 'tryloom-settings-general', 'tryloom_general_section');
		add_settings_field('tryloom_try_on_method', __('Try-On Method', 'tryloom'), array($this, 'try_on_method_callback'), 'tryloom-settings-general', 'tryloom_general_section');
		add_settings_field('tryloom_button_placement', __('Button Placement', 'tryloom'), array($this, 'button_placement_callback'), 'tryloom-settings-general', 'tryloom_general_section');
		add_settings_field('tryloom_allowed_categories', __('Allowed Categories', 'tryloom'), array($this, 'allowed_categories_callback'), 'tryloom-settings-general', 'tryloom_general_section');

		// Appearance Settings Field Callbacks
		add_settings_field('tryloom_theme_color', __('Theme Color', 'tryloom'), array($this, 'theme_color_callback'), 'tryloom-settings-appearance', 'tryloom_appearance_section');
		add_settings_field('tryloom_primary_color', __('Primary Button Color', 'tryloom'), array($this, 'primary_color_callback'), 'tryloom-settings-appearance', 'tryloom_appearance_section');
		add_settings_field('tryloom_retry_button', __('Show Retry Button', 'tryloom'), array($this, 'retry_button_callback'), 'tryloom-settings-appearance', 'tryloom_appearance_section');
		add_settings_field('tryloom_hide_variations', __('Hide Variations', 'tryloom'), array($this, 'hide_variations_callback'), 'tryloom-settings-appearance', 'tryloom_appearance_section');
		add_settings_field('tryloom_custom_button_css', __('Custom Button CSS', 'tryloom'), array($this, 'custom_button_css_callback'), 'tryloom-settings-appearance', 'tryloom_appearance_section');
		add_settings_field('tryloom_custom_popup_css', __('Custom Popup CSS', 'tryloom'), array($this, 'custom_popup_css_callback'), 'tryloom-settings-appearance', 'tryloom_appearance_section');
		add_settings_field('tryloom_custom_account_css', __('Custom Account Page CSS', 'tryloom'), array($this, 'custom_account_css_callback'), 'tryloom-settings-appearance', 'tryloom_appearance_section');

		// Access Settings Field Callbacks
		add_settings_field('tryloom_allowed_user_roles', __('Allowed User Roles', 'tryloom'), array($this, 'allowed_user_roles_callback'), 'tryloom-settings-access', 'tryloom_access_section');
		add_settings_field('tryloom_generation_limit', __('Generation Limit', 'tryloom'), array($this, 'generation_limit_callback'), 'tryloom-settings-access', 'tryloom_access_section');
		add_settings_field('tryloom_time_period', __('Time Period', 'tryloom'), array($this, 'time_period_callback'), 'tryloom-settings-access', 'tryloom_access_section');
		add_settings_field('tryloom_role_limits', __('Advanced Limits', 'tryloom'), array($this, 'role_limits_callback'), 'tryloom-settings-access', 'tryloom_access_section');
		add_settings_field('tryloom_limit_upsell_url', __('Limit Exceeded Upsell URL', 'tryloom'), array($this, 'limit_upsell_url_callback'), 'tryloom-settings-access', 'tryloom_access_section');

		// Privacy Settings Field Callbacks
		add_settings_field('tryloom_save_photos', __('Save User Photos', 'tryloom'), array($this, 'save_photos_callback'), 'tryloom-settings-privacy', 'tryloom_privacy_section');
		add_settings_field('tryloom_delete_photos_days', __('Delete Photos After (Days)', 'tryloom'), array($this, 'delete_photos_days_callback'), 'tryloom-settings-privacy', 'tryloom_privacy_section');
		add_settings_field('tryloom_enable_history', __('Enable History', 'tryloom'), array($this, 'enable_history_callback'), 'tryloom-settings-privacy', 'tryloom_privacy_section');
		add_settings_field('tryloom_privacy_policy_statement', __('Privacy Policy Statement', 'tryloom'), array($this, 'privacy_policy_statement_callback'), 'tryloom-settings-privacy', 'tryloom_privacy_section');

		// Advanced Settings Field Callbacks
		add_settings_field('tryloom_enable_logging', __('Enable Logging', 'tryloom'), array($this, 'enable_logging_callback'), 'tryloom-settings-advanced', 'tryloom_advanced_section');
		add_settings_field('tryloom_show_popup_errors', __('Show Browser Popup Errors', 'tryloom'), array($this, 'show_popup_errors_callback'), 'tryloom-settings-advanced', 'tryloom_advanced_section');
		add_settings_field('tryloom_admin_user_roles', __('Admin Access Roles', 'tryloom'), array($this, 'admin_user_roles_callback'), 'tryloom-settings-advanced', 'tryloom_advanced_section');
		add_settings_field('tryloom_remove_data_on_delete', __('Remove Data on Uninstall', 'tryloom'), array($this, 'remove_data_on_delete_callback'), 'tryloom-settings-advanced', 'tryloom_advanced_section');
		add_settings_field('tryloom_turnstile_enabled', __('Enable Cloudflare Turnstile', 'tryloom'), array($this, 'turnstile_enabled_callback'), 'tryloom-settings-advanced', 'tryloom_advanced_section');
		add_settings_field('tryloom_turnstile_site_key', __('Turnstile Site Key', 'tryloom'), array($this, 'turnstile_site_key_callback'), 'tryloom-settings-advanced', 'tryloom_advanced_section');
		add_settings_field('tryloom_turnstile_secret_key', __('Turnstile Secret Key', 'tryloom'), array($this, 'turnstile_secret_key_callback'), 'tryloom-settings-advanced', 'tryloom_advanced_section');
	}

	/**
	 * Sanitize array.
	 *
	 * @param array $input Input array.
	 * @return array
	 */
	public function sanitize_array($input)
	{
		if (!is_array($input)) {
			return array();
		}

		$sanitized_input = array();

		foreach ($input as $key => $value) {
			// For category IDs, we want to preserve numeric values
			if (is_numeric($value)) {
				$sanitized_input[sanitize_text_field($key)] = absint($value);
			} else {
				$sanitized_input[sanitize_text_field($key)] = sanitize_text_field($value);
			}
		}

		return $sanitized_input;
	}



	/**
	 * Sanitize role limits array.
	 *
	 * @param array $input Input array.
	 * @return array
	 */
	public function sanitize_role_limits_array($input)
	{
		if (!is_array($input)) {
			return array();
		}

		$sanitized_input = array();

		foreach ($input as $role_key => $limit) {
			// Ensure role key is safe (alphanumeric and underscores usually)
			$safe_key = sanitize_key($role_key);

			// If explicitly left blank, don't save. It falls back to global.
			if (trim($limit) === '') {
				continue;
			}

			// Save the numeric limit. Allowing 0 explicitly blocks the role.
			$sanitized_input[$safe_key] = absint($limit);
		}

		return $sanitized_input;
	}



	/**
	 * Sanitize platform key.
	 * Also clears free trial ended flag if paid key is added.
	 *
	 * @param string $input Platform key input.
	 * @return string
	 */
	public function sanitize_platform_key($input)
	{
		$sanitized = sanitize_text_field($input);
		$current_key = get_option('tryloom_platform_key', '');

		// Only perform actions if the key is actually changing
		if (!empty($sanitized) && $sanitized !== $current_key) {
			// Reset the subscription ended flag for new key
			update_option('tryloom_subscription_ended', 'no');

			// Clear any previous error messages
			delete_option('tryloom_free_trial_error');

			// Clear usage stats only when key changes to force fresh check
			delete_option('tryloom_usage_used');
			delete_option('tryloom_usage_limit');
		}

		return $sanitized;
	}

	/**
	 * General section callback.
	 */
	public function general_section_callback()
	{
		echo '<p>' . esc_html__('Configure core settings and view your current API usage.', 'tryloom') . '</p>';
	}

	/**
	 * Appearance section callback.
	 */
	public function appearance_section_callback()
	{
		echo '<p>' . esc_html__('Customize the look and feel of the TryLoom interface.', 'tryloom') . '</p>';
	}

	/**
	 * Access section callback.
	 */
	public function access_section_callback()
	{
		echo '<p>' . esc_html__('Control who can use the feature and set tier limits.', 'tryloom') . '</p>';
	}

	/**
	 * Privacy section callback.
	 */
	public function privacy_section_callback()
	{
		echo '<p>' . esc_html__('Manage user data retention, photos, and history.', 'tryloom') . '</p>';
	}

	/**
	 * Role Limits callback.
	 */
	public function role_limits_callback()
	{
		$roles = wp_roles()->get_names();
		$role_limits = get_option('tryloom_role_limits', array());
		?>
		<details class="tryloom-admin__settings-details">
			<summary class="tryloom-admin__settings-summary">
				<?php esc_html_e('Show Role-Based Limits', 'tryloom'); ?>
			</summary>
			<div class="tryloom-admin__settings-desc-box">
				<p class="description">
					<?php esc_html_e('Override the base generation limit for specific roles. Leave blank to use the global limit. Users with multiple roles will receive the highest limit they qualify for.', 'tryloom'); ?>
				</p>
				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ($roles as $role_key => $role_name): ?>
							<tr>
								<th scope="row" class="tryloom-admin__settings-th">
									<label><?php echo esc_html(translate_user_role($role_name)); ?></label>
								</th>
								<td class="tryloom-admin__settings-td">
									<input type="number" name="tryloom_role_limits[<?php echo esc_attr($role_key); ?>]"
										value="<?php echo isset($role_limits[$role_key]) ? esc_attr($role_limits[$role_key]) : ''; ?>"
										min="0" step="1" placeholder="<?php esc_attr_e('Default', 'tryloom'); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</details>
		<p class="description">
			<?php esc_html_e('Optional: Provide unique limits based on user role. This will override the global limit above.', 'tryloom'); ?>
		</p>
		<?php
	}

	/**
	 * Advanced section callback.
	 */
	public function advanced_section_callback()
	{
		echo '<p>' . esc_html__('Developer-level options and diagnostic controls.', 'tryloom') . '</p>';
	}

	/**
	 * Enabled callback.
	 */
	public function enabled_callback()
	{
		$enabled = get_option('tryloom_enabled', 'yes');
		?>
		<select name="tryloom_enabled">
			<option value="yes" <?php selected($enabled, 'yes'); ?>><?php esc_html_e('Yes', 'tryloom'); ?></option>
			<option value="no" <?php selected($enabled, 'no'); ?>><?php esc_html_e('No', 'tryloom'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Enable or disable the Try On feature.', 'tryloom'); ?></p>
		<?php
	}

	/**
	 * Try-On Method callback.
	 */
	public function try_on_method_callback()
	{
		$value = get_option('tryloom_try_on_method', 'tryon');
		?>
		<div class="tryloom-radio-group">
			<label class="tryloom-radio-label">
				<input type="radio" name="tryloom_try_on_method" value="auto" <?php checked($value, 'auto'); ?>>
				<span class="tryloom-admin__help-tip">
					<?php esc_html_e('Auto', 'tryloom'); ?>
					<span class="tryloom-admin__tooltip-content">
						<?php esc_html_e('Smart AI automatically selects the best mode for customer photo to ensure quality and usability.', 'tryloom'); ?>
						<br><a
							href="https://gettryloom.com/docs/general-faq/what-is-the-difference-between-try-on-mode-and-studio-mode/"
							target="_blank"><?php esc_html_e('Learn more', 'tryloom'); ?></a>
					</span>
				</span>
			</label>

			<label class="tryloom-radio-label">
				<input type="radio" name="tryloom_try_on_method" value="tryon" <?php checked($value, 'tryon'); ?>>
				<span class="tryloom-admin__help-tip">
					<?php esc_html_e('Try-On', 'tryloom'); ?>
					<span class="tryloom-admin__tooltip-content">
						<?php esc_html_e('Maximum realism. Preserves exact facial features, fabric textures and background.', 'tryloom'); ?>
						<br><a
							href="https://gettryloom.com/docs/general-faq/what-is-the-difference-between-try-on-mode-and-studio-mode/"
							target="_blank"><?php esc_html_e('Learn more', 'tryloom'); ?></a>
					</span>
				</span>
			</label>

			<label class="tryloom-radio-label">
				<input type="radio" name="tryloom_try_on_method" value="studio" <?php checked($value, 'studio'); ?>>
				<span class="tryloom-admin__help-tip">
					<?php esc_html_e('Studio', 'tryloom'); ?>
					<span class="tryloom-admin__tooltip-content">
						<?php esc_html_e('Fastest option. Creates high-quality, studio-lit images with professional lighting and background.', 'tryloom'); ?>
						<br><a
							href="https://gettryloom.com/docs/general-faq/what-is-the-difference-between-try-on-mode-and-studio-mode/"
							target="_blank"><?php esc_html_e('Learn more', 'tryloom'); ?></a>
					</span>
				</span>
			</label>
		</div>
		<p class="description"><?php esc_html_e('Select the method used for virtual try-on processing.', 'tryloom'); ?></p>
		<?php
	}

	/**
	 * Platform key callback.
	 */
	public function platform_key_callback()
	{
		$platform_key = get_option('tryloom_platform_key', '');
		?>
		<input type="text" name="tryloom_platform_key" value="<?php echo esc_attr($platform_key); ?>" class="regular-text" />
		<p class="description">
			<?php
			echo wp_kses_post(
				__('By default, you are on the free plan. Enter your TryLoom platform key for more freedom. <a href="https://gettryloom.com/my-account" target="_blank">Get your key here</a>.', 'tryloom')
			);
			?>
		</p>
		<?php
	}


	/**
	 * Allowed categories callback.
	 */
	public function allowed_categories_callback()
	{
		$allowed_categories = get_option('tryloom_allowed_categories', array());
		$product_categories = get_terms(array(
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
		));
		?>
		<select name="tryloom_allowed_categories[]" multiple="multiple" class="wc-enhanced-select tryloom-admin__select-wide">
			<?php
			foreach ($product_categories as $category) {
				$selected = in_array($category->term_id, $allowed_categories, true) ? 'selected="selected"' : '';
				echo '<option value="' . esc_attr($category->term_id) . '" ' . esc_attr($selected) . '>' . esc_html($category->name) . '</option>';
			}
			?>
		</select>
		<p class="description">
			<?php esc_html_e('Choose which product categories will display the Try-On button. Leave empty to enable for all categories.', 'tryloom'); ?>
		</p>
		<?php
	}

	/**
	 * Button placement callback.
	 */
	public function button_placement_callback()
	{
		$button_placement = get_option('tryloom_button_placement', 'default');
		?>
		<select name="tryloom_button_placement">
			<option value="default" <?php selected($button_placement, 'default'); ?>>
				<?php esc_html_e('Default WooCommerce Product Page', 'tryloom'); ?>
			</option>
			<option value="shortcode" <?php selected($button_placement, 'shortcode'); ?>>
				<?php esc_html_e('Shortcode Only', 'tryloom'); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e('Choose where the Try-On button appears.', 'tryloom'); ?>
			<?php if ('shortcode' === $button_placement): ?>
				<br />
				<?php esc_html_e('Use shortcode: ', 'tryloom'); ?><code>[tryloom]</code>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Theme color callback.
	 */
	public function theme_color_callback()
	{
		$theme_color = get_option('tryloom_theme_color', 'light');
		?>
		<select name="tryloom_theme_color">
			<option value="light" <?php selected($theme_color, 'light'); ?>><?php esc_html_e('Light', 'tryloom'); ?>
			</option>
			<option value="dark" <?php selected($theme_color, 'dark'); ?>><?php esc_html_e('Dark', 'tryloom'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Choose the theme color for the Try On popup.', 'tryloom'); ?></p>
		<?php
	}

	/**
	 * Primary color callback.
	 */
	public function primary_color_callback()
	{
		$primary_color = get_option('tryloom_primary_color', '');
		?>
		<input type="text" name="tryloom_primary_color" value="<?php echo esc_attr($primary_color); ?>"
			class="tryloom-admin__color-picker" data-default-color="#552FBC" />
		<p class="description">
			<?php esc_html_e('Set the main color used for Try-On buttons and UI highlights. Leave empty to inherit WooCommerce Add to Cart styling.', 'tryloom'); ?>
		</p>
		<?php
	}

	/**
	 * Retry button callback.
	 */
	public function retry_button_callback()
	{
		$retry_button = get_option('tryloom_retry_button', 'yes');
		?>
		<select name="tryloom_retry_button">
			<option value="yes" <?php selected($retry_button, 'yes'); ?>><?php esc_html_e('Yes', 'tryloom'); ?></option>
			<option value="no" <?php selected($retry_button, 'no'); ?>><?php esc_html_e('No', 'tryloom'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Show or hide the retry button in the Try-On popup.', 'tryloom'); ?></p>
		<?php
	}

	/**
	 * Hide variations callback.
	 */
	public function hide_variations_callback()
	{
		$hide_variations = get_option('tryloom_hide_variations', 'no');
		?>
		<select name="tryloom_hide_variations">
			<option value="yes" <?php selected($hide_variations, 'yes'); ?>><?php esc_html_e('Yes', 'tryloom'); ?></option>
			<option value="no" <?php selected($hide_variations, 'no'); ?>><?php esc_html_e('No', 'tryloom'); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e('If yes, the variation selector is hidden and standard main image testing is defaulted.', 'tryloom'); ?>
		</p>
		<?php
	}



	/**
	 * Privacy Policy Statement callback.
	 * Generates a suggested privacy policy text based on Save Photos and Enable History settings.
	 */
	public function privacy_policy_statement_callback()
	{
		$save_photos = get_option('tryloom_save_photos', 'yes');
		$enable_history = get_option('tryloom_enable_history', 'yes');

		$base_text = __('To provide our Virtual Try-On service, we securely transmit the photos you upload to a third-party Artificial Intelligence (AI) service provider. This provider processes your image transiently solely to generate the try-on result. Your photos are not retained by the AI provider, nor are they used to train any AI models.', 'tryloom');

		$scenario_a = __('To improve your shopping experience, we securely store your uploaded photos and generated try-on images on our servers. This allows you to quickly try on items and view your Try-On history. You can manage or delete your saved images at any time through your My Account dashboard.', 'tryloom');
		$scenario_b = __('We adhere to a strict minimal-retention policy on our servers. Your original uploaded photos are deleted immediately after processing, and the generated try-on images are automatically and permanently deleted from our servers shortly after your session is complete.', 'tryloom');
		$scenario_c = __('We securely store your original uploaded photos on our servers to streamline your future try-on sessions. However, your Try-On history is not tracked, and the generated try-on result images are automatically deleted from our servers shortly after your session is complete.', 'tryloom');
		$scenario_d = __('Your original uploaded photos are processed securely and deleted immediately. However, to allow you to view your Try-On history, the final generated try-on images are securely stored on our servers. You can manage or delete these generated images at any time through your My Account dashboard.', 'tryloom');

		// Determine initial dynamic text based on current settings.
		if ($save_photos === 'yes' && $enable_history === 'yes') {
			$dynamic_text = $scenario_a;
		} elseif ($save_photos === 'no' && $enable_history === 'no') {
			$dynamic_text = $scenario_b;
		} elseif ($save_photos === 'yes' && $enable_history === 'no') {
			$dynamic_text = $scenario_c;
		} else {
			$dynamic_text = $scenario_d; // Save OFF, History ON
		}
		?>
		<div class="tryloom-admin__privacy-statement" style="max-width: 700px;">
			<p class="description" style="margin-bottom: 8px;">
				<?php esc_html_e('Please add the following statement to your store\'s official Privacy Policy page:', 'tryloom'); ?>
			</p>
			<textarea id="tryloom_privacy_statement_textarea" rows="12" class="large-text code" readonly
				style="background:#f9f9f9; cursor: text; color: #333;"><?php echo esc_textarea($base_text . "\n\n" . $dynamic_text); ?></textarea>
			<p class="description" style="margin-top: 6px;">
				<?php esc_html_e('This text updates automatically based on your "Save User Photos" and "Enable History" settings above.', 'tryloom'); ?>
			</p>
		</div>
		<script type="text/javascript">
			(function ($) {
				var baseText = <?php echo wp_json_encode($base_text); ?>;
				var scenarioA = <?php echo wp_json_encode($scenario_a); ?>;
				var scenarioB = <?php echo wp_json_encode($scenario_b); ?>;
				var scenarioC = <?php echo wp_json_encode($scenario_c); ?>;
				var scenarioD = <?php echo wp_json_encode($scenario_d); ?>;

				function updatePrivacyStatement() {
					var savePhotos = $('[name="tryloom_save_photos"]').val();
					var enableHistory = $('[name="tryloom_enable_history"]').val();
					var dynamicText;

					if (savePhotos === 'yes' && enableHistory === 'yes') {
						dynamicText = scenarioA;
					} else if (savePhotos === 'no' && enableHistory === 'no') {
						dynamicText = scenarioB;
					} else if (savePhotos === 'yes' && enableHistory === 'no') {
						dynamicText = scenarioC;
					} else {
						dynamicText = scenarioD;
					}

					$('#tryloom_privacy_statement_textarea').val(baseText + '\n\n' + dynamicText);
				}

				$(document).ready(function () {
					$('[name="tryloom_save_photos"], [name="tryloom_enable_history"]').on('change', updatePrivacyStatement);
				});
			}(jQuery));
		</script>
		<?php
	}

	/**
	 * Save photos callback.
	 */
	public function save_photos_callback()
	{
		$save_photos = get_option('tryloom_save_photos', 'yes');

		// Calculate stats for User Photos
		$stats = $this->get_storage_stats('tryloom_user_photos', 'image_url');
		$has_photos = $stats['count'] > 0;
		?>
		<select name="tryloom_save_photos">
			<option value="yes" <?php selected($save_photos, 'yes'); ?>><?php esc_html_e('Yes', 'tryloom'); ?></option>
			<option value="no" <?php selected($save_photos, 'no'); ?>><?php esc_html_e('No', 'tryloom'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Choose whether user photos should be saved on the server.', 'tryloom'); ?>
		</p>

		<?php
		// Always show the button container, but handle empty state
		?>
		<div class="tryloom-admin__settings-desc-box">
			<button type="button" id="tryloom_delete_photos_btn" class="button button-secondary" <?php echo !$has_photos ? 'disabled' : ''; ?>>
				<?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-trash.php'; ?>
				<?php esc_html_e('Delete Saved User Photos', 'tryloom'); ?>
			</button>
			<span class="tryloom-admin__italic-hint">
				<?php
				/* translators: 1: Number of photos, 2: Total size of photos */
				printf(esc_html__('(Saved: %1$d photos | Size: %2$s)', 'tryloom'), esc_html($stats['count']), esc_html($this->format_size($stats['size'])));
				?>
			</span>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('#tryloom_delete_photos_btn').on('click', function (e) {
						e.preventDefault();
						if ($(this).attr('disabled')) return;
						if (confirm('<?php echo esc_js(__('Are you sure you want to delete all saved user photos? This cannot be undone.', 'tryloom')); ?>')) {
							// Create a form outside of the existing one to avoid nesting issues
							var form = $('<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">' +
								'<input type="hidden" name="action" value="tryloom_delete_user_photos" />' +
								'<input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('tryloom_delete_user_photos')); ?>" />' +
								'</form>');
							$('body').append(form);
							form.submit();
						}
					});
				});
			</script>
		</div>
		<?php
	}

	/**
	 * Generation limit callback.
	 */
	public function generation_limit_callback()
	{
		$generation_limit = get_option('tryloom_generation_limit', 10);
		?>
		<input type="number" name="tryloom_generation_limit" value="<?php echo esc_attr($generation_limit); ?>" min="1"
			step="1" />
		<p class="description"><?php esc_html_e('Set the maximum number of Try-On generations per user.', 'tryloom'); ?></p>
		<?php
	}

	/**
	 * Time period callback.
	 */
	public function time_period_callback()
	{
		$time_period = get_option('tryloom_time_period', 'hour');
		?>
		<select name="tryloom_time_period">
			<option value="hour" <?php selected($time_period, 'hour'); ?>><?php esc_html_e('Hour', 'tryloom'); ?></option>
			<option value="day" <?php selected($time_period, 'day'); ?>><?php esc_html_e('Day', 'tryloom'); ?></option>
			<option value="week" <?php selected($time_period, 'week'); ?>><?php esc_html_e('Week', 'tryloom'); ?></option>
			<option value="month" <?php selected($time_period, 'month'); ?>><?php esc_html_e('Month', 'tryloom'); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e('Select the time period for the generation limit (Hour / Day / Week / Month).', 'tryloom'); ?>
		</p>
		<?php
	}

	/**
	 * Delete photos days callback.
	 */
	public function delete_photos_days_callback()
	{
		$delete_photos_days = get_option('tryloom_delete_photos_days', 30);
		?>
		<input type="number" name="tryloom_delete_photos_days" value="<?php echo esc_attr($delete_photos_days); ?>" min="1"
			step="1" />
		<p class="description">
			<?php esc_html_e("Automatically deletes user photos and generated results older than this limit.", 'tryloom'); ?>
		</p>
		<?php
	}

	/**
	 * Allowed user roles callback.
	 */
	public function allowed_user_roles_callback()
	{
		$allowed_user_roles = get_option('tryloom_allowed_user_roles', array('customer'));
		$roles = get_editable_roles();
		?>
		<select name="tryloom_allowed_user_roles[]" multiple="multiple" class="wc-enhanced-select tryloom-admin__select-wide">
			<?php
			foreach ($roles as $role_key => $role) {
				$selected = in_array($role_key, $allowed_user_roles, true) ? 'selected="selected"' : '';
				echo '<option value="' . esc_attr($role_key) . '" ' . esc_attr($selected) . '>' . esc_html(translate_user_role($role['name'])) . '</option>';
			}
			?>
		</select>
		<p class="description"><?php esc_html_e('Select which user roles can use the Try-On feature.', 'tryloom'); ?></p>
		<?php
	}

	/**
	 * Limit upsell url callback.
	 */
	public function limit_upsell_url_callback()
	{
		$limit_upsell_url = get_option('tryloom_limit_upsell_url', '');
		?>
		<input type="url" name="tryloom_limit_upsell_url" value="<?php echo esc_attr($limit_upsell_url); ?>"
			class="regular-text" placeholder="https://" />
		<p class="description">
			<?php esc_html_e('URL to redirect users or link to when they reach their generation limit (e.g., pricing page).', 'tryloom'); ?>
		</p>
		<?php
	}

	/**
	 * Enable logging callback.
	 */
	public function enable_logging_callback()
	{
		$enable_logging = get_option('tryloom_enable_logging', 'no');
		?>
		<select name="tryloom_enable_logging">
			<option value="yes" <?php selected($enable_logging, 'yes'); ?>><?php esc_html_e('Yes', 'tryloom'); ?></option>
			<option value="no" <?php selected($enable_logging, 'no'); ?>><?php esc_html_e('No', 'tryloom'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Turn on TryLoom system logs for debugging.', 'tryloom'); ?></p>
		<?php
	}

	/**
	 * Admin user roles callback.
	 */
	public function admin_user_roles_callback()
	{
		$admin_user_roles = get_option('tryloom_admin_user_roles', array('administrator', 'shop_manager'));
		$roles = get_editable_roles();
		?>
		<select name="tryloom_admin_user_roles[]" multiple="multiple" class="wc-enhanced-select tryloom-admin__select-wide">
			<?php
			foreach ($roles as $role_key => $role) {
				$selected = in_array($role_key, $admin_user_roles, true) ? 'selected="selected"' : '';
				echo '<option value="' . esc_attr($role_key) . '" ' . esc_attr($selected) . '>' . esc_html(translate_user_role($role['name'])) . '</option>';
			}
			?>
		</select>
		<p class="description"><?php esc_html_e('Select which admin roles can access TryLoom settings.', 'tryloom'); ?></p>
		<?php
	}

	/**
	 * Show popup errors callback.
	 */
	public function show_popup_errors_callback()
	{
		$show_popup_errors = get_option('tryloom_show_popup_errors', 'no');
		?>
		<select name="tryloom_show_popup_errors">
			<option value="yes" <?php selected($show_popup_errors, 'yes'); ?>><?php esc_html_e('Yes', 'tryloom'); ?>
			</option>
			<option value="no" <?php selected($show_popup_errors, 'no'); ?>><?php esc_html_e('No', 'tryloom'); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e('Show frontend popup errors for missing keys, API failures, or other issues. Recommended only for debugging.', 'tryloom'); ?>
		</p>
		<?php
	}

	/**
	 * Remove data on delete callback.
	 */
	public function remove_data_on_delete_callback()
	{
		$remove_data = get_option('tryloom_remove_data_on_delete', 'no');
		?>
		<label>
			<input type="hidden" name="tryloom_remove_data_on_delete" value="no" />
			<input type="checkbox" name="tryloom_remove_data_on_delete" value="yes" <?php checked($remove_data, 'yes'); ?> />
			<?php esc_html_e('Remove all data when I delete the plugin', 'tryloom'); ?>
		</label>
		<p class="description">
			<?php esc_html_e('If checked, all TryLoom database tables, settings, and uploaded images will be permanently deleted when you delete the plugin from the Plugins screen.', 'tryloom'); ?>
		</p>
		<?php
	}

	/**
	 * Turnstile enabled callback.
	 */
	public function turnstile_enabled_callback()
	{
		$turnstile_enabled = get_option('tryloom_turnstile_enabled', 'no');
		?>
		<select name="tryloom_turnstile_enabled">
			<option value="yes" <?php selected($turnstile_enabled, 'yes'); ?>><?php esc_html_e('Yes', 'tryloom'); ?></option>
			<option value="no" <?php selected($turnstile_enabled, 'no'); ?>><?php esc_html_e('No', 'tryloom'); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e('Enable Cloudflare Turnstile to block bots from exploiting virtual try-on limits.', 'tryloom'); ?>
		</p>
		<?php
	}

	/**
	 * Turnstile site key callback.
	 */
	public function turnstile_site_key_callback()
	{
		$site_key = get_option('tryloom_turnstile_site_key', '');
		?>
		<input type="text" name="tryloom_turnstile_site_key" value="<?php echo esc_attr($site_key); ?>" class="regular-text" />
		<p class="description">
			<?php esc_html_e('Your Cloudflare Turnstile Site Key.', 'tryloom'); ?>
		</p>
		<?php
	}

	/**
	 * Turnstile secret key callback.
	 */
	public function turnstile_secret_key_callback()
	{
		$secret_key = get_option('tryloom_turnstile_secret_key', '');
		?>
		<input type="text" name="tryloom_turnstile_secret_key" value="<?php echo esc_attr($secret_key); ?>"
			class="regular-text" />
		<p class="description">
			<?php esc_html_e('Your Cloudflare Turnstile Secret Key.', 'tryloom'); ?><br>
			<strong><?php esc_html_e('Tip:', 'tryloom'); ?></strong>
			<?php esc_html_e('When creating your Turnstile widget in Cloudflare, we highly recommend choosing "Invisible" mode so it doesn\'t interrupt your real shoppers.', 'tryloom'); ?><br>
			<?php
			echo wp_kses_post(
				__('Don\'t have these keys? <a href="https://gettryloom.com/cloudflare-turnstile-setup-for-woocommerce/" target="_blank">Click here to read our 3-minute guide</a> on how to get your free Cloudflare Turnstile keys.', 'tryloom')
			);
			?>
		</p>
		<?php
	}

	/**
	 * Custom popup CSS callback.
	 */
	public function custom_popup_css_callback()
	{
		$css = get_option('tryloom_custom_popup_css', '');
		?>
		<textarea name="tryloom_custom_popup_css" rows="10" class="large-text code"><?php echo esc_textarea($css); ?></textarea>
		<p class="description">
			<?php esc_html_e('Add custom CSS for the TryLoom popup modal.', 'tryloom'); ?><br>
			<strong><?php esc_html_e('CSS Classes:', 'tryloom'); ?></strong>
			<code>.tryloom-popup</code>, <code>.tryloom-popup__content</code>,
			<code>.tryloom-popup__header</code>, <code>.tryloom-popup__body</code>,
			<code>.tryloom-popup__upload-area</code>, <code>.tryloom-popup__variations-container</code>,
			<code>.tryloom-popup__result</code>
		</p>
		<?php
	}

	/**
	 * Custom button CSS callback.
	 */
	public function custom_button_css_callback()
	{
		$css = get_option('tryloom_custom_button_css', '');
		?>
		<textarea name="tryloom_custom_button_css" rows="10"
			class="large-text code"><?php echo esc_textarea($css); ?></textarea>
		<p class="description">
			<?php esc_html_e('Add custom CSS for the Try-On button.', 'tryloom'); ?><br>
			<strong><?php esc_html_e('CSS Classes:', 'tryloom'); ?></strong>
			<code>.tryloom-button</code>
		</p>
		<?php
	}

	/**
	 * Custom account CSS callback.
	 */
	public function custom_account_css_callback()
	{
		$css = get_option('tryloom_custom_account_css', '');
		?>
		<textarea name="tryloom_custom_account_css" rows="10"
			class="large-text code"><?php echo esc_textarea($css); ?></textarea>
		<p class="description">
			<?php esc_html_e('Add custom CSS for the Try-On tab in the My Account page.', 'tryloom'); ?><br>
			<strong><?php esc_html_e('CSS Classes:', 'tryloom'); ?></strong>
			<code>.tryloom-account</code>, <code>.tryloom-account__photos</code>,
			<code>.tryloom-account__photo</code>, <code>.tryloom-account__history-table</code>
		</p>
		<?php
	}

	/**
	 * Enable history callback.
	 */
	public function enable_history_callback()
	{
		$enabled = get_option('tryloom_enable_history', 'yes');

		// Calculate stats for History
		$stats = $this->get_storage_stats('tryloom_history', 'generated_image_url');
		$has_history = $stats['count'] > 0;
		?>
		<select name="tryloom_enable_history">
			<option value="yes" <?php selected($enabled, 'yes'); ?>><?php esc_html_e('Yes', 'tryloom'); ?></option>
			<option value="no" <?php selected($enabled, 'no'); ?>><?php esc_html_e('No', 'tryloom'); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e('Enable or disable Try-On history tracking.', 'tryloom'); ?><br>
			<?php esc_html_e('When off: No history shown, generated images auto-delete after 5 minutes and no  Try-On tab in My Account.', 'tryloom'); ?><br>
		</p>
		<div class="tryloom-admin__settings-desc-box">
			<button type="button" id="tryloom_clear_history_btn" class="button button-secondary" <?php echo !$has_history ? 'disabled' : ''; ?>>
				<?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-trash.php'; ?>
				<?php esc_html_e('Clear All History', 'tryloom'); ?>
			</button>
			<span class="tryloom-admin__italic-hint">
				<?php
				/* translators: 1: Number of images, 2: Total size of images */
				printf(esc_html__('(Saved: %1$d images | Size: %2$s)', 'tryloom'), esc_html($stats['count']), esc_html($this->format_size($stats['size'])));
				?>
			</span>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('#tryloom_clear_history_btn').on('click', function (e) {
						e.preventDefault();
						if ($(this).attr('disabled')) return;
						if (confirm('<?php echo esc_js(__('Are you sure you want to clear all try-on history? This will permanently delete all generated images from the server.', 'tryloom')); ?>')) {
							// Create a form outside of the existing one to avoid nesting issues
							var form = $('<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">' +
								'<input type="hidden" name="action" value="tryloom_clear_all_history" />' +
								'<input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('tryloom_clear_all_history')); ?>" />' +
								'</form>');
							$('body').append(form);
							form.submit();
						}
					});
				});
			</script>
		</div>
		<?php
	}



	/**
	 * Add settings link on plugin page.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_settings_link($links)
	{
		$settings_link = '<a href="admin.php?page=tryloom-settings">' . __('Settings', 'tryloom') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
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
	 * Clear all try-on history.
	 */
	public function clear_all_history()
	{
		// Check nonce and permissions.
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'tryloom_clear_all_history') || !current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'tryloom'));
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'tryloom_history';

		// Get all history records to delete associated files.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$history_records = $wpdb->get_results('SELECT generated_image_url FROM ' . esc_sql($table_name) . " WHERE generated_image_url IS NOT NULL AND generated_image_url != ''");

		// Delete associated files.
		foreach ($history_records as $record) {
			if (!empty($record->generated_image_url)) {
				$file_path = $this->get_file_path_from_url($record->generated_image_url);
				if ($file_path && file_exists($file_path)) {
					wp_delete_file($file_path);
				}

				// Delete from media library.
				$attachment_id = attachment_url_to_postid($record->generated_image_url);
				if ($attachment_id) {
					wp_delete_attachment($attachment_id, true);
				}
			}
		}

		// Clear all history records.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$wpdb->query('DELETE FROM ' . esc_sql($table_name));

		// Redirect back with success message.
		wp_safe_redirect(add_query_arg(array('page' => 'tryloom-settings', 'history_cleared' => '1'), admin_url('admin.php')));
		exit;
	}

	/**
	 * Delete all saved user photos.
	 */
	public function delete_user_photos()
	{
		// Check nonce and permissions.
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'tryloom_delete_user_photos') || !current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'tryloom'));
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'tryloom_user_photos';

		// Get all photo records to delete associated files.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, not public data
		$photo_records = $wpdb->get_results('SELECT image_url, attachment_id FROM ' . esc_sql($table_name));

		// Delete associated files.
		foreach ($photo_records as $record) {

			// Delete from media library if attached
			if (!empty($record->attachment_id)) {
				wp_delete_attachment($record->attachment_id, true);
			}

			// Delete file from custom path if exists (and not already deleted by attachment delete)
			if (!empty($record->image_url)) {
				$file_path = $this->get_file_path_from_url($record->image_url);
				if ($file_path && file_exists($file_path)) {
					wp_delete_file($file_path);
				}
			}
		}

		// Delete from DB.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom table cleanup
		$wpdb->query("DELETE FROM " . esc_sql($table_name));

		// Redirect back with success message.
		wp_safe_redirect(add_query_arg(array('page' => 'tryloom-settings', 'message' => 'photos_deleted'), admin_url('admin.php')));
		exit;
	}

	/**
	 * Helper: Get storage stats (count and size) for a table's image column.
	 * 
	 * @param string $table_name Table name (without prefix).
	 * @param string $url_column Column name containing the image URL.
	 * @return array ['count' => int, 'size' => int]
	 */
	private function get_storage_stats($table_name_suffix, $url_column)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . $table_name_suffix;

		// Check table exists first to avoid errors during install/updates
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- System table check
		if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
			return array('count' => 0, 'size' => 0);
		}

		$safe_column = esc_sql($url_column);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Column name validated through esc_sql sanitization
		$urls = $wpdb->get_col("SELECT `$safe_column` FROM " . esc_sql($table_name) . " WHERE `$safe_column` IS NOT NULL AND `$safe_column` != ''");

		$count = 0;
		$size = 0;

		foreach ($urls as $url) {
			$path = $this->get_file_path_from_url($url);
			if ($path && file_exists($path)) {
				$count++;
				$size += filesize($path);
			}
		}

		return array('count' => count($urls), 'found_files_count' => $count, 'size' => $size);
	}

	/**
	 * Helper: Format bytes to human readable string.
	 * 
	 * @param int $bytes
	 * @param int $precision
	 * @return string
	 */
	private function format_size($bytes, $precision = 2)
	{
		$units = array('B', 'KB', 'MB', 'GB', 'TB');
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);
		return round($bytes, $precision) . ' ' . $units[$pow];
	}

	/**
	 * Display admin notices.
	 */
	public function admin_notices()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (isset($_GET['history_cleared']) && '1' === sanitize_text_field(wp_unslash($_GET['history_cleared']))) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('All try-on history has been cleared successfully.', 'tryloom') . '</p></div>';
		}

		// Show subscription ended notice on all admin pages (critical business notice).
		$subscription_ended = get_option('tryloom_subscription_ended', 'no');
		if ('yes' === $subscription_ended) {
			echo '<div class="notice notice-error is-dismissible"><p>' .
				wp_kses_post( __('Your TryLoom subscription has expired or payment failed.<br><strong>Your customers cannot see the Virtual Try-On button.</strong><br><a href="https://gettryloom.com/my-account/">Click here to renew now</a> to restore service immediately.', 'tryloom') ) .
				'</p></div>';
		}

		// Only show remaining notices on TryLoom settings page (performance optimization).
		$screen = get_current_screen();
		if (!$screen || ('toplevel_page_tryloom-settings' !== $screen->id && 'woocommerce_page_tryloom-settings' !== $screen->id)) {
			return;
		}

		// Warning if Cron is not working (only on TryLoom page now).
		if (!wp_next_scheduled('tryloom_check_account_status') || !wp_next_scheduled('tryloom_cleanup_inactive_users')) {
			echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__('TryLoom System Warning:', 'tryloom') . '</strong> ' . esc_html__('Scheduled tasks (WP-Cron) do not appear to be running. This may prevent automatic history cleanup and account status checks.', 'tryloom') . '</p></div>';
		}

		// Check for potential conflicts.
		$conflicts = array();

		// Check for JavaScript conflicts.
		if (wp_script_is('jquery-ui-dialog') || wp_script_is('fancybox')) {
			$conflicts[] = __('Popup/Modal library detected that may conflict with Try On popup functionality.', 'tryloom');
		}

		// Check for CSS conflicts - look for common optimization plugins.
		$active_plugins = get_option('active_plugins');
		if (is_array($active_plugins)) {
			foreach ($active_plugins as $plugin) {
				if (
					strpos($plugin, 'autoptimize') !== false ||
					strpos($plugin, 'wp-rocket') !== false ||
					strpos($plugin, 'w3-total-cache') !== false
				) {
					$conflicts[] = __('CSS/JS optimization plugin detected. If Try On features don\'t work properly, try clearing cache or excluding Try On files from optimization.', 'tryloom');
					break;
				}
			}
		}

		// Check for theme conflicts by looking for custom CSS that might override.
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$custom_css = $wpdb->get_var("SELECT post_content FROM {$wpdb->posts} WHERE post_type = 'custom_css' AND post_status = 'publish' LIMIT 1");
		if ($custom_css && (strpos($custom_css, '.tryloom') !== false || strpos($custom_css, 'try-on') !== false)) {
			$conflicts[] = __('Custom CSS targeting Try On elements detected in theme customizer. This may affect plugin styling.', 'tryloom');
		}

		// Check for AJAX conflicts.
		global $wp_filter;
		if (isset($wp_filter['wp_ajax_tryloom_generate']) && is_array($wp_filter['wp_ajax_tryloom_generate']) && count($wp_filter['wp_ajax_tryloom_generate']) > 1) {
			$conflicts[] = __('Another plugin is hooking into Try On AJAX actions. This may cause functionality issues.', 'tryloom');
		}

		// Display warnings if conflicts detected.
		if (!empty($conflicts)) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>' . esc_html__('TryLoom - Potential Conflicts Detected:', 'tryloom') . '</strong></p>';
			echo '<ul class="tryloom-admin__shortcode-list">';
			foreach ($conflicts as $conflict) {
				echo '<li>' . esc_html($conflict) . '</li>';
			}
			echo '</ul>';
			echo '<p>' . esc_html__('If you experience issues, try deactivating other plugins one by one to identify the conflict, or contact support.', 'tryloom') . '</p>';
			echo '</div>';
		}
	}


	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_scripts($hook)
	{
		// Only load assets on TryLoom settings page.
		if ('toplevel_page_tryloom-settings' !== $hook && 'woocommerce_page_tryloom-settings' !== $hook) {
			return;
		}



		// Enqueue WooCommerce Admin Styles (for SelectWoo/Select2).
		wp_enqueue_style('woocommerce_admin_styles');

		// Enqueue color picker.
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');

		// Enqueue media uploader.
		wp_enqueue_media();

		// Enqueue admin script.
		wp_enqueue_script(
			'tryloom-admin',
			TRYLOOM_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery', 'wp-color-picker', 'selectWoo'),
			time(), // Force reload
			true
		);

		// Localize admin script with AJAX data for async subscription check.
		wp_localize_script('tryloom-admin', 'tryloom_admin_params', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('tryloom_admin_nonce'),
			'check_subscription' => ('yes' === get_option('tryloom_subscription_ended', 'no')) ? '1' : '0',
		));

		// Enqueue admin style.
		wp_enqueue_style(
			'tryloom-admin',
			TRYLOOM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TRYLOOM_VERSION
		);
	}

	/**
	 * Add dashboard widget for statistics.
	 */
	public function add_dashboard_widget()
	{
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		wp_add_dashboard_widget(
			'tryloom_stats_widget',
			__('Try On Statistics', 'tryloom'),
			array($this, 'dashboard_widget_callback')
		);
	}

	/**
	 * Dashboard widget callback.
	 */
	public function dashboard_widget_callback()
	{
		// Try to get cached statistics first (3 hour cache).
		$stats = get_transient('tryloom_dashboard_stats');

		if (false === $stats) {
			// Cache miss - fetch fresh data from database.
			$stats = $this->fetch_dashboard_stats();

			// Cache for 3 hours.
			set_transient('tryloom_dashboard_stats', $stats, 3 * HOUR_IN_SECONDS);
		}

		// Extract cached values.
		$total_hour = $stats['total_hour'];
		$total_day = $stats['total_day'];
		$total_week = $stats['total_week'];
		$total_all = $stats['total_all'];
		$top_products = $stats['top_products'];

		// Display statistics.
		?>
		<div class="tryloom-stats">
			<div class="tryloom-stats-item">
				<h4><?php esc_html_e('Try-Ons in the Last Hour', 'tryloom'); ?></h4>
				<p class="tryloom-stats-number"><?php echo esc_html($total_hour); ?></p>
			</div>
			<div class="tryloom-stats-item">
				<h4><?php esc_html_e('Try-Ons in the Last Day', 'tryloom'); ?></h4>
				<p class="tryloom-stats-number"><?php echo esc_html($total_day); ?></p>
			</div>
			<div class="tryloom-stats-item">
				<h4><?php esc_html_e('Try-Ons in the Last Week', 'tryloom'); ?></h4>
				<p class="tryloom-stats-number"><?php echo esc_html($total_week); ?></p>
			</div>
			<div class="tryloom-stats-item">
				<h4><?php esc_html_e('Total Try-Ons', 'tryloom'); ?></h4>
				<p class="tryloom-stats-number"><?php echo esc_html($total_all); ?></p>
			</div>
		</div>

		<h4><?php esc_html_e('Top Products', 'tryloom'); ?></h4>
		<ul class="tryloom-top-products">
			<?php
			if (!empty($top_products)) {
				foreach ($top_products as $product) {
					$product_title = get_the_title($product->product_id);
					echo '<li><a href="' . esc_url(get_edit_post_link($product->product_id)) . '">' . esc_html($product_title) . '</a> (' . esc_html($product->count) . ')</li>';
				}
			} else {
				echo '<li>' . esc_html__('No data available yet.', 'tryloom') . '</li>';
			}
			?>
		</ul>

		<p>
			<a href="<?php echo esc_url(admin_url('admin.php?page=tryloom-settings')); ?>" class="button">
				<?php esc_html_e('Try On Settings', 'tryloom'); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Fetch dashboard statistics from database.
	 * This is called only when the transient cache is empty.
	 *
	 * @return array Statistics data.
	 */
	private function fetch_dashboard_stats()
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'tryloom_history';

		// Get total try-ons in the last hour.
		$hour_ago = gmdate('Y-m-d H:i:s', strtotime('-1 hour'));
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$total_hour = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . esc_sql($table_name) . ' WHERE created_at > %s', $hour_ago));

		// Get total try-ons in the last day.
		$day_ago = gmdate('Y-m-d H:i:s', strtotime('-1 day'));
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$total_day = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . esc_sql($table_name) . ' WHERE created_at > %s', $day_ago));

		// Get total try-ons in the last week.
		$week_ago = gmdate('Y-m-d H:i:s', strtotime('-1 week'));
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$total_week = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . esc_sql($table_name) . ' WHERE created_at > %s', $week_ago));

		// Get total try-ons all time.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$total_all = $wpdb->get_var('SELECT COUNT(*) FROM ' . esc_sql($table_name));

		// Get top products.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$top_products = $wpdb->get_results(
			'SELECT product_id, COUNT(*) as count FROM ' . esc_sql($table_name) . ' GROUP BY product_id ORDER BY count DESC LIMIT 5'
		);

		return array(
			'total_hour' => $total_hour,
			'total_day' => $total_day,
			'total_week' => $total_week,
			'total_all' => $total_all,
			'top_products' => $top_products,
		);
	}

	/**
	 * Settings page.
	 */
	public function settings_page()
	{
		// Note: Subscription status check is now handled asynchronously via AJAX
		// to prevent the admin panel from freezing if the API server is slow (Bug 4).

		// Get statistics.
		global $wpdb;
		// Use datetime strings for index-friendly range queries (avoids DATE() function which disables indexes)
		// Bug 3: Use wp_date() instead of gmdate() so dashboard stats match the local timezone used by the limit engine.
		$today_start = wp_date('Y-m-d 00:00:00');
		$today_end = wp_date('Y-m-d 00:00:00', strtotime('+1 day'));
		$thirty_days_ago_start = wp_date('Y-m-d 00:00:00', strtotime('-30 days'));
		$history_table = $wpdb->prefix . 'tryloom_history';

		// Today's Active Users - Count distinct users who used try-on today.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$today_active_users = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT user_id) FROM ' . esc_sql($history_table) . ' WHERE created_at >= %s AND created_at < %s',
				$today_start,
				$today_end
			)
		);

		// Today's Try-On Used Times - Total generations today.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$today_try_on_count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . esc_sql($history_table) . ' WHERE created_at >= %s AND created_at < %s',
				$today_start,
				$today_end
			)
		);

		// Last 30 Days Active Users.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$last_30_days_users = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT user_id) FROM ' . esc_sql($history_table) . ' WHERE created_at >= %s',
				$thirty_days_ago_start
			)
		);

		// Last 30 Days Try-On Used Times.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
		$last_30_days_count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . esc_sql($history_table) . ' WHERE created_at >= %s',
				$thirty_days_ago_start
			)
		);
		?>
		<div id="tryloom-admin-wrap" class="wrap tryloom-admin">

			<h1 class="tryloom-admin__title">
				<?php esc_html_e('TryLoom Settings  - AI Virtual Try On for WooCommerce', 'tryloom'); ?>
			</h1>

			<?php
			$active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
			?>
			<nav class="nav-tab-wrapper tryloom-admin-tabs">
				<a href="?page=tryloom-settings&tab=general"
					class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General', 'tryloom'); ?></a>
				<a href="?page=tryloom-settings&tab=appearance"
					class="nav-tab <?php echo $active_tab === 'appearance' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Appearance', 'tryloom'); ?></a>
				<a href="?page=tryloom-settings&tab=access"
					class="nav-tab <?php echo $active_tab === 'access' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Access', 'tryloom'); ?></a>
				<a href="?page=tryloom-settings&tab=privacy"
					class="nav-tab <?php echo $active_tab === 'privacy' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Privacy', 'tryloom'); ?></a>
				<a href="?page=tryloom-settings&tab=advanced"
					class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Advanced', 'tryloom'); ?></a>
			</nav>

			<?php if ($active_tab === 'general'): ?>
				<!-- Statistics Grid -->
				<div class="tryloom-admin__stats-grid">
					<!-- Box 1: Today's Performance -->
					<div class="tryloom-admin__stat-card">
						<div class="tryloom-admin__stat-card-header">
							<h2><?php esc_html_e("Today's Performance", 'tryloom'); ?></h2>
						</div>
						<div class="tryloom-admin__stat-card-body tryloom-admin__stat-row">
							<div>
								<h3 class="tryloom-admin__stat-title"><?php esc_html_e('Active Users', 'tryloom'); ?></h3>
								<p class="tryloom-admin__stat-value"><?php echo esc_html($today_active_users); ?></p>
							</div>
							<div class="tryloom-admin__stat-divider"></div>
							<div>
								<h3 class="tryloom-admin__stat-title"><?php esc_html_e('Try-On Uses', 'tryloom'); ?></h3>
								<p class="tryloom-admin__stat-value"><?php echo esc_html($today_try_on_count); ?></p>
							</div>
						</div>
					</div>

					<!-- Box 2: Last 30 Days -->
					<div class="tryloom-admin__stat-card">
						<div class="tryloom-admin__stat-card-header">
							<h2><?php esc_html_e("Last 30 Days", 'tryloom'); ?></h2>
						</div>
						<div class="tryloom-admin__stat-card-body tryloom-admin__stat-row">
							<div>
								<h3 class="tryloom-admin__stat-title"><?php esc_html_e('Active Users', 'tryloom'); ?></h3>
								<p class="tryloom-admin__stat-value"><?php echo esc_html($last_30_days_users); ?></p>
							</div>
							<div class="tryloom-admin__stat-divider"></div>
							<div>
								<h3 class="tryloom-admin__stat-title"><?php esc_html_e('Try-On Uses', 'tryloom'); ?></h3>
								<p class="tryloom-admin__stat-value"><?php echo esc_html($last_30_days_count); ?></p>
							</div>
						</div>
					</div>

					<!-- Box 3: Usage Counter -->
					<?php
					$usage_used = get_option('tryloom_usage_used', null);
					$usage_limit = get_option('tryloom_usage_limit', null);
					if (null !== $usage_used && null !== $usage_limit) { ?>
						<div class="tryloom-admin__stat-card">
							<div class="tryloom-admin__stat-card-header">
								<h2><?php esc_html_e('Usage Counter', 'tryloom'); ?></h2>
							</div>
							<div class="tryloom-admin__stat-card-body tryloom-admin__usage-flex">
								<p class="description tryloom-admin__usage-desc">
									<?php esc_html_e('Your current Try-On usage compared to your monthly (or plan-based) limit.', 'tryloom'); ?>
								</p>
								<p class="tryloom-admin__usage-val">
									<?php echo esc_html($usage_used); ?> / <span
										class="tryloom-admin__usage-sub"><?php echo esc_html($usage_limit); ?></span>
								</p>
							</div>
						</div>
					<?php } ?>

					<!-- Box 4: Subscription Options -->
					<?php
					$paid_key = get_option('tryloom_platform_key', '');
					$free_key = get_option('tryloom_free_platform_key', '');
					$show_start_free_button = empty($paid_key) && empty($free_key);
					?>
					<div class="tryloom-admin__stat-card">
						<div class="tryloom-admin__stat-card-header">
							<h2><?php esc_html_e('Explore Subscription Plans for TryLoom', 'tryloom'); ?></h2>
						</div>
						<div class="tryloom-admin__stat-card-body tryloom-admin__sub-flex">
							<div class="tryloom-admin__header-actions tryloom-admin__sub-actions">
								<?php if ($show_start_free_button): ?>
									<a href="https://gettryloom.com/my-account/"
										class="button button-primary tryloom-admin__margin-right-10" target="_blank">
										<?php esc_html_e('Start for Free', 'tryloom'); ?>
									</a>
								<?php endif; ?>
								<a href="https://gettryloom.com/#pricing" class="button button-primary" target="_blank">
									<?php esc_html_e('Subscription Options', 'tryloom'); ?>
								</a>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Settings Form -->
			<form method="post" action="options.php" class="tryloom-admin__form">
				<?php
				settings_fields("tryloom-settings-group-{$active_tab}");
				do_settings_sections("tryloom-settings-{$active_tab}");
				submit_button();
				?>
			</form>

		</div> <!-- End #tryloom-admin-wrap -->
		<?php
	}

	/**
	 * AJAX handler to check subscription status asynchronously.
	 * Prevents admin page freeze when API server is slow or unresponsive (Bug 4).
	 */
	public function ajax_check_subscription()
	{
		check_ajax_referer('tryloom_admin_nonce', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error('Unauthorized');
		}

		if (function_exists('tryloom') && tryloom()->api) {
			tryloom()->api->check_usage_status();
			wp_send_json_success(array(
				'subscription_ended' => get_option('tryloom_subscription_ended', 'no'),
			));
		}

		wp_send_json_error('API not available');
	}
}