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
register_activation_hook(__FILE__, 'direktt_cross_sell_create_issued_database_table');
register_activation_hook(__FILE__, 'direktt_cross_sell_create_used_individual_database_table');
register_activation_hook(__FILE__, 'direktt_cross_sell_create_used_bulk_database_table');

//Cross-Sell Partner Meta Boxes
add_action('add_meta_boxes', 'direktt_cross_sell_partners_add_custom_box');
add_action('save_post', 'save_direktt_cross_sell_partner_meta');

//Cross-Sell Coupon Groups Meta Boxes
add_action('add_meta_boxes', 'direktt_cross_sell_coupon_groups_add_custom_box');
add_action('save_post', 'save_direktt_cross_sell_coupon_groups_meta');

//Cross-Sell Profile Tool Setup
add_action('direktt_setup_profile_tools', 'direktt_cross_sell_setup_profile_tool');

//Reports ajax handlers
add_action('wp_ajax_direktt_cross_sell_get_issued_report', 'handle_direktt_cross_sell_get_issued_report');
add_action('wp_ajax_direktt_cross_sell_get_used_report', 'handle_direktt_cross_sell_get_used_report');

// [direktt_cross_sell_my_coupons] shortcode implementation
add_shortcode('direktt_cross_sell_my_coupons', 'direktt_cross_sell_my_coupons');

// [direktt_cross_sell_coupon_validation] shortcode implementation
add_shortcode('direktt_cross_sell_coupon_validation', 'direktt_cross_sell_coupon_validation');

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
                    <th scope="row"><label for="direktt_cross_sell_issue_categories">Users to Issue Coupons</label></th>
                    <td>
                        <select name="direktt_cross_sell_issue_categories" id="direktt_cross_sell_issue_categories">
                            <option value="0">Select Category</option>
                            <?php foreach ($all_categories as $category): ?>
                                <option value="<?php echo esc_attr($category['value']); ?>" <?php selected($issue_categories, $category['value']); ?>>
                                    <?php echo esc_html($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Users belonging to this category will be able to Issue Coupons.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_issue_tags">Users to Issue Coupons</label></th>
                    <td>
                        <select name="direktt_cross_sell_issue_tags" id="direktt_cross_sell_issue_tags">
                            <option value="0">Select Tag</option>
                            <?php foreach ($all_tags as $tag): ?>
                                <option value="<?php echo esc_attr($tag['value']); ?>" <?php selected($issue_tags, $tag['value']); ?>>
                                    <?php echo esc_html($tag['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Users with this tag will be able to Issue Coupons.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_review_categories">Users to Review Coupons</label></th>
                    <td>
                        <select name="direktt_cross_sell_review_categories" id="direktt_cross_sell_review_categories">
                            <option value="0">Select Category</option>
                            <?php foreach ($all_categories as $category): ?>
                                <option value="<?php echo esc_attr($category['value']); ?>" <?php selected($review_categories, $category['value']); ?>>
                                    <?php echo esc_html($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Users belonging to this category will be able to Review Coupons.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_review_tags">Users to Review Coupons</label></th>
                    <td>
                        <select name="direktt_cross_sell_review_tags" id="direktt_cross_sell_review_tags">
                            <option value="0">Select Tag</option>
                            <?php foreach ($all_tags as $tag): ?>
                                <option value="<?php echo esc_attr($tag['value']); ?>" <?php selected($review_tags, $tag['value']); ?>>
                                    <?php echo esc_html($tag['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Users with this tag will be able to Review Coupons.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_cross_sell_check_url">Coupon Validation Url</label></th>
                    <td>
                        <input type="text" name="direktt_cross_sell_check_url" id="direktt_cross_sell_check_url" value="<?php echo esc_attr($check_url); ?>" size="80" />
                        <p class="description">Url of the page with the Cross-Sell Coupon Validation shortcode</p>
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
                        <p class="description">Message Template for User Message on Coupon Validation</p>
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
                        <p class="description">Message Template for Admin Message on Coupon Validation</p>
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
                        <p class="description">Message Template for Salesman Message on Coupon Validation</p>
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

    add_meta_box(
        'direktt_cs_partners_reports_mb',           // ID
        __('CSV Reports', 'direktt-cross-sell'),                       // Title
        'direktt_cross_sell_partners_render_reports_meta_box',    // Callback function
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
                <p class="description">Partner users belonging to this category will be able to validate coupons.</p>
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
                <p class="description">Partner users with this tag will be able to validate coupons.</p>
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

function direktt_cross_sell_partners_render_reports_meta_box($post)
{
    // Security nonce
    wp_nonce_field('direktt_reports_meta_box', 'direktt_reports_meta_box_nonce');

    // Use esc to be safe
    $post_id = intval($post->ID);
?>
    <div class="direktt-reports-meta-box">
        <p>
            <label for="direktt-report-range"><strong><?php echo esc_html__('Range', 'direktt-cross-sell'); ?></strong></label>
            <select id="direktt-report-range" name="direktt_report_range">
                <option value="7"><?php echo esc_html__('Last 7 days', 'direktt-cross-sell'); ?></option>
                <option value="30"><?php echo esc_html__('Last 30 days', 'direktt-cross-sell'); ?></option>
                <option value="90"><?php echo esc_html__('Last 90 days', 'direktt-cross-sell'); ?></option>
                <option value="custom"><?php echo esc_html__('Custom date range', 'direktt-cross-sell'); ?></option>
            </select>
        </p>

        <div id="direktt-custom-dates" style="display: none; margin-top: 8px;">
            <p>
                <label for="direktt-date-from"><?php echo esc_html__('From', 'direktt-cross-sell'); ?></label>
                <input type="date" id="direktt-date-from" name="direktt_date_from" />
            </p>
            <p>
                <label for="direktt-date-to"><?php echo esc_html__('To', 'direktt-cross-sell'); ?></label>
                <input type="date" id="direktt-date-to" name="direktt_date_to" />
            </p>
        </div>

        <p style="margin-top:12px;">
            <button type="button" class="button" id="direktt-generate-issued"><?php echo esc_html__('Generate Issued Report', 'direktt-cross-sell'); ?></button>
            <button type="button" class="button" id="direktt-generate-used"><?php echo esc_html__('Generate Used Report', 'direktt-cross-sell'); ?></button>
        </p>

        <!-- Hidden field for post_id so JS can read it -->
        <input type="hidden" id="direktt-post-id" value="<?php echo esc_attr($post_id); ?>" />
    </div>
    <script>
        jQuery(document).ready(function($) {
            // toggle custom date inputs
            $('#direktt-report-range').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#direktt-custom-dates').slideDown();
                } else {
                    $('#direktt-custom-dates').slideUp();
                }
            });

            // helper to collect data
            function collectReportData(type) {
                var post_id = $('#direktt-post-id').val();
                var nonce = $('input[name="direktt_reports_meta_box_nonce"]').val();
                var range = $('#direktt-report-range').val();
                var from = $('#direktt-date-from').val();
                var to = $('#direktt-date-to').val();

                var ajaxData = {
                    action: type === 'issued' ? 'direktt_cross_sell_get_issued_report' : 'direktt_cross_sell_get_used_report',
                    post_id: post_id,
                    range: range,
                    nonce: nonce
                };

                if (range === 'custom') {
                    ajaxData.from = from;
                    ajaxData.to = to;
                }

                return ajaxData;
            }

            // Bind buttons
            $('#direktt-generate-issued').on('click', function() {
                event.preventDefault();
                var $btn = $(this);
                var data = collectReportData('issued');
                // Basic client-side validation for custom range
                if (data.range === 'custom' && (!data.from || !data.to)) {
                    alert('Please select both From and To dates for a custom range.');
                    return;
                }

                $.ajax({
                    url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                    method: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.url;
                        } else {
                            alert(response.data);
                        }
                    },
                    error: function() {

                    }
                });
            });

            $('#direktt-generate-used').on('click', function() {
                event.preventDefault();
                var $btn = $(this);
                var data = collectReportData('used');
                if (data.range === 'custom' && (!data.from || !data.to)) {
                    alert('Please select both From and To dates for a custom range.');
                    return;
                }

                $.ajax({
                    url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                    method: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.url;
                        } else {
                            alert(response.data);
                        }
                    }
                });
            });
        });
    </script>
<?php
}

