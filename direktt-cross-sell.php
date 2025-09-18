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
register_activation_hook(__FILE__, 'direktt_cross_sell_create_used_database_table');

//Cross-Sell Partner Meta Boxes
add_action('add_meta_boxes', 'direktt_cross_sell_partners_add_custom_box');
add_action('save_post', 'save_direktt_cross_sell_partner_meta');
add_action('admin_enqueue_scripts', 'direktt_cross_sell_partners_enqueue_scripts');
add_action('wp_enqueue_scripts', 'direktt_cross_sell_enqueue_fe_scripts');

//Cross-Sell Coupon Groups Meta Boxes
add_action('add_meta_boxes', 'direktt_cross_sell_coupon_groups_add_custom_box');
add_action('save_post', 'save_direktt_cross_sell_coupon_groups_meta');

//Cross-Sell Profile Tool Setup
add_action('direktt_setup_profile_tools', 'direktt_cross_sell_setup_profile_tool');

//Reports ajax handlers
add_action('wp_ajax_direktt_cross_sell_get_issued_report', 'handle_direktt_cross_sell_get_issued_report');
add_action('wp_ajax_direktt_cross_sell_get_used_report', 'handle_direktt_cross_sell_get_used_report');

// [direktt_cross_sell_coupon_validation] shortcode implementation
add_shortcode('direktt_cross_sell_coupon_validation', 'direktt_cross_sell_coupon_validation');

// [direktt_cross_sell_user_tool] shortcode implementation
add_shortcode('direktt_cross_sell_user_tool', 'direktt_cross_sell_user_tool');

// handle api for issue coupon
add_action( 'direktt/action/issue_coupon', 'direktt_cross_sell_on_issue_coupon' );

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

        update_option('direktt_cross_sell_check_slug', isset($_POST['direktt_cross_sell_check_slug']) ? sanitize_text_field($_POST['direktt_cross_sell_check_slug']) : '');
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

    $check_slug = get_option('direktt_cross_sell_check_slug');
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
                    <th scope="row"><label for="direktt_cross_sell_check_slug">Coupon Validation Slug</label></th>
                    <td>
                        <input type="text" name="direktt_cross_sell_check_slug" id="direktt_cross_sell_check_slug" value="<?php echo esc_attr($check_slug); ?>" size="80" />
                        <p class="description">Slug of the page with the Cross-Sell Coupon Validation shortcode</p>
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
        esc_html__('Partner Properties', 'direktt-cross-sell'),                       // Title
        'direktt_cross_sell_partners_render_custom_box',    // Callback function
        'direkttcspartners',                    // CPT slug
        'normal',                        // Context
        'high'                           // Priority
    );

    add_meta_box(
        'direktt_cs_partners_reports_mb',           // ID
        esc_html__('CSV Reports', 'direktt-cross-sell'),                       // Title
        'direktt_cross_sell_partners_render_reports_meta_box',    // Callback function
        'direkttcspartners',                    // CPT slug
        'normal',                        // Context
        'high'                           // Priority
    );
}

function direktt_cross_sell_get_partners_coupon_groups($post_id)
{
    $coupon_groups = direktt_cross_sell_get_coupon_groups();
    $partners_coupon_groups = array();
    foreach ( $coupon_groups as $group ) {
        if ( $post_id === intval(get_post_meta($group['value'], 'direktt_cross_sell_partner_for_coupon_group', true)) ) {
            $partners_coupon_groups[] = array(
                'value' => $group['value'],
                'title' => $group['title']
            );
        }
    }
    return $partners_coupon_groups;
}

