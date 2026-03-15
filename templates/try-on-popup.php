<?php
/**
 * Try On Popup Template.
 *
 * @package TryLoom
 */

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
// Template variables and WooCommerce hooks are acceptable in template files.

// Get settings (these may be passed from add_try_on_popup, but we need them for shortcode usage too).
$theme = isset($theme_color) ? $theme_color : get_option('tryloom_theme_color', 'light');
$primary_color = isset($primary_color) ? $primary_color : get_option('tryloom_primary_color', '#552FBC');
$watermark = isset($watermark) ? $watermark : get_option('tryloom_brand_watermark', '');
if ($watermark && is_numeric($watermark)) {
    $watermark = wp_get_attachment_url($watermark);
}
$retry_button = get_option('tryloom_retry_button', 'yes');

// Use passed $default_photo_url if available (from add_try_on_popup), otherwise it's empty.
// This variable is already set by add_try_on_popup() with the optimized single query.
// Images use direct URLs with UUID filenames for security (no PHP proxy needed).
if (!isset($default_photo_url)) {
    $default_photo_url = '';
}
?>
<!-- TryLoom Specificity Wrapper -->
<div id="tryloom-popup-wrap">
    <div class="tryloom-popup tryloom-popup--theme-<?php echo esc_attr($theme); ?>" id="tryloom-popup"
        aria-hidden="true" role="dialog" aria-labelledby="tryloom-popup-title"
        data-primary-color="<?php echo esc_attr($primary_color); ?>">
        <div class="tryloom-popup__content">
            <div class="tryloom-popup__header">
                <h3 id="tryloom-popup-title" class="tryloom-popup__title">
                    <?php esc_html_e('AI Fitting Room', 'tryloom'); ?></h3>
                <button class="tryloom-popup__close-btn"
                    aria-label="<?php esc_attr_e('Close', 'tryloom'); ?>">&times;</button>
            </div>

            <div class="tryloom-popup__body">
                <!-- Step 1: Upload and Select Variation -->
                <div class="tryloom-popup__step tryloom-popup__step--1 is-active">
                    <div class="tryloom-popup__upload-section">
                        <h4 class="tryloom-popup__section-title"><?php esc_html_e('Your Photo', 'tryloom'); ?></h4>
                        <div class="tryloom-popup__upload-area">
                            <div class="tryloom-popup__upload-preview">
                                <?php if ($default_photo_url): ?>
                                    <div class="tryloom-popup__preview-container">
                                        <img src="<?php echo esc_url($default_photo_url); ?>"
                                            alt="<?php esc_attr_e('Your Photo', 'tryloom'); ?>"
                                            class="tryloom-popup__preview-image" />
                                    </div>
                                <?php else: ?>
                                    <div class="tryloom-popup__upload-placeholder">
                                        <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/img/tryloom_upload_placeholder.png'); ?>"
                                            alt="<?php esc_attr_e('Upload', 'tryloom'); ?>" width="80" height="80"
                                            class="tryloom-popup__upload-icon" />
                                        <p class="tryloom-popup__upload-title">
                                            <?php esc_html_e('Upload your photo', 'tryloom'); ?></p>
                                        <p class="tryloom-popup__upload-subtitle">
                                            <?php esc_html_e('or drag and drop here.', 'tryloom'); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <input type="file" id="tryloom-file" accept="image/*" class="tryloom-popup__hidden-file-input">

                    <?php if ('yes' !== get_option('tryloom_hide_variations', 'no')): ?>
                        <div class="tryloom-popup__variations-section">
                            <h4 class="tryloom-popup__section-title">
                                <?php esc_html_e('Select Product Variation', 'tryloom'); ?></h4>
                            <div class="tryloom-popup__variations-container">
                                <p class="tryloom-popup__loading-msg">
                                    <?php esc_html_e('Loading variations...', 'tryloom'); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php 
                    // Render Cloudflare Turnstile if enabled
                    $turnstile_enabled = get_option('tryloom_turnstile_enabled', 'no');
                    if ('yes' === $turnstile_enabled) {
                        $site_key = get_option('tryloom_turnstile_site_key', '');
                        if (!empty($site_key)) {
                            echo '<div class="tryloom-turnstile-container" style="margin-top: 15px; margin-bottom: 5px; display: flex; justify-content: center;">';
                            echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '" data-theme="auto" data-size="flexible"></div>';
                            echo '</div>';
                        }
                    }
                    ?>
                    
                    <div class="tryloom-popup__actions">
                        <?php
                        // Get Add to Cart button classes.
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                        $button_classes = apply_filters('tryloom_product_single_add_to_cart_button_classes', 'button alt');
                        ?>
                        <button type="button"
                            class="<?php echo esc_attr($button_classes); ?> tryloom-popup__generate-btn">
                            <?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-magic.php'; ?>
                            <?php esc_html_e('See My Look', 'tryloom'); ?>
                        </button>
                    </div>
                </div><!-- End tryloom-step-1 -->

                <!-- Step 2: Result -->
                <div class="tryloom-popup__step tryloom-popup__step--2">
                    <div class="tryloom-popup__result">
                        <div class="tryloom-popup__result-image-wrapper">
                            <div class="tryloom-popup__result-loading" aria-hidden="true">
                                <div class="tryloom-popup__spinner"></div>
                            </div>
                            <img src="" alt="<?php esc_attr_e('Try On Result', 'tryloom'); ?>"
                                class="tryloom-popup__result-image">
                            <?php if ($watermark): ?>
                                <div class="tryloom-popup__watermark">
                                    <img src="<?php echo esc_url($watermark); ?>"
                                        alt="<?php esc_attr_e('Watermark', 'tryloom'); ?>"
                                        class="tryloom-popup__watermark-img">
                                </div>
                            <?php endif; ?>

                            <!-- Image action icons (using inline SVG for reliability) -->
                            <div class="tryloom-popup__image-actions">
                                <a href="#" class="tryloom-popup__icon-button tryloom-popup__download-icon" download
                                    title="<?php esc_attr_e('Download', 'tryloom'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                    </svg>
                                </a>
                                <?php if ('yes' === $retry_button): ?>
                                    <button type="button" class="tryloom-popup__icon-button tryloom-popup__retry-icon"
                                        title="<?php esc_attr_e('Try Again', 'tryloom'); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <polyline points="23 4 23 10 17 10"></polyline>
                                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tryloom-popup__result-actions">
                            <?php
                            // Get Add to Cart button classes.
                            $button_classes = apply_filters('tryloom_product_single_add_to_cart_button_classes', 'button alt');
                            ?>
                            <button type="button"
                                class="<?php echo esc_attr($button_classes); ?> tryloom-popup__add-to-cart-btn">
                                <?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-shopping-cart.php'; ?>
                                <?php esc_html_e('Looks Good', 'tryloom'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Step 3: Limit Exceeded -->
                <div class="tryloom-popup__step tryloom-popup__step--3">
                    <div class="tryloom-popup__limit-exceeded">
                        <span class="tryloom-popup__alert-icon">
                            <?php
                            $lock_icon = file_get_contents(TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-lock.php');
                            echo str_replace(array('width="24"', 'height="24"'), array('width="48"', 'height="48"'), $lock_icon);
                            ?>
                        </span>
                        <p class="tryloom-popup__alert-msg">
                            <?php esc_html_e('You have reached your total allowed try-ons.', 'tryloom'); ?></p>
                        <p class="tryloom-popup__reset-time">
                            <?php esc_html_e('Usage resets:', 'tryloom'); ?> <span></span>
                        </p>
                        <a href="#" class="tryloom-popup__upsell-btn button alt tryloom-popup__reset-btn" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Upgrade Plan', 'tryloom'); ?>
                        </a>
                    </div>
                </div>
                <!-- Loading Overlay INSIDE popup content -->
                <div class="tryloom-popup__loading-overlay" style="display: none;">
                    <div class="tryloom-popup__progress-container">
                        <svg class="tryloom-popup__progress-ring" width="80" height="80">
                            <circle class="tryloom-popup__progress-ring-bg" cx="40" cy="40" r="34" fill="none"
                                stroke-width="6">
                            </circle>
                            <circle class="tryloom-popup__progress-ring-fill" cx="40" cy="40" r="34" fill="none"
                                stroke-width="6" stroke-dasharray="214" stroke-dashoffset="214" stroke-linecap="round">
                            </circle>
                        </svg>
                    </div>
                    <p class="tryloom-popup__loading-status-msg"><?php esc_html_e('Processing...', 'tryloom'); ?></p>
                </div>
            </div>
        </div>