function handle_direktt_cross_sell_get_issued_report()
{
    if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'direktt_reports_meta_box')) {
        wp_send_json_error(esc_html__('Invalid nonce.', 'direktt-cross-sell'));
        wp_die();
    }

    if (! isset($_POST['post_id'], $_POST['range'])) {
        wp_send_json_error(esc_html__('Data error.', 'direktt-cross-sell'));
        wp_die();
    }

    global $wpdb;

    $post_id      = intval($_POST['post_id']); // used as partner_id
    $range        = sanitize_text_field($_POST['range']);
    $issued_table = $wpdb->prefix . 'direktt_cross_sell_issued';

    // Build WHERE
    $where = $wpdb->prepare("partner_id = %d", $post_id);

    if (in_array($range, ['7', '30', '90'], true)) {
        $days  = intval($range);
        $where .= $wpdb->prepare(" AND coupon_time >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);
    } elseif ($range === 'custom') {
        if (! isset($_POST['from'], $_POST['to'])) {
            wp_send_json_error(esc_html__('Data error.', 'direktt-cross-sell'));
            wp_die();
        }
        $from = sanitize_text_field($_POST['from']); // format: Y-m-d or Y-m-d H:i:s
        $to   = sanitize_text_field($_POST['to']);
        $where .= $wpdb->prepare(" AND coupon_time BETWEEN %s AND %s", $from, $to);
    }

    // Get issued coupons
    $query   = "SELECT * FROM {$issued_table} WHERE {$where}";
    $results = $wpdb->get_results($query);

    if (empty($results)) {
        wp_send_json_error(esc_html__('No data found.', 'direktt-cross-sell'));
        wp_die();
    }

    // Build CSV with custom columns
    $csv = fopen('php://temp', 'r+');

    // Headers
    $headers = [
        'ID',
        'Partner Name',
        'Voucher Group Name',
        'Voucher Group ID',
        'Time of Issue',
        'Time of Expiring',
        'Coupon Valid'
    ];
    fputcsv($csv, $headers);

    foreach ($results as $row) {
        $partner_name = get_the_title($row->partner_id);
        $voucher_group_name = get_the_title($row->coupon_group_id);

        $line = [
            $row->ID,
            $partner_name,
            $voucher_group_name,
            $row->coupon_group_id,
            $row->coupon_time,
            $row->coupon_expires,
            $row->coupon_valid == 1 ? 'true' : 'false'
        ];
        fputcsv($csv, $line);
    }

    rewind($csv);
    $csv_content = stream_get_contents($csv);
    fclose($csv);

    // Save to uploads
    $upload_dir = wp_upload_dir();
    $filename   = 'issued_report_' . time() . '.csv';
    $filepath   = $upload_dir['path'] . '/' . $filename;
    $fileurl    = $upload_dir['url'] . '/' . $filename;

    file_put_contents($filepath, $csv_content);

    wp_send_json_success(['url' => $fileurl]);
    wp_die();
}