function direktt_cross_sell_partners_render_custom_box($post)
{
    $all_categories = Direktt_User::get_all_user_categories();
    $all_tags = Direktt_User::get_all_user_tags();

    $partner_categories = get_post_meta($post->ID, 'direktt_cross_sell_partner_categories', true);
    $partner_tags = get_post_meta($post->ID, 'direktt_cross_sell_partner_tags', true);

    $partners_coupon_groups = direktt_cross_sell_get_partners_coupon_groups($post->ID);

    $partners = array_filter(direktt_cross_sell_get_all_partners(), function ($partner) use ($post) {
        return $partner['ID'] !== $post->ID;
    });
    $partner_ids_for_who_can_edit = get_post_meta($post->ID, 'direktt_cross_sell_partners_for_who_can_edit', false);

    $qr_code_image = get_post_meta($post->ID, 'direktt_cross_sell_qr_code_image', true);
    $qr_code_color = get_post_meta($post->ID, 'direktt_cross_sell_qr_code_color', true);
    $qr_code_bg_color = get_post_meta($post->ID, 'direktt_cross_sell_qr_code_bg_color', true);

    wp_nonce_field('direktt_cross_sell_save', 'direktt_cross_sell_nonce');
?>

    <script>
        var allPartners = <?php echo json_encode(array_values($partners)); ?>;
        var partners = <?php echo json_encode($partner_ids_for_who_can_edit); ?>;
    </script>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="direktt_cross_sell_partners_coupon_groups">Coupon Groups</label></th>
            <td>
                <?php if (count($partners_coupon_groups) > 0): ?>
                    <ul>
                        <?php foreach ($partners_coupon_groups as $group): ?>
                            <li><?php echo esc_html($group['title']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No coupon groups assigned to this partner.</p>
                <?php endif; ?>
                <p class="description">These are the coupon groups assigned to this partner.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Partners for who can edit</th>
            <td>
                <div id="direktt_cross_sell_partners_repeater">
                    <!-- JS will render fields here -->
                </div>
                <button type="button" class="button" id="add_partner">Add Partner</button>
                <script>
                    (function($) {
                        function renderGroup(index, value) {
                            var options = '<option value="0">Select Partner</option>';
                            if (allPartners.length) {
                                allPartners.forEach(function(p) {
                                    var selected = (p.ID == value) ? 'selected' : '';
                                    options += `<option value="${p.ID}" ${selected}>${p.title}</option>`;
                                });
                            }
                            return `
                        <div class="partner" style="margin-bottom:8px;">
                            <label>
                                <select name="direktt_cross_sell_partners_for_who_can_edit[]" class="direktt_partner_select">
                                    ${options}
                                </select>
                            </label>
                            <button type="button" class="button remove_partner">Remove</button>
                        </div>`;
                        }

                        function refreshPartners() {
                            var html = '';
                            if (partners.length) {
                                for (var i = 0; i < partners.length; i++) {
                                    html += renderGroup(i, partners[i]);
                                }
                            }
                            $('#direktt_cross_sell_partners_repeater').html(html);
                        }
                        $(document).ready(function() {
                            refreshPartners();
                            $('#add_partner').on('click', function(e) {
                                e.preventDefault();
                                $('#direktt_cross_sell_partners_repeater').append(renderGroup('', '0'));
                            });
                            $('#direktt_cross_sell_partners_repeater').on('click', '.remove_partner', function(e) {
                                e.preventDefault();
                                $(this).closest('.partner').remove();
                            });
                        });
                    })(jQuery);
                </script>
                <p class="description">Add partners which this partner will be available to edit.</p>
            </td>
        </tr>
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
            <th scope="row"><label for="direktt_cross_sell_qr_code_image">QR Code Image</label></th>
            <td>
                <input type="text" id="direktt_cross_sell_qr_code_image" name="direktt_cross_sell_qr_code_image" value="<?php echo esc_attr($qr_code_image ?? ''); ?>" />
                <input type="button" id="direktt_cross_sell_qr_code_image_button" class="button" value="Choose Image" />
                <p class="description">TODO Promeniti ovaj tekst: This image will be in center of the QR Code.</p>
                <script>
                    jQuery(document).ready(function($) {
                        var mediaUploader;

                        $('#direktt_cross_sell_qr_code_image_button').click(function(e) {
                            e.preventDefault();

                            // If the uploader object has already been created, reopen it
                            if (mediaUploader) {
                                mediaUploader.open();
                                return;
                            }

                            // Create the media uploader
                            mediaUploader = wp.media.frames.file_frame = wp.media({
                                title: '<?php echo esc_js(__('Choose Image', 'direktt-cross-sell')); ?>',
                                button: {
                                    text: '<?php echo esc_js(__('Choose Image', 'direktt-cross-sell')); ?>'
                                },
                                multiple: false
                            });

                            // When an image is selected, run a callback
                            mediaUploader.on('select', function() {
                                var attachment = mediaUploader.state().get('selection').first().toJSON();
                                $('#direktt_cross_sell_qr_code_image').val(attachment.url);
                                $('#direktt_cross_sell_qr_code_image').trigger('change');
                            });

                            // Open the uploader dialog
                            mediaUploader.open();
                        });
                    });
                </script>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="direktt_cross_sell_qr_code_color">QR Code Color</label></th>
            <td>
                <input type="text" id="direktt_cross_sell_qr_code_color" name="direktt_cross_sell_qr_code_color" value="<?php echo esc_attr($qr_code_color ?? '#000000'); ?>" />
                <p class="description">TODO Promeniti ovaj tekst: This color will be the color of the dots in the QR Code.</p>
                <script>
                    jQuery(document).ready(function($) {
                        $('#direktt_cross_sell_qr_code_color').wpColorPicker();
                    });
                </script>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="direktt_cross_sell_qr_code_bg_color">QR Code Background Color</label></th>
            <td>
                <input type="text" id="direktt_cross_sell_qr_code_bg_color" name="direktt_cross_sell_qr_code_bg_color" value="<?php echo esc_attr($qr_code_bg_color ?? '#ffffff'); ?>" />
                <p class="description">TODO Promeniti ovaj tekst: This color will be the color of the background in the QR Code.</p>
                <script>
                    jQuery(document).ready(function($) {
                        $('#direktt_cross_sell_qr_code_bg_color').wpColorPicker();
                    });
                </script>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="direktt_cross_sell_qr_code_bg_color">QR Code Preview</label></th>
            <td>
                <div id="canvas"></div>
                <?php
                $actionObject = json_encode(
                    array(
                        "action" => array(
                            "type" => "link",
                            "params" => array(
                                "url" => "direktt.com",
                                "target" => "browser"
                            ),
                            "retVars" => array()
                        )
                    )
                );
                ?>
                <script type="text/javascript">
                    const qrCode = new QRCodeStyling({
                        width: 350,
                        height: 350,
                        type: "svg",
                        data: '<?php echo $actionObject ?>',
                        image: '<?php echo $qr_code_image ? esc_js($qr_code_image) : ''; ?>',
                        dotsOptions: {
                            color: '<?php echo $qr_code_color ? esc_js($qr_code_color) : '#000000'; ?>',
                            type: "rounded"
                        },
                        backgroundOptions: {
                            color: '<?php echo $qr_code_bg_color ? esc_js($qr_code_bg_color) : '#ffffff'; ?>',
                        },
                        imageOptions: {
                            crossOrigin: "anonymous",
                            margin: 20
                        }
                    });

                    qrCode.append(document.getElementById("canvas"));

                    jQuery(document).ready(function($) {
                        $('#direktt_cross_sell_qr_code_image').on('change', function() {
                            var newQrCode = new QRCodeStyling({
                                width: 350,
                                height: 350,
                                type: "svg",
                                data: '<?php echo $actionObject ?>',
                                image: $('#direktt_cross_sell_qr_code_image').val() ? $('#direktt_cross_sell_qr_code_image').val() : '',
                                dotsOptions: {
                                    color: $('#direktt_cross_sell_qr_code_color').val() ? $('#direktt_cross_sell_qr_code_color').val() : '#000000',
                                    type: "rounded"
                                },
                                backgroundOptions: {
                                    color: $('#direktt_cross_sell_qr_code_bg_color').val() ? $('#direktt_cross_sell_qr_code_bg_color').val() : '#ffffff',
                                },
                                imageOptions: {
                                    crossOrigin: "anonymous",
                                    margin: 20
                                }
                            });

                            $('#canvas').empty();
                            newQrCode.append(document.getElementById("canvas"));
                        });
                        $('#direktt_cross_sell_qr_code_color').wpColorPicker({
                            change: function (event, ui) {
                                let color = ui.color.toString();

                                var newQrCode = new QRCodeStyling({
                                    width: 350,
                                    height: 350,
                                    type: "svg",
                                    data: '<?php echo $actionObject ?>',
                                    image: $('#direktt_cross_sell_qr_code_image').val() ? $('#direktt_cross_sell_qr_code_image').val() : '',
                                    dotsOptions: {
                                        color: color,
                                        type: "rounded"
                                    },
                                    backgroundOptions: {
                                        color: $('#direktt_cross_sell_qr_code_bg_color').val() ? $('#direktt_cross_sell_qr_code_bg_color').val() : '#ffffff',
                                    },
                                    imageOptions: {
                                        crossOrigin: "anonymous",
                                        margin: 20
                                    }
                                });

                                $('#canvas').empty();
                                newQrCode.append(document.getElementById("canvas"));
                            }
                        });
                         $('#direktt_cross_sell_qr_code_bg_color').wpColorPicker({
                            change: function (event, ui) {
                                let color = ui.color.toString();

                                var newQrCode = new QRCodeStyling({
                                    width: 350,
                                    height: 350,
                                    type: "svg",
                                    data: '<?php echo $actionObject ?>',
                                    image: $('#direktt_cross_sell_qr_code_image').val() ? $('#direktt_cross_sell_qr_code_image').val() : '',
                                    dotsOptions: {
                                        color: $('#direktt_cross_sell_qr_code_color').val() ? $('#direktt_cross_sell_qr_code_color').val() : '#000000',
                                        type: "rounded"
                                    },
                                    backgroundOptions: {
                                        color: color,
                                    },
                                    imageOptions: {
                                        crossOrigin: "anonymous",
                                        margin: 20
                                    }
                                });

                                $('#canvas').empty();
                                newQrCode.append(document.getElementById("canvas"));
                            }
                        });
                    });
                </script>
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
    <table class="form-table">
        <tr>
            <th scope="row"><label for="direktt-report-range"><?php echo esc_html__('Range', 'direktt-cross-sell'); ?></label></th>
            <td>
                <select id="direktt-report-range" name="direktt_report_range">
                    <option value="7"><?php echo esc_html__('Last 7 days', 'direktt-cross-sell'); ?></option>
                    <option value="30"><?php echo esc_html__('Last 30 days', 'direktt-cross-sell'); ?></option>
                    <option value="90"><?php echo esc_html__('Last 90 days', 'direktt-cross-sell'); ?></option>
                    <option value="custom"><?php echo esc_html__('Custom date range', 'direktt-cross-sell'); ?></option>
                </select>
            </td>
        </tr>
        <tr style="display: none;" id="direktt-custom-dates">
            <th scope="row"><label for="direktt-date-from"><?php echo esc_html__('From - To', 'direktt-cross-sell'); ?></label></th>
            <td>
                <input type="date" id="direktt-date-from" name="direktt_date_from" />
                <?php echo esc_html__('-', 'direktt-cross-sell'); ?>
                <input type="date" id="direktt-date-to" name="direktt_date_to" />
            </td>
        </tr>
    </table>

    <p>
        <button type="button" class="button" id="direktt-generate-issued"><?php echo esc_html__('Generate Issued Report', 'direktt-cross-sell'); ?></button>
        <button type="button" class="button" id="direktt-generate-used"><?php echo esc_html__('Generate Used Report', 'direktt-cross-sell'); ?></button>
    </p>

    <input type="hidden" id="direktt-post-id" value="<?php echo esc_attr($post_id); ?>" />
    <script>
        jQuery(document).ready(function($) {
            // toggle custom date inputs
            $('#direktt-report-range').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#direktt-custom-dates').show();
                } else {
                    $('#direktt-custom-dates').hide();
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
                var data = collectReportData('issued');
                // Basic client-side validation for custom range
                if (data.range === 'custom') {
                    if (!data.from || !data.to) {
                        alert("<?php echo esc_js(__('Please select both From and To dates for a custom range.', 'direktt-cross-sell')); ?>");
                        return;
                    }
                    if (data.from > data.to) {
                        alert("<?php echo esc_js(__('The From date cannot be later than the To date.', 'direktt-cross-sell')); ?>");
                        return;
                    }
                }

                $(this).prop('disabled', true);
                $(this).text("<?php echo esc_js(__('Generating report...', 'direktt-cross-sell')); ?>");
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
                        alert("<?php echo esc_js(__('There was an error.', 'direktt-cross-sell')); ?>");
                    }
                }).always(function() {
                    $('#direktt-generate-issued').prop('disabled', false);
                    $('#direktt-generate-issued').text("<?php echo esc_js(__('Generate Issued Report', 'direktt-cross-sell')); ?>");
                });
            });

            $('#direktt-generate-used').on('click', function() {
                event.preventDefault();
                var data = collectReportData('used');
                if (data.range === 'custom') {
                    if (!data.from || !data.to) {
                        alert("<?php echo esc_js(__('Please select both From and To dates for a custom range.', 'direktt-cross-sell')); ?>");
                        return;
                    }
                    if (data.from > data.to) {
                        alert("<?php echo esc_js(__('The From date cannot be later than the To date.', 'direktt-cross-sell')); ?>");
                        return;
                    }
                }

                $(this).prop('disabled', true);
                $(this).text("<?php echo esc_js(__('Generating report...', 'direktt-cross-sell')); ?>");
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
                        alert("<?php echo esc_js(__('There was an error.', 'direktt-cross-sell')); ?>");
                    }
                }).always(function() {
                    $('#direktt-generate-used').prop('disabled', false);
                    $('#direktt-generate-used').text("<?php echo esc_js(__('Generate Used Report', 'direktt-cross-sell')); ?>");
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
    $used_ind_table  = $wpdb->prefix . 'direktt_cross_sell_used';

    // --- Range filter (applies to coupon_used_time) ---
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

    // --- Used ---
    $query = "
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
    $results = $wpdb->get_results($wpdb->prepare($query, $post_id));

    // --- CSV ---
    $csv = fopen('php://temp', 'r+');

    $headers = [
        'Partner Name',
        'Issue ID',
        'Voucher Group Name',
        'Validator Display Name',
        'Validation Time',
    ];
    fputcsv($csv, $headers);

    // Add results
    foreach ($results as $row) {
        $partner_name       = get_the_title($row->partner_id);
        $voucher_group_name = get_the_title($row->coupon_group_id);
        $profile_user       = Direktt_User::get_user_by_subscription_id($row->direktt_validator_user_id);
        $validator_name     = $profile_user['direktt_display_name'];

        $line = [
            $partner_name,
            $row->issued_id,
            $voucher_group_name,
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

    if (isset($_POST['direktt_cross_sell_partners_for_who_can_edit']) && is_array($_POST['direktt_cross_sell_partners_for_who_can_edit'])) {

        $groups = array_map('sanitize_text_field', $_POST['direktt_cross_sell_partners_for_who_can_edit']);
        $groups = array_filter($groups, function ($group) {
            return !empty($group) && $group !== '0';
        });

        $groups = array_unique($groups);

        delete_post_meta($post_id, 'direktt_cross_sell_partners_for_who_can_edit');
        foreach ($groups as $group) {
            add_post_meta($post_id, 'direktt_cross_sell_partners_for_who_can_edit', $group);
        }
    } else {
        delete_post_meta($post_id, 'direktt_cross_sell_partners_for_who_can_edit');
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
    if (isset($_POST['direktt_cross_sell_qr_code_image'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_qr_code_image',
            sanitize_text_field($_POST['direktt_cross_sell_qr_code_image'])
        );
    }
    if (isset($_POST['direktt_cross_sell_qr_code_color'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_qr_code_color',
            sanitize_text_field($_POST['direktt_cross_sell_qr_code_color'])
        );
    }
    if (isset($_POST['direktt_cross_sell_qr_code_bg_color'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_qr_code_bg_color',
            sanitize_text_field($_POST['direktt_cross_sell_qr_code_bg_color'])
        );
    }
}

function direktt_cross_sell_partners_enqueue_scripts($hook)
{
    $screen = get_current_screen();
    if (in_array($hook, ['post.php', 'post-new.php']) && $screen->post_type === 'direkttcspartners') {
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script(
            'qr-code-styling', // Handle
            plugin_dir_url( __FILE__ ). 'assets/js/qr-code-styling.js', // Source
            array(), // Dependencies (none in this case)
            null,
        );
    }
}

function direktt_cross_sell_enqueue_fe_scripts($hook)
{
    global $enqueue_direktt_cross_sell_scripts;
    if ($enqueue_direktt_cross_sell_scripts) {
        wp_enqueue_script(
            'qr-code-styling', // Handle
            plugin_dir_url( __FILE__ ). 'assets/js/qr-code-styling.js', // Source
            array(), // Dependencies (none in this case)
            null,
        );
    }
}

function direktt_cross_sell_coupon_groups_add_custom_box()
{
    add_meta_box(
        'direktt_cs_partners_mb',           // ID
        esc_html__('Coupon Properties', 'direktt-cross-sell'),                       // Title
        'direktt_cross_sell_coupon_groups_render_custom_box',    // Callback function
        'direkttcscoupon',                    // CPT slug
        'normal',                        // Context
        'high'                           // Priority
    );
}

function direktt_cross_sell_coupon_groups_render_custom_box($post)
{
    $templates = Direktt_Message_Template::get_templates(['all', 'none']);

    $group_validity = get_post_meta($post->ID, 'direktt_cross_sell_group_validity', true);
    $max_usage = get_post_meta($post->ID, 'direktt_cross_sell_max_usage', true);
    if ($max_usage === false) $max_usage = '1';
    $max_issuance = get_post_meta($post->ID, 'direktt_cross_sell_max_issuance', true);
    $group_template = get_post_meta($post->ID, 'direktt_cross_sell_group_template', true);
    $qr_code_message = get_post_meta($post->ID, 'direktt_cross_sell_qr_code_message', true);

    $partners = direktt_cross_sell_get_all_partners();
    $selected_partner = get_post_meta($post->ID, 'direktt_cross_sell_partner_for_coupon_group', true);

    wp_nonce_field('direktt_cross_sell_save', 'direktt_cross_sell_nonce');
?>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="direktt_cross_sell_partner_for_coupon_group">Partner</label></th>
            <td>
                <select name="direktt_cross_sell_partner_for_coupon_group" id="direktt_cross_sell_partner_for_coupon_group">
                    <option value="0">Select Partner</option>
                    <?php foreach ($partners as $partner): ?>
                        <option value="<?php echo esc_attr($partner['ID']); ?>" <?php selected($selected_partner, $partner['ID']); ?>>
                            <?php echo esc_html($partner['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Partner to whom this Coupon Group belongs. Only this partner will be able to issue coupons from this group.</p>
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
            <th scope="row"><label for="direktt_cross_sell_max_usage">How many times can a single coupon be used?</label></th>
            <td>
                <input type="text" name="direktt_cross_sell_max_usage" id="direktt_cross_sell_max_usage" value="<?php echo $max_usage !== false &&  $max_usage !== '' ? intval($max_usage) : 1; ?>" /> 0 - unlimited
                <p class="description">Maximum number of usages per coupon.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="direktt_cross_sell_max_issuance">How many coupons can be issued in total?</label></th>
            <td>
                <input type="text" name="direktt_cross_sell_max_issuance" id="direktt_cross_sell_max_issuance" value="<?php echo $max_issuance ? intval($max_issuance) : 0; ?>" /> 0 - unlimited
                <p class="description">Maximum total number of issuances per coupon. If this number is exceeded, the coupon will not be available for issuance</p>
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
        <tr>
            <th scope="row"><label for="direktt_cross_sell_qr_code_message">QR Code Message</label></th>
            <td>
                <input type="text" name="direktt_cross_sell_qr_code_message" id="direktt_cross_sell_qr_code_message" value="<?php echo esc_attr($qr_code_message); ?>" />
                <p class="description">TODO Promeniti ovaj tekst: When Share button on the Bulk Coupons shortcode page is clicked this message will be displayed with the QR Code.</p>
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

    if (isset($_POST['direktt_cross_sell_partner_for_coupon_group'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_partner_for_coupon_group',
            sanitize_text_field($_POST['direktt_cross_sell_partner_for_coupon_group'])
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
    if (isset($_POST['direktt_cross_sell_qr_code_message'])) {
        update_post_meta(
            $post_id,
            'direktt_cross_sell_qr_code_message',
            sanitize_text_field($_POST['direktt_cross_sell_qr_code_message'])
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

function direktt_cross_sell_create_used_database_table()
{
    // Table for used coupons
    global $wpdb;

    $table_name = $wpdb->prefix . 'direktt_cross_sell_used';

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

function direktt_cross_sell_get_partners()
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
        $coupon_group_ids = direktt_cross_sell_get_partners_coupon_groups($post->ID);
        if (empty($coupon_group_ids)) continue;

        // Query attached coupon groups, filter to published, not fully issued
        $eligible_groups = [];
        foreach ($coupon_group_ids as $index => $group) {
            // Get max issuance
            $max_issuance = get_post_meta($group['value'], 'direktt_cross_sell_max_issuance', true);
            $max_issuance = (string)$max_issuance === '' ? 0 : intval($max_issuance); // default to 0 (unlimited)

            // If limit, get count of valid issued for this group
            if ($max_issuance > 0) {
                $issued_count = direktt_cross_sell_get_issue_count($group['value'], $post->ID);
                // Omit group if fully issued
                if ($issued_count >= $max_issuance) continue;
            }
            // At this point: it's eligible
            $eligible_groups[] = $group['value'];
        }

        // Only keep partners with at least one issuable group
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
    global $wpdb;

    $coupon_group_ids = direktt_cross_sell_get_partners_coupon_groups($partner_id);
    if (empty($coupon_group_ids)) return [];
    $eligible_group_ids = [];
    foreach ($coupon_group_ids as $index => $group) {
        // Max issuance filter
        $max_issuance = get_post_meta($group['value'], 'direktt_cross_sell_max_issuance', true);
        $max_issuance = (string)$max_issuance === '' ? 0 : intval($max_issuance);

        $issued_count = direktt_cross_sell_get_issue_count($group['value'], $partner_id);

        if ($max_issuance > 0 && $issued_count >= $max_issuance) {
            continue;
        } else {
            $eligible_group_ids[] = $group['value'];
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

function direktt_cross_sell_process_use_coupon($coupon)
{

    $use_coupon_id = intval($coupon->ID);

    $wpdb = $GLOBALS['wpdb'];
    $used_table = $wpdb->prefix . 'direktt_cross_sell_used';
    $issued_table = $wpdb->prefix . 'direktt_cross_sell_issued';

    // RACE CONDITION SAFE usage check!
    $latest_used_count = direktt_cross_sell_get_used_count(intval($coupon->ID));
    $latest_max_usage = intval(get_post_meta($coupon->coupon_group_id, 'direktt_cross_sell_max_usage', true));

    if ($latest_max_usage > 0 && $latest_used_count >= $latest_max_usage) {
        // Usage limit reached - set error flag
        $redirect_url = add_query_arg([
            'direktt_action'    => 'use_coupon',
            'coupon_id' => intval($use_coupon_id),
            'cross_sell_use_flag' => 2, // error: limit exceeded
        ], remove_query_arg('cross_sell_use_flag'));
        wp_safe_redirect($redirect_url);
        exit;
    } else {
        $now_time = current_time('mysql');
        $coupon_issued = $wpdb->get_row($wpdb->prepare("SELECT * FROM $issued_table WHERE ID = %d", $use_coupon_id));
        if (!empty($coupon_issued->coupon_expires) && $coupon_issued->coupon_expires != '0000-00-00 00:00:00' && $coupon_issued->coupon_expires < $now_time) {
            // Redirect with error flag or message for expired coupon
            $redirect_url = add_query_arg([
                'direktt_action'    => 'use_coupon',
                'coupon_id' => intval($use_coupon_id),
                'cross_sell_use_flag' => 4, // error: expired
            ], remove_query_arg('cross_sell_use_flag'));
            wp_safe_redirect($redirect_url);
            exit;
        }
        // Do insert
        $inserted = $wpdb->insert(
            $used_table,
            [
                'issued_id' => intval($coupon->ID),
                'direktt_validator_user_id' => isset($GLOBALS['direktt_user']['direktt_user_id']) ? $GLOBALS['direktt_user']['direktt_user_id'] : '',
            ],
            [
                '%d',
                '%s'
            ]
        );
        $redirect_url = add_query_arg([
            'direktt_action'    => 'use_coupon',
            'coupon_id' => intval($use_coupon_id),
            'cross_sell_use_flag' => $inserted ? 1 : 3, // 1=success, 3=insert failed
        ], remove_query_arg('cross_sell_use_flag'));
        wp_safe_redirect($redirect_url);
        exit;
    }
}

function direktt_cross_sell_render_use_coupon($use_coupon_id)
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

        // Expiry/issuance formatting
        $issued_date = esc_html(mysql2date('Y-m-d H:i:s', $coupon->coupon_time));
        $expires = (empty($coupon->coupon_expires) || $coupon->coupon_expires == '0000-00-00 00:00:00')
            ? esc_html__('No expiry', 'direktt-cross-sell')
            : esc_html(mysql2date('Y-m-d H:i:s', $coupon->coupon_expires));

        // --- Usage counts ---

        $used_count = direktt_cross_sell_get_used_count(intval($coupon->ID));
        $max_usage = intval(get_post_meta($coupon->coupon_group_id, 'direktt_cross_sell_max_usage', true));

        // --- Use Handler ---
        $status_message = '';
        // --- Use Handler ---
        if (
            isset($_POST['direktt_cs_use_coupon']) &&
            isset($_POST['direktt_cs_use_coupon_nonce']) &&
            wp_verify_nonce($_POST['direktt_cs_use_coupon_nonce'], 'direktt_cs_use_coupon_action')
        ) {
            direktt_cross_sell_process_use_coupon($coupon);
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
            } elseif ($flag === 4) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Coupon has expired; cannot use coupon.', 'direktt-cross-sell') . '</p></div>';
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

        echo '<tr><th>' . esc_html__('Usages', 'direktt-cross-sell') . '</th><td>';
        printf(
            esc_html__('%1$s / %2$s', 'direktt-cross-sell'),
            esc_html($used_count),
            ($max_usage == 0 ? esc_html__('Unlimited', 'direktt-cross-sell') : esc_html($max_usage))
        );
        echo '</td></tr>';
        echo '</table>';

        // Only show "Use" button if not used up
        $disable_use = false;

        $now_time = current_time('mysql');
        $is_expired = !empty($coupon->coupon_expires)
            && $coupon->coupon_expires != '0000-00-00 00:00:00'
            && $coupon->coupon_expires < $now_time;

        if ($max_usage > 0 && $used_count >= $max_usage) {
            $disable_use = true;
        }
        if ($is_expired) {
            $disable_use = true;
        }

        if (!$disable_use && empty($status_message)) {
    ?>
            <form method="post" action="" onsubmit="return direkttCSConfirmCouponUse('<?php echo esc_js($group_title); ?>');">
                <input type="hidden" name="direktt_cs_use_coupon_nonce" value="<?php echo esc_attr(wp_create_nonce('direktt_cs_use_coupon_action')); ?>">
                <input type="submit" name="direktt_cs_use_coupon" class="button button-primary" value="<?php echo esc_attr__('Use Coupon', 'direktt-cross-sell'); ?>">
            </form>
            <script>
                function direkttCSConfirmCouponUse(title) {
                    return window.confirm('<?php echo esc_js(__('Are you sure you want to use this coupon for: ', 'direktt-cross-sell')); ?>' + title + '?');
                }
            </script>
        <?php
        } elseif ($is_expired) {
            // Coupon expired message
            echo '<p><em>' . esc_html__('This coupon has expired and cannot be used.', 'direktt-cross-sell') . '</em></p>';
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

function direktt_cross_sell_get_used_count($issue_id)
{
    global $wpdb;
    $used_table = $wpdb->prefix . 'direktt_cross_sell_used';
    $used_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $used_table WHERE issued_id = %s",
        $issue_id
    ));
    return intval($used_count);
}

function direktt_cross_sell_render_one_partner($partner_id)
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

    $groups = direktt_cross_sell_get_partner_coupon_groups($partner_id);

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

    // Get coupon group properties
    $group_validity = get_post_meta($coupon_group_id, 'direktt_cross_sell_group_validity', true);   // days (0 = no expiry)

    $table = $wpdb->prefix . 'direktt_cross_sell_issued';

    if (!empty($direktt_receiver_user_id) && !empty($coupon_group_id) && !empty($partner_id) && !empty($direktt_user['direktt_user_id'])) {

        // coupon logic (old code, as before)
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

function direktt_cross_sell_render_partners()
{
    $partners = direktt_cross_sell_get_partners();

    echo '<h2>' . esc_html__('Issue New Coupons', 'direktt-cross-sell') . '</h2>';

    if (empty($partners)) {
        echo '<p>' . esc_html__('No Partners with Coupons Found.', 'direktt-cross-sell') . '</p>';
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

function direktt_cross_sell_render_issued($subscription_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'direktt_cross_sell_issued';
    $now = current_time('mysql');

    $coupon_results = [];
    if (!empty($subscription_id)) {
        $coupon_results = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE direktt_receiver_user_id = %s
          AND (coupon_expires IS NULL OR coupon_expires = '0000-00-00 00:00:00' OR coupon_expires >= %s)
          AND coupon_valid = 1
        ORDER BY coupon_time DESC
    ", $subscription_id, $now));
    }

    // 3. Output Coupons
    echo '<h2>' . esc_html__('Issued Coupons', 'direktt-cross-sell') . '</h2>';

    $filtered_coupon_results = [];

    foreach ($coupon_results as $row) {
        $partner_post = get_post($row->partner_id);
        $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $coupon_group_post = get_post($row->coupon_group_id);
        $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $issued = esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_time));
        $expires = (empty($row->coupon_expires) || $row->coupon_expires == '0000-00-00 00:00:00')
            ? esc_html__('No expiry', 'direktt-cross-sell')
            : esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_expires));

        // Usage filtering for Coupons
        $max_usage = intval(get_post_meta($row->coupon_group_id, 'direktt_cross_sell_max_usage', true));
        if (!$max_usage) $max_usage = 0;

        $used_count = direktt_cross_sell_get_used_count(intval($row->ID));

        // Hide fully used coupons
        if ($max_usage > 0 && $used_count >= $max_usage) continue;

        $filtered_coupon_results[] = $row;
    }

    if (empty($filtered_coupon_results)) {
        echo '<p>' . esc_html__('No active or valid coupons issued to this user.', 'direktt-cross-sell') . '</p>';
    } else {
        echo '<table class="widefat" style="margin-top:16px;"><thead><tr>';
        echo '<th>' . esc_html__('Partner Name', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Coupon Group', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Issued at', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Expires', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Used', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Actions', 'direktt-cross-sell') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($filtered_coupon_results as $row) {
            $partner_post = get_post($row->partner_id);
            $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
            $coupon_group_post = get_post($row->coupon_group_id);
            $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
            $issued = esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_time));
            $expires = (empty($row->coupon_expires) || $row->coupon_expires == '0000-00-00 00:00:00')
                ? esc_html__('No expiry', 'direktt-cross-sell')
                : esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_expires));
            $max_usage = intval(get_post_meta($row->coupon_group_id, 'direktt_cross_sell_max_usage', true));
            if (!$max_usage) $max_usage = 0;
            $used_count = direktt_cross_sell_get_used_count(intval($row->ID));

            echo '<tr>';
            echo '<td>' . $partner_name . '</td>';
            echo '<td>' . $group_title . '</td>';
            echo '<td>' . $issued . '</td>';
            echo '<td>' . $expires . '</td>';
            echo '<td>' . $used_count . ' / ' . ($max_usage > 0 ? $max_usage : 'Unlimited') . '</td>';
            echo '<td>';
            $invalidate_url =  $url = add_query_arg([
                'direktt_action' => 'invalidate_coupon'
            ]);
            $use_url = add_query_arg([
                'direktt_action' => 'use_coupon',
                'coupon_id' => intval($row->ID),
            ], remove_query_arg(['direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag']));
            echo '<a class="button button-primary" href="' . esc_url($use_url) . '">' . esc_html__('Use', 'direktt-cross-sell') . '</a>';
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

    // --- Show Use Coupon Screen ---
    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'view_coupon' && isset($_GET['coupon_id'])) {

        $coupons = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE ID = %d
      AND direktt_receiver_user_id = %s
      AND coupon_valid = 1
", intval($_GET['coupon_id']), $subscription_id));

        if (empty($coupons)) {
            ob_start();
            echo '<p>' . esc_html__('Coupon not found or you do not have permission to view it.', 'direktt-cross-sell') . '</p>';
            return ob_get_clean();
        }

        $coupon = $coupons[0];

        ob_start();

        $qr_code_image = get_post_meta(intval($coupon->partner_id), 'direktt_cross_sell_qr_code_image', true);
        $qr_code_color = get_post_meta(intval($coupon->partner_id), 'direktt_cross_sell_qr_code_color', true);
        $qr_code_bg_color = get_post_meta(intval($coupon->partner_id), 'direktt_cross_sell_qr_code_bg_color', true);

        $back_url = remove_query_arg(['direktt_action', 'coupon_id']);
        echo '<a href="' . esc_url($back_url) . '">' . esc_html__('Back', 'direktt-cross-sell') . '</a>';

        $check_slug = get_option('direktt_cross_sell_check_slug');
        $validation_url = site_url($check_slug, 'https');

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

        global $enqueue_direktt_cross_sell_scripts;
        $enqueue_direktt_cross_sell_scripts = true;

        ?>

        <div id="canvas"></div>
        <script type="text/javascript">
            const qrCode = new QRCodeStyling({
                width: 350,
                height: 350,
                type: "svg",
                data: '<?php echo $actionObject ?>',
                image: '<?php echo $qr_code_image ? esc_js($qr_code_image) : ''; ?>',
                dotsOptions: {
                    color: '<?php echo $qr_code_color ? esc_js($qr_code_color) : '#000000'; ?>',
                    type: "rounded"
                },
                backgroundOptions: {
                    color: '<?php echo $qr_code_bg_color ? esc_js($qr_code_bg_color) : '#ffffff'; ?>',
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

    $coupon_results = [];
    if (!empty($subscription_id)) {
        $coupon_results = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE direktt_receiver_user_id = %s
          AND (coupon_expires IS NULL OR coupon_expires = '0000-00-00 00:00:00' OR coupon_expires >= %s)
          AND coupon_valid = 1
        ORDER BY coupon_time DESC
    ", $subscription_id, $now));
    }

    // 3. Output Coupons
    echo '<h2>' . esc_html__('Issued Coupons', 'direktt-cross-sell') . '</h2>';

    $filtered_coupon_results = [];

    foreach ($coupon_results as $row) {
        $partner_post = get_post($row->partner_id);
        $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $coupon_group_post = get_post($row->coupon_group_id);
        $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
        $issued = esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_time));
        $expires = (empty($row->coupon_expires) || $row->coupon_expires == '0000-00-00 00:00:00')
            ? esc_html__('No expiry', 'direktt-cross-sell')
            : esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_expires));

        // Usage filtering for Coupons
        $max_usage = intval(get_post_meta($row->coupon_group_id, 'direktt_cross_sell_max_usage', true));
        if (!$max_usage) $max_usage = 0;

        $used_count = direktt_cross_sell_get_used_count(intval($row->ID));

        // Hide fully used coupons
        if ($max_usage > 0 && $used_count >= $max_usage) continue;

        $filtered_coupon_results[] = $row;
    }

    if (empty($filtered_coupon_results)) {
        echo '<p>' . esc_html__('There are no active or valid coupons issued', 'direktt-cross-sell') . '</p>';
    } else {
        echo '<table><thead><tr>';
        echo '<th>' . esc_html__('Partner Name', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Coupon Name', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Issued', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Expires', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('Used', 'direktt-cross-sell') . '</th>';
        echo '<th>' . esc_html__('View coupon', 'direktt-cross-sell') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($filtered_coupon_results as $row) {
            $partner_post = get_post($row->partner_id);
            $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
            $coupon_group_post = get_post($row->coupon_group_id);
            $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
            $issued = esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_time));
            $expires = (empty($row->coupon_expires) || $row->coupon_expires == '0000-00-00 00:00:00')
                ? esc_html__('No expiry', 'direktt-cross-sell')
                : esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_expires));
            $max_usage = intval(get_post_meta($row->coupon_group_id, 'direktt_cross_sell_max_usage', true));
            if (!$max_usage) $max_usage = 0;
            $used_count = direktt_cross_sell_get_used_count(intval($row->ID));

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

/**
 * Render coupon information table for coupons (pure display).
 *
 * @param array  $opts
 *      - partner_post: WP_Post
 *      - coupon_group_post: WP_Post
 *      - coupon_data: (object|array) Issued row
 *      - used_count: int
 *      - max_usage: int
 *      - issued_date: string
 *      - expires: string
 */
function direktt_cross_sell_display_coupon_info_table($opts)
{
    $partner_post = $opts['partner_post'];
    $coupon_group_post = $opts['coupon_group_post'];
    $issued_date = $opts['issued_date'];
    $expires = $opts['expires'];
    $used_count = $opts['used_count'];
    $max_usage = $opts['max_usage'];

    $partner_name = $partner_post ? esc_html($partner_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
    $group_title = $coupon_group_post ? esc_html($coupon_group_post->post_title) : esc_html__('Unknown', 'direktt-cross-sell');
    $group_descr = $coupon_group_post ? esc_html($coupon_group_post->post_content) : '';

    echo '<table class="form-table">';
    echo '<tr><th>' . esc_html__('Partner Name', 'direktt-cross-sell') . '</th><td>' . $partner_name . '</td></tr>';
    echo '<tr><th>' . esc_html__('Coupon Title', 'direktt-cross-sell') . '</th><td>' . $group_title . '</td></tr>';
    echo '<tr><th>' . esc_html__('Description', 'direktt-cross-sell') . '</th><td>' . $group_descr . '</td></tr>';
    echo '<tr><th>' . esc_html__('Issued At', 'direktt-cross-sell') . '</th><td>' . $issued_date . '</td></tr>';
    echo '<tr><th>' . esc_html__('Expires', 'direktt-cross-sell') . '</th><td>' . $expires . '</td></tr>';
    echo '<tr><th>' . esc_html__('Usages', 'direktt-cross-sell') . '</th><td>';
    printf(
        esc_html__('%1$s / %2$s', 'direktt-cross-sell'),
        esc_html($used_count),
        ($max_usage == 0 ? esc_html__('Unlimited', 'direktt-cross-sell') : esc_html($max_usage))
    );
    echo '</td></tr>';
    echo '</table>';
}

function direktt_cross_sell_get_partner_and_group_by_coupon_guid($coupon_guid)
{
    global $wpdb;
    $table = $wpdb->prefix . 'direktt_cross_sell_issued';
    $coupon_guid = sanitize_text_field($coupon_guid);

    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT partner_id, coupon_group_id FROM $table WHERE coupon_guid = %s LIMIT 1",
        $coupon_guid
    ), ARRAY_A);

    return $result ? $result : false;
}

function direktt_cross_sell_user_can_validate($partner_id)
{
    global $direktt_user;

    // Always allow admin
    if (class_exists('Direktt_User') && Direktt_User::is_direktt_admin()) {
        return true;
    }

    // Check category

    $validate_categories = intval(get_post_meta(intval($partner_id), 'direktt_cross_sell_partner_categories', true));
    $validate_tags = intval(get_post_meta(intval($partner_id), 'direktt_cross_sell_partner_tags', true));

    $category_slug = '';
    $tag_slug = '';

    if ($validate_categories !== 0) {
        $category = get_term($validate_categories, 'direkttusercategories');
        $category_slug = $category ? $category->slug : '';
    }

    if ($validate_tags !== 0) {
        $tag = get_term($validate_tags, 'direkttusertags');
        $tag_slug = $tag ? $tag->slug : '';
    }

    // Check via provided function
    if (class_exists('Direktt_User') && Direktt_User::has_direktt_taxonomies($direktt_user, $category_slug ? [$category_slug] : [], $tag_slug ? [$tag_slug] : [])) {
        return true;
    }
    return false;
}

function direktt_cross_sell_coupon_validation()
{
    global $wpdb;

    if (isset($_GET['coupon_code'])) {
        $coupon_code = sanitize_text_field($_GET['coupon_code']);
        $additional_data = direktt_cross_sell_get_partner_and_group_by_coupon_guid($coupon_code);
        if ($additional_data) {
            $coupon_id = $additional_data['coupon_group_id'];
            $partner_id =  $additional_data['partner_id'];
        } else {
            ob_start();
            echo esc_html__('The coupon code is not valid', 'direktt-cross-sell');
            return ob_get_clean();
        }
    } else if (isset($_GET['coupon_id']) && isset($_GET['partner_id'])) {
        $coupon_id = intval($_GET['coupon_id']);
        $partner_id = intval($_GET['partner_id']);
    } else {
        return;
    }

    if (!direktt_cross_sell_user_can_validate($partner_id)) {
        return;
    }

    $notice = ''; // For status flags after redirect

    if (isset($coupon_code)) {

        $table = $wpdb->prefix . 'direktt_cross_sell_issued';
        $coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE coupon_guid = %s", $coupon_code));
        if (!$coupon) {
            return '<div class="notice notice-error"><p>' . esc_html__('Coupon not found.', 'direktt-cross-sell') . '</p></div>';
        }

        $partner_post = get_post($coupon->partner_id);
        $coupon_group_post = get_post($coupon->coupon_group_id);

        $issued_date = esc_html(mysql2date('Y-m-d H:i:s', $coupon->coupon_time));
        $expires = (empty($coupon->coupon_expires) || $coupon->coupon_expires == '0000-00-00 00:00:00')
            ? esc_html__('No expiry', 'direktt-cross-sell')
            : esc_html(mysql2date('Y-m-d H:i:s', $coupon->coupon_expires));

        $used_count = direktt_cross_sell_get_used_count(intval($coupon->ID));
        $max_usage = intval(get_post_meta($coupon->coupon_group_id, 'direktt_cross_sell_max_usage', true));
        if (!$max_usage) $max_usage = 0;

        $now_time = current_time('mysql');
        $is_expired = !empty($coupon->coupon_expires)
            && $coupon->coupon_expires != '0000-00-00 00:00:00'
            && $coupon->coupon_expires < $now_time;

        $disable_use = false;
        if ($max_usage > 0 && $used_count >= $max_usage) $disable_use = true;
        if ($is_expired) $disable_use = true;

        // ---- Handle POST (use coupon) -----
        if (
            isset($_POST['direktt_cs_use_coupon']) &&
            isset($_POST['direktt_cs_use_coupon_nonce']) &&
            wp_verify_nonce($_POST['direktt_cs_use_coupon_nonce'], 'direktt_cs_use_coupon_action')
        ) {
            // This will redirect/exit as needed after processing
            direktt_cross_sell_process_use_coupon($coupon);
        }

        // Show notices (using URL flag)
        if (isset($_GET['cross_sell_use_flag'])) {
            $flag = intval($_GET['cross_sell_use_flag']);
            if ($flag === 1) {
                $notice = '<div class="notice notice-success"><p>' . esc_html__('Coupon used successfully.', 'direktt-cross-sell') . '</p></div>';
            } elseif ($flag === 2) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Coupon usage limit has been reached; cannot use coupon.', 'direktt-cross-sell') . '</p></div>';
            } elseif ($flag === 3) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('There was an error recording coupon usage. Please try again.', 'direktt-cross-sell') . '</p></div>';
            } elseif ($flag === 4) {
                $notice = '<div class="notice notice-error"><p>' . esc_html__('Coupon has expired; cannot use coupon.', 'direktt-cross-sell') . '</p></div>';
            }
        }

        // --- Output ---
        ob_start();
        echo $notice;
        direktt_cross_sell_display_coupon_info_table([
            'partner_post'      => $partner_post,
            'coupon_group_post' => $coupon_group_post,
            'issued_date'       => $issued_date,
            'expires'           => $expires,
            'used_count'        => $used_count,
            'max_usage'         => $max_usage,
        ]);

        // Show "Use Coupon" button with JS confirm, if allowed
        if (!$disable_use) { ?>
            <form method="post" action="" onsubmit="return direkttCSConfirmCouponUse('<?php echo esc_js($coupon_group_post ? $coupon_group_post->post_title : 'Coupon'); ?>');">
                <input type="hidden" name="direktt_cs_use_coupon_nonce" value="<?php echo esc_attr(wp_create_nonce('direktt_cs_use_coupon_action')); ?>">
                <input type="submit" name="direktt_cs_use_coupon" class="button button-primary" value="<?php echo esc_attr__('Use Coupon', 'direktt-cross-sell'); ?>">
            </form>
            <script>
                function direkttCSConfirmCouponUse(title) {
                    return window.confirm('<?php echo esc_js(__('Are you sure you want to use this coupon for: ', 'direktt-cross-sell')); ?>' + title + '?');
                }
            </script>
        <?php
        } elseif ($is_expired) {
            echo '<p><em>' . esc_html__('This coupon has expired and cannot be used.', 'direktt-cross-sell') . '</em></p>';
        } elseif ($disable_use) {
            echo '<p><em>' . esc_html__('This coupon has reached its usage limit.', 'direktt-cross-sell') . '</em></p>';
        }

        return ob_get_clean();
    }
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
    // --- Show Use Coupon Screen ---
    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'use_coupon' && isset($_GET['coupon_id'])) {

        direktt_cross_sell_render_use_coupon(intval($_GET['coupon_id']));
        return;
    }

    // --- Show Partner Screen ---

    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'view_partner_coupons' && isset($_GET['direktt_partner_id'])) {

        direktt_cross_sell_render_one_partner(intval($_GET['direktt_partner_id']));
        return;
    }

    // --- Process Coupon invalidation -- invalidate_coupon

    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'invalidate_coupon') {
        direktt_cross_sell_process_coupon_invalidate();
    }

    // Partners with Coupon offering
    direktt_cross_sell_render_partners();

    // Show issued coupon list for review-capable users, if subscriptionId is present
    $subscription_id = isset($_GET['subscriptionId']) ? sanitize_text_field($_GET['subscriptionId']) : '';
    if (direktt_cross_sell_user_can_review() && !empty($subscription_id)) {
        direktt_cross_sell_render_issued($subscription_id);
    }
}

function direktt_cross_sell_user_tool() {
    global $direktt_user;
    global $wpdb;
    $table = $wpdb->prefix . 'direktt_cross_sell_issued';
    $now = current_time('mysql');
    if ( ! $direktt_user ) {
        return esc_html__( 'You must be logged in to view your coupons.', 'direktt-cross-sell' );
    }
    $subscription_id = $direktt_user['direktt_user_id'];

    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'view_coupon' && isset($_GET['coupon_id'])) {
        $coupons = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table
            WHERE ID = %d
            AND direktt_receiver_user_id = %s
            AND coupon_valid = 1
        ", intval($_GET['coupon_id']), $subscription_id));

        if (empty($coupons)) {
            ob_start();
            echo '<p>' . esc_html__('Coupon not found or you do not have permission to view it.', 'direktt-cross-sell') . '</p>';
            return ob_get_clean();
        }

        $coupon = $coupons[0];

        ob_start();

        $qr_code_image = get_post_meta(intval($coupon->partner_id), 'direktt_cross_sell_qr_code_image', true);
        $qr_code_color = get_post_meta(intval($coupon->partner_id), 'direktt_cross_sell_qr_code_color', true);
        $qr_code_bg_color = get_post_meta(intval($coupon->partner_id), 'direktt_cross_sell_qr_code_bg_color', true);

        $back_url = remove_query_arg(['coupon_id']);
        $back_url = add_query_arg( 'direktt_action', 'my_coupons', $back_url );
        echo '<a href="' . esc_url($back_url) . '">' . esc_html__('Back', 'direktt-cross-sell') . '</a>';

        $check_slug = get_option('direktt_cross_sell_check_slug');
        $validation_url = site_url($check_slug, 'https');

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

        global $enqueue_direktt_cross_sell_scripts;
        $enqueue_direktt_cross_sell_scripts = true;

        ?>

        <div id="canvas"></div>
        <script type="text/javascript">
            const qrCode = new QRCodeStyling({
                width: 350,
                height: 350,
                type: "svg",
                data: '<?php echo $actionObject ?>',
                image: '<?php echo $qr_code_image ? esc_js($qr_code_image) : ''; ?>',
                dotsOptions: {
                    color: '<?php echo $qr_code_color ? esc_js($qr_code_color) : '#000000'; ?>',
                    type: "rounded"
                },
                backgroundOptions: {
                    color: '<?php echo $qr_code_bg_color ? esc_js($qr_code_bg_color) : '#ffffff'; ?>',
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

    if (isset($_GET['direktt_action']) && $_GET['direktt_action'] === 'view_partner_coupons' && isset($_GET['direktt_partner_id'])) {
        ob_start();
        $partner_id = intval($_GET['direktt_partner_id']);
        if (
            isset($_POST['direktt_cs_issue_coupon']) &&
            isset($_POST['direktt_coupon_group_id']) &&
            isset($_POST['direktt_cs_issue_coupon_nonce']) &&
            wp_verify_nonce($_POST['direktt_cs_issue_coupon_nonce'], 'direktt_cs_issue_coupon_action')
        ) {
            $qr_code_image = get_post_meta(intval($partner_id), 'direktt_cross_sell_qr_code_image', true);
            $qr_code_color = get_post_meta(intval($partner_id), 'direktt_cross_sell_qr_code_color', true);
            $qr_code_bg_color = get_post_meta(intval($partner_id), 'direktt_cross_sell_qr_code_bg_color', true);
            $qr_code_message = get_post_meta(intval($_POST['direktt_coupon_group_id']), 'direktt_cross_sell_qr_code_message', true);

            $back_url = remove_query_arg(['coupon_id', 'direktt_action']);
            $back_url = add_query_arg( 'direktt_action', 'view_partner_coupons', $back_url );
            echo '<a href="' . esc_url($back_url) . '">' . esc_html__('Back', 'direktt-cross-sell') . '</a>';

            $actionObject = json_encode(
                array(
                    "action" => array(
                        "type" => "api",
                        "params" => array(
                            "actionType" => "issue_coupon"
                        ),
                        "retVars" => array(
                            "partner_id" => sanitize_text_field($partner_id),
                            "coupon_group_id" => sanitize_text_field($_POST['direktt_coupon_group_id']),
                            "issuer_id" => $subscription_id,
                        )
                    )
                )
            );

            global $enqueue_direktt_cross_sell_scripts;
            $enqueue_direktt_cross_sell_scripts = true;

            ?>

            <div id="canvas"></div>
            <button id="share"><?php echo esc_html__('Share', 'direktt-cross-sell'); ?></button>
            <script type="text/javascript">
                const qrCode = new QRCodeStyling({
                    width: 350,
                    height: 350,
                    type: "svg",
                    data: '<?php echo $actionObject ?>',
                    image: '<?php echo $qr_code_image ? esc_js($qr_code_image) : ''; ?>',
                    dotsOptions: {
                        color: '<?php echo $qr_code_color ? esc_js($qr_code_color) : '#000000'; ?>',
                        type: "rounded"
                    },
                    backgroundOptions: {
                        color: '<?php echo $qr_code_bg_color ? esc_js($qr_code_bg_color) : '#ffffff'; ?>',
                    },
                    imageOptions: {
                        crossOrigin: "anonymous",
                        margin: 20
                    }
                });

                qrCode.append(document.getElementById("canvas"));

                document.getElementById("share").addEventListener("click", async () => {
                    qrCode.getRawData("png").then(async (blob) => {
                        const img = new Image();
                        img.onload = async () => {
                            const margin = 20; // margin in pixels
                            const bgColor = '<?php echo $qr_code_bg_color ? esc_js($qr_code_bg_color) : "#ffffff"; ?>';
                            const canvas = document.createElement("canvas");
                            canvas.width = img.width + margin * 2;
                            canvas.height = img.height + margin * 2;
                            const ctx = canvas.getContext("2d");

                            // Fill background
                            ctx.fillStyle = bgColor;
                            ctx.fillRect(0, 0, canvas.width, canvas.height);

                            // Draw QR code in center
                            ctx.drawImage(img, margin, margin);

                            // Convert to blob
                            canvas.toBlob(async (newBlob) => {
                                const file = new File([newBlob], "qr-code.png", {
                                    type: "image/png"
                                });

                                if (navigator.canShare && navigator.canShare({
                                        files: [file]
                                    })) {
                                    try {
                                        await navigator.share({
                                            text: '<?php echo esc_js($qr_code_message); ?>',
                                            files: [file]
                                        });
                                    } catch (err) {
                                        alert('<?php echo esc_js(__("Share failed:", 'direktt-cross-sell')); ?> ' + err.message);
                                    }
                                } else {
                                    alert('<?php echo esc_js(__("Your browser does not support sharing files.", 'direktt-cross-sell')); ?>');
                                }
                            }, "image/png");
                        };
                        img.src = URL.createObjectURL(blob);
                    });
                });
            </script>
            </script>
            <?php
            return ob_get_clean();
        }

        $partner = get_post($partner_id);
        if (!$partner || $partner->post_type !== 'direkttcspartners') {
            echo '<p>' . esc_html__('Invalid partner selected.', 'direktt-cross-sell') . '</p>';
            $back_url = remove_query_arg(['direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag']);
            echo '<a href="' . esc_url($back_url) . '">' . esc_html__('Back to Cross-Sell', 'direktt-cross-sell') . '</a>';
            return;
        }

        echo '<h2>' . esc_html($partner->post_title) . '</h2>';

        $groups = direktt_cross_sell_get_partner_coupon_groups($partner_id);

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
                        <form method="post" action="" style="display:inline;" class="direktt-cs-issue-form">
                            <input type="hidden" name="direktt_coupon_group_id" value="<?php echo esc_attr($group->ID); ?>">
                            <input type="hidden" name="direktt_cs_issue_coupon_nonce" value="<?php echo esc_attr(wp_create_nonce('direktt_cs_issue_coupon_action')); ?>">
                            <input type="submit" name="direktt_cs_issue_coupon" class="button button-primary" value="<?php echo esc_attr__('Issue', 'direktt-cross-sell'); ?>">
                        </form>
                    </li>
                <?php
                }
                ?>
            </ul>
        <?php
        }
        $url = remove_query_arg(['coupon_id', 'direktt_partner_id', 'direktt_action']);
        echo '<a href="' . esc_url($url) . '">' . esc_html__('Back', 'direktt-cross-sell') . '</a>';
        return ob_get_clean();
    }
    
    ob_start();
    echo direktt_cross_sell_my_coupons();
    $partners = direktt_cross_sell_get_partners();
    $eligible_partners = array();
    $category_ids = Direktt_User::get_user_categories($direktt_user['ID']);
    $tag_ids = Direktt_User::get_user_tags($direktt_user['ID']);
    if ( Direktt_User::is_direktt_admin() ) {
        $eligible_partners = $partners;
    } else {
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key' => 'direktt_cross_sell_partner_categories',
                'value' => $category_ids,
                'compare' => 'IN',
            ),
            array(
                'key' => 'direktt_cross_sell_partner_tags',
                'value' => $tag_ids,
                'compare' => 'IN',
            ),
        );

        $args = array(
            'post_type' => 'direkttcspartners',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => $meta_query,
        );

        $partners_with_cat_tag = get_posts($args);
        if ( ! empty( $partners_with_cat_tag ) ) {
            foreach($partners_with_cat_tag as $partner) {
                // uzeti kats i tags od trenutnog usera
                // pogledati da li postoji partner sa tim kats i tags izabranim
                // u eligible partners se dodaje taj partner koji ima kats i tags + svi iz njegove liste partners for who can edit
                $eligible_partners[] = array(
                    'ID' => $partner->ID,
                    'title' => $partner->post_title,
                );

                // Dodati sve partnere iz liste "partners for who can edit"
                $partners_for_edit = get_post_meta($partner->ID, 'direktt_cross_sell_partners_for_who_can_edit', true);
                if (!empty($partners_for_edit)) {
                    if (!is_array($partners_for_edit)) {
                        $partners_for_edit = array($partners_for_edit);
                    }
                    foreach($partners_for_edit as $edit_partner_id) {
                        $edit_partner = get_post($edit_partner_id);
                        if ($edit_partner) {
                            $eligible_partners[] = array(
                                'ID' => $edit_partner->ID,
                                'title' => $edit_partner->post_title,
                            );
                        }
                    }
                }
            }
        }
    }
    
    if (!empty($eligible_partners)) {
        if (!is_array($eligible_partners)) {
            $eligible_partners = array($eligible_partners);
        } else {
            $eligible_partners = array_unique($eligible_partners, SORT_REGULAR);
        }
        echo '<h2>' . esc_html__('Issue New Coupons', 'direktt-cross-sell') . '</h2>';
        echo '<ul>';

        foreach ($eligible_partners as $eligible_partner) {
            $url = add_query_arg([
                'direktt_partner_id' => intval($eligible_partner['ID']),
                'direktt_action' => 'view_partner_coupons'
            ]);
            echo '<li><a href="' . esc_url($url) . '">' . esc_html($eligible_partner['title']) . '</a></li>';
        }
        echo '</ul>';
    }
    return ob_get_clean();
}

function direktt_cross_sell_on_issue_coupon($request) {
    global $direktt_user;
    $direktt_receiver_user_id = $direktt_user['direktt_user_id'] ?? '';
    $coupon_group_id = intval($request['coupon_group_id'] ?? 0);
    $partner_id = intval($request['partner_id'] ?? 0);
    $issuer_id = sanitize_text_field($request['issuer_id'] ?? '');

    global $wpdb;

    $issued = false;

    // Get coupon group properties
    $group_validity = get_post_meta($coupon_group_id, 'direktt_cross_sell_group_validity', true);   // days (0 = no expiry)

    $table = $wpdb->prefix . 'direktt_cross_sell_issued';

    if (!empty($direktt_receiver_user_id) && !empty($coupon_group_id) && !empty($partner_id) && !empty($issuer_id)) {

        // coupon logic (old code, as before)
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

    $data = array(
        'message' => __( 'Coupon issued successfully.', 'direktt-cross-sell' ),
    );
    wp_send_json_success($data, 200);
}