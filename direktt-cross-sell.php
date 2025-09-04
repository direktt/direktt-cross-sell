<?php

/**
 * Plugin Name: Direktt Cross-Sell
 * Description: Direktt Cross-Sell Direktt Plugin
 * Version: 1.0.0
 * Author: Direktt
 * Author URI: https://direktt.com/
 * License: GPL2
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'direktt_cross_sell_activation_check', -20);

//Settings Page
add_action('direktt_setup_settings_pages', 'direktt_cross_sell_setup_settings_page');

//Custom Post Types
add_action('init', 'direktt_cross_sell_register_custom_post_types');

//Custom Database Table
register_activation_hook( __FILE__, 'direktt_cross_sell_create_database_table');

//Cross-Sell Partner Meta Boxes
add_action('add_meta_boxes', 'direktt_cross_sell_partners_add_custom_box');
add_action('save_post', 'save_direktt_cross_sell_partner_meta');

//Cross-Sell Coupon Groups Meta Boxes
add_action('add_meta_boxes', 'direktt_cross_sell_coupon_groups_add_custom_box');
add_action('save_post', 'save_direktt_cross_sell_coupon_groups_meta');

function direktt_cross_sell_activation_check()
{
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $required_plugin = 'direktt-plugin/direktt.php';

    if (!is_plugin_active($required_plugin)) {

        add_action('after_plugin_row_direktt-cross-sell/direktt-cross-sell.php', function ($plugin_file, $plugin_data, $status) {
            $colspan = 3;
?>
            <tr class="plugin-update-tr">
                <td colspan="<?php echo $colspan; ?>" style="box-shadow: none;">
                    <div style="color: #b32d2e; font-weight: bold;">
                        <?php echo (esc_html__('Direktt Cross-Sell requires the Direktt WordPress Plugin to be active. Please activate Direktt WordPress Plugin first.', 'direktt-cross-sell')); ?>
                    </div>
                </td>
            </tr>
    <?php
        }, 10, 3);

        deactivate_plugins(plugin_basename(__FILE__));
    }
}

function direktt_cross_sell_setup_settings_page()
{
    Direktt::add_settings_page(
        array(
            "id" => "cross-sell",
            "label" => __('Cross-Sell Settings', 'direktt-cross-sell'),
            "callback" => 'direktt_cross_sell_settings',
            "priority" => 1
        )
    );
}

function direktt_cross_sell_settings()
{
    // Success message flag
    $success = false;

    // Handle form submission
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['direktt_admin_cross_sales_nonce'])
        && wp_verify_nonce($_POST['direktt_admin_cross_sales_nonce'], 'direktt_admin_cross_sales_save')
    ) {
        // Sanitize and update options

        update_option('direktt_cross_sell_issue_categories', isset($_POST['direktt_cross_sell_issue_categories']) ? intval($_POST['direktt_cross_sell_issue_categories']) : 0);
        update_option('direktt_cross_sell_issue_tags', isset($_POST['direktt_cross_sell_issue_tags']) ? intval($_POST['direktt_cross_sell_issue_tags']) : 0);
        update_option('direktt_cross_sell_review_categories', isset($_POST['direktt_cross_sell_review_categories']) ? intval($_POST['direktt_cross_sell_review_categories']) : 0);
        update_option('direktt_cross_sell_review_tags', isset($_POST['direktt_cross_sell_review_tags']) ? intval($_POST['direktt_cross_sell_review_tags']) : 0);

        update_option('direktt_cross_sell_check_url', isset($_POST['direktt_cross_sell_check_url']) ? sanitize_text_field($_POST['direktt_cross_sell_check_url']) : '');
        update_option('direktt_cross_sell_user_template', intval($_POST['direktt_cross_sell_user_template']));
        update_option('direktt_cross_sell_admin_template', intval($_POST['direktt_cross_sell_admin_template']));
        update_option('direktt_cross_sell_salesman_template', intval($_POST['direktt_cross_sell_salesman_template']));

        $success = true;
    }

    // Load stored values
    $issue_categories = get_option('direktt_cross_sell_issue_categories', 0);
    $issue_tags = get_option('direktt_cross_sell_issue_tags', 0);
    $review_categories = get_option('direktt_cross_sell_review_categories', 0);
    $review_tags = get_option('direktt_cross_sell_review_tags', 0);

    $check_url = get_option('direktt_cross_sell_check_url');
    $user_template = intval(get_option('direktt_cross_sell_user_template', 0));
    $admin_template = intval(get_option('direktt_cross_sell_admin_template', 0));
    $salesman_template = intval(get_option('direktt_cross_sell_salesman_template', 0));

    $templates = Direktt_Message_Template::get_templates(['all', 'none']);

    $all_categories = Direktt_User::get_all_user_categories();
    $all_tags = Direktt_User::get_all_user_tags();

    ?>
    <div class="wrap">
        <?php if ($success): ?>
            <div class="updated notice is-dismissible">
                <p>Settings saved successfully.</p>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <?php wp_nonce_field('direktt_admin_cross_sales_save', 'direktt_admin_cross_sales_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_issue_categories">Users to Issue Vouchers</label></th>
                    <td>
                        <select name="direktt_cross_sell_issue_categories" id="direktt_cross_sell_issue_categories">
                            <option value="0">Select Category</option>
                            <?php foreach ($all_categories as $category): ?>
                                <option value="<?php echo esc_attr($category['value']); ?>" <?php selected($issue_categories, $category['value']); ?>>
                                    <?php echo esc_html($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Users belonging to this category will be able to Issue Vouchers.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_issue_tags">Users to Issue Vouchers</label></th>
                    <td>
                        <select name="direktt_cross_sell_issue_tags" id="direktt_cross_sell_issue_tags">
                            <option value="0">Select Tag</option>
                            <?php foreach ($all_tags as $tag): ?>
                                <option value="<?php echo esc_attr($tag['value']); ?>" <?php selected($issue_tags, $tag['value']); ?>>
                                    <?php echo esc_html($tag['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Users with this tag will be able to Issue Vouchers.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_review_categories">Users to Review Vouchers</label></th>
                    <td>
                        <select name="direktt_cross_sell_review_categories" id="direktt_cross_sell_review_categories">
                            <option value="0">Select Category</option>
                            <?php foreach ($all_categories as $category): ?>
                                <option value="<?php echo esc_attr($category['value']); ?>" <?php selected($review_categories, $category['value']); ?>>
                                    <?php echo esc_html($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Users belonging to this category will be able to Review Vouchers.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_review_tags">Users to Review Vouchers</label></th>
                    <td>
                        <select name="direktt_cross_sell_review_tags" id="direktt_cross_sell_review_tags">
                            <option value="0">Select Tag</option>
                            <?php foreach ($all_tags as $tag): ?>
                                <option value="<?php echo esc_attr($tag['value']); ?>" <?php selected($review_tags, $tag['value']); ?>>
                                    <?php echo esc_html($tag['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Users with this tag will be able to Review Vouchers.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_check_url">Voucher Validation Url</label></th>
                    <td>
                        <input type="text" name="direktt_cross_sell_check_url" id="direktt_cross_sell_check_url" value="<?php echo esc_attr($check_url); ?>" size="80" />
                        <p class="description">Url of the page with the Cross-Sell Voucher Validation shortcode</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_user_template">User Validated Template</label></th>
                    <td>
                        <select name="direktt_cross_sell_user_template" id="direktt_cross_sell_user_template">
                            <option value="0">Select Template</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo esc_attr($template['value']); ?>" <?php selected($user_template, $template['value']); ?>>
                                    <?php echo esc_html($template['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Message Template for User Message on Voucher Validation</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_admin_template">Admin Validated Template</label></th>
                    <td>
                        <select name="direktt_cross_sell_admin_template" id="direktt_cross_sell_admin_template">
                            <option value="0">Select Template</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo esc_attr($template['value']); ?>" <?php selected($admin_template, $template['value']); ?>>
                                    <?php echo esc_html($template['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Message Template for Admin Message on Voucher Validation</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_salesman_template">Salesman Validated Template</label></th>
                    <td>
                        <select name="direktt_cross_sell_salesman_template" id="direktt_cross_sell_salesman_template">
                            <option value="0">Select Template</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo esc_attr($template['value']); ?>" <?php selected($salesman_template, $template['value']); ?>>
                                    <?php echo esc_html($template['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Message Template for Salesman Message on Voucher Validation</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
<?php
}

function direktt_cross_sell_register_custom_post_types()
{

    $labels = array(
        'name'                => __('Cross-Sell Partners', 'direktt-cross-sell'),
        'singular_name'       => __('Cross-Sell Partner',  'direktt-cross-sell'),
        'menu_name'           => __('Direktt', 'direktt-cross-sell'),
        'all_items'           => __('Cross-Sell Partners', 'direktt-cross-sell'),
        'view_item'           => __('View Partner', 'direktt-cross-sell'),
        'add_new_item'        => __('Add New Partner', 'direktt-cross-sell'),
        'add_new'             => __('Add New', 'direktt-cross-sell'),
        'edit_item'           => __('Edit Partner', 'direktt-cross-sell'),
        'update_item'         => __('Update Partner', 'direktt-cross-sell'),
        'search_items'        => __('Search Partners', 'direktt-cross-sell'),
        'not_found'           => __('Not Found', 'direktt-cross-sell'),
        'not_found_in_trash'  => __('Not found in Trash', 'direktt-cross-sell'),
    );

    $args = array(
        'label'               => __('partners', 'direktt-cross-sell'),
        'description'         => __('Cross-Sell Partners', 'direktt-cross-sell'),
        'labels'              => $labels,
        'supports'            => array('title', 'editor'),
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'direktt-dashboard',
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        'menu_position'       => 5,
        'can_export'          => true,
        'has_archive'         => false,
        'exclude_from_search' => false,
        'publicly_queryable'  => false,
        'capability_type'     => 'post',
        'capabilities'          => array(),
        'show_in_rest'    => false,
    );

    register_post_type('direkttcspartners', $args);

    // Message templates

    $labels = array(
        'name'                => __('Cross-Sell Coupon Groups', 'direktt-cross-sell'),
        'singular_name'       => __('Cross-Sell Coupon Group',  'direktt-cross-sell'),
        'menu_name'           => __('Direktt', 'direktt-cross-sell'),
        'all_items'           => __('Cross-Sell Coupon Groups', 'direktt-cross-sell'),
        'view_item'           => __('View Coupon Group', 'direktt-cross-sell'),
        'add_new_item'        => __('Add New Coupon Group', 'direktt-cross-sell'),
        'add_new'             => __('Add New', 'direktt-cross-sell'),
        'edit_item'           => __('Edit Coupon Group', 'direktt-cross-sell'),
        'update_item'         => __('Update Coupon Group', 'direktt-cross-sell'),
        'search_items'        => __('Search Coupon Groups', 'direktt-cross-sell'),
        'not_found'           => __('Not Found', 'direktt-cross-sell'),
        'not_found_in_trash'  => __('Not found in Trash', 'direktt-cross-sell'),
    );

    $args = array(
        'label'               => __('Cross-Sell Coupon Groups', 'direktt-cross-sell'),
        'description'         => __('Cross-Sell Partners', 'direktt-cross-sell'),
        'labels'              => $labels,
        'supports'            => array('title', 'editor', 'thumbnail'),
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'direktt-dashboard',
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        'menu_position'       => 5,
        'can_export'          => true,
        'has_archive'         => false,
        'exclude_from_search' => false,
        'publicly_queryable'  => false,
        'capability_type'     => 'post',
        'capabilities'          => array(),
        'show_in_rest'    => false,
    );

    register_post_type('direkttcscoupon', $args);
}

function direktt_cross_sell_get_coupon_groups()
{
    $cs_args = [
        'post_type'      => 'direkttcscoupon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];

    $cs_posts = get_posts($cs_args);

    $coupon_groups = [];

    foreach ($cs_posts as $post) {
        $coupon_groups[] = array(
            "value" => $post->ID,
            "title" =>     $post->post_title
        );
    }

    return $coupon_groups;
}

function direktt_cross_sell_partners_add_custom_box()
{
    add_meta_box(
        'direktt_cs_partners_mb',           // ID
        __('Partner Properties', 'direktt-cross-sell'),                       // Title
        'direktt_cross_sell_partners_render_custom_box',    // Callback function
        'direkttcspartners',                    // CPT slug
        'normal',                        // Context
        'high'                           // Priority
    );
}

function direktt_cross_sell_partners_render_custom_box($post)
{
    $all_categories = Direktt_User::get_all_user_categories();
    $all_tags = Direktt_User::get_all_user_tags();

    $partner_categories = get_post_meta($post->ID, 'direktt_cross_sell_partner_categories', true);
    $partner_tags = get_post_meta($post->ID, 'direktt_cross_sell_partner_tags', true);

    $coupon_groups = direktt_cross_sell_get_coupon_groups();

    wp_nonce_field('direktt_cross_sell_save', 'direktt_cross_sell_nonce');
?>

    <script>
        var allCouponGroups = <?php echo json_encode($coupon_groups); ?>;
        var couponGroups = <?php echo json_encode(get_post_meta($post->ID, 'direktt_cross_sell_coupon_groups', false)); ?>;
    </script>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="direktt_cross_sell_partner_categories">Partner Categories</label></th>
            <td>
                <select name="direktt_cross_sell_partner_categories" id="direktt_cross_sell_partner_categories">
                    <option value="0">Select Category</option>
                    <?php foreach ($all_categories as $category): ?>
                        <option value="<?php echo esc_attr($category['value']); ?>" <?php selected($partner_categories, $category['value']); ?>>
                            <?php echo esc_html($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Partner users belonging to this category will be able to Validate Vouchers.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="direktt_cross_sell_issue_tags">Partner Tags</label></th>
            <td>
                <select name="direktt_cross_sell_issue_tags" id="direktt_cross_sell_issue_tags">
                    <option value="0">Select Tag</option>
                    <?php foreach ($all_tags as $tag): ?>
                        <option value="<?php echo esc_attr($tag['value']); ?>" <?php selected($partner_tags, $tag['value']); ?>>
                            <?php echo esc_html($tag['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Partner users with this tag will be able to Validate Vouchers.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Coupon Groups</th>
            <td>
                <div id="direktt_cross_sell_coupon_groups_repeater">
                    <!-- JS will render fields here -->
                </div>
                <button type="button" class="button" id="add_coupon_group">Add Coupon Group</button>
                <script>
                    (function($) {
                        function renderGroup(index, value) {
                            var options = '<option value="0">Select Coupon Group</option>';
                            allCouponGroups.forEach(function(cs) {
                                var selected = (cs.value == value) ? 'selected' : '';
                                options += `<option value="${cs.value}" ${selected}>${cs.title}</option>`;
                            });
                            return `
                        <div class="coupon-group" style="margin-bottom:8px;">
                            <label>
                                <select name="direktt_cross_sell_coupon_groups[]" class="direktt_coupon_group_select">
                                    ${options}
                                </select>
                            </label>
                            <button type="button" class="button remove_coupon_group">Remove</button>
                        </div>`;
                        }

                        function refreshGroups() {
                            var html = '';
                            if (couponGroups.length) {
                                for (var i = 0; i < couponGroups.length; i++) {
                                    html += renderGroup(i, couponGroups[i]);
                                }
                            }
                            $('#direktt_cross_sell_coupon_groups_repeater').html(html);
                        }
                        $(document).ready(function() {
                            refreshGroups();
                            $('#add_coupon_group').on('click', function(e) {
                                e.preventDefault();
                                $('#direktt_cross_sell_coupon_groups_repeater').append(renderGroup('', '0'));
                            });
                            $('#direktt_cross_sell_coupon_groups_repeater').on('click', '.remove_coupon_group', function(e) {
                                e.preventDefault();
                                $(this).closest('.coupon-group').remove();
                            });
                        });
                    })(jQuery);
                </script>
                <p class="description">Add coupon groups for this partner.</p>
            </td>
        </tr>
    </table>
<?php
}

function save_direktt_cross_sell_partner_meta($post_id)
{

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['post_type']) || $_POST['post_type'] !== 'direkttcspartners') return;

    if (!isset($_POST['direktt_cross_sell_nonce']) || !wp_verify_nonce($_POST['direktt_cross_sell_nonce'], 'direktt_cross_sell_save')) return;

    if (isset($_POST['direktt_cross_sell_coupon_groups']) && is_array($_POST['direktt_cross_sell_coupon_groups'])) {

        $groups = array_map('sanitize_text_field', $_POST['direktt_cross_sell_coupon_groups']);
        $groups = array_filter($groups, function ($group) {
            return !empty($group) && $group !== '0';
        });

        $groups = array_unique($groups);

        delete_post_meta($post_id, 'direktt_cross_sell_coupon_groups');
        foreach ($groups as $group) {
            add_post_meta($post_id, 'direktt_cross_sell_coupon_groups', $group);
        }
    } else {
        delete_post_meta($post_id, 'direktt_cross_sell_coupon_groups');
    }

    if (isset($_POST['direktt_cross_sell_partner_categories'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_partner_categories',
            sanitize_text_field($_POST['direktt_cross_sell_partner_categories'])
        );
    }
    if (isset($_POST['direktt_cross_sell_issue_tags'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_partner_tags',
            sanitize_text_field($_POST['direktt_cross_sell_issue_tags'])
        );
    }
}

function direktt_cross_sell_coupon_groups_add_custom_box()
{
    add_meta_box(
        'direktt_cs_partners_mb',           // ID
        __('Partner Properties', 'direktt-cross-sell'),                       // Title
        'direktt_cross_sell_coupon_groups_render_custom_box',    // Callback function
        'direkttcscoupon',                    // CPT slug
        'normal',                        // Context
        'high'                           // Priority
    );
}

function direktt_cross_sell_coupon_groups_render_custom_box($post)
{
    $templates = Direktt_Message_Template::get_templates(['all', 'none']);

    $group_type = get_post_meta($post->ID, 'direktt_cross_sell_group_type', true);
    $group_validity = get_post_meta($post->ID, 'direktt_cross_sell_group_validity', true);
    $group_template = get_post_meta($post->ID, 'direktt_cross_sell_group_template', true);

    wp_nonce_field('direktt_cross_sell_save', 'direktt_cross_sell_nonce');
?>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="direktt_cross_sell_group_type">Type</label></th>
            <td>
                <select name="direktt_cross_sell_group_type" id="direktt_cross_sell_group_type">
                    <option value="1" <?php selected($group_type, 1); ?>>Individual Coupon</option>
                    <option value="2" <?php selected($group_type, 2); ?>>Bulk Coupon</option>
                </select>
                <p class="description">Coupon Group Type. Can be individual - issued to a single user and used only once or bulk</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="direktt_cross_sell_group_validity">Coupon Validity</label></th>
            <td>
                <input type="text" name="direktt_cross_sell_group_validity" id="direktt_cross_sell_group_validity" value="<?php echo $group_validity ? intval($group_validity) : 0; ?>" />
                <p class="description">Coupon validity in days. If equals to 0, the coupon does not expire</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="direktt_cross_sell_group_template">Message Template</label></th>
            <td>
                <select name="direktt_cross_sell_group_template" id="direktt_cross_sell_group_template">
                    <option value="0">Select Template</option>
                    <?php foreach ($templates as $template): ?>
                        <option value="<?php echo esc_attr($template['value']); ?>" <?php selected($group_template, $template['value']); ?>>
                            <?php echo esc_html($template['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Message Template used when Coupon is issued. If none set, default will be sent</p>
            </td>
        </tr>
    </table>
<?php
}

function save_direktt_cross_sell_coupon_groups_meta($post_id)
{

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['post_type']) || $_POST['post_type'] !== 'direkttcscoupon') return;

    if (!isset($_POST['direktt_cross_sell_nonce']) || !wp_verify_nonce($_POST['direktt_cross_sell_nonce'], 'direktt_cross_sell_save')) return;

    if (isset($_POST['direktt_cross_sell_group_type'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_group_type',
            sanitize_text_field($_POST['direktt_cross_sell_group_type'])
        );
    }
    if (isset($_POST['direktt_cross_sell_group_validity'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_group_validity',
            sanitize_text_field($_POST['direktt_cross_sell_group_validity'])
        );
    }
    if (isset($_POST['direktt_cross_sell_group_template'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_group_template',
            sanitize_text_field($_POST['direktt_cross_sell_group_template'])
        );
    }
}

function direktt_cross_sell_create_database_table()
{
    //die();
    global $wpdb;

    $table_name = $wpdb->prefix . 'direktt_cross_sell';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
  			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            partner_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            coupon_group_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  			direktt_issuer_user_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            direktt_receiver_user_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            coupon_time timestamp NOT NULL,
            coupon_expires timestamp DEFAULT NULL,
            coupon_guid varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            coupon_used boolean DEFAULT FALSE,
            coupon_used_time timestamp DEFAULT NULL,
  			PRIMARY KEY  (ID),
  			KEY partner_id (partner_id),
  			KEY coupon_group_id (coupon_group_id),
            KEY direktt_receiver_user_id (direktt_receiver_user_id),
            KEY coupon_time (coupon_time),
            KEY coupon_guid (coupon_guid),
            KEY coupon_used_time (coupon_used_time)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    $the_default_timestamp_query = "ALTER TABLE $table_name MODIFY COLUMN coupon_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;";

    $wpdb->query($the_default_timestamp_query);
}