function handle_direktt_cross_sell_get_used_report()
{
    if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'direktt_reports_meta_box')) {
        wp_send_json_error(esc_html__('Invalid nonce.', 'direktt-cross-sell'));
        wp_die();
    }

    if (! isset($_POST['post_id'], $_POST['range'])) {
        wp_send_json_error(esc_html__('Data error.', 'direktt-cross-sell'));
        wp_die();
    }

    global $wpdb;

    $post_id = intval($_POST['post_id']);
    $range   = sanitize_text_field($_POST['range']);

    $issued_table    = $wpdb->prefix . 'direktt_cross_sell_issued';
    $used_ind_table  = $wpdb->prefix . 'direktt_cross_sell_used_individual';
    $used_bulk_table = $wpdb->prefix . 'direktt_cross_sell_used_bulk';

    // --- Range filter (applies to coupon_used_time / bulk used_time) ---
    $date_condition = '';
    if (in_array($range, ['7', '30', '90'], true)) {
        $days = intval($range);
        $date_condition = $wpdb->prepare("AND u.coupon_used_time >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);
    } elseif ($range === 'custom') {
        if (! isset($_POST['from'], $_POST['to'])) {
            wp_send_json_error(esc_html__('Data error.', 'direktt-cross-sell'));
            wp_die();
        }
        $from = sanitize_text_field($_POST['from']);
        $to   = sanitize_text_field($_POST['to']);
        $date_condition = $wpdb->prepare("AND u.coupon_used_time BETWEEN %s AND %s", $from, $to);
    }

    // --- Individual used ---
    $query_individual = "
        SELECT u.ID,
               u.issued_id,
               u.direktt_validator_user_id,
               u.coupon_used_time,
               i.partner_id,
               i.coupon_group_id
        FROM {$used_ind_table} u
        INNER JOIN {$issued_table} i ON u.issued_id = i.ID
        WHERE i.partner_id = %d {$date_condition}
    ";
    $results_individual = $wpdb->get_results($wpdb->prepare($query_individual, $post_id));

    // --- Bulk used ---
    $date_condition_bulk = '';
    if (in_array($range, ['7', '30', '90'], true)) {
        $days = intval($range);
        $date_condition_bulk = $wpdb->prepare("AND coupon_used_time >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);
    } elseif ($range === 'custom') {
        $date_condition_bulk = $wpdb->prepare("AND coupon_used_time BETWEEN %s AND %s", $from, $to);
    }

    $query_bulk = "
        SELECT ID,
               partner_id,
               issued_id,
               direktt_validator_user_id,
               coupon_used_time
        FROM {$used_bulk_table}
        WHERE partner_id = %d {$date_condition_bulk}
    ";
    $results_bulk = $wpdb->get_results($wpdb->prepare($query_bulk, $post_id));

    if (empty($results_individual) && empty($results_bulk)) {
        wp_send_json_error(esc_html__('No data found.', 'direktt-cross-sell'));
        wp_die();
    }

    // --- CSV ---
    $csv = fopen('php://temp', 'r+');

    $headers = [
        'Partner Name',
        'Issue ID',
        'Voucher Group Name',
        'Voucher Group Type',
        'Validator Display Name',
        'Validation Time',
    ];
    fputcsv($csv, $headers);

    // Add individual results
    foreach ($results_individual as $row) {
        $partner_name       = get_the_title($row->partner_id);
        $voucher_group_name = get_the_title($row->coupon_group_id);
        $profile_user       = Direktt_User::get_user_by_subscription_id($row->direktt_validator_user_id);
        $validator_name     = $profile_user['direktt_display_name'];

        $line = [
            $partner_name,
            $row->issued_id,
            $voucher_group_name,
            'Individual',
            $validator_name,
            $row->coupon_used_time,
        ];
        fputcsv($csv, $line);
    }

    // Add bulk results
    foreach ($results_bulk as $row) {
        $partner_name       = get_the_title($row->partner_id);
        $voucher_group_name = get_the_title($row->issued_id);
        $profile_user       = Direktt_User::get_user_by_subscription_id($row->direktt_validator_user_id);
        $validator_name     = $profile_user['direktt_display_name'];

        $line = [
            $partner_name,
            '',
            $voucher_group_name,
            'Bulk',
            $validator_name,
            $row->coupon_used_time,
        ];
        fputcsv($csv, $line);
    }

    rewind($csv);
    $csv_content = stream_get_contents($csv);
    fclose($csv);

    // Save to uploads
    $upload_dir = wp_upload_dir();
    $filename   = 'used_report_' . time() . '.csv';
    $filepath   = $upload_dir['path'] . '/' . $filename;
    $fileurl    = $upload_dir['url'] . '/' . $filename;

    file_put_contents($filepath, $csv_content);

    wp_send_json_success(['url' => $fileurl]);
    wp_die();
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
    $max_usage = get_post_meta($post->ID, 'direktt_cross_sell_max_usage', true);
    if ($max_usage === false) $max_usage = '1';
    $max_issuance = get_post_meta($post->ID, 'direktt_cross_sell_max_issuance', true);
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
                <input type="text" name="direktt_cross_sell_group_validity" id="direktt_cross_sell_group_validity" value="<?php echo $group_validity ? intval($group_validity) : 0; ?>" /> 0 - does not expire
                <p class="description">Coupon validity in days. If equals to 0, the coupon does not expire</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="direktt_cross_sell_max_usage">Individual - How many times can a single individual coupon be used?<br>Bulk - How many times in total can a bulk coupon be used?</label></th>
            <td>
                <input type="text" name="direktt_cross_sell_max_usage" id="direktt_cross_sell_max_usage" value="<?php echo $max_usage !== false &&  $max_usage !== '' ? intval($max_usage) : 1; ?>" /> 0 - unlimited
                <p class="description">Maximum number of usages per coupon. <br>For Bulk coupons, maximum number of total usages. If this number is exceeded, the coupon will not be available for issuance and will not validate.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="direktt_cross_sell_max_issuance">How many individual coupons can be issued in total?<br>Not used for bulk coupons</label></th>
            <td>
                <input type="text" name="direktt_cross_sell_max_issuance" id="direktt_cross_sell_max_issuance" value="<?php echo $max_issuance ? intval($max_issuance) : 0; ?>" /> 0 - unlimited
                <p class="description">Maximum total number of issuances per individual coupon. If this number is exceeded, the coupon will not be available for issuance</p>
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
    if (isset($_POST['direktt_cross_sell_max_usage'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_max_usage',
            sanitize_text_field($_POST['direktt_cross_sell_max_usage'])
        );
    }
    if (isset($_POST['direktt_cross_sell_max_issuance'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_max_issuance',
            sanitize_text_field($_POST['direktt_cross_sell_max_issuance'])
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

function direktt_cross_sell_create_issued_database_table()
{
    // Table for issued coupons
    global $wpdb;

    $table_name = $wpdb->prefix . 'direktt_cross_sell_issued';

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
            coupon_valid boolean DEFAULT TRUE,
  			PRIMARY KEY  (ID),
  			KEY partner_id (partner_id),
  			KEY coupon_group_id (coupon_group_id),
            KEY direktt_receiver_user_id (direktt_receiver_user_id),
            KEY coupon_time (coupon_time),
            KEY coupon_guid (coupon_guid)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta($sql);

    $the_default_timestamp_query = "ALTER TABLE $table_name MODIFY COLUMN coupon_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;";

    $wpdb->query($the_default_timestamp_query);
}

function direktt_cross_sell_create_used_individual_database_table()
{
    // Table for used coupons
    global $wpdb;

    $table_name = $wpdb->prefix . 'direktt_cross_sell_used_individual';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
  			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            issued_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  			direktt_validator_user_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            coupon_used_time timestamp NOT NULL,
  			PRIMARY KEY  (ID),
  			KEY issued_id (issued_id),
            KEY direktt_validator_user_id (direktt_validator_user_id),
            KEY coupon_used_time (coupon_used_time)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    $the_default_timestamp_query = "ALTER TABLE $table_name MODIFY COLUMN coupon_used_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;";

    $wpdb->query($the_default_timestamp_query);
}

function direktt_cross_sell_create_used_bulk_database_table()
{
    // Table for used coupons
    global $wpdb;

    $table_name = $wpdb->prefix . 'direktt_cross_sell_used_bulk';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
  			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            issued_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            partner_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  			direktt_validator_user_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            direktt_receiver_user_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            coupon_used_time timestamp NOT NULL,
  			PRIMARY KEY  (ID),
  			KEY issued_id (issued_id),
            KEY partner_id (partner_id),
            KEY direktt_validator_user_id (direktt_validator_user_id),
            KEY direktt_receiver_user_id (direktt_receiver_user_id),
            KEY coupon_used_time (coupon_used_time)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    $the_default_timestamp_query = "ALTER TABLE $table_name MODIFY COLUMN coupon_used_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;";

    $wpdb->query($the_default_timestamp_query);
}

function direktt_cross_sell_get_all_partners()
{
    $args = [
        'post_type'      => 'direkttcspartners',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];

    $posts = get_posts($args);
    $partners = [];
    foreach ($posts as $post) {
        $partners[] = [
            'ID'    => $post->ID,
            'title' => $post->post_title,
        ];
    }
    return $partners;
}

function direktt_cross_sell_get_partners_individual()
{
    global $wpdb;

    $args = [
        'post_type'      => 'direkttcspartners',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];

    $posts = get_posts($args);
    $partners = [];
    foreach ($posts as $post) {
        // Get associated coupon group IDs
        $coupon_group_ids = get_post_meta($post->ID, 'direktt_cross_sell_coupon_groups', false);
        if (empty($coupon_group_ids)) continue;

        // Query attached coupon groups, filter to published, correct type, not fully issued
        $eligible_groups = [];
        foreach ($coupon_group_ids as $group_id) {
            // Is it individual type?
            $type = get_post_meta($group_id, 'direktt_cross_sell_group_type', true);
            if ($type != '1') continue;

            // Get max issuance
            $max_issuance = get_post_meta($group_id, 'direktt_cross_sell_max_issuance', true);
            $max_issuance = (string)$max_issuance === '' ? 0 : intval($max_issuance); // default to 0 (unlimited)

            // If limit, get count of valid issued for this group
            if ($max_issuance > 0) {
                $issued_count = direktt_cross_sell_get_issue_count($group_id, $post->ID);
                // Omit group if fully issued
                if ($issued_count >= $max_issuance) continue;
            }
            // At this point: it's eligible
            $eligible_groups[] = $group_id;
        }

        // Only keep partners with at least one issuable individual group
        if (!empty($eligible_groups)) {
            $partners[] = [
                'ID'    => $post->ID,
                'title' => $post->post_title,
            ];
        }
    }
    return $partners;
}

function direktt_cross_sell_get_partner_coupon_groups($partner_id)
{
    $coupon_group_ids = get_post_meta($partner_id, 'direktt_cross_sell_coupon_groups', false);
    // Some partners may have no associated groups
    if (empty($coupon_group_ids)) return [];
    $args = [
        'post_type'      => 'direkttcscoupon',
        'post__in'       => $coupon_group_ids,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];
    return get_posts($args);
}

function direktt_cross_sell_get_partner_individual_coupon_groups($partner_id)
{
    global $wpdb;

    $coupon_group_ids = get_post_meta($partner_id, 'direktt_cross_sell_coupon_groups', false);
    if (empty($coupon_group_ids)) return [];
    $eligible_group_ids = [];
    foreach ($coupon_group_ids as $gid) {
        // Individual only
        $type = get_post_meta($gid, 'direktt_cross_sell_group_type', true);
        if ($type != '1') continue;

        // Max issuance filter
        $max_issuance = get_post_meta($gid, 'direktt_cross_sell_max_issuance', true);
        $max_issuance = (string)$max_issuance === '' ? 0 : intval($max_issuance);

        $issued_count = direktt_cross_sell_get_issue_count($gid, $partner_id);

        if ($max_issuance > 0 && $issued_count >= $max_issuance) {
            continue;
        } else {
            $eligible_group_ids[] = $gid;
        }
    }
    if (empty($eligible_group_ids)) return [];
    $args = [
        'post_type'      => 'direkttcscoupon',
        'post__in'       => $eligible_group_ids,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];
    return get_posts($args);
}

function direktt_cross_sell_user_can_review()
{
    global $direktt_user;

    // Always allow admin
    if (class_exists('Direktt_User') && Direktt_User::is_direktt_admin()) {
        return true;
    }

    // Check category
    $review_categories = intval(get_option('direktt_cross_sell_review_categories', 0));
    $review_tags = intval(get_option('direktt_cross_sell_review_tags', 0));

    $category_slug = '';
    $tag_slug = '';

    if ($review_categories !== 0) {
        $category = get_term($review_categories, 'direkttusercategories');
        $category_slug = $category ? $category->slug : '';
    }

    if ($review_tags !== 0) {
        $tag = get_term($review_tags, 'direkttusertags');
        $tag_slug = $tag ? $tag->slug : '';
    }

    // Check via provided function
    if (class_exists('Direktt_User') && Direktt_User::has_direktt_taxonomies($direktt_user, $category_slug ? [$category_slug] : [], $tag_slug ? [$tag_slug] : [])) {
        return true;
    }
    return false;
}

function direktt_cross_sell_setup_profile_tool()
{
    $issue_categories = intval(get_option('direktt_cross_sell_issue_categories', 0));
    $issue_tags = intval(get_option('direktt_cross_sell_issue_tags', 0));

    if ($issue_categories !== 0) {
        $category = get_term($issue_categories, 'direkttusercategories');
        $category_slug = $category ? $category->slug : '';
    } else {
        $category_slug = '';
    }

    if ($issue_tags !== 0) {
        $tag = get_term($issue_tags, 'direkttusertags');
        $tag_slug = $tag ? $tag->slug : '';
    } else {
        $tag_slug = '';
    }

    Direktt_Profile::add_profile_tool(
        array(
            "id" => "cross-sell-tool",
            "label" => esc_html__('Cross Sell', 'direktt-cross-sell'),
            "callback" => 'direktt_cross_sell_render_profile_tool',
            "categories" => $category_slug ? [$category_slug] : [],
            "tags" => $tag_slug ? [$tag_slug] : [],
            "priority" => 2
        )
    );
}

/**
 * Get all available bulk coupon groups, with their assigned partners, for display/issue/use.
 *
 * Returns array of:
 * [
 *   'partner_id'    => (int),
 *   'partner_title' => (string),
 *   'coupon_group_id' => (int),
 *   'coupon_group_title' => (string),
 *   'issued_id'     => (int|null),  // issued row ID (if exists), or null
 *   'used_count'    => (int),
 *   'max_usage'     => (int),       // 0 = unlimited
 *   'expires'       => (datetime|null),
 *   'is_available'  => (bool),      // logic eligible for display/use
 * ]
 */
function direktt_cross_sell_get_all_available_bulk_coupons()
{
    global $wpdb;

    // 1. Get all coupon groups of type bulk
    $bulk_coupon_groups = get_posts([
        'post_type'      => 'direkttcscoupon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key'   => 'direktt_cross_sell_group_type',
                'value' => '2', // bulk
            ]
        ],
    ]);

    if (empty($bulk_coupon_groups)) return [];

    $now = current_time('mysql');
    $results = [];

    foreach ($bulk_coupon_groups as $bulk_group) {
        $group_id = $bulk_group->ID;
        $group_title = $bulk_group->post_title;

        // Coupon meta: expiry, max_usage
        $group_validity = get_post_meta($group_id, 'direktt_cross_sell_group_validity', true);
        $max_usage     = intval(get_post_meta($group_id, 'direktt_cross_sell_max_usage', true));
        if ($max_usage < 0) $max_usage = 0;

        // Check expiry logic: always check `direktt_cross_sell_group_validity` and/or possibly individual expiry for issued row
        $group_expiry_days = intval($group_validity);
        $group_expires = null;
        if ($group_expiry_days > 0) {
            $group_expires = date('Y-m-d H:i:s', strtotime($bulk_group->post_date . " +$group_expiry_days days"));
            if ($group_expires < $now) {
                // Coupon group expired, skip
                continue;
            }
        }
        // 2. For each partner *to which this group is assigned*:
        $partner_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'direktt_cross_sell_coupon_groups' AND meta_value = %d",
            $group_id
        ));
        if (empty($partner_ids)) continue;

        // Get partner posts in batch
        $partners = get_posts([
            'post_type' => 'direkttcspartners',
            'post__in'  => $partner_ids,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        $partner_lookup = [];
        foreach ($partners as $p) {
            $partner_lookup[$p->ID] = $p;
        }

        foreach ($partner_ids as $partner_id) {
            if (empty($partner_lookup[$partner_id])) continue;

            $latest_used_count = direktt_cross_sell_get_used_bulk_count($group_id, $partner_id);

            if ($max_usage > 0 && $latest_used_count >= $max_usage) {
                // Usage limit reached - continue
                continue;
            }

            $partner_title = $partner_lookup[$partner_id]->post_title;

            $results[] = [
                'partner_id'         => $partner_id,
                'partner_title'      => $partner_title,
                'coupon_group_id'    => $group_id,
                'coupon_group_title' => $group_title,
                'used_count'         => $latest_used_count,
                'max_usage'          => $max_usage,
                'expires'            => $group_expires,
                'is_available'       => true,
            ];
        }
    }

    return $results;
}

function direktt_cross_sell_process_use_coupon_individual($coupon)
{

    $use_coupon_id = intval($coupon->ID);

    $wpdb = $GLOBALS['wpdb'];
    $used_table = $wpdb->prefix . 'direktt_cross_sell_used_individual';

    // RACE CONDITION SAFE usage check!
    $latest_used_count = direktt_cross_sell_get_used_individual_count(intval($coupon->ID));
    $latest_max_usage = intval(get_post_meta($coupon->coupon_group_id, 'direktt_cross_sell_max_usage', true));

    if ($latest_max_usage > 0 && $latest_used_count >= $latest_max_usage) {
        // Usage limit reached - set error flag
        $redirect_url = add_query_arg([
            'direktt_action'    => 'use_coupon_individual',
            'coupon_id' => intval($use_coupon_id),
            'cross_sell_use_flag' => 2, // error: limit exceeded
        ], remove_query_arg('cross_sell_use_flag'));
        wp_safe_redirect($redirect_url);
        exit;
    } else {
        // Do insert
        $inserted = $wpdb->insert(
            $used_table,
            [
                'issued_id' => $coupon->ID,
                'direktt_validator_user_id' => isset($GLOBALS['direktt_user']['direktt_user_id']) ? $GLOBALS['direktt_user']['direktt_user_id'] : '',
            ],
            [
                '%d',
                '%s'
            ]
        );
        $redirect_url = add_query_arg([
            'direktt_action'    => 'use_coupon_individual',
            'coupon_id' => intval($use_coupon_id),
            'cross_sell_use_flag' => $inserted ? 1 : 3, // 1=success, 3=insert failed
        ], remove_query_arg('cross_sell_use_flag'));
        wp_safe_redirect($redirect_url);
        exit;
    }
}

function direktt_cross_sell_process_use_coupon_bulk($use_coupon_id, $use_partner_id)
{

    $use_coupon_id = intval($use_coupon_id);
    $use_partner_id = intval($use_partner_id);

    $direktt_receiver_user_id = isset($_GET['subscriptionId']) ? sanitize_text_field($_GET['subscriptionId']) : '';
    $direktt_validator_user_id = isset($GLOBALS['direktt_user']['direktt_user_id']) ? $GLOBALS['direktt_user']['direktt_user_id'] : '';

    $wpdb = $GLOBALS['wpdb'];
    $used_table = $wpdb->prefix . 'direktt_cross_sell_used_bulk';

    // RACE CONDITION SAFE usage check!
    $latest_used_count = direktt_cross_sell_get_used_bulk_count($use_coupon_id, $use_partner_id);
    $latest_max_usage = intval(get_post_meta($use_coupon_id, 'direktt_cross_sell_max_usage', true));

    if ($latest_max_usage > 0 && $latest_used_count >= $latest_max_usage) {
        // Usage limit reached - set error flag
        $redirect_url = add_query_arg([
            'direktt_action'    => 'use_coupon_bulk',
            'coupon_id' => $use_coupon_id,
            'cross_sell_use_flag' => 2, // error: limit exceeded
        ], remove_query_arg('cross_sell_use_flag'));
        wp_safe_redirect($redirect_url);
        exit;
    } else {
        // Do insert
        $inserted = $wpdb->insert(
            $used_table,
            [
                'issued_id' => $use_coupon_id,
                'direktt_validator_user_id' => $direktt_validator_user_id,
                'direktt_receiver_user_id' =>  $direktt_receiver_user_id,
                'partner_id' => $use_partner_id
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d'
            ]
        );
        $redirect_url = add_query_arg([
            'direktt_action'    => 'use_coupon_bulk',
            'coupon_id' => $use_coupon_id,
            'cross_sell_use_flag' => $inserted ? 1 : 3, // 1=success, 3=insert failed
        ], remove_query_arg('cross_sell_use_flag'));
        wp_safe_redirect($redirect_url);
        exit;
    }
}

function direktt_cross_sell_render_use_coupon_individual($use_coupon_id)
{
    $wpdb = $GLOBALS['wpdb'];
    $issued_table = $wpdb->prefix . 'direktt_cross_sell_issued';

    $coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM $issued_table WHERE ID = %d", $use_coupon_id));

    if (!$coupon) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Coupon not found.', 'direktt-cross-sell') . '</p></div>';
    } else {

        $partner_post = get_post($coupon->partner_id);
        $coupon_group_post = get_post($coupon->coupon_group_id);

        $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $group_descr = $coupon_group_post ? esc_html($coupon_group_post->post_content) : '';
        $group_type = get_post_meta($coupon->coupon_group_id, 'direktt_cross_sell_group_type', true);

        // Expiry/issuance formatting
        $issued_date = esc_html(mysql2date('Y-m-d H:i:s', $coupon->coupon_time));
        $expires = (empty($coupon->coupon_expires) || $coupon->coupon_expires == '0000-00-00 00:00:00')
            ? esc_html__('No expiry', 'direktt-cross-sell')
            : esc_html(mysql2date('Y-m-d H:i:s', $coupon->coupon_expires));

        // --- Usage counts ---

        $used_count = direktt_cross_sell_get_used_individual_count(intval($coupon->ID));
        $max_usage = intval(get_post_meta($coupon->coupon_group_id, 'direktt_cross_sell_max_usage', true));

        // --- Use Handler ---
        $status_message = '';
        // --- Use Handler ---
        if (
            isset($_POST['direktt_cs_use_coupon_individual']) &&
            isset($_POST['direktt_cs_use_coupon_nonce']) &&
            wp_verify_nonce($_POST['direktt_cs_use_coupon_nonce'], 'direktt_cs_use_coupon_action')
        ) {
            direktt_cross_sell_process_use_coupon_individual($coupon);
        }

        // Display appropriate cross_sell_use_flag notice
        if (isset($_GET['cross_sell_use_flag'])) {
            $flag = intval($_GET['cross_sell_use_flag']);
            if ($flag === 1) {
                echo '<div class="notice notice-success"><p>' . esc_html__('Coupon used successfully.', 'direktt-cross-sell') . '</p></div>';
            } elseif ($flag === 2) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Coupon usage limit has been reached; cannot use coupon.', 'direktt-cross-sell') . '</p></div>';
            } elseif ($flag === 3) {
                echo '<div class="notice notice-error"><p>' . esc_html__('There was an error recording coupon usage. Please try again.', 'direktt-cross-sell') . '</p></div>';
            }
        }

        // Coupon info table
        echo '<h2>' . esc_html__('Use Coupon', 'direktt-cross-sell') . '</h2>';
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__('Partner Name', 'direktt-cross-sell') . '</th><td>' . $partner_name . '</td></tr>';
        echo '<tr><th>' . esc_html__('Coupon Title', 'direktt-cross-sell') . '</th><td>' . $group_title . '</td></tr>';
        echo '<tr><th>' . esc_html__('Description', 'direktt-cross-sell') . '</th><td>' . $group_descr . '</td></tr>';
        echo '<tr><th>' . esc_html__('Issued At', 'direktt-cross-sell') . '</th><td>' . $issued_date . '</td></tr>';
        echo '<tr><th>' . esc_html__('Expires', 'direktt-cross-sell') . '</th><td>' . $expires . '</td></tr>';

        if ($group_type == '2') {
            echo '<tr><th>' . esc_html__('Bulk Coupon Usage', 'direktt-cross-sell') . '</th><td>';
            printf(
                esc_html__('%1$s / %2$s', 'direktt-cross-sell'),
                esc_html($used_count),
                ($max_usage == 0 ? esc_html__('Unlimited', 'direktt-cross-sell') : esc_html($max_usage))
            );
            echo '</td></tr>';
        } else {
            echo '<tr><th>' . esc_html__('Usages', 'direktt-cross-sell') . '</th><td>';
            printf(
                esc_html__('%1$s / %2$s', 'direktt-cross-sell'),
                esc_html($used_count),
                ($max_usage == 0 ? esc_html__('Unlimited', 'direktt-cross-sell') : esc_html($max_usage))
            );
            echo '</td></tr>';
        }
        echo '</table>';

        // Only show "Use" button if not used up
        $disable_use = false;
        if ($max_usage > 0 && $used_count >= $max_usage) {
            $disable_use = true;
        }
        if (!$disable_use && empty($status_message)) {
    ?>
            <form method="post" action="" onsubmit="return direkttCSConfirmCouponUse('<?php echo esc_js($group_title); ?>');">
                <input type="hidden" name="direktt_cs_use_coupon_nonce" value="<?php echo esc_attr(wp_create_nonce('direktt_cs_use_coupon_action')); ?>">
                <input type="submit" name="direktt_cs_use_coupon_individual" class="button button-primary" value="<?php echo esc_attr__('Use Coupon', 'direktt-cross-sell'); ?>">
            </form>
            <script>
                function direkttCSConfirmCouponUse(title) {
                    return window.confirm('<?php echo esc_js(__('Are you sure you want to use this coupon for: ', 'direktt-cross-sell')); ?>' + title + '?');
                }
            </script>
        <?php
        } elseif ($disable_use) {
            echo '<p><em>' . esc_html__('This coupon has reached its usage limit.', 'direktt-cross-sell') . '</em></p>';
        }

        // Back
        $back_url = remove_query_arg(['direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag']);
        echo '<a href="' . esc_url($back_url) . '">' . esc_html__('Back to Cross-Sell', 'direktt-cross-sell') . '</a>';
    }
}

function direktt_cross_sell_render_use_coupon_bulk($use_coupon_id, $use_partner_id)
{
    $wpdb = $GLOBALS['wpdb'];

    $coupon_group_post = get_post(intval($use_coupon_id));

    if (!$coupon_group_post) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Coupon not found.', 'direktt-cross-sell') . '</p></div>';
    } else {

        $partner_post = get_post(intval($use_partner_id));

        $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $group_descr = $coupon_group_post ? esc_html($coupon_group_post->post_content) : '';
        $group_type = get_post_meta($coupon_group_post->ID, 'direktt_cross_sell_group_type', true);

        // Expiry/issuance formatting

        $published_datetime_object = get_post_datetime($coupon_group_post->ID, 'date');
        $issued_date = $published_datetime_object->format('Y-m-d H:i:s');

        $coupon_expires = null;
        $group_validity = get_post_meta($coupon_group_post->ID, 'direktt_cross_sell_group_validity', true);
        if (!empty($group_validity) && intval($group_validity) > 0) {
            $now = current_time('mysql'); // 'Y-m-d H:i:s'
            $coupon_expires = date('Y-m-d H:i:s', strtotime($now . ' + ' . intval($group_validity) . ' days'));
        }

        $expires = (empty($coupon_expires) || $coupon_expires == '0000-00-00 00:00:00')
            ? esc_html__('No expiry', 'direktt-cross-sell')
            : esc_html(mysql2date('Y-m-d H:i:s', $coupon_expires));

        // --- Usage counts ---

        $used_count = direktt_cross_sell_get_used_bulk_count($use_coupon_id, $use_partner_id);
        $max_usage = intval(get_post_meta($coupon_group_post->ID, 'direktt_cross_sell_max_usage', true));

        // --- Use Handler ---
        $status_message = '';
        // --- Use Handler ---
        if (
            isset($_POST['direktt_cs_use_coupon_bulk']) &&
            isset($_POST['direktt_cs_use_coupon_nonce']) &&
            wp_verify_nonce($_POST['direktt_cs_use_coupon_nonce'], 'direktt_cs_use_coupon_action')
        ) {
            direktt_cross_sell_process_use_coupon_bulk($use_coupon_id, $use_partner_id);
        }

        // Display appropriate cross_sell_use_flag notice
        if (isset($_GET['cross_sell_use_flag'])) {
            $flag = intval($_GET['cross_sell_use_flag']);
            if ($flag === 1) {
                echo '<div class="notice notice-success"><p>' . esc_html__('Coupon used successfully.', 'direktt-cross-sell') . '</p></div>';
            } elseif ($flag === 2) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Coupon usage limit has been reached; cannot use coupon.', 'direktt-cross-sell') . '</p></div>';
            } elseif ($flag === 3) {
                echo '<div class="notice notice-error"><p>' . esc_html__('There was an error recording coupon usage. Please try again.', 'direktt-cross-sell') . '</p></div>';
            }
        }

        // Coupon info table
        echo '<h2>' . esc_html__('Use Coupon', 'direktt-cross-sell') . '</h2>';
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__('Partner Name', 'direktt-cross-sell') . '</th><td>' . $partner_name . '</td></tr>';
        echo '<tr><th>' . esc_html__('Coupon Title', 'direktt-cross-sell') . '</th><td>' . $group_title . '</td></tr>';
        echo '<tr><th>' . esc_html__('Description', 'direktt-cross-sell') . '</th><td>' . $group_descr . '</td></tr>';
        echo '<tr><th>' . esc_html__('Issued At', 'direktt-cross-sell') . '</th><td>' . $issued_date . '</td></tr>';
        echo '<tr><th>' . esc_html__('Expires', 'direktt-cross-sell') . '</th><td>' . $expires . '</td></tr>';

        if ($group_type == '2') {
            echo '<tr><th>' . esc_html__('Bulk Coupon Usage', 'direktt-cross-sell') . '</th><td>';
            printf(
                esc_html__('%1$s / %2$s', 'direktt-cross-sell'),
                esc_html($used_count),
                ($max_usage == 0 ? esc_html__('Unlimited', 'direktt-cross-sell') : esc_html($max_usage))
            );
            echo '</td></tr>';
        } else {
            echo '<tr><th>' . esc_html__('Usages', 'direktt-cross-sell') . '</th><td>';
            printf(
                esc_html__('%1$s / %2$s', 'direktt-cross-sell'),
                esc_html($used_count),
                ($max_usage == 0 ? esc_html__('Unlimited', 'direktt-cross-sell') : esc_html($max_usage))
            );
            echo '</td></tr>';
        }
        echo '</table>';

        // Only show "Use" button if not used up
        $disable_use = false;
        if ($max_usage > 0 && $used_count >= $max_usage) {
            $disable_use = true;
        }
        if (!$disable_use && empty($status_message)) {
        ?>
            <form method="post" action="" onsubmit="return direkttCSConfirmCouponUse('<?php echo esc_js($group_title); ?>');">
                <input type="hidden" name="direktt_cs_use_coupon_nonce" value="<?php echo esc_attr(wp_create_nonce('direktt_cs_use_coupon_action')); ?>">
                <input type="submit" name="direktt_cs_use_coupon_bulk" class="button button-primary" value="<?php echo esc_attr__('Use Coupon', 'direktt-cross-sell'); ?>">
            </form>
            <script>
                function direkttCSConfirmCouponUse(title) {
                    return window.confirm('<?php echo esc_js(__('Are you sure you want to use this coupon for: ', 'direktt-cross-sell')); ?>' + title + '?');
                }
            </script>
        <?php
        } elseif ($disable_use) {
            echo '<p><em>' . esc_html__('This coupon has reached its usage limit.', 'direktt-cross-sell') . '</em></p>';
        }

        // Back
        $back_url = remove_query_arg(['direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag']);
        echo '<a href="' . esc_url($back_url) . '">' . esc_html__('Back to Cross-Sell', 'direktt-cross-sell') . '</a>';
    }
}

function direktt_cross_sell_get_issue_count($coupon_group_id, $partner_id)
{
    global $wpdb;

    $table = $wpdb->prefix . 'direktt_cross_sell_issued';
    $issued_count = (int)$wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE coupon_group_id = %d AND partner_id = %d",
            intval($coupon_group_id),
            intval($partner_id)
        )
    );

    return intval($issued_count);
}

function direktt_cross_sell_get_used_individual_count($issue_id)
{
    global $wpdb;
    $used_table = $wpdb->prefix . 'direktt_cross_sell_used_individual';
    $used_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $used_table WHERE issued_id = %s",
        $issue_id
    ));
    return intval($used_count);
}

function direktt_cross_sell_get_used_bulk_count($use_coupon_id, $use_partner_id)
{
    global $wpdb;
    $used_table = $wpdb->prefix . 'direktt_cross_sell_used_bulk';
    $used_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $used_table WHERE issued_id = %d AND partner_id = %d",
        intval($use_coupon_id),
        intval($use_partner_id)
    ));
    return intval($used_count);
}

function direktt_cross_sell_render_individual_partner($partner_id)
{

    if (
        isset($_POST['direktt_cs_issue_coupon']) &&
        isset($_POST['direktt_coupon_group_id']) &&
        isset($_POST['direktt_cs_issue_coupon_nonce']) &&
        wp_verify_nonce($_POST['direktt_cs_issue_coupon_nonce'], 'direktt_cs_issue_coupon_action')
    ) {
        direktt_cross_sell_process_coupon_issue($partner_id, intval($_POST['direktt_coupon_group_id']));
    }

    $status_flag = isset($_GET['cross_sell_status_flag']) ? intval($_GET['cross_sell_status_flag']) : 0;
    $status_message = '';
    if ($status_flag === 1) {
        $status_message = esc_html__('Coupon issued successfully.', 'direktt-cross-sell');
    } elseif ($status_flag === 2) {
        $status_message = esc_html__('There was an error while issuing the coupon.', 'direktt-cross-sell');
    } elseif ($status_flag === 3) {
        $status_message = esc_html__('The issue count has reached its max number. The coupon can not be issued', 'direktt-cross-sell');
    }

    if ($status_message) {
        echo '<div class="notice notice-success"><p>' . $status_message . '</p></div>';
    }

    $partner = get_post($partner_id);
    if (!$partner || $partner->post_type !== 'direkttcspartners') {
        echo '<p>' . esc_html__('Invalid partner selected.', 'direktt-cross-sell') . '</p>';
        $back_url = remove_query_arg(['direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag']);
        echo '<a href="' . esc_url($back_url) . '">' . esc_html__('Back to Cross-Sell', 'direktt-cross-sell') . '</a>';
        return;
    }

    echo '<h2>' . esc_html($partner->post_title) . '</h2>';

    $groups = direktt_cross_sell_get_partner_individual_coupon_groups($partner_id);

    if (empty($groups)) {
        echo '<p>' . esc_html__('No Coupon Groups for this partner.', 'direktt-cross-sell') . '</p>';
    } else {
        ?>
        <ul>
            <?php
            foreach ($groups as $group) {
                $max_issue = intval(get_post_meta($group->ID, 'direktt_cross_sell_max_issuance', true));
                $issue_label = ($max_issue == 0)
                    ? esc_html__('Unlimited', 'direktt-cross-sell')
                    : $max_issue;
            ?>
                <li>
                    <?php echo esc_html($group->post_title); ?>
                    <span>( Issued: <?php echo direktt_cross_sell_get_issue_count($group->ID, $partner_id); ?> / <?php echo $issue_label; ?> )</span>
                    <form method="post" action="" style="display:inline;" class="direktt-cs-issue-form" onsubmit="return direkttCSConfirmIssue('<?php echo esc_js($group->post_title); ?>');">
                        <input type="hidden" name="direktt_coupon_group_id" value="<?php echo esc_attr($group->ID); ?>">
                        <input type="hidden" name="direktt_cs_issue_coupon_nonce" value="<?php echo esc_attr(wp_create_nonce('direktt_cs_issue_coupon_action')); ?>">
                        <input type="submit" name="direktt_cs_issue_coupon" class="button button-primary" value="<?php echo esc_attr__('Issue', 'direktt-cross-sell'); ?>">
                    </form>
                </li>
            <?php
            }
            ?>
        </ul>
        <script>
            function direkttCSConfirmIssue(title) {
                return window.confirm('<?php echo esc_js(__('Are you sure you want to issue a coupon for: ', 'direktt-cross-sell')); ?>' + title + '?');
            }
        </script>
    <?php
    }
    echo '<a href="' . esc_url(remove_query_arg(['direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag'])) . '">' . esc_html__('Back to Cross-Sell', 'direktt-cross-sell') . '</a>';
}

