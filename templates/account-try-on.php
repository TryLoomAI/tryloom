<?php
/**
 * Account Try On Tab Template.
 *
 * @package TryLoom
 */

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// Template variables are acceptable in template files.

// Only show content if user context is available
if (!isset($user_id)) {
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
}

if (isset($GLOBALS['tryloom_user_id'])) {
    $user_id = $GLOBALS['tryloom_user_id'];
}

// Get page number from query parameter
// Verify nonce for pagination
$page = 1;
if (isset($_GET['history_page'])) {
    // Verify nonce for pagination
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'tryloom_history_pagination')) {
        // Invalid nonce, default to page 1
        $page = 1;
    } else {
        $page = max(1, intval(wp_unslash($_GET['history_page'])));
    }
}
$items_per_page = 10;

// Get primary color.
$primary_color = get_option('tryloom_primary_color', '#552FBC');

// Get user photos.
global $wpdb;
$photos_table = $wpdb->prefix . 'tryloom_user_photos';
$history_table = $wpdb->prefix . 'tryloom_history';

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
$photos = $wpdb->get_results($wpdb->prepare(
    'SELECT * FROM ' . esc_sql($photos_table) . ' WHERE user_id = %d ORDER BY is_default DESC, id DESC',
    $user_id
));

$history = array();
$total_history = 0;
$total_pages = 0;
$enable_history = get_option('tryloom_enable_history', 'yes');

if ('yes' === $enable_history) {
    // Get total count for pagination
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
    $total_history = $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . esc_sql($history_table) . ' h WHERE h.user_id = %d',
        $user_id
    ));

    // Calculate total pages
    $total_pages = ceil($total_history / $items_per_page);

    // Get offset for pagination
    $offset = ($page - 1) * $items_per_page;

    // Get paginated results
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name sanitized with esc_sql()
    $history = $wpdb->get_results($wpdb->prepare(
        'SELECT h.*, p.name as product_name, p.permalink as product_permalink 
        FROM ' . esc_sql($history_table) . ' h 
        LEFT JOIN ' . esc_sql($wpdb->prefix . 'posts') . ' p ON h.product_id = p.ID 
        WHERE h.user_id = %d 
        ORDER BY h.created_at DESC 
        LIMIT %d OFFSET %d',
        $user_id,
        $items_per_page,
        $offset
    ));
}
?>

<div id="tryloom-popup-wrap" class="tryloom-popup__account">
    <?php
    $enable_history = get_option('tryloom_enable_history', 'yes');
    if ('yes' === $enable_history): ?>
        <h2><?php esc_html_e('My Virtual Closet', 'tryloom'); ?></h2>

        <?php if (!empty($history)): ?>
            <div class="tryloom-popup__history-actions">
                <button class="button tryloom-popup__delete-all-history">
                    <?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-trash.php'; ?>
                    <?php esc_html_e('Delete All History', 'tryloom'); ?>
                </button>
            </div>

            <table class="tryloom-popup__history-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'tryloom'); ?></th>
                        <th><?php esc_html_e('Product', 'tryloom'); ?></th>
                        <th><?php esc_html_e('Image', 'tryloom'); ?></th>
                        <th><?php esc_html_e('Actions', 'tryloom'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item):
                        $result_image = $item->generated_image_url;
                        if (!$result_image) {
                            continue;
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at))); ?>
                            </td>
                            <td>
                                <?php
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                $product = wc_get_product($item->product_id);
                                if ($product): ?>
                                    <a
                                        href="<?php echo esc_url(get_permalink($item->product_id)); ?>"><?php echo esc_html($product->get_name()); ?></a>
                                <?php else: ?>
                                    <?php esc_html_e('Unknown Product', 'tryloom'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                // Images use direct URLs with UUID filenames for security (no PHP proxy needed).
                                ?>
                                <a href="<?php echo esc_url($result_image); ?>" target="_blank"
                                    class="tryloom-popup__history-image">
                                    <img src="<?php echo esc_url($result_image); ?>"
                                        alt="<?php esc_attr_e('Try On Result', 'tryloom'); ?>" width="60" height="60">
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($result_image); ?>" download class="button tryloom-popup__account-btn"
                                    title="<?php esc_attr_e('Download', 'tryloom'); ?>">
                                    <?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-download.php'; ?>
                                </a>
                                <a href="<?php echo esc_url(get_permalink($item->product_id)); ?>"
                                    class="button tryloom-popup__account-btn" title="<?php esc_attr_e('Try Again', 'tryloom'); ?>">
                                    <?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-redo.php'; ?>
                                </a>
                                <button class="button tryloom-popup__delete-history tryloom-popup__account-btn"
                                    data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('Delete', 'tryloom'); ?>">
                                    <?php include TRYLOOM_PLUGIN_DIR . 'templates/icons/icon-trash.php'; ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tryloom-popup__pagination">
                    <?php
                    $base_url = remove_query_arg(array('history_page', '_wpnonce'));
                    $pagination_nonce = wp_create_nonce('tryloom_history_pagination');

                    echo wp_kses_post(paginate_links(array(
                        'base' => add_query_arg('history_page', '%#%'),
                        'format' => '',
                        'current' => $page,
                        'total' => $total_pages,
                        'prev_text' => '&laquo; ' . __('Previous', 'tryloom'),
                        'next_text' => __('Next', 'tryloom') . ' &raquo;',
                        'type' => 'plain',
                        'add_args' => array('_wpnonce' => $pagination_nonce),
                    )));
                    ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <p><?php esc_html_e('You haven\'t tried on any products yet.', 'tryloom'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>