function direktt_cross_sell_process_coupon_issue($partner_id, $coupon_group_id)
{

    global $wpdb, $direktt_user;

    $issued = false;

    // Get receiver user ID from subscriptionId GET param
    $direktt_receiver_user_id = isset($_GET['subscriptionId']) ? sanitize_text_field($_GET['subscriptionId']) : '';

    // Proceed only if there is a receiver for *individuals* (for bulk, not needed)
    // Get coupon group properties
    $group_type     = get_post_meta($coupon_group_id, 'direktt_cross_sell_group_type', true);       // 1 = individual, 2 = bulk
    $group_validity = get_post_meta($coupon_group_id, 'direktt_cross_sell_group_validity', true);   // days (0 = no expiry)

    $table = $wpdb->prefix . 'direktt_cross_sell_issued';

    if ($group_type == '1' && !empty($direktt_receiver_user_id) && !empty($coupon_group_id) && !empty($partner_id) && !empty($direktt_user['direktt_user_id'])) {

        // INDIVIDUAL coupon logic (old code, as before)
        $coupon_expires = null;
        if (!empty($group_validity) && intval($group_validity) > 0) {
            $now = current_time('mysql');
            $coupon_expires = date('Y-m-d H:i:s', strtotime($now . ' + ' . intval($group_validity) . ' days'));
        }

        $max_issuance = get_post_meta(intval($coupon_group_id), 'direktt_cross_sell_max_issuance', true);
        $max_issuance = (string)$max_issuance === '' ? 0 : intval($max_issuance);

        $table = $wpdb->prefix . 'direktt_cross_sell_issued';
        $issued_count = direktt_cross_sell_get_issue_count($coupon_group_id, $partner_id);

        if ($max_issuance != 0 && $issued_count >= $max_issuance) {
            $issued = 3;
        } else {

            $coupon_guid = wp_generate_uuid4();

            $inserted = $wpdb->insert(
                $table,
                [
                    'partner_id'               => $partner_id,
                    'coupon_group_id'          => $coupon_group_id,
                    'direktt_issuer_user_id'   => $direktt_user['direktt_user_id'],
                    'direktt_receiver_user_id' => $direktt_receiver_user_id,
                    'coupon_expires'           => $coupon_expires,
                    'coupon_guid'              => $coupon_guid,
                    // coupon_time: DB default
                ],
                [
                    '%d', // partner_id
                    '%d', // group_id
                    '%s', // issuer
                    '%s', // receiver
                    is_null($coupon_expires) ? 'NULL' : '%s',
                    '%s'  // guid
                ]
            );
            if ((bool) $inserted) {
                $issued = 1;
                //TODO Send Issuance messages
            } else {
                $issued = 2;
            }
        }
    }

    $redirect_url = add_query_arg([
        'direktt_partner_id' => intval($partner_id),
        'cross_sell_status_flag' => $issued
    ], remove_query_arg('cross_sell_status_flag'));
    wp_safe_redirect($redirect_url);
    exit;
}

function direktt_cross_sell_render_partners_individual()
{
    $partners = direktt_cross_sell_get_partners_individual();

    echo '<h2>' . esc_html__('Issue New Coupons', 'direktt-cross-sell') . '</h2>';

    if (empty($partners)) {
        echo '<p>' . esc_html__('No Partners with Individual Coupons Found.', 'direktt-cross-sell') . '</p>';
    } else {
        echo '<ul>';

        foreach ($partners as $partner) {
            $url = add_query_arg([
                'direktt_partner_id' => intval($partner['ID']),
                'direktt_action' => 'view_partner_coupons'
            ]);
            echo '<li><a href="' . esc_url($url) . '">' . esc_html($partner['title']) . '</a></li>';
        }
        echo '</ul>';
    }
}

function direktt_cross_sell_render_available_bulk()
{
    // 4. Output Bulk Coupons
    echo '<h2>' . esc_html__('Available Bulk Coupons', 'direktt-cross-sell') . '</h2>';

    $filtered_bulk_results = direktt_cross_sell_get_all_available_bulk_coupons();

    if (empty($filtered_bulk_results)) {
        echo '<p>' . esc_html__('No active or valid bulk coupons available.', 'direktt-cross-sell') . '</p>';
    } else {
        echo '<table><thead><tr>';
        echo '<th>' . esc_html__('Partner Name', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Coupon Group', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Expires', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Actions', 'direktt-cross-sell') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($filtered_bulk_results as $coupon) {

            $expiry_display = empty($coupon['expires']) || $coupon['expires'] === '0000-00-00 00:00:00'
                ? 'No expiry'
                : esc_html($coupon['expires']);

            echo '<tr>';
            echo '<td>' . esc_html($coupon['partner_title']) . '</td>';
            echo '<td>' . esc_html($coupon['coupon_group_title']) . '</td>';
            echo '<td>' . $expiry_display . '</td>';
            echo '<td>';
            $use_url = add_query_arg([
                'direktt_action' => 'use_coupon_bulk',
                'coupon_id' => intval($coupon['coupon_group_id']),
                'partner_id' => intval($coupon['partner_id']),
            ], remove_query_arg(['direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag']));
            echo '<a class="button button-primary" href="' . esc_url($use_url) . '">' . esc_html__('Use', 'direktt-cross-sell') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}

function direktt_cross_sell_render_issued_individual($subscription_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'direktt_cross_sell_issued';
    $now = current_time('mysql');

    $individual_results = [];
    if (!empty($subscription_id)) {
        $individual_results = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE direktt_receiver_user_id = %s
          AND (coupon_expires IS NULL OR coupon_expires = '0000-00-00 00:00:00' OR coupon_expires >= %s)
          AND coupon_valid = 1
        ORDER BY coupon_time DESC
    ", $subscription_id, $now));
    }

    // 3. Output Individual Coupons
    echo '<h2>' . esc_html__('Issued Individual Coupons', 'direktt-cross-sell') . '</h2>';

    $filtered_individual_results = [];

    foreach ($individual_results as $row) {
        $group_type_val = get_post_meta($row->coupon_group_id, 'direktt_cross_sell_group_type', true);
        if ($group_type_val != '1') continue;

        $partner_post = get_post($row->partner_id);
        $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $coupon_group_post = get_post($row->coupon_group_id);
        $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $issued = esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_time));
        $expires = (empty($row->coupon_expires) || $row->coupon_expires == '0000-00-00 00:00:00')
            ? esc_html__('No expiry', 'direktt-cross-sell')
            : esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_expires));

        // Usage filtering for Individual Coupons
        $max_usage = intval(get_post_meta($row->coupon_group_id, 'direktt_cross_sell_max_usage', true));
        if (!$max_usage) $max_usage = 0;

        $used_count = direktt_cross_sell_get_used_individual_count(intval($row->ID));

        // Hide fully used individual coupons
        if ($max_usage > 0 && $used_count >= $max_usage) continue;

        $filtered_individual_results[] = $row;
    }

    if (empty($filtered_individual_results)) {
        echo '<p>' . esc_html__('No active or valid individual coupons issued to this user.', 'direktt-cross-sell') . '</p>';
    } else {
        echo '<table class="widefat" style="margin-top:16px;"><thead><tr>';
        echo '<th>' . esc_html__('Partner Name', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Coupon Group', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Issued at', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Expires', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Actions', 'direktt-cross-sell') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($filtered_individual_results as $row) {
            // Group type check (individual only)

            $partner_post = get_post($row->partner_id);
            $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
            $coupon_group_post = get_post($row->coupon_group_id);
            $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
            $issued = esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_time));
            $expires = (empty($row->coupon_expires) || $row->coupon_expires == '0000-00-00 00:00:00')
                ? esc_html__('No expiry', 'direktt-cross-sell')
                : esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_expires));
            $used_count = direktt_cross_sell_get_used_individual_count(intval($row->ID));

            echo '<tr>';
            echo '<td>' . $partner_name . '</td>';
            echo '<td>' . $group_title . '</td>';
            echo '<td>' . $issued . '</td>';
            echo '<td>' . $expires . '</td>';
            echo '<td>';
            $invalidate_url =  $url = add_query_arg([
                'direktt_action' => 'invalidate_coupon'
            ]);
            $use_url = add_query_arg([
                'direktt_action' => 'use_coupon_individual',
                'coupon_id' => intval($row->ID),
            ], remove_query_arg(['direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag']));
            echo '<a class="button button-primary" href="' . esc_url($use_url) . '">' . esc_html__('Use', 'direktt-cross-sell') . '</a>';
            // Only show Invalidate for Individual
            echo '<form method="post" action="' . $invalidate_url . '" style="display:inline;" class="direktt-cs-invalidate-form" onsubmit="return direkttCSConfirmInvalidate(\'' . esc_js($group_title) . '\', \'' . esc_js($issued) . '\');">';
            echo '<input type="hidden" name="direktt_cs_invalidate_coupon_nonce" value="' . esc_attr(wp_create_nonce('direktt_cs_invalidate_coupon_action')) . '">';
            echo '<input type="hidden" name="invalid_coupon_id" value="' . esc_attr($row->ID) . '">';
            echo '<input type="submit" name="direktt_cs_invalidate_coupon" class="button button-secondary" value="' . esc_attr__('Invalidate', 'direktt-cross-sell') . '">';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    ?>
    <script>
        function direkttCSConfirmInvalidate(title, issued) {
            return window.confirm('<?php echo esc_js(__('Are you sure you want to invalidate this coupon for: ', 'direktt-cross-sell')); ?>' + title + ' (Issued: ' + issued + ')?');
        }
    </script>
    <?php
}

function direktt_cross_sell_my_coupons()
{

    global $direktt_user;
    global $wpdb;

    $subscription_id = $direktt_user['direktt_user_id'];

    $table = $wpdb->prefix . 'direktt_cross_sell_issued';
    $now = current_time('mysql');

    // --- Show Use Individual Coupon Screen ---
    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'view_coupon' && isset($_GET['coupon_id'])) {

        $coupons = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE ID = %d
    ", intval($_GET['coupon_id'])));

        $coupon = $coupons[0];

        ob_start();

        $back_url = remove_query_arg(['direktt_action', 'coupon_id']);
        echo '<a href="' . esc_url($back_url) . '">' . esc_html__('Back to My Coupons', 'direktt-cross-sell') . '</a>';

        $check_url = get_option('direktt_cross_sell_check_url');
        $validation_url = site_url($check_url, 'https');

        $actionObject = json_encode(
            array(
                "action" => array(
                    "type" => "link",
                    "params" => array(
                        "url" => $validation_url,
                        "target" => "app"
                    ),
                    "retVars" => array(
                        "coupon_code" => $coupon->coupon_guid
                    )
                )
            )
        );

    ?>

        <script type="text/javascript" src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
        <div id="canvas"></div>
        <script type="text/javascript">
            const qrCode = new QRCodeStyling({
                width: 350,
                height: 350,
                type: "svg",
                data: '<?php echo $actionObject ?>',
                image: "https://upload.wikimedia.org/wikipedia/commons/5/51/Facebook_f_logo_%282019%29.svg",
                dotsOptions: {
                    color: "#4267b2",
                    type: "rounded"
                },
                backgroundOptions: {
                    color: "#e9ebee",
                },
                imageOptions: {
                    crossOrigin: "anonymous",
                    margin: 20
                }
            });

            qrCode.append(document.getElementById("canvas"));
            /* qrCode.download({
                name: "qr",
                extension: "svg"
            });*/
        </script>

<?php
  
        return ob_get_clean();
    }

    ob_start();

    $individual_results = [];
    if (!empty($subscription_id)) {
        $individual_results = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE direktt_receiver_user_id = %s
          AND (coupon_expires IS NULL OR coupon_expires = '0000-00-00 00:00:00' OR coupon_expires >= %s)
          AND coupon_valid = 1
        ORDER BY coupon_time DESC
    ", $subscription_id, $now));
    }

    // 3. Output Individual Coupons
    echo '<h2>' . esc_html__('Issued Individual Coupons', 'direktt-cross-sell') . '</h2>';

    $filtered_individual_results = [];

    foreach ($individual_results as $row) {
        $group_type_val = get_post_meta($row->coupon_group_id, 'direktt_cross_sell_group_type', true);
        if ($group_type_val != '1') continue;

        $partner_post = get_post($row->partner_id);
        $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $coupon_group_post = get_post($row->coupon_group_id);
        $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $type_label = esc_html__('Individual', 'direktt-cross-sell');
        $issued = esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_time));
        $expires = (empty($row->coupon_expires) || $row->coupon_expires == '0000-00-00 00:00:00')
            ? esc_html__('No expiry', 'direktt-cross-sell')
            : esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_expires));

        // Usage filtering for Individual Coupons
        $max_usage = intval(get_post_meta($row->coupon_group_id, 'direktt_cross_sell_max_usage', true));
        if (!$max_usage) $max_usage = 0;

        $used_count = direktt_cross_sell_get_used_individual_count(intval($row->ID));

        // Hide fully used individual coupons
        if ($max_usage > 0 && $used_count >= $max_usage) continue;

        $filtered_individual_results[] = $row;
    }

    if (empty($filtered_individual_results)) {
        echo '<p>' . esc_html__('There are no active or valid individual coupons issued', 'direktt-cross-sell') . '</p>';
    } else {
        echo '<table><thead><tr>';
        echo '<th>' . esc_html__('Partner Name', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Coupon Name', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Issued', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Expires', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Used', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('View coupon', 'direktt-cross-sell') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($filtered_individual_results as $row) {
            // Group type check (individual only)

            $partner_post = get_post($row->partner_id);
            $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
            $coupon_group_post = get_post($row->coupon_group_id);
            $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
            $issued = esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_time));
            $expires = (empty($row->coupon_expires) || $row->coupon_expires == '0000-00-00 00:00:00')
                ? esc_html__('No expiry', 'direktt-cross-sell')
                : esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_expires));
            $used_count = direktt_cross_sell_get_used_individual_count(intval($row->ID));

            echo '<tr>';
            echo '<td>' . $partner_name . '</td>';
            echo '<td>' . $group_title . '</td>';
            echo '<td>' . $issued . '</td>';
            echo '<td>' . $expires . '</td>';
            echo '<td>' . $used_count . ' / ' . ($max_usage > 0 ? $max_usage : 'Unlimited') . '</td>';
            $coupon_url = add_query_arg([
                'direktt_action' => 'view_coupon',
                'coupon_id' => $row->ID
            ]);
            echo '<td><a href="' . esc_url($coupon_url) . '">' . esc_html__('View Coupon', 'direktt-cross-sell') . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    return ob_get_clean();
}

function direktt_cross_sell_coupon_validation()
{

    global $direktt_user;
    global $wpdb;

    $subscription_id = $direktt_user['direktt_user_id'];

    ob_start();

    echo 'Validation';
    var_dump($_GET);

    return ob_get_clean();
}

function direktt_cross_sell_process_coupon_invalidate()
{
    global $wpdb;
    $table = $wpdb->prefix . 'direktt_cross_sell_issued';

    if (
        isset($_POST['direktt_cs_invalidate_coupon']) &&
        isset($_POST['invalid_coupon_id']) &&
        isset($_POST['direktt_cs_invalidate_coupon_nonce']) &&
        wp_verify_nonce($_POST['direktt_cs_invalidate_coupon_nonce'], 'direktt_cs_invalidate_coupon_action')
    ) {

        $updated = $wpdb->update(
            $table,
            ['coupon_valid' => 0],
            ['ID' => intval($_POST['invalid_coupon_id'])],
            ['%d'],
            ['%d']
        );

        $redirect_url = remove_query_arg('cross_sell_invalidate_flag');
        wp_safe_redirect($redirect_url);
        exit;
    }
}

function direktt_cross_sell_render_profile_tool()
{
    // --- Show Use Individual Coupon Screen ---
    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'use_coupon_individual' && isset($_GET['coupon_id'])) {

        direktt_cross_sell_render_use_coupon_individual(intval($_GET['coupon_id']));
        return;
    }

    // --- Show Use Individual Coupon Screen ---
    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'use_coupon_bulk' && isset($_GET['coupon_id']) && isset($_GET['partner_id'])) {

        direktt_cross_sell_render_use_coupon_bulk(intval($_GET['coupon_id']), intval($_GET['partner_id']));
        return;
    }

    // --- Show Individual Partner Screen ---

    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'view_partner_coupons' && isset($_GET['direktt_partner_id'])) {

        direktt_cross_sell_render_individual_partner(intval($_GET['direktt_partner_id']));
        return;
    }

    // --- Process Coupon invalidation -- invalidate_coupon

    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'invalidate_coupon') {
        direktt_cross_sell_process_coupon_invalidate();
    }

    // Partners with Individual Coupon offering
    direktt_cross_sell_render_partners_individual();

    // Show issued coupon list for review-capable users, if subscriptionId is present
    $subscription_id = isset($_GET['subscriptionId']) ? sanitize_text_field($_GET['subscriptionId']) : '';
    if (direktt_cross_sell_user_can_review() && !empty($subscription_id)) {
        direktt_cross_sell_render_issued_individual($subscription_id);
        direktt_cross_sell_render_available_bulk();
    }
}
