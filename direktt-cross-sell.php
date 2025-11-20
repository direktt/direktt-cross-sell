<?php

/**
 * Plugin Name: Direktt Cross-Sell
 * Description: Direktt Cross-Sell Direktt Plugin
 * Version: 1.0.2
 * Author: Direktt
 * Author URI: https://direktt.com/
 * License: GPL2
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$direktt_cross_sell_plugin_version = "1.0.2";
$direktt_cross_sell_github_update_cache_allowed = true;

require_once plugin_dir_path( __FILE__ ) . 'direktt-github-updater/class-direktt-github-updater.php';

$direktt_cross_sell_plugin_github_updater  = new Direktt_Github_Updater( 
    $direktt_cross_sell_plugin_version, 
    'direktt-cross-sell/direktt-cross-sell.php',
    'https://raw.githubusercontent.com/direktt/direktt-cross-sell/master/info.json',
    'direktt_cross_sell_github_updater',
    $direktt_cross_sell_github_update_cache_allowed );

add_filter( 'plugins_api', array( $direktt_cross_sell_plugin_github_updater, 'github_info' ), 20, 3 );
add_filter( 'site_transient_update_plugins', array( $direktt_cross_sell_plugin_github_updater, 'github_update' ));
add_filter( 'upgrader_process_complete', array( $direktt_cross_sell_plugin_github_updater, 'purge'), 10, 2 );

add_action( 'plugins_loaded', 'direktt_cross_sell_activation_check', -20 );

// Settings Page
add_action( 'direktt_setup_settings_pages', 'direktt_cross_sell_setup_settings_page' );

// Custom Post Types
add_action( 'init', 'direktt_cross_sell_register_custom_post_types' );

// Custom Database Table
register_activation_hook( __FILE__, 'direktt_cross_sell_create_issued_database_table' );
register_activation_hook( __FILE__, 'direktt_cross_sell_create_used_database_table' );

// Cross-Sell Partner Meta Boxes
add_action( 'add_meta_boxes', 'direktt_cross_sell_partners_add_custom_box' );
add_action( 'save_post', 'save_direktt_cross_sell_partner_meta' );
add_action( 'wp_enqueue_scripts', 'direktt_cross_sell_enqueue_fe_scripts' );

// Cross-Sell Coupon Groups Meta Boxes
add_action( 'add_meta_boxes', 'direktt_cross_sell_coupon_groups_add_custom_box' );
add_action( 'save_post', 'save_direktt_cross_sell_coupon_groups_meta' );

// Cross-Sell Profile Tool Setup
add_action( 'direktt_setup_profile_tools', 'direktt_cross_sell_setup_profile_tool' );

// Reports ajax handlers
add_action( 'wp_ajax_direktt_cross_sell_get_issued_report', 'handle_direktt_cross_sell_get_issued_report' );
add_action( 'wp_ajax_direktt_cross_sell_get_used_report', 'handle_direktt_cross_sell_get_used_report' );

// [direktt_cross_sell_coupon_validation] shortcode implementation
add_shortcode( 'direktt_cross_sell_coupon_validation', 'direktt_cross_sell_coupon_validation' );

// [direktt_cross_sell_user_tool] shortcode implementation
add_shortcode( 'direktt_cross_sell_user_tool', 'direktt_cross_sell_user_tool' );

// handle api for issue coupon
add_action( 'direktt/action/issue_coupon', 'direktt_cross_sell_on_issue_coupon' );

// Setup menus
add_action( 'direktt_setup_admin_menu', 'direktt_cross_sell_setup_menu' );

// Add new column for Cross Sell Partner
add_filter( 'manage_direkttcscoupon_posts_columns', 'add_direktt_cross_sell_coupon_cpt_column' );

// Display the Cross Sell Partner data in the column
add_action( 'manage_direkttcscoupon_posts_custom_column', 'display_direktt_cross_sell_coupon_cpt_column', 10, 2 );

// Make the Cross Sell Partner column sortable
add_filter( 'manage_edit-direkttcscoupon_sortable_columns', 'direktt_cross_sell_partner_column_make_sortable' );

// Add sorting functionality for the Cross Sell Partner column
add_action( 'pre_get_posts', 'direktt_cross_sell_sort_partner_column' );

// highlight submenu when on partner/coupon group edit screen
add_action( 'parent_file', 'direktt_cross_sell_highlight_submenu' );

function direktt_cross_sell_activation_check() {

	if (! function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $required_plugin = 'direktt/direktt.php';
    $is_required_active = is_plugin_active($required_plugin)
        || (is_multisite() && is_plugin_active_for_network($required_plugin));

    if (! $is_required_active) {
        // Deactivate this plugin
        deactivate_plugins(plugin_basename(__FILE__));

        // Prevent the “Plugin activated.” notice
        if (isset($_GET['activate'])) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, just removing a query var.
            unset($_GET['activate']);
        }

        // Show an error notice for this request
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__('Direktt Cross-Sell activation failed: The Direktt WordPress Plugin must be active first.', 'direktt-cross-sell')
                . '</p></div>';
        });

        // Optionally also show the inline row message in the plugins list
        add_action(
            'after_plugin_row_direktt-cross-sell/direktt-cross-sell.php',
            function () {
                echo '<tr class="plugin-update-tr"><td colspan="3" style="box-shadow:none;">'
                    . '<div style="color:#b32d2e;font-weight:bold;">'
                    . esc_html__('Direktt Cross-Sell requires the Direktt WordPress Plugin to be active. Please activate it first.', 'direktt-cross-sell')
                    . '</div></td></tr>';
            },
            10,
            0
        );
    }
}

function direktt_cross_sell_setup_menu() {
	add_submenu_page(
		'direktt-dashboard',
		esc_html__( 'Cross-Sell Partners', 'direktt-cross-sell' ),
		esc_html__( 'Cross-Sell Partners', 'direktt-cross-sell' ),
		'edit_posts',
		'edit.php?post_type=direkttcspartners',
		null,
		10
	);

	add_submenu_page(
		'direktt-dashboard',
		esc_html__( 'Cross-Sell Coupon Groups', 'direktt-cross-sell' ),
		esc_html__( 'Cross-Sell Coupon Groups', 'direktt-cross-sell' ),
		'edit_posts',
		'edit.php?post_type=direkttcscoupon',
		null,
		11
	);
}

function direktt_cross_sell_setup_settings_page() {
	Direktt::add_settings_page(
		array(
			'id'       => 'cross-sell',
			'label'    => esc_html__( 'Cross-Sell Settings', 'direktt-cross-sell' ),
			'callback' => 'direktt_cross_sell_settings',
			'priority' => 1,
		)
	);
}

function direktt_cross_sell_settings() {
	// Success message flag
	$success = false;

	// Handle form submission
	if (
		isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['direktt_admin_cross_sales_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['direktt_admin_cross_sales_nonce'] ) ), 'direktt_admin_cross_sales_save' )
	) {
		// Sanitize and update options

		update_option( 'direktt_cross_sell_check_slug', isset( $_POST['direktt_cross_sell_check_slug'] ) ? sanitize_title( wp_unslash( $_POST['direktt_cross_sell_check_slug'] ) ) : '' );
		update_option( 'direktt_cross_sell_issue_categories', isset( $_POST['direktt_cross_sell_issue_categories'] ) ? intval( $_POST['direktt_cross_sell_issue_categories'] ) : 0 );
		update_option( 'direktt_cross_sell_issue_tags', isset( $_POST['direktt_cross_sell_issue_tags'] ) ? intval( $_POST['direktt_cross_sell_issue_tags'] ) : 0 );
		update_option( 'direktt_cross_sell_review_categories', isset( $_POST['direktt_cross_sell_review_categories'] ) ? intval( $_POST['direktt_cross_sell_review_categories'] ) : 0 );
		update_option( 'direktt_cross_sell_review_tags', isset( $_POST['direktt_cross_sell_review_tags'] ) ? intval( $_POST['direktt_cross_sell_review_tags'] ) : 0 );
		update_option( 'direktt_cross_sell_user_issuance', isset( $_POST['direktt_cross_sell_user_issuance'] ) ? 'yes' : 'no' );
		update_option( 'direktt_cross_sell_user_issuance_template', isset( $_POST['direktt_cross_sell_user_issuance_template'] ) ? intval( $_POST['direktt_cross_sell_user_issuance_template'] ) : 0 );
		update_option( 'direktt_cross_sell_admin_issuance', isset( $_POST['direktt_cross_sell_admin_issuance'] ) ? 'yes' : 'no' );
		update_option( 'direktt_cross_sell_admin_issuance_template', isset( $_POST['direktt_cross_sell_admin_issuance_template'] ) ? intval( $_POST['direktt_cross_sell_admin_issuance_template'] ) : 0 );
		update_option( 'direktt_cross_sell_user_usage', isset( $_POST['direktt_cross_sell_user_usage'] ) ? 'yes' : 'no' );
		update_option( 'direktt_cross_sell_user_usage_template', isset( $_POST['direktt_cross_sell_user_usage_template'] ) ? intval( $_POST['direktt_cross_sell_user_usage_template'] ) : 0 );
		update_option( 'direktt_cross_sell_admin_usage', isset( $_POST['direktt_cross_sell_admin_usage'] ) ? 'yes' : 'no' );
		update_option( 'direktt_cross_sell_admin_usage_template', isset( $_POST['direktt_cross_sell_admin_usage_template'] ) ? intval( $_POST['direktt_cross_sell_admin_usage_template'] ) : 0 );

		$success = true;
	}

	// Load stored values
	$check_slug                         = get_option( 'direktt_cross_sell_check_slug' );
	$issue_categories                   = get_option( 'direktt_cross_sell_issue_categories', 0 );
	$issue_tags                         = get_option( 'direktt_cross_sell_issue_tags', 0 );
	$review_categories                  = get_option( 'direktt_cross_sell_review_categories', 0 );
	$review_tags                        = get_option( 'direktt_cross_sell_review_tags', 0 );
	$cross_sell_user_issuance           = get_option( 'direktt_cross_sell_user_issuance', 'no' ) === 'yes';
	$cross_sell_user_issuance_template  = get_option( 'direktt_cross_sell_user_issuance_template', 0 );
	$cross_sell_admin_issuance          = get_option( 'direktt_cross_sell_admin_issuance', 'no' ) === 'yes';
	$cross_sell_admin_issuance_template = get_option( 'direktt_cross_sell_admin_issuance_template', 0 );
	$cross_sell_user_usage              = get_option( 'direktt_cross_sell_user_usage', 'no' ) === 'yes';
	$cross_sell_user_usage_template     = get_option( 'direktt_cross_sell_user_usage_template', 0 );
	$cross_sell_admin_usage             = get_option( 'direktt_cross_sell_admin_usage', 'no' ) === 'yes';
	$cross_sell_admin_usage_template    = get_option( 'direktt_cross_sell_admin_usage_template', 0 );

	// Query for template posts
    $template_args  = array(
        'post_type'      => 'direkttmtemplates',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- - Justification: bounded, cached, selective query on small dataset
            array(
                'key'     => 'direkttMTType',
                'value'   => array( 'all', 'none' ),
                'compare' => 'IN',
            ),
        ),
    );
    $template_posts = get_posts( $template_args );

	$all_categories = Direktt_User::get_all_user_categories();
	$all_tags       = Direktt_User::get_all_user_tags();

	?>
	<div class="wrap">
		<?php if ( $success ) : ?>
			<div class="notice notice-success">
				<p><?php esc_html_e( 'Settings saved successfully.', 'direktt-cross-sell' ); ?></p>
			</div>
		<?php endif; ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'direktt_admin_cross_sales_save', 'direktt_admin_cross_sales_nonce' ); ?>

			<h2 class="title"><?php echo esc_html__( 'General Settings', 'direktt-cross-sell' ); ?></h2>
			<table class="form-table direktt-cross-sell-table">
				<tr>
					<th scope="row"><label for="direktt_cross_sell_check_slug"><?php echo esc_html__( 'Coupon Validation Page Slug', 'direktt-cross-sell' ); ?></label></th>
					<td>
						<input type="text" name="direktt_cross_sell_check_slug" id="direktt_cross_sell_check_slug" value="<?php echo esc_attr( $check_slug ); ?>" size="40" />
						<p class="description"><?php esc_html_e( 'Slug of the page with the Cross-Sell Coupon Validation shortcode', 'direktt-cross-sell' ); ?></p>
					</td>
				</tr>
				<tr>
                    <th scope="row"><label for="direktt_cross_sell_issue_categories"><?php echo esc_html__( 'Users to Issue Coupons', 'direktt-cross-sell' ); ?></label></th>
                    <td>
                       <fieldset class="direktt-category-tag-fieldset">
                            <legend class="screen-reader-text"><span><?php echo esc_html__( 'Users to Issue Coupons', 'direktt-cross-sell' ); ?></span></legend>
                            <label for="direktt_cross_sell_issue_categories"><?php echo esc_html__( 'Category', 'direktt-cross-sell' ); ?></label>
                            <select name="direktt_cross_sell_issue_categories" id="direktt_cross_sell_issue_categories">
								<option value="0"><?php echo esc_html__( 'Select Category', 'direktt-cross-sell' ); ?></option>
								<?php foreach ( $all_categories as $category ) : ?>
									<option value="<?php echo esc_attr( $category['value'] ); ?>" <?php selected( $issue_categories, $category['value'] ); ?>>
										<?php echo esc_html( $category['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
                            <br>
                            <label for="direktt_cross_sell_issue_tags"><?php echo esc_html__( 'Tag', 'direktt-cross-sell' ); ?></label>
                            <select name="direktt_cross_sell_issue_tags" id="direktt_cross_sell_issue_tags">
								<option value="0"><?php echo esc_html__( 'Select Tag', 'direktt-cross-sell' ); ?></option>
								<?php foreach ( $all_tags as $tag ) : ?>
									<option value="<?php echo esc_attr( $tag['value'] ); ?>" <?php selected( $issue_tags, $tag['value'] ); ?>>
										<?php echo esc_html( $tag['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
                        </fieldset>
                        <p class="description"><?php echo esc_html__( 'Users with this category/tag will be able to issue coupons.', 'direktt-cross-sell' ); ?></p>
                    </td>
                </tr>
				<tr>
                    <th scope="row"><label for="direktt_cross_sell_review_categories"><?php echo esc_html__( 'Users to Review Coupons', 'direktt-cross-sell' ); ?></label></th>
                    <td>
                       <fieldset class="direktt-category-tag-fieldset">
                            <legend class="screen-reader-text"><span><?php echo esc_html__( 'Users to Review Coupons', 'direktt-cross-sell' ); ?></span></legend>
                            <label for="direktt_cross_sell_review_categories"><?php echo esc_html__( 'Category', 'direktt-cross-sell' ); ?></label>
                            <select name="direktt_cross_sell_review_categories" id="direktt_cross_sell_review_categories">
								<option value="0"><?php echo esc_html__( 'Select Category', 'direktt-cross-sell' ); ?></option>
								<?php foreach ( $all_categories as $category ) : ?>
									<option value="<?php echo esc_attr( $category['value'] ); ?>" <?php selected( $review_categories, $category['value'] ); ?>>
										<?php echo esc_html( $category['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
                            <br>
                            <label for="direktt_cross_sell_review_tags"><?php echo esc_html__( 'Tag', 'direktt-cross-sell' ); ?></label>
                            <select name="direktt_cross_sell_review_tags" id="direktt_cross_sell_review_tags">
								<option value="0"><?php echo esc_html__( 'Select Tag', 'direktt-cross-sell' ); ?></option>
								<?php foreach ( $all_tags as $tag ) : ?>
									<option value="<?php echo esc_attr( $tag['value'] ); ?>" <?php selected( $review_tags, $tag['value'] ); ?>>
										<?php echo esc_html( $tag['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
                        </fieldset>
                        <p class="description"><?php echo esc_html__( 'Users with this category/tag will be able to review coupons.', 'direktt-cross-sell' ); ?></p>
                    </td>
                </tr>
			</table>
			<h2 class="title"><?php echo esc_html__( 'Coupon Issuance Messages', 'direktt-cross-sell' ); ?></h2>
			<table class="form-table direktt-cross-sell-table">
				<tr>
                    <th scope="row"><label for="direktt_cross_sell_user_issuance"><?php echo esc_html__( 'Send to Subscriber', 'direktt-cross-sell' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="direktt_cross_sell_user_issuance" id="direktt_cross_sell_user_issuance" value="yes" <?php checked( $cross_sell_user_issuance ); ?> />
                        <label for="direktt_cross_sell_user_issuance"><span class="description"><?php echo esc_html__( 'When enabled, a message will be sent to the subscriber when coupon is issued to them.', 'direktt-cross-sell' ); ?></span></label>
                    </td>
                </tr>
                <tr id="direktt-cross-sell-settings-mt-user-issuance-row">
                    <th scope="row"></th>
                    <td>
                        <select name="direktt_cross_sell_user_issuance_template" id="direktt_cross_sell_user_issuance_template">
                            <option value="0"><?php echo esc_html__( 'Select Message Template', 'direktt-cross-sell' ); ?></option>
                            <?php foreach ( $template_posts as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $cross_sell_user_issuance_template, $post->ID ); ?>>
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__( 'You can use following dynamic placeholders in this template:', 'direktt-cross-sell' ); ?></p>
                        <p class="description"><code><?php echo esc_html( '#TODO#' ); ?></code><?php echo esc_html__( ' - TODO.', 'direktt-cross-sell' ); ?></p>
                    </td>
                </tr>
				<tr>
                    <th scope="row"><label for="direktt_cross_sell_admin_issuance"><?php echo esc_html__( 'Send to Admin', 'direktt-cross-sell' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="direktt_cross_sell_admin_issuance" id="direktt_cross_sell_admin_issuance" value="yes" <?php checked( $cross_sell_admin_issuance ); ?> />
                        <label for="direktt_cross_sell_admin_issuance"><span class="description"><?php echo esc_html__( 'When enabled, a message will be sent to the admin when coupon is issued.', 'direktt-cross-sell' ); ?></span></label>
                    </td>
                </tr>
                <tr id="direktt-cross-sell-settings-mt-admin-issuance-row">
                    <th scope="row"></label></th>
                    <td>
                        <select name="direktt_cross_sell_admin_issuance_template" id="direktt_cross_sell_admin_issuance_template">
                            <option value="0"><?php echo esc_html__( 'Select Message Template', 'direktt-cross-sell' ); ?></option>
                            <?php foreach ( $template_posts as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $cross_sell_admin_issuance_template, $post->ID ); ?>>
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__( 'You can use following dynamic placeholders in this template:', 'direktt-cross-sell' ); ?></p>
                        <p class="description"><code><?php echo esc_html( '#TODO#' ); ?></code><?php echo esc_html__( ' - TODO.', 'direktt-cross-sell' ); ?></p>
                    </td>
                </tr>
			</table>
			<h2 class="title"><?php echo esc_html__( 'Coupon Usage Messages', 'direktt-cross-sell' ); ?></h2>
			<table class="form-table direktt-cross-sell-table">
				<tr>
                    <th scope="row"><label for="direktt_cross_sell_user_usage"><?php echo esc_html__( 'Send to Subscriber', 'direktt-cross-sell' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="direktt_cross_sell_user_usage" id="direktt_cross_sell_user_usage" value="yes" <?php checked( $cross_sell_user_usage ); ?> />
                        <label for="direktt_cross_sell_user_usage"><span class="description"><?php echo esc_html__( 'When enabled, a message will be sent to the subscriber when their coupon is used.', 'direktt-cross-sell' ); ?></span></label>
                    </td>
                </tr>
                <tr id="direktt-cross-sell-settings-mt-user-usage-row">
                    <th scope="row"></label></th>
                    <td>
                        <select name="direktt_cross_sell_user_usage_template" id="direktt_cross_sell_user_usage_template">
                            <option value="0"><?php echo esc_html__( 'Select Message Template', 'direktt-cross-sell' ); ?></option>
                            <?php foreach ( $template_posts as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $cross_sell_user_usage_template, $post->ID ); ?>>
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__( 'You can use following dynamic placeholders in this template:', 'direktt-cross-sell' ); ?></p>
                        <p class="description"><code><?php echo esc_html( '#TODO#' ); ?></code><?php echo esc_html__( ' - TODO.', 'direktt-cross-sell' ); ?></p>
                    </td>
                </tr>
				<tr>
                    <th scope="row"><label for="direktt_cross_sell_admin_usage"><?php echo esc_html__( 'Send to Admin', 'direktt-cross-sell' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="direktt_cross_sell_admin_usage" id="direktt_cross_sell_admin_usage" value="yes" <?php checked( $cross_sell_admin_usage ); ?> />
                        <label for="direktt_cross_sell_admin_usage"><span class="description"><?php echo esc_html__( 'When enabled, a message will be sent to the admin when coupon is used.', 'direktt-cross-sell' ); ?></span></label>
                    </td>
                </tr>
                <tr id="direktt-cross-sell-settings-mt-admin-usage-row">
                    <th scope="row"></label></th>
                    <td>
                        <select name="direktt_cross_sell_admin_usage_template" id="direktt_cross_sell_admin_usage_template">
                            <option value="0"><?php echo esc_html__( 'Select Message Template', 'direktt-cross-sell' ); ?></option>
                            <?php foreach ( $template_posts as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $cross_sell_admin_issuance_template, $post->ID ); ?>>
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__( 'You can use following dynamic placeholders in this template:', 'direktt-cross-sell' ); ?></p>
                        <p class="description"><code><?php echo esc_html( '#TODO#' ); ?></code><?php echo esc_html__( ' - TODO.', 'direktt-cross-sell' ); ?></p>
                    </td>
                </tr>
			</table>

			<?php submit_button( esc_html__( 'Save Settings', 'direktt-cross-sell' ) ); ?>
		</form>
	</div>
	<?php
}

function direktt_cross_sell_register_custom_post_types() {

	$labels = array(
		'name'               => esc_html__( 'Cross-Sell Partners', 'direktt-cross-sell' ),
		'singular_name'      => esc_html__( 'Cross-Sell Partner', 'direktt-cross-sell' ),
		'menu_name'          => esc_html__( 'Direktt', 'direktt-cross-sell' ),
		'all_items'          => esc_html__( 'Cross-Sell Partners', 'direktt-cross-sell' ),
		'view_item'          => esc_html__( 'View Partner', 'direktt-cross-sell' ),
		'add_new_item'       => esc_html__( 'Add New Partner', 'direktt-cross-sell' ),
		'add_new'            => esc_html__( 'Add New', 'direktt-cross-sell' ),
		'edit_item'          => esc_html__( 'Edit Partner', 'direktt-cross-sell' ),
		'update_item'        => esc_html__( 'Update Partner', 'direktt-cross-sell' ),
		'search_items'       => esc_html__( 'Search Partners', 'direktt-cross-sell' ),
		'not_found'          => esc_html__( 'Not Found', 'direktt-cross-sell' ),
		'not_found_in_trash' => esc_html__( 'Not found in Trash', 'direktt-cross-sell' ),
	);

	$args = array(
		'label'               => esc_html__( 'partners', 'direktt-cross-sell' ),
		'description'         => esc_html__( 'Cross-Sell Partners', 'direktt-cross-sell' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor' ),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 10,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => false,
		'publicly_queryable'  => false,
		'capability_type'     => 'post',
		'capabilities'        => array(),
		'show_in_rest'        => false,
	);

	register_post_type( 'direkttcspartners', $args );

	// Message templates

	$labels = array(
		'name'               => esc_html__( 'Cross-Sell Coupon Groups', 'direktt-cross-sell' ),
		'singular_name'      => esc_html__( 'Cross-Sell Coupon Group', 'direktt-cross-sell' ),
		'menu_name'          => esc_html__( 'Direktt', 'direktt-cross-sell' ),
		'all_items'          => esc_html__( 'Cross-Sell Coupon Groups', 'direktt-cross-sell' ),
		'view_item'          => esc_html__( 'View Coupon Group', 'direktt-cross-sell' ),
		'add_new_item'       => esc_html__( 'Add New Coupon Group', 'direktt-cross-sell' ),
		'add_new'            => esc_html__( 'Add New', 'direktt-cross-sell' ),
		'edit_item'          => esc_html__( 'Edit Coupon Group', 'direktt-cross-sell' ),
		'update_item'        => esc_html__( 'Update Coupon Group', 'direktt-cross-sell' ),
		'search_items'       => esc_html__( 'Search Coupon Groups', 'direktt-cross-sell' ),
		'not_found'          => esc_html__( 'Not Found', 'direktt-cross-sell' ),
		'not_found_in_trash' => esc_html__( 'Not found in Trash', 'direktt-cross-sell' ),
	);

	$args = array(
		'label'               => esc_html__( 'Cross-Sell Coupon Groups', 'direktt-cross-sell' ),
		'description'         => esc_html__( 'Cross-Sell Partners', 'direktt-cross-sell' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor', 'thumbnail' ),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 11,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => false,
		'publicly_queryable'  => false,
		'capability_type'     => 'post',
		'capabilities'        => array(),
		'show_in_rest'        => false,
	);

	register_post_type( 'direkttcscoupon', $args );
}

function direktt_cross_sell_get_coupon_groups() {
	$cs_args = array(
		'post_type'      => 'direkttcscoupon',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	$cs_posts = get_posts( $cs_args );

	$coupon_groups = array();

	foreach ( $cs_posts as $post ) {
		$coupon_groups[] = array(
			'value' => $post->ID,
			'title' => $post->post_title,
		);
	}

	return $coupon_groups;
}

function direktt_cross_sell_partners_add_custom_box() {
	add_meta_box(
		'direktt_cs_partners_mb',           // ID
		esc_html__( 'Partner Properties', 'direktt-cross-sell' ),                       // Title
		'direktt_cross_sell_partners_render_custom_box',    // Callback function
		'direkttcspartners',                    // CPT slug
		'normal',                        // Context
		'high'                           // Priority
	);

	add_meta_box(
		'direktt_cs_partners_reports_mb',           // ID
		esc_html__( 'CSV Reports', 'direktt-cross-sell' ),                       // Title
		'direktt_cross_sell_partners_render_reports_meta_box',    // Callback function
		'direkttcspartners',                    // CPT slug
		'normal',                        // Context
		'high'                           // Priority
	);
}

function direktt_cross_sell_get_partners_coupon_groups( $post_id ) {
	$coupon_groups          = direktt_cross_sell_get_coupon_groups();
	$partners_coupon_groups = array();
	foreach ( $coupon_groups as $group ) {
		if ( $post_id === intval( get_post_meta( $group['value'], 'direktt_cross_sell_partner_for_coupon_group', true ) ) ) {
			$partners_coupon_groups[] = array(
				'value' => $group['value'],
				'title' => $group['title'],
			);
		}
	}
	return $partners_coupon_groups;
}

function direktt_cross_sell_partners_render_custom_box( $post ) {
	$all_categories = Direktt_User::get_all_user_categories();
	$all_tags       = Direktt_User::get_all_user_tags();

	$partner_categories = get_post_meta( $post->ID, 'direktt_cross_sell_partner_categories', true );
	$partner_tags       = get_post_meta( $post->ID, 'direktt_cross_sell_partner_tags', true );

	$partners_coupon_groups = direktt_cross_sell_get_partners_coupon_groups( $post->ID );

	$partners                     = array_filter(
		direktt_cross_sell_get_all_partners(),
		function ( $partner ) use ( $post ) {
			return $partner['ID'] !== $post->ID;
		}
	);
	$partner_ids_for_who_can_edit = get_post_meta( $post->ID, 'direktt_cross_sell_partners_for_who_can_edit', false );

	$qr_code_image    = get_post_meta( $post->ID, 'direktt_cross_sell_qr_code_image', true );
	$qr_code_color    = get_post_meta( $post->ID, 'direktt_cross_sell_qr_code_color', true );
	$qr_code_bg_color = get_post_meta( $post->ID, 'direktt_cross_sell_qr_code_bg_color', true );

	wp_nonce_field( 'direktt_cross_sell_save', 'direktt_cross_sell_nonce' );
	?>

	<script>
		var allPartners = <?php echo json_encode( array_values( $partners ) ); ?>;
		var partners = <?php echo json_encode( $partner_ids_for_who_can_edit ); ?>;
	</script>

	<table class="direktt-profile-data-cross-sell-tool-table">
			<thead>
			<tr>
				<th scope="row"><label for="direktt_cross_sell_partners_coupon_groups"><?php echo esc_html__( 'Coupon Groups', 'direktt-cross-sell' ); ?></label></th>
				<td>
					<?php if ( count( $partners_coupon_groups ) > 0 ) : ?>
						<ul>
							<?php foreach ( $partners_coupon_groups as $group ) : ?>
								<li><?php echo esc_html( $group['title'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p><?php echo esc_html__( 'No coupon groups assigned to this partner.', 'direktt-cross-sell' ); ?></p>
					<?php endif; ?>
					<p class="description"><?php echo esc_html__( 'These are the coupon groups assigned to this partner.', 'direktt-cross-sell' ); ?></p>
				</td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Can issue coupons for:', 'direktt-cross-sell' ); ?></th>
				<td>
					<div id="direktt_cross_sell_partners_repeater">
						<!-- JS will render fields here -->
					</div>
					<button type="button" class="button" id="add_partner"><?php echo esc_html__( 'Add Partner', 'direktt-cross-sell' ); ?></button>
					<script>
						(function($) {
							function renderGroup(index, value) {
								var options = '<option value="0"><?php echo esc_js( __( 'Select Partner', 'direktt-cross-sell' ) ); ?></option>';
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
					<p class="description"><?php echo esc_html__( 'Select Partners for Which This Partner Can Issue Coupons.', 'direktt-cross-sell' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="direktt_cross_sell_partner_categories"><?php echo esc_html__( 'Partner Categories', 'direktt-cross-sell' ); ?></label></th>
				<td>
					<select name="direktt_cross_sell_partner_categories" id="direktt_cross_sell_partner_categories">
						<option value="0"><?php echo esc_html__( 'Select Category', 'direktt-cross-sell' ); ?></option>
						<?php foreach ( $all_categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category['value'] ); ?>" <?php selected( $partner_categories, $category['value'] ); ?>>
								<?php echo esc_html( $category['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php echo esc_html__( 'Partner users belonging to this category will be able to validate coupons.', 'direktt-cross-sell' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="direktt_cross_sell_issue_tags"><?php echo esc_html__( 'Partner Tags', 'direktt-cross-sell' ); ?></label></th>
				<td>
					<select name="direktt_cross_sell_issue_tags" id="direktt_cross_sell_issue_tags">
						<option value="0"><?php echo esc_html__( 'Select Tag', 'direktt-cross-sell' ); ?></option>
						<?php foreach ( $all_tags as $tag ) : ?>
							<option value="<?php echo esc_attr( $tag['value'] ); ?>" <?php selected( $partner_tags, $tag['value'] ); ?>>
								<?php echo esc_html( $tag['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php echo esc_html__( 'Partner users with this tag will be able to validate coupons.', 'direktt-cross-sell' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
}

function direktt_cross_sell_partners_render_reports_meta_box( $post ) {
	// Security nonce
	wp_nonce_field( 'direktt_reports_meta_box', 'direktt_reports_meta_box_nonce' );

	// Use esc to be safe
	$post_id = intval( $post->ID );
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="direktt-report-range"><?php echo esc_html__( 'Range', 'direktt-cross-sell' ); ?></label></th>
			<td>
				<select id="direktt-report-range" name="direktt_report_range">
					<option value="7"><?php echo esc_html__( 'Last 7 days', 'direktt-cross-sell' ); ?></option>
					<option value="30"><?php echo esc_html__( 'Last 30 days', 'direktt-cross-sell' ); ?></option>
					<option value="90"><?php echo esc_html__( 'Last 90 days', 'direktt-cross-sell' ); ?></option>
					<option value="custom"><?php echo esc_html__( 'Custom date range', 'direktt-cross-sell' ); ?></option>
				</select>
			</td>
		</tr>
		<tr style="display: none;" id="direktt-custom-dates">
			<th scope="row"><label for="direktt-date-from"><?php echo esc_html__( 'From - To', 'direktt-cross-sell' ); ?></label></th>
			<td>
				<input type="date" id="direktt-date-from" name="direktt_date_from" />
				<?php echo esc_html__( '-', 'direktt-cross-sell' ); ?>
				<input type="date" id="direktt-date-to" name="direktt_date_to" />
			</td>
		</tr>
	</table>

	<p>
		<button type="button" class="button" id="direktt-generate-issued"><?php echo esc_html__( 'Generate Issued Report', 'direktt-cross-sell' ); ?></button>
		<button type="button" class="button" id="direktt-generate-used"><?php echo esc_html__( 'Generate Used Report', 'direktt-cross-sell' ); ?></button>
	</p>

	<input type="hidden" id="direktt-post-id" value="<?php echo esc_attr( $post_id ); ?>" />
	<script>
		jQuery(document).ready(function($) {
			// toggle custom date inputs
			$( '#direktt-report-range' ).on('change', function() {
				if ( $( this ).val() === 'custom' ) {
					$( '#direktt-custom-dates' ).show();
				} else {
					$( '#direktt-custom-dates' ).hide();
				}
			});

			// helper to collect data
			function collectReportData(type) {
				var post_id = $( '#direktt-post-id' ).val();
				var nonce = $( 'input[name="direktt_reports_meta_box_nonce"]' ).val();
				var range = $( '#direktt-report-range' ).val();
				var from = $( '#direktt-date-from' ).val();
				var to = $( '#direktt-date-to' ).val();

				var ajaxData = {
					action: type === 'issued' ? 'direktt_cross_sell_get_issued_report' : 'direktt_cross_sell_get_used_report',
					post_id: post_id,
					range: range,
					nonce: nonce
				};

				if ( range === 'custom' ) {
					ajaxData.from = from;
					ajaxData.to = to;
				}

				return ajaxData;
			}

			// Bind buttons
			$( '#direktt-generate-issued' ).off( 'click' ).on( 'click', function( event ) {
				event.preventDefault();
				var data = collectReportData( 'issued' );
				// Basic client-side validation for custom range
				if ( data.range === 'custom' ) {
					if ( ! data.from || ! data.to ) {
						alert("<?php echo esc_js( __( 'Please select both From and To dates for a custom range.', 'direktt-cross-sell' ) ); ?>");
						return;
					}
					if ( data.from > data.to ) {
						alert("<?php echo esc_js( __( 'The From date cannot be later than the To date.', 'direktt-cross-sell' ) ); ?>");
						return;
					}
				}

				$( this ).prop( 'disabled', true );
				$( this ).text( "<?php echo esc_js( __( 'Generating report...', 'direktt-cross-sell' ) ); ?>" );
				$.ajax({
					url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
					method: 'POST',
					data: data,
					success: function( response ) {
						if ( response.success ) {
							window.location.href = response.data.url;
						} else {
							alert( response.data );
						}
					},
					error: function() {
						alert("<?php echo esc_js( __( 'There was an error.', 'direktt-cross-sell' ) ); ?>");
					}
				}).always(function() {
					$( '#direktt-generate-issued' ).prop( 'disabled', false );
					$( '#direktt-generate-issued' ).text( "<?php echo esc_js( __( 'Generate Issued Report', 'direktt-cross-sell' ) ); ?>" );
				});
			});

			$( '#direktt-generate-used' ).off( 'click' ).on( 'click', function( event ) {
				event.preventDefault();
				var data = collectReportData( 'used' );
				if ( data.range === 'custom' ) {
					if ( ! data.from || ! data.to ) {
						alert( "<?php echo esc_js( __( 'Please select both From and To dates for a custom range.', 'direktt-cross-sell' ) ); ?>" );
						return;
					}
					if ( data.from > data.to ) {
						alert( "<?php echo esc_js( __( 'The From date cannot be later than the To date.', 'direktt-cross-sell' ) ); ?>" );
						return;
					}
				}

				$( this ).prop( 'disabled', true );
				$( this ).text( "<?php echo esc_js( __( 'Generating report...', 'direktt-cross-sell' ) ); ?>" );
				$.ajax({
					url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
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
						alert("<?php echo esc_js( __( 'There was an error.', 'direktt-cross-sell' ) ); ?>");
					}
				}).always(function() {
					$( '#direktt-generate-used' ).prop( 'disabled', false );
					$( '#direktt-generate-used' ).text( "<?php echo esc_js( __( 'Generate Used Report', 'direktt-cross-sell' ) ); ?>" );
				});
			});
		});
	</script>
	<?php
}

function handle_direktt_cross_sell_get_issued_report() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'direktt_reports_meta_box' ) ) {
		wp_send_json_error( esc_html__( 'Invalid nonce.', 'direktt-cross-sell' ) );
		wp_die();
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'direktt-cross-sell' ) );
		wp_die();
	}

	if ( ! isset( $_POST['post_id'], $_POST['range'] ) ) {
		wp_send_json_error( esc_html__( 'Data error.', 'direktt-cross-sell' ) );
		wp_die();
	}

	global $wpdb;

	$post_id      = intval( $_POST['post_id'] ); // used as partner_id
	$range        = sanitize_text_field( wp_unslash( $_POST['range'] ) );
	$issued_table = $wpdb->prefix . 'direktt_cross_sell_issued';

	// Build WHERE
	$where = $wpdb->prepare( 'partner_id = %d', $post_id );

	if ( in_array( $range, array( '7', '30', '90' ), true ) ) {
		$days   = intval( $range );
		$where .= $wpdb->prepare( ' AND coupon_time >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );
	} elseif ( $range === 'custom' ) {
		if ( ! isset( $_POST['from'], $_POST['to'] ) ) {
			wp_send_json_error( esc_html__( 'Data error.', 'direktt-cross-sell' ) );
			wp_die();
		}
		$from   = sanitize_text_field( wp_unslash( $_POST['from'] ) ); // format: Y-m-d or Y-m-d H:i:s
		$to     = sanitize_text_field( wp_unslash( $_POST['to'] ) );
		$where .= $wpdb->prepare( ' AND coupon_time BETWEEN %s AND %s', $from, $to );
	}

	// Get issued coupons
	$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $issued_table WHERE $where" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string, $where is built from literal string + sanitized inputs.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.

	if ( empty( $results ) ) {
		wp_send_json_error( esc_html__( 'No data found.', 'direktt-cross-sell' ) );
		wp_die();
	}

	if ( ! function_exists( 'get_filesystem_method' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
    }

    global $wp_filesystem;
    $method = get_filesystem_method();
    if ( 'direct' === $method ) {
        WP_Filesystem();
    }

	// Prepare CSV content in an array format (no need for fopen())
    $csv_content = '';

	// Headers
	$headers = array(
		'ID',
		'Partner Name',
		'Voucher Group Name',
		'Voucher Group ID',
		'Time of Issue',
		'Time of Expiring',
		'Coupon Valid',
	);

	// Add headers to the CSV content
    $csv_content .= implode( ',', $headers ) . "\n";

	foreach ( $results as $row ) {
		$partner_name       = get_the_title( $row->partner_id );
		$voucher_group_name = get_the_title( $row->coupon_group_id );

		$line = array(
			$row->ID,
			$partner_name,
			$voucher_group_name,
			$row->coupon_group_id,
			$row->coupon_time,
			$row->coupon_expires,
			$row->coupon_valid == 1 ? 'true' : 'false',
		);

		// Add each row to the CSV content
        $csv_content .= implode( ',', $line ) . "\n";
	}

	// Save to uploads directory using WP_Filesystem
    $upload_dir = wp_upload_dir();
    $filename   = 'issued_report_' . time() . '.csv';
    $filepath   = $upload_dir['path'] . '/' . $filename;
    $fileurl    = $upload_dir['url'] . '/' . $filename;

	// Write CSV content to the file using WP_Filesystem
    if ( ! $wp_filesystem->put_contents( $filepath, $csv_content, FS_CHMOD_FILE ) ) {
        wp_send_json_error( esc_html__( 'Error saving the file.', 'direktt-cross-sell' ) );
        wp_die();
    }

	wp_send_json_success( array( 'url' => $fileurl ) );
	wp_die();
}

function handle_direktt_cross_sell_get_used_report() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'direktt_reports_meta_box' ) ) {
		wp_send_json_error( esc_html__( 'Invalid nonce.', 'direktt-cross-sell' ) );
		wp_die();
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'direktt-cross-sell' ) );
		wp_die();
	}

	if ( ! isset( $_POST['post_id'], $_POST['range'] ) ) {
		wp_send_json_error( esc_html__( 'Data error.', 'direktt-cross-sell' ) );
		wp_die();
	}

	global $wpdb;

	$post_id = intval( $_POST['post_id'] );
	$range   = sanitize_text_field( wp_unslash( $_POST['range'] ) );

	$issued_table   = $wpdb->prefix . 'direktt_cross_sell_issued';
	$used_ind_table = $wpdb->prefix . 'direktt_cross_sell_used';

	// --- Range filter (applies to coupon_used_time) ---
	$date_condition = '';
	if ( in_array( $range, array( '7', '30', '90' ), true ) ) {
		$days           = intval( $range );
		$date_condition = $wpdb->prepare( 'AND u.coupon_used_time >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );
	} elseif ( $range === 'custom' ) {
		if ( ! isset( $_POST['from'], $_POST['to'] ) ) {
			wp_send_json_error( esc_html__( 'Data error.', 'direktt-cross-sell' ) );
			wp_die();
		}
		$from           = sanitize_text_field( wp_unslash( $_POST['from'] ) );
		$to             = sanitize_text_field( wp_unslash( $_POST['to'] ) );
		$date_condition = $wpdb->prepare( 'AND u.coupon_used_time BETWEEN %s AND %s', $from, $to );
	}

	// --- Used ---
	$results = $wpdb->get_results( $wpdb->prepare( "SELECT u.ID, u.issued_id, u.direktt_validator_user_id, u.coupon_used_time, i.partner_id, i.coupon_group_id FROM $used_ind_table u INNER JOIN $issued_table i ON u.issued_id = i.ID WHERE i.partner_id = $post_id $date_condition" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table and $used_table are built from $wpdb->prefix + literal string, $post_id is sanitized input, $date_condition is built from literal string + sanitized inputs.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.

	// --- Prepare CSV Content (in memory) ---
	$csv_content = '';

	$headers = array(
		'Partner Name',
		'Issue ID',
		'Voucher Group Name',
		'Validator Display Name',
		'Validation Time',
	);

	// Add headers
	$csv_content .= implode( ',', $headers ) . "\n";

	// Add results
	foreach ( $results as $row ) {
		$partner_name       = get_the_title( $row->partner_id );
		$voucher_group_name = get_the_title( $row->coupon_group_id );
		$profile_user       = Direktt_User::get_user_by_subscription_id( $row->direktt_validator_user_id );
		$validator_name     = $profile_user['direktt_display_name'];

		$line = array(
			$partner_name,
			$row->issued_id,
			$voucher_group_name,
			$validator_name,
			$row->coupon_used_time,
		);

		$csv_content .= implode( ',', $line ) . "\n";
	}

	// --- Prepare to save with WP_Filesystem ---
	if ( ! function_exists( 'get_filesystem_method' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	global $wp_filesystem;
	if ( ! is_object( $wp_filesystem ) ) {
		WP_Filesystem();
	}

	$upload_dir = wp_upload_dir();
	$filename   = 'used_report_' . time() . '.csv';
	$filepath   = $upload_dir['path'] . '/' . $filename;
	$fileurl    = $upload_dir['url'] . '/' . $filename;

	// Save file using WP_Filesystem
	$write_success = $wp_filesystem->put_contents( $filepath, $csv_content, FS_CHMOD_FILE );

	if ( ! $write_success ) {
		wp_send_json_error( esc_html__( 'Failed to save report file.', 'direktt-cross-sell' ) );
		wp_die();
	}

	wp_send_json_success( array( 'url' => $fileurl ) );
	wp_die();
}

function save_direktt_cross_sell_partner_meta( $post_id ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['post_type'] ) || $_POST['post_type'] !== 'direkttcspartners' ) {
		return;
	}

	if ( ! isset( $_POST['direktt_cross_sell_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_nonce'] ) ), 'direktt_cross_sell_save' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['direktt_cross_sell_partners_for_who_can_edit'] ) && is_array( $_POST['direktt_cross_sell_partners_for_who_can_edit'] ) ) {

		$groups = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['direktt_cross_sell_partners_for_who_can_edit'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Justification: both sanitized and unslashed by array_map functions.
		$groups = array_filter(
			$groups,
			function ( $group ) {
				return ! empty( $group ) && $group !== '0';
			}
		);

		$groups = array_unique( $groups );

		delete_post_meta( $post_id, 'direktt_cross_sell_partners_for_who_can_edit' );
		foreach ( $groups as $group ) {
			add_post_meta( $post_id, 'direktt_cross_sell_partners_for_who_can_edit', $group );
		}
	} else {
		delete_post_meta( $post_id, 'direktt_cross_sell_partners_for_who_can_edit' );
	}

	if ( isset( $_POST['direktt_cross_sell_partner_categories'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_partner_categories',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_partner_categories'] ) )
		);
	}
	if ( isset( $_POST['direktt_cross_sell_issue_tags'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_partner_tags',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_issue_tags'] ) )
		);
	}
	if ( isset( $_POST['direktt_cross_sell_qr_code_image'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_qr_code_image',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_qr_code_image'] ) )
		);
	}
	if ( isset( $_POST['direktt_cross_sell_qr_code_color'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_qr_code_color',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_qr_code_color'] ) )
		);
	}
	if ( isset( $_POST['direktt_cross_sell_qr_code_bg_color'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_qr_code_bg_color',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_qr_code_bg_color'] ) )
		);
	}
}

function direktt_cross_sell_enqueue_fe_scripts( $hook ) {
	global $enqueue_direktt_cross_sell_scripts;
	if ( $enqueue_direktt_cross_sell_scripts ) {
		wp_enqueue_script(
			'qr-code-styling', // Handle
			plugin_dir_url( __DIR__ ) . 'direktt/public/js/qr-code-styling.js', // Source
			array(), // Dependencies (none in this case)
			filemtime( plugin_dir_path( __DIR__ ) . 'direktt/public/js/qr-code-styling.js' ),
			false
		);
	}
}

function direktt_cross_sell_coupon_groups_add_custom_box() {
	add_meta_box(
		'direktt_cs_partners_mb',           // ID
		esc_html__( 'Coupon Properties', 'direktt-cross-sell' ),                       // Title
		'direktt_cross_sell_coupon_groups_render_custom_box',    // Callback function
		'direkttcscoupon',                    // CPT slug
		'normal',                        // Context
		'high'                           // Priority
	);
}

function direktt_cross_sell_coupon_groups_render_custom_box( $post ) {
	$templates = Direktt_Message_Template::get_templates( array( 'all', 'none' ) );

	$group_validity = get_post_meta( $post->ID, 'direktt_cross_sell_group_validity', true );
	$max_usage      = get_post_meta( $post->ID, 'direktt_cross_sell_max_usage', true );
	if ( $max_usage === false ) {
		$max_usage = '1';
	}
	$max_issuance    = get_post_meta( $post->ID, 'direktt_cross_sell_max_issuance', true );
	$group_template  = get_post_meta( $post->ID, 'direktt_cross_sell_group_template', true );
	$qr_code_message = get_post_meta( $post->ID, 'direktt_cross_sell_qr_code_message', true );

	$partners         = direktt_cross_sell_get_all_partners();
	$selected_partner = get_post_meta( $post->ID, 'direktt_cross_sell_partner_for_coupon_group', true );

	wp_nonce_field( 'direktt_cross_sell_save', 'direktt_cross_sell_nonce' );
	?>

	<table class="form-table">
		<tr>
			<th scope="row"><label for="direktt_cross_sell_partner_for_coupon_group"><?php echo esc_html__( 'Partner', 'direktt-cross-sell' ); ?></label></th>
			<td>
				<select name="direktt_cross_sell_partner_for_coupon_group" id="direktt_cross_sell_partner_for_coupon_group">
					<option value="0"><?php echo esc_html__( 'Select Partner', 'direktt-cross-sell' ); ?></option>
					<?php foreach ( $partners as $partner ) : ?>
						<option value="<?php echo esc_attr( $partner['ID'] ); ?>" <?php selected( $selected_partner, $partner['ID'] ); ?>>
							<?php echo esc_html( $partner['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php echo esc_html__( 'Partner to whom this Coupon Group belongs. Only this partner will be able to issue coupons from this group.', 'direktt-cross-sell' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="direktt_cross_sell_group_validity"><?php echo esc_html__( 'Coupon Validity', 'direktt-cross-sell' ); ?></label></th>
			<td>
				<input type="text" name="direktt_cross_sell_group_validity" id="direktt_cross_sell_group_validity" value="<?php echo $group_validity ? intval( $group_validity ) : 0; ?>" /> <?php echo esc_html__( '0 - does not expire', 'direktt-cross-sell' ); ?>
				<p class="description"><?php echo esc_html__( 'Coupon validity in days. If equals to 0, the coupon does not expire', 'direktt-cross-sell' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="direktt_cross_sell_max_usage"><?php echo esc_html__( 'How many times can a single coupon be used?', 'direktt-cross-sell' ); ?></label></th>
			<td>
				<input type="text" name="direktt_cross_sell_max_usage" id="direktt_cross_sell_max_usage" value="<?php echo $max_usage !== false && $max_usage !== '' ? intval( $max_usage ) : 1; ?>" /> <?php echo esc_html__( '0 - unlimited', 'direktt-cross-sell' ); ?>
				<p class="description"><?php echo esc_html__( 'Maximum number of usages per coupon.', 'direktt-cross-sell' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="direktt_cross_sell_max_issuance"><?php echo esc_html__( 'How many coupons can be issued in total?', 'direktt-cross-sell' ); ?></label></th>
			<td>
				<input type="text" name="direktt_cross_sell_max_issuance" id="direktt_cross_sell_max_issuance" value="<?php echo $max_issuance ? intval( $max_issuance ) : 0; ?>" /> <?php echo esc_html__( '0 - unlimited', 'direktt-cross-sell' ); ?>
				<p class="description"><?php echo esc_html__( 'Maximum total number of issuances per coupon. If this number is exceeded, the coupon will not be available for issuance', 'direktt-cross-sell' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="direktt_cross_sell_group_template"><?php echo esc_html__( 'Message Template', 'direktt-cross-sell' ); ?></label></th>
			<td>
				<select name="direktt_cross_sell_group_template" id="direktt_cross_sell_group_template">
					<option value="0"><?php echo esc_html__( 'Select Message Template', 'direktt-cross-sell' ); ?></option>
					<?php foreach ( $templates as $template ) : ?>
						<option value="<?php echo esc_attr( $template['value'] ); ?>" <?php selected( $group_template, $template['value'] ); ?>>
							<?php echo esc_html( $template['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php echo esc_html__( 'Message Template used when Coupon is issued. If none set, default will be sent', 'direktt-cross-sell' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="direktt_cross_sell_qr_code_message"><?php echo esc_html__( 'Text for QR Code Sharing', 'direktt-cross-sell' ); ?></label></th>
			<td>
				<input type="text" name="direktt_cross_sell_qr_code_message" id="direktt_cross_sell_qr_code_message" value="<?php echo esc_attr( $qr_code_message ); ?>" />
				<p class="description"><?php echo esc_html__( 'Optional Text to Share With Coupon QR Code', 'direktt-cross-sell' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

function save_direktt_cross_sell_coupon_groups_meta( $post_id ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['post_type'] ) || $_POST['post_type'] !== 'direkttcscoupon' ) {
		return;
	}

	if ( ! isset( $_POST['direktt_cross_sell_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_nonce'] ) ), 'direktt_cross_sell_save' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['direktt_cross_sell_partner_for_coupon_group'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_partner_for_coupon_group',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_partner_for_coupon_group'] ) )
		);
	}

	if ( isset( $_POST['direktt_cross_sell_group_validity'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_group_validity',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_group_validity'] ) )
		);
	}
	if ( isset( $_POST['direktt_cross_sell_max_usage'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_max_usage',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_max_usage'] ) )
		);
	}
	if ( isset( $_POST['direktt_cross_sell_max_issuance'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_max_issuance',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_max_issuance'] ) )
		);
	}
	if ( isset( $_POST['direktt_cross_sell_group_template'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_group_template',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_group_template'] ) )
		);
	}
	if ( isset( $_POST['direktt_cross_sell_qr_code_message'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_cross_sell_qr_code_message',
			sanitize_text_field( wp_unslash( $_POST['direktt_cross_sell_qr_code_message'] ) )
		);
	}
}

function direktt_cross_sell_create_issued_database_table() {
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

	dbDelta( $sql );

	$wpdb->query( $wpdb->prepare( "ALTER TABLE $table_name MODIFY COLUMN coupon_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $table_name is built from $wpdb->prefix + literal string.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct database query is acceptable here since schema changes cannot be performed via WordPress APIs or $wpdb helper functions. This runs only on plugin activation.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is irrelevant for schema modifications. The query changes table structure, not data, so wp_cache_* functions are not applicable.
	// WordPress.DB.DirectDatabaseQuery.SchemaChange: Schema changes are discouraged in normal runtime, but this code executes only once during plugin activation to ensure correct table structure.
}

function direktt_cross_sell_create_used_database_table() {
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
	dbDelta( $sql );

	$wpdb->query( $wpdb->prepare( "ALTER TABLE $table_name MODIFY COLUMN coupon_used_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $table_name is built from $wpdb->prefix + literal string.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct database query is acceptable here since schema changes cannot be performed via WordPress APIs or $wpdb helper functions. This runs only on plugin activation.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is irrelevant for schema modifications. The query changes table structure, not data, so wp_cache_* functions are not applicable.
	// WordPress.DB.DirectDatabaseQuery.SchemaChange: Schema changes are discouraged in normal runtime, but this code executes only once during plugin activation to ensure correct table structure.
}

function direktt_cross_sell_get_all_partners() {
	$args = array(
		'post_type'      => 'direkttcspartners',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	$posts    = get_posts( $args );
	$partners = array();
	foreach ( $posts as $post ) {
		$partners[] = array(
			'ID'    => $post->ID,
			'title' => $post->post_title,
		);
	}
	return $partners;
}

function direktt_cross_sell_get_partners() {
	global $wpdb;

	$args = array(
		'post_type'      => 'direkttcspartners',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	$posts    = get_posts( $args );
	$partners = array();
	foreach ( $posts as $post ) {
		// Get associated coupon group IDs
		$coupon_group_ids = direktt_cross_sell_get_partners_coupon_groups( $post->ID );
		if ( empty( $coupon_group_ids ) ) {
			continue;
		}

		// Query attached coupon groups, filter to published, not fully issued
		$eligible_groups = array();
		foreach ( $coupon_group_ids as $index => $group ) {
			// Get max issuance
			$max_issuance = get_post_meta( $group['value'], 'direktt_cross_sell_max_issuance', true );
			$max_issuance = (string) $max_issuance === '' ? 0 : intval( $max_issuance ); // default to 0 (unlimited)

			// If limit, get count of valid issued for this group
			if ( $max_issuance > 0 ) {
				$issued_count = direktt_cross_sell_get_issue_count( $group['value'], $post->ID );
				// Omit group if fully issued
				if ( $issued_count >= $max_issuance ) {
					continue;
				}
			}
			// At this point: it's eligible
			$eligible_groups[] = $group['value'];
		}

		// Only keep partners with at least one issuable group
		if ( ! empty( $eligible_groups ) ) {
			$partners[] = array(
				'ID'    => $post->ID,
				'title' => $post->post_title,
			);
		}
	}
	return $partners;
}

function direktt_cross_sell_get_partner_coupon_groups( $partner_id ) {
	global $wpdb;

	$coupon_group_ids = direktt_cross_sell_get_partners_coupon_groups( $partner_id );
	if ( empty( $coupon_group_ids ) ) {
		return array();
	}
	$eligible_group_ids = array();
	foreach ( $coupon_group_ids as $index => $group ) {
		// Max issuance filter
		$max_issuance = get_post_meta( $group['value'], 'direktt_cross_sell_max_issuance', true );
		$max_issuance = (string) $max_issuance === '' ? 0 : intval( $max_issuance );

		$issued_count = direktt_cross_sell_get_issue_count( $group['value'], $partner_id );

		if ( $max_issuance > 0 && $issued_count >= $max_issuance ) {
			continue;
		} else {
			$eligible_group_ids[] = $group['value'];
		}
	}
	if ( empty( $eligible_group_ids ) ) {
		return array();
	}
	$args = array(
		'post_type'      => 'direkttcscoupon',
		'post__in'       => $eligible_group_ids,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	return get_posts( $args );
}

function direktt_cross_sell_user_can_review() {
	global $direktt_user;

	// Always allow admin
	if ( class_exists( 'Direktt_User' ) && Direktt_User::is_direktt_admin() ) {
		return true;
	}

	// Check category
	$review_categories = intval( get_option( 'direktt_cross_sell_review_categories', 0 ) );
	$review_tags       = intval( get_option( 'direktt_cross_sell_review_tags', 0 ) );

	$category_slug = '';
	$tag_slug      = '';

	if ( $review_categories !== 0 ) {
		$category      = get_term( $review_categories, 'direkttusercategories' );
		$category_slug = $category ? $category->slug : '';
	}

	if ( $review_tags !== 0 ) {
		$tag      = get_term( $review_tags, 'direkttusertags' );
		$tag_slug = $tag ? $tag->slug : '';
	}

	// Check via provided function
	if ( class_exists( 'Direktt_User' ) && Direktt_User::has_direktt_taxonomies( $direktt_user, $category_slug ? array( $category_slug ) : array(), $tag_slug ? array( $tag_slug ) : array() ) ) {
		return true;
	}
	return false;
}

function direktt_cross_sell_setup_profile_tool() {
	$issue_categories = intval( get_option( 'direktt_cross_sell_issue_categories', 0 ) );
	$issue_tags       = intval( get_option( 'direktt_cross_sell_issue_tags', 0 ) );

	if ( $issue_categories !== 0 ) {
		$category      = get_term( $issue_categories, 'direkttusercategories' );
		$category_slug = $category ? $category->slug : '';
	} else {
		$category_slug = '';
	}

	if ( $issue_tags !== 0 ) {
		$tag      = get_term( $issue_tags, 'direkttusertags' );
		$tag_slug = $tag ? $tag->slug : '';
	} else {
		$tag_slug = '';
	}

	Direktt_Profile::add_profile_tool(
		array(
			'id'         => 'cross-sell-tool',
			'label'      => esc_html__( 'Cross Sell', 'direktt-cross-sell' ),
			'callback'   => 'direktt_cross_sell_render_profile_tool',
			'categories' => $category_slug ? array( $category_slug ) : array(),
			'tags'       => $tag_slug ? array( $tag_slug ) : array(),
			'priority'   => 2,
		)
	);
}

function direktt_cross_sell_process_use_coupon( $coupon ) {
	// TODO testirati ovo
	global $direktt_user;
	if ( ! $direktt_user ) {
		return;
	}
	$partners          = direktt_cross_sell_get_partners();
	$eligible_partners = array();
	$category_ids      = Direktt_User::get_user_categories( $direktt_user['ID'] );
	$tag_ids           = Direktt_User::get_user_tags( $direktt_user['ID'] );
	if ( class_exists( 'Direktt_User' ) && Direktt_User::is_direktt_admin() ) {
		$eligible_partners = $partners;
	} else {
		if ( empty( $category_ids ) && empty( $tag_ids ) ) {
			$partners_with_cat_tag = array();
		} else {
			$meta_query = array( 'relation' => 'OR' );

			if ( ! empty( $category_ids ) ) {
				$meta_query[] = array(
					'key'     => 'direktt_cross_sell_partner_categories',
					'value'   => $category_ids,
					'compare' => 'IN',
				);
			}

			if ( ! empty( $tag_ids ) ) {
				$meta_query[] = array(
					'key'     => 'direktt_cross_sell_partner_tags',
					'value'   => $tag_ids,
					'compare' => 'IN',
				);
			}

			$args = array(
				'post_type'      => 'direkttcspartners',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- - Justification: bounded, cached, selective query on small dataset
			);

			$partners_with_cat_tag = get_posts( $args );
		}

		if ( ! empty( $partners_with_cat_tag ) ) {
			foreach ( $partners_with_cat_tag as $partner ) {
				$eligible_partners[] = array(
					'ID'    => $partner->ID,
					'title' => $partner->post_title,
				);

				$partners_for_edit = get_post_meta( $partner->ID, 'direktt_cross_sell_partners_for_who_can_edit', false );
				if ( ! empty( $partners_for_edit ) ) {
					foreach ( $partners_for_edit as $edit_partner_id ) {
						$edit_partner = get_post( $edit_partner_id );
						if ( $edit_partner ) {
							$eligible_partners[] = array(
								'ID'    => $edit_partner->ID,
								'title' => $edit_partner->post_title,
							);
						}
					}
				}
			}
		}
	}

	$use_coupon_id = intval( $coupon->ID );

	$wpdb         = $GLOBALS['wpdb'];
	$used_table   = $wpdb->prefix . 'direktt_cross_sell_used';
	$issued_table = $wpdb->prefix . 'direktt_cross_sell_issued';

	$partner_post_id = $coupon->partner_id;

	$can_use = false;

	if ( ! empty( $eligible_partners ) ) {
		if ( ! is_array( $eligible_partners ) ) {
			$eligible_partners = array( $eligible_partners );
		} else {
			$eligible_partners = array_unique( $eligible_partners, SORT_REGULAR );
		}

		foreach ( $eligible_partners as $eligible_partner ) {
			if ( intval( $eligible_partner['ID'] ) === intval( $partner_post_id ) ) {
				$can_use = true;
				break;
			}
		}
	} else {
		return;
	}

	if ( ! $can_use ) {
		return;
	}

	// RACE CONDITION SAFE usage check!
	$latest_used_count = direktt_cross_sell_get_used_count( intval( $coupon->ID ) );
	$latest_max_usage  = intval( get_post_meta( $coupon->coupon_group_id, 'direktt_cross_sell_max_usage', true ) );

	if ( $latest_max_usage > 0 && $latest_used_count >= $latest_max_usage ) {
		// Usage limit reached - set error flag
		$redirect_url = add_query_arg(
			array(
				'direktt_action'      => 'use_coupon',
				'coupon_id'           => intval( $use_coupon_id ),
				'cross_sell_use_flag' => 2, // error: limit exceeded
			),
			remove_query_arg( 'cross_sell_use_flag' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	} else {
		$now_time      = current_time( 'mysql' );
		$coupon_issued = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $issued_table WHERE ID = %d", $use_coupon_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Justifications for phpcs ignores:
		// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
		// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
		// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.

		if ( ! empty( $coupon_issued->coupon_expires ) && $coupon_issued->coupon_expires != '0000-00-00 00:00:00' && $coupon_issued->coupon_expires < $now_time ) {
			// Redirect with error flag or message for expired coupon
			$redirect_url = add_query_arg(
				array(
					'direktt_action'      => 'use_coupon',
					'coupon_id'           => intval( $use_coupon_id ),
					'cross_sell_use_flag' => 4, // error: expired
				),
				remove_query_arg( 'cross_sell_use_flag' )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}
		// Do insert
		$inserted     = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database insert is required here to add a record to a custom plugin table; $wpdb->insert() is the official safe WordPress method using prepared statements and proper data escaping.
			$used_table,
			array(
				'issued_id'                 => intval( $coupon->ID ),
				'direktt_validator_user_id' => isset( $GLOBALS['direktt_user']['direktt_user_id'] ) ? $GLOBALS['direktt_user']['direktt_user_id'] : '',
			),
			array(
				'%d',
				'%s',
			)
		);
        // TODO if inserted success send usage message
		$redirect_url = add_query_arg(
			array(
				'direktt_action'      => 'use_coupon',
				'coupon_id'           => intval( $use_coupon_id ),
				'cross_sell_use_flag' => $inserted ? 1 : 3, // 1=success, 3=insert failed
			),
			remove_query_arg( 'cross_sell_use_flag' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}
}

function direktt_cross_sell_render_use_coupon( $use_coupon_id ) {
	$wpdb         = $GLOBALS['wpdb'];
	$issued_table = $wpdb->prefix . 'direktt_cross_sell_issued';

	$coupon = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $issued_table WHERE ID = %d", $use_coupon_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.

	if ( ! $coupon ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found.', 'direktt-cross-sell' ) . '</p></div>';
	} else {

		$partner_post      = get_post( $coupon->partner_id );
		$coupon_group_post = get_post( $coupon->coupon_group_id );

        if ( ! $partner_post || ! $coupon_group_post ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found.', 'direktt-cross-sell' ) . '</p></div>';
            return;
        } else {
            if ( $partner_post->post_status !== 'publish' || $coupon_group_post->post_status !== 'publish' ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found.', 'direktt-cross-sell' ) . '</p></div>';
                return;
            }
        }

		$partner_name = $partner_post ? $partner_post->post_title : esc_html__( 'Unknown', 'direktt-cross-sell' );
		$group_title  = $coupon_group_post ? $coupon_group_post->post_title : esc_html__( 'Unknown', 'direktt-cross-sell' );
		$group_descr  = $coupon_group_post ? $coupon_group_post->post_content : '';

		// Expiry/issuance formatting
		$issued_date = mysql2date( 'Y-m-d H:i:s', $coupon->coupon_time );
		$expires     = ( empty( $coupon->coupon_expires ) || $coupon->coupon_expires == '0000-00-00 00:00:00' )
			? esc_html__( 'No expiry', 'direktt-cross-sell' )
			: mysql2date( 'Y-m-d H:i:s', $coupon->coupon_expires );

		// --- Usage counts ---

		$used_count = direktt_cross_sell_get_used_count( intval( $coupon->ID ) );
		$max_usage  = intval( get_post_meta( $coupon->coupon_group_id, 'direktt_cross_sell_max_usage', true ) );

		// --- Use Handler ---
		$status_message = '';
		// --- Use Handler ---
		if (
			isset( $_POST['direktt_cs_use_coupon'] ) &&
			isset( $_POST['direktt_cs_use_coupon_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['direktt_cs_use_coupon_nonce'] ) ), 'direktt_cs_use_coupon_action' )
		) {
			direktt_cross_sell_process_use_coupon( $coupon );
		}

		// Display appropriate cross_sell_use_flag notice
		if ( isset( $_GET['cross_sell_use_flag'] ) ) {
			$flag = intval( $_GET['cross_sell_use_flag'] );
			if ( $flag === 1 ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Coupon used successfully.', 'direktt-cross-sell' ) . '</p></div>';
			} elseif ( $flag === 2 ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Coupon usage limit has been reached; cannot use coupon.', 'direktt-cross-sell' ) . '</p></div>';
			} elseif ( $flag === 3 ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'There was an error recording coupon usage. Please try again.', 'direktt-cross-sell' ) . '</p></div>';
			} elseif ( $flag === 4 ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Coupon has expired; cannot use coupon.', 'direktt-cross-sell' ) . '</p></div>';
			}
		}

		// Coupon info table
		echo '<h2>' . esc_html__( 'Use Coupon', 'direktt-cross-sell' ) . '</h2>';
		echo '<table class="direktt-profile-data-cross-sell-tool-single-coupon-table">';
		echo '<tbody>';
		echo '<tr><th>' . esc_html__( 'Partner', 'direktt-cross-sell' ) . '</th><td><h3>' . esc_html( $partner_name ) . '</h3></td></tr>';
		echo '<tr><th>' . esc_html__( 'Coupon', 'direktt-cross-sell' ) . '</th><td>' . esc_html( $group_title ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Description', 'direktt-cross-sell' ) . '</th><td>' . nl2br( wp_kses_post( $group_descr ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Issued at', 'direktt-cross-sell' ) . '</th><td>' . esc_html( $issued_date ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Expires', 'direktt-cross-sell' ) . '</th><td>' . esc_html( $expires ) . '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Usages', 'direktt-cross-sell' ) . '</th><td>';
		printf(
			// translators: %1$s is the used count, %2$s is the max usage limit (or 'Unlimited')
			esc_html__( '%1$s / %2$s', 'direktt-cross-sell' ),
			esc_html( $used_count ),
			( $max_usage == 0 ? esc_html__( 'Unlimited', 'direktt-cross-sell' ) : esc_html( $max_usage ) )
		);
		echo '</td></tr>';
		echo '</tbody>';
		echo '</table>';

		// Only show "Use" button if not used up
		$disable_use = false;

		$now_time   = current_time( 'mysql' );
		$is_expired = ! empty( $coupon->coupon_expires )
			&& $coupon->coupon_expires != '0000-00-00 00:00:00'
			&& $coupon->coupon_expires < $now_time;

		if ( $max_usage > 0 && $used_count >= $max_usage ) {
			$disable_use = true;
		}
		if ( $is_expired ) {
			$disable_use = true;
		}

		if ( ! $disable_use && empty( $status_message ) ) {
			?>
			<form method="post" action="" class="direktt-profile-data-cross-sell-tool-single-coupon-form">
                <?php
				$allowed_html = wp_kses_allowed_html( 'post' );
                echo wp_kses( Direktt_Public::direktt_render_confirm_popup( 'direktt-cross-sell-confirm-use', __( 'Are you sure that you want to use this coupon for:', 'direktt-cross-sell' ) . ' ' . esc_html( $group_title ) . '?' ), $allowed_html );
                echo wp_kses( Direktt_Public::direktt_render_loader( __( 'Don\'t refresh the page', 'direktt-cross-sell' ) ), $allowed_html );
                ?>
				<input type="hidden" name="direktt_cs_use_coupon_nonce" value="<?php echo esc_attr( wp_create_nonce( 'direktt_cs_use_coupon_action' ) ); ?>">
				<input type="button" name="direktt_cs_use_coupon_btn" class="button button-primary button-large" value="<?php echo esc_attr__( 'Use Coupon', 'direktt-cross-sell' ); ?>">
			</form>
			<script>
                jQuery( document ).ready( function($) {
                    $( 'input[name="direktt_cs_use_coupon_btn"]' ).off( 'click' ).on( 'click', function(e) {
                        e.preventDefault();
                        $( '#direktt-cross-sell-confirm-use' ).fadeIn();
                    });

                    $( '#direktt-cross-sell-confirm-use .direktt-popup-no' ).off( 'click' ).on( 'click', function() {
                        event.preventDefault();
                        $( '#direktt-cross-sell-confirm-use' ).fadeOut();
                    });

                    $( '#direktt-cross-sell-confirm-use .direktt-popup-yes' ).off( 'click' ).on( 'click', function() {
                        event.preventDefault();
                        $( '#direktt-cross-sell-confirm-use' ).fadeOut();
                        $( '.direktt-loader-overlay' ).fadeIn();
                        $( 'form' ).append( '<input type="hidden" name="direktt_cs_use_coupon" value="1">' );
                        setTimeout(function() {
                            $( 'form' ).submit();
                        }, 500);
                    });
                });
			</script>
			<?php
		} elseif ( $is_expired ) {
			// Coupon expired message
			echo '<div class="notice notice-error"><p><em>' . esc_html__( 'This coupon has expired and cannot be used.', 'direktt-cross-sell' ) . '</em></p></div>';
		} elseif ( $disable_use ) {
			echo '<div class="notice notice-error"><p><em>' . esc_html__( 'This coupon has reached its usage limit.', 'direktt-cross-sell' ) . '</em></p></div>';
		}

		// Back
		$back_url = remove_query_arg( array( 'direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag' ) );
		echo '<a href="' . esc_url( $back_url ) . '" class="button button-invert direktt-cross-sell-back button-dark-gray">' . esc_html__( 'Back to Cross-Sell', 'direktt-cross-sell' ) . '</a>';
	}
}

function direktt_cross_sell_get_issue_count( $coupon_group_id, $partner_id ) {
	global $wpdb;

	$table        = $wpdb->prefix . 'direktt_cross_sell_issued';
	$issued_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE coupon_group_id = %d AND partner_id = %d", intval( $coupon_group_id ), intval( $partner_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $table is built from $wpdb->prefix + literal string.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_var() is the official WordPress method for this.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.

	return intval( $issued_count );
}

function direktt_cross_sell_get_used_count( $issue_id ) {
	global $wpdb;
	$used_table = $wpdb->prefix . 'direktt_cross_sell_used';
	$used_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $used_table WHERE issued_id = %s", $issue_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $used_table is built from $wpdb->prefix + literal string.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_var() is the official WordPress method for this.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
	return intval( $used_count );
}

function direktt_cross_sell_render_one_partner( $partner_id ) {

	if (
		isset( $_POST['direktt_cs_issue_coupon'] ) &&
		isset( $_POST['direktt_coupon_group_id'] ) &&
		isset( $_POST['direktt_cs_issue_coupon_nonce'] ) &&
		wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['direktt_cs_issue_coupon_nonce'] ) ), 'direktt_cs_issue_coupon_action' )
	) {
		direktt_cross_sell_process_coupon_issue( $partner_id, intval( $_POST['direktt_coupon_group_id'] ) );
	}

	$status_flag    = isset( $_GET['cross_sell_status_flag'] ) ? intval( $_GET['cross_sell_status_flag'] ) : 0;
	$status_message = '';
	if ( $status_flag === 1 ) {
		$status_message = esc_html__( 'Coupon issued successfully.', 'direktt-cross-sell' );
	} elseif ( $status_flag === 2 ) {
		$status_message = esc_html__( 'There was an error while issuing the coupon.', 'direktt-cross-sell' );
	} elseif ( $status_flag === 3 ) {
		$status_message = esc_html__( 'The issue count has reached its max number. The coupon can not be issued', 'direktt-cross-sell' );
	}

	if ( $status_message ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $status_message ) . '</p></div>';
	}

	$partner = get_post( $partner_id );
	if ( ! $partner || $partner->post_type !== 'direkttcspartners' || $partner->post_status !== 'publish' ) {
		echo '<p class="notice notice-error">' . esc_html__( 'Invalid partner selected.', 'direktt-cross-sell' ) . '</p>';
		$back_url = remove_query_arg( array( 'direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag' ) );
		echo '<a href="' . esc_url( $back_url ) . '" class="button button-invert direktt-cross-sell-back button-dark-gray">' . esc_html__( 'Back to Cross-Sell', 'direktt-cross-sell' ) . '</a>';
		return;
	}

	echo '<h2>' . esc_html( $partner->post_title ) . '</h2>';

	$groups = direktt_cross_sell_get_partner_coupon_groups( $partner_id );

	if ( empty( $groups ) ) {
		echo '<p class="notice notice-error">' . esc_html__( 'No Coupon Groups for this partner.', 'direktt-cross-sell' ) . '</p>';
	} else {
		?>
		<ul class="direktt-cross-sell-partner-list">
			<?php
			foreach ( $groups as $group ) {
				$max_issue   = intval( get_post_meta( $group->ID, 'direktt_cross_sell_max_issuance', true ) );
				$issue_label = ( $max_issue == 0 )
					? esc_html__( 'Unlimited', 'direktt-cross-sell' )
					: $max_issue;
				?>
				<li>
					<div class="direktt-cross-sell-title-area">
						<h3 class="direktt-cross-sell-title"><?php echo esc_html( $group->post_title ); ?></h3>
						<p class="direktt-cross-sell-data"><?php echo esc_html__( 'Issued:', 'direktt-cross-sell' ); ?> <span><?php echo esc_html( direktt_cross_sell_get_issue_count( $group->ID, $partner_id ) ); ?></span> <?php echo esc_html( '/' ); ?> <strong><?php echo esc_html( $issue_label ); ?></strong></p>
					</div>
					<form method="post" action="" style="display:inline;" class="direktt-cs-issue-form">
                        <?php
						$allowed_html = wp_kses_allowed_html( 'post' );
                        echo wp_kses( Direktt_Public::direktt_render_confirm_popup( '', __( 'Are you sure that you want to issue this coupon for:', 'direktt-cross-sell' ) . ' ' . esc_html( $group->post_title ) . '?' ), $allowed_html );
                        ?>
						<input type="hidden" name="direktt_coupon_group_id" value="<?php echo esc_attr( $group->ID ); ?>">
						<input type="hidden" name="direktt_cs_issue_coupon_nonce" value="<?php echo esc_attr( wp_create_nonce( 'direktt_cs_issue_coupon_action' ) ); ?>">
						<input type="submit" name="direktt_cs_issue_coupon_btn" class="button button-primary button-large" value="<?php echo esc_attr__( 'Issue', 'direktt-cross-sell' ); ?>">
					</form>
				</li>
				<?php
			}
			?>
		</ul>
        <?php
		$allowed_html = wp_kses_allowed_html( 'post' );
        echo wp_kses( Direktt_Public::direktt_render_loader( __( 'Don\'t refresh the page', 'direktt-cross-sell' ) ), $allowed_html );
        ?>
        <script>
            jQuery( document ).ready( function ($) {
                $( 'input[name="direktt_cs_issue_coupon_btn"]' ).off( 'click' ).on( 'click', function (e) {
                    e.preventDefault();
                    var $form = $( this ).closest( 'form' );
                    var $popup = $form.find( '.direktt-confirm-popup' );
                    var $loader = $( '.direktt-loader-overlay' );

                    $popup.fadeIn();

                    $popup.find( '.direktt-popup-yes' ).off( 'click' ).on( 'click', function () {
                        event.preventDefault();
                        $popup.fadeOut();
                        $loader.fadeIn();
                        $form.append( '<input type="hidden" name="direktt_cs_issue_coupon" value="1">' );
                        setTimeout(function() {
                            $form.submit();
                        }, 500);
                    });

                    $popup.find( '.direktt-popup-no' ).off( 'click' ).on( 'click', function () {
                        event.preventDefault();
                        $popup.fadeOut();
                    });
                });
            });
        </script>
		<?php
	}
	echo '<a href="' . esc_url( remove_query_arg( array( 'direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag' ) ) ) . '" class="button button-invert direktt-cross-sell-back button-dark-gray">' . esc_html__( 'Back to Cross-Sell', 'direktt-cross-sell' ) . '</a>';
}

function direktt_cross_sell_process_coupon_issue( $partner_id, $coupon_group_id ) {

	global $wpdb, $direktt_user;

	$issued = false;

	// Get receiver user ID from subscriptionId GET param
	$direktt_receiver_user_id = isset( $_GET['subscriptionId'] ) ? sanitize_text_field( wp_unslash( $_GET['subscriptionId'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, reading a query var to know which is the current Direktt user. 

	// Get coupon group properties
	$group_validity = get_post_meta( $coupon_group_id, 'direktt_cross_sell_group_validity', true );   // days (0 = no expiry)

	$table = $wpdb->prefix . 'direktt_cross_sell_issued';

	if ( ! empty( $direktt_receiver_user_id ) && ! empty( $coupon_group_id ) && ! empty( $partner_id ) && ! empty( $direktt_user['direktt_user_id'] ) ) {

		// coupon logic (old code, as before)
		$coupon_expires = null;
		if ( ! empty( $group_validity ) && intval( $group_validity ) > 0 ) {
			$now            = current_time( 'mysql' );
			$coupon_expires = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' + ' . intval( $group_validity ) . ' days' ) );
		}

		$max_issuance = get_post_meta( intval( $coupon_group_id ), 'direktt_cross_sell_max_issuance', true );
		$max_issuance = (string) $max_issuance === '' ? 0 : intval( $max_issuance );

		$table        = $wpdb->prefix . 'direktt_cross_sell_issued';
		$issued_count = direktt_cross_sell_get_issue_count( $coupon_group_id, $partner_id );

		if ( $max_issuance != 0 && $issued_count >= $max_issuance ) {
			$issued = 3;
		} else {

			$coupon_guid = wp_generate_uuid4();

			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database insert is required here to add a record to a custom plugin table; $wpdb->insert() is the official safe WordPress method using prepared statements and proper data escaping.
				$table,
				array(
					'partner_id'               => $partner_id,
					'coupon_group_id'          => $coupon_group_id,
					'direktt_issuer_user_id'   => $direktt_user['direktt_user_id'],
					'direktt_receiver_user_id' => $direktt_receiver_user_id,
					'coupon_expires'           => $coupon_expires,
					'coupon_guid'              => $coupon_guid,
					// coupon_time: DB default
				),
				array(
					'%d', // partner_id
					'%d', // group_id
					'%s', // issuer
					'%s', // receiver
					is_null( $coupon_expires ) ? 'NULL' : '%s',
					'%s',  // guid
				)
			);
			if ( (bool) $inserted ) {
				$issued = 1;
				// TODO Send Issuance messages
			} else {
				$issued = 2;
			}
		}
	}

	$redirect_url = add_query_arg(
		array(
			'direktt_partner_id'     => intval( $partner_id ),
			'cross_sell_status_flag' => $issued,
		),
		remove_query_arg( 'cross_sell_status_flag' )
	);
	wp_safe_redirect( $redirect_url );
	exit;
}

function direktt_cross_sell_render_partners() {
	$partners = direktt_cross_sell_get_partners();

	echo '<h2>' . esc_html__( 'Issue New Coupons', 'direktt-cross-sell' ) . '</h2>';

	if ( empty( $partners ) ) {
		echo '<p class="notice notice-error">' . esc_html__( 'No Partners with Coupons Found.', 'direktt-cross-sell' ) . '</p>';
	} else {
		echo '<ul>';

		foreach ( $partners as $partner ) {
			$url = add_query_arg(
				array(
					'direktt_partner_id' => intval( $partner['ID'] ),
					'direktt_action'     => 'view_partner_coupons',
				)
			);
			echo '<li><a href="' . esc_url( $url ) . '" class="direktt-button button-large button-primary">' . esc_html( $partner['title'] ) . '</a></li>';
		}
		echo '</ul>';
	}
}

function direktt_cross_sell_render_issued( $subscription_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'direktt_cross_sell_issued';
	$now   = current_time( 'mysql' );

	$coupon_results = array();
	if ( ! empty( $subscription_id ) ) {
		$coupon_results = $wpdb->get_results( $wpdb->prepare( " SELECT * FROM $table WHERE direktt_receiver_user_id = %s AND (coupon_expires IS NULL OR coupon_expires = '0000-00-00 00:00:00' OR coupon_expires >= %s) AND coupon_valid = 1 ORDER BY coupon_time DESC ", $subscription_id, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Justifications for phpcs ignores:
		// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $table is built from $wpdb->prefix + literal string, $where is built from literal string + sanitized inputs.
		// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
		// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
	}

	// 3. Output Coupons
	echo '<div class="direktt-cross-sell-issued-coupons-div">';
	echo '<h3>' . esc_html__( 'Issued Coupons', 'direktt-cross-sell' ) . '</h3>';

	$filtered_coupon_results = array();

	foreach ( $coupon_results as $row ) {
		$partner_post = get_post( $row->partner_id );
        if ( ! $partner_post ) {
            continue;
        } else {
            if ( $partner_post->post_status !== 'publish' ) {
                continue;
            }
            $partner_name = esc_html( $partner_post->post_title );
        }
		$coupon_group_post = get_post( $row->coupon_group_id );
        if ( ! $coupon_group_post ) {
            continue;
        } else {
            if ( $coupon_group_post->post_status !== 'publish' ) {
                continue;
            }
            $group_title = esc_html( $coupon_group_post->post_title );
        }
		$issued  = esc_html( mysql2date( 'Y-m-d H:i:s', $row->coupon_time ) );
		$expires = ( empty( $row->coupon_expires ) || $row->coupon_expires == '0000-00-00 00:00:00' )
			? esc_html__( 'No expiry', 'direktt-cross-sell' )
			: esc_html( mysql2date( 'Y-m-d H:i:s', $row->coupon_expires ) );

		// Usage filtering for Coupons
		$max_usage = intval( get_post_meta( $row->coupon_group_id, 'direktt_cross_sell_max_usage', true ) );
		if ( ! $max_usage ) {
			$max_usage = 0;
		}

		$used_count = direktt_cross_sell_get_used_count( intval( $row->ID ) );

		// Hide fully used coupons
		if ( $max_usage > 0 && $used_count >= $max_usage ) {
			continue;
		}

		$filtered_coupon_results[] = $row;
	}

	if ( empty( $filtered_coupon_results ) ) {
		echo '<p class="notice notice-error">' . esc_html__( 'No active or valid coupons issued to this user.', 'direktt-cross-sell' ) . '</p>';
	} else {
		echo '<table class="direktt-cross-sell-issued-coupons-table"><thead><tr>';
		echo '<th>';
		echo '<strong>' . esc_html__( 'Partner', 'direktt-cross-sell' ) . '</strong>';
		echo ' (' . esc_html__( 'Group', 'direktt-cross-sell' ) . ')';
		echo '</th>';
		echo '<th>' . esc_html__( 'Issued', 'direktt-cross-sell' ) . '</th>';
		echo '<th>' . esc_html__( 'Expires', 'direktt-cross-sell' ) . '</th>';
		echo '<th>' . esc_html__( 'Used', 'direktt-cross-sell' ) . '</th>';
		// echo '<th>' . esc_html__('Actions', 'direktt-cross-sell') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $filtered_coupon_results as $row ) {
			$partner_post = get_post( $row->partner_id );
            if ( ! $partner_post ) {
                continue;
            } else {
                if ( $partner_post->post_status !== 'publish' ) {
                    continue;
                }
                $partner_name = esc_html( $partner_post->post_title );
            }
			$coupon_group_post = get_post( $row->coupon_group_id );
			if ( ! $coupon_group_post ) {
				continue;
			} else {
                if ( $coupon_group_post->post_status !== 'publish' ) {
                    continue;
                }
				$group_title = esc_html( $coupon_group_post->post_title );
			}
			// $issued = esc_html(mysql2date('Y-m-d H:i:s', $row->coupon_time));
			$issued  = $row->coupon_time;
			$expires = ( empty( $row->coupon_expires ) || $row->coupon_expires == '0000-00-00 00:00:00' )
				? esc_html__( 'No expiry', 'direktt-cross-sell' )
				: esc_html( mysql2date( 'Y-m-d H:i:s', $row->coupon_expires ) );
			$max_usage = intval( get_post_meta( $row->coupon_group_id, 'direktt_cross_sell_max_usage', true ) );
			if ( ! $max_usage ) {
				$max_usage = 0;
			}
			$used_count = direktt_cross_sell_get_used_count( intval( $row->ID ) );

			echo '<tr>';
				echo '<td class="direktt-cross-sell-name">';
				echo '<strong>' . esc_html( $partner_name ) . '</strong>';
				echo ' <br/><i>' . esc_html( $group_title ) . '</i>';
				echo '</td>';
				echo '<td class="direktt-cross-sell-issued">' . esc_html( human_time_diff( strtotime( $issued ) ) ) . esc_html__( ' ago', 'direktt-cross-sell' ) . '</td>';
				echo '<td class="direktt-cross-sell-expires">' . esc_html( $expires ) . '</td>';
				echo '<td class="direktt-cross-sell-count">' . esc_html( $used_count . ' / ' . ( $max_usage > 0 ? $max_usage : __( 'Unlimited', 'direktt-cross-sell' ) ) ) . '</td>';
			echo '</tr>';
			echo '<tr class="direktt-cross-sell-actions">';
				echo '<td colspan="2">';
					$invalidate_url = $url = add_query_arg(
						array(
							'direktt_action' => 'invalidate_coupon',
						)
					);
					$use_url        = add_query_arg(
						array(
							'direktt_action' => 'use_coupon',
							'coupon_id'      => intval( $row->ID ),
						),
						remove_query_arg( array( 'direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag' ) )
					);
					echo '<a class="button" href="' . esc_url( $use_url ) . '">' . esc_html__( 'Use', 'direktt-cross-sell' ) . '</a>';
				echo '</td>';
				echo '<td colspan="3">';
					echo '<form method="post" action="' . esc_url( $invalidate_url ) . '" class="direktt-cross-sell-invalidate-form">';
					$allowed_html = wp_kses_allowed_html( 'post' );
                    echo wp_kses( Direktt_Public::direktt_render_confirm_popup( '', __( 'Are you sure that you want to issue this coupon for:', 'direktt-cross-sell' ) . ' ' . esc_html( $group_title ) . ' (' . esc_html__( 'Issued:', 'direktt-cross-sell' ) . ' ' . $issued . '?' ), $allowed_html );
					echo '<input type="hidden" name="direktt_cs_invalidate_coupon_nonce" value="' . esc_attr( wp_create_nonce( 'direktt_cs_invalidate_coupon_action' ) ) . '">';
					echo '<input type="hidden" name="invalid_coupon_id" value="' . esc_attr( $row->ID ) . '">';
					echo '<input type="submit" name="direktt_cs_invalidate_coupon_btn" class="button button-red button-invert" value="' . esc_attr__( 'Invalidate', 'direktt-cross-sell' ) . '">';
					echo '</form>';
				echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
	$allowed_html = wp_kses_allowed_html( 'post' );
    echo wp_kses( Direktt_Public::direktt_render_loader( __( 'Don\'t refresh the page', 'direktt-cross-sell' ) ), $allowed_html );
	?>
    <script>
        jQuery( document ).ready( function ($) {
            $( 'input[name="direktt_cs_invalidate_coupon_btn"]' ).off( 'click' ).on( 'click', function (e) {
                e.preventDefault();
                var $form = $( this ).closest( 'form' );
                var $popup = $form.find( '.direktt-confirm-popup' );
                var $loader = $( '.direktt-loader-overlay' );

                $popup.fadeIn();

                $popup.find( '.direktt-popup-yes' ).off( 'click' ).on( 'click', function () {
                    event.preventDefault();
                    $popup.fadeOut();
                    $loader.fadeIn();
                    $form.append( '<input type="hidden" name="direktt_cs_invalidate_coupon" value="1">' );
                    setTimeout(function() {
                        $form.submit();
                    }, 500);
                });

                $popup.find( '.direktt-popup-no' ).off( 'click' ).on( 'click', function () {
                    event.preventDefault();
                    $popup.fadeOut();
                });
            });
        });
    </script>
	<?php
}

function direktt_cross_sell_my_coupons() {

	global $direktt_user;
	global $wpdb;

	$subscription_id = $direktt_user['direktt_user_id'];

	$table = $wpdb->prefix . 'direktt_cross_sell_issued';
	$now   = current_time( 'mysql' );

	ob_start();

	$coupon_results = array();
	if ( ! empty( $subscription_id ) ) {
		$coupon_results = $wpdb->get_results( $wpdb->prepare( " SELECT * FROM $table WHERE direktt_receiver_user_id = %s AND (coupon_expires IS NULL OR coupon_expires = '0000-00-00 00:00:00' OR coupon_expires >= %s) AND coupon_valid = 1 ORDER BY coupon_time DESC", $subscription_id, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Justifications for phpcs ignores:
		// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $table is built from $wpdb->prefix + literal string, $where is built from literal string + sanitized inputs.
		// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
		// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
	}

	// 3. Output Coupons
	echo '<h2>' . esc_html__( 'Issued Coupons', 'direktt-cross-sell' ) . '</h2>';

	$filtered_coupon_results = array();

	foreach ( $coupon_results as $row ) {
		$partner_post = get_post( $row->partner_id );
        if ( ! $partner_post ) {
            continue;
        } else {
            if ( $partner_post->post_status !== 'publish' ) {
                continue;
            }
            $partner_name = esc_html( $partner_post->post_title );
        }
		$coupon_group_post = get_post( $row->coupon_group_id );
        if ( ! $coupon_group_post ) {
            continue;
        } else {
            if ( $coupon_group_post->post_status !== 'publish' ) {
                continue;
            }
            $group_title = esc_html( $coupon_group_post->post_title );
        }
		$issued  = esc_html( mysql2date( 'Y-m-d H:i:s', $row->coupon_time ) );
		$expires = ( empty( $row->coupon_expires ) || $row->coupon_expires == '0000-00-00 00:00:00' )
			? esc_html__( 'No expiry', 'direktt-cross-sell' )
			: esc_html( mysql2date( 'Y-m-d H:i:s', $row->coupon_expires ) );

		// Usage filtering for Coupons
		$max_usage = intval( get_post_meta( $row->coupon_group_id, 'direktt_cross_sell_max_usage', true ) );
		if ( ! $max_usage ) {
			$max_usage = 0;
		}

		$used_count = direktt_cross_sell_get_used_count( intval( $row->ID ) );

		// Hide fully used coupons
		if ( $max_usage > 0 && $used_count >= $max_usage ) {
			continue;
		}

		$filtered_coupon_results[] = $row;
	}

	if ( empty( $filtered_coupon_results ) ) {
		echo '<p class="notice notice-error">' . esc_html__( 'There are no active or valid coupons issued', 'direktt-cross-sell' ) . '</p>';
	} else {
		echo '<table class="direktt-cross-sell-issued-coupons-table"><thead><tr>';		
		echo '<th>';
		echo '<strong>' . esc_html__( 'Partner', 'direktt-cross-sell' ) . '</strong>';
		echo ' (' . esc_html__( 'Group', 'direktt-cross-sell' ) . ')';
		echo '</th>';
		echo '<th>' . esc_html__( 'Issued', 'direktt-cross-sell' ) . '</th>';
		echo '<th>' . esc_html__( 'Expires', 'direktt-cross-sell' ) . '</th>';
		echo '<th>' . esc_html__( 'Used', 'direktt-cross-sell' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'direktt-cross-sell' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $filtered_coupon_results as $row ) {
			$partner_post      = get_post( $row->partner_id );
            if ( ! $partner_post ) {
                continue;
            } else {
                if ( $partner_post->post_status !== 'publish' ) {
                    continue;
                }
                $partner_name = esc_html( $partner_post->post_title );
            }
			$coupon_group_post = get_post( $row->coupon_group_id );
            if ( ! $coupon_group_post ) {
                continue;
            } else {
                if ( $coupon_group_post->post_status !== 'publish' ) {
                    continue;
                }
                $group_title = esc_html( $coupon_group_post->post_title );
            }
			$issued  = esc_html( mysql2date( 'Y-m-d H:i:s', $row->coupon_time ) );
			$expires = ( empty( $row->coupon_expires ) || $row->coupon_expires == '0000-00-00 00:00:00' )
				? esc_html__( 'No expiry', 'direktt-cross-sell' )
				: esc_html( mysql2date( 'Y-m-d H:i:s', $row->coupon_expires ) );
			$max_usage         = intval( get_post_meta( $row->coupon_group_id, 'direktt_cross_sell_max_usage', true ) );
			if ( ! $max_usage ) {
				$max_usage = 0;
			}
			$used_count = direktt_cross_sell_get_used_count( intval( $row->ID ) );

			echo '<tr>';
			echo '<td class="direktt-cross-sell-name"><strong>' . esc_html( $group_title ) . '</strong><br/><i>' . esc_html( $partner_name ) . '</i></td>';
			echo '<td>' . esc_html( human_time_diff( strtotime( $issued ) ) ) . esc_html__( ' ago', 'direktt-cross-sell' ) . '</td>';
			echo '<td>' . esc_html( $expires ) . '</td>';
			echo '<td>' . esc_html( $used_count . ' / ' . ( $max_usage > 0 ? $max_usage : esc_html__( 'Unlimited', 'direktt-cross-sell' ) ) ) . '</td>';
			$coupon_url = add_query_arg(
				array(
					'direktt_action' => 'view_coupon',
					'coupon_id'      => $row->ID,
				)
			);
			echo '<td><a href="' . esc_url( $coupon_url ) . '" class="direktt-button button-invert">' . esc_html__( 'View', 'direktt-cross-sell' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
	return ob_get_clean();
}

/**
 * Render coupon information table for coupons (pure display).
 *
 * @param array $opts
 *      - partner_post: WP_Post
 *      - coupon_group_post: WP_Post
 *      - coupon_data: (object|array) Issued row
 *      - used_count: int
 *      - max_usage: int
 *      - issued_date: string
 *      - expires: string
 */
function direktt_cross_sell_display_coupon_info_table( $opts ) {
	$partner_post      = $opts['partner_post'];
	$coupon_group_post = $opts['coupon_group_post'];
	$issued_date       = $opts['issued_date'];
	$expires           = $opts['expires'];
	$used_count        = $opts['used_count'];
	$max_usage         = $opts['max_usage'];

	$partner_name = $partner_post ? $partner_post->post_title : esc_html__( 'Unknown', 'direktt-cross-sell' );
	$group_title  = $coupon_group_post ? $coupon_group_post->post_title : esc_html__( 'Unknown', 'direktt-cross-sell' );
	$group_descr  = $coupon_group_post ? $coupon_group_post->post_content : '';

	echo '<h3>' . esc_html( $group_title ) . '</h3>';
	echo '<table class="direktt-cross-sell-issued-coupon-table">';
		echo '<thead>';
			echo '<tr><td>' . esc_html__( 'Partner', 'direktt-cross-sell' ) . '</td><td>' . esc_html( $partner_name ) . '</td></tr>';
		echo '</thead>';
		echo '<tbody>';
			echo '<tr><td>' . esc_html__( 'Description', 'direktt-cross-sell' ) . '</td><td>' . wp_kses_post( $group_descr ) . '</td></tr>';
			echo '<tr><td>' . esc_html__( 'Issued', 'direktt-cross-sell' ) . '</td><td>' . esc_html( human_time_diff( strtotime( $issued_date ) ) ) . '</td></tr>';
			echo '<tr><td>' . esc_html__( 'Expires', 'direktt-cross-sell' ) . '</td><td>' . esc_html( $expires ) . '</td></tr>';
			echo '<tr><td>' . esc_html__( 'Usages', 'direktt-cross-sell' ) . '</td><td>';
			printf(
				// translators: %1$s is the used count, %2$s is the max usage limit (or 'Unlimited')
				esc_html__( '%1$s / %2$s', 'direktt-cross-sell' ),
				esc_html( $used_count ),
				( $max_usage == 0 ? esc_html__( 'Unlimited', 'direktt-cross-sell' ) : esc_html( $max_usage ) )
			);
			echo '</td></tr>';
		echo '</tbody>';
	echo '</table>';
}

function direktt_cross_sell_get_partner_and_group_by_coupon_guid( $coupon_guid ) {
	global $wpdb;
	$table       = $wpdb->prefix . 'direktt_cross_sell_issued';
	$coupon_guid = sanitize_text_field( $coupon_guid );

	$result = $wpdb->get_row( $wpdb->prepare( "SELECT partner_id, coupon_group_id FROM $table WHERE coupon_guid = %s LIMIT 1", $coupon_guid ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $table is built from $wpdb->prefix + literal string, $where is built from literal string + sanitized inputs.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.

	return $result ? $result : false;
}

function direktt_cross_sell_user_can_validate( $partner_id ) {
	global $direktt_user;

	// Always allow admin
	if ( class_exists( 'Direktt_User' ) && Direktt_User::is_direktt_admin() ) {
		return true;
	}

	// Check category

	$validate_categories = intval( get_post_meta( intval( $partner_id ), 'direktt_cross_sell_partner_categories', true ) );
	$validate_tags       = intval( get_post_meta( intval( $partner_id ), 'direktt_cross_sell_partner_tags', true ) );

	$category_slug = '';
	$tag_slug      = '';

	if ( $validate_categories !== 0 ) {
		$category      = get_term( $validate_categories, 'direkttusercategories' );
		$category_slug = $category ? $category->slug : '';
	}

	if ( $validate_tags !== 0 ) {
		$tag      = get_term( $validate_tags, 'direkttusertags' );
		$tag_slug = $tag ? $tag->slug : '';
	}

	// Check via provided function
	if ( class_exists( 'Direktt_User' ) && Direktt_User::has_direktt_taxonomies( $direktt_user, $category_slug ? array( $category_slug ) : array(), $tag_slug ? array( $tag_slug ) : array() ) ) {
		return true;
	}
	return false;
}

function direktt_cross_sell_coupon_validation() {
	global $wpdb;

	if ( isset( $_GET['coupon_code'] ) ) {
		$coupon_code     = sanitize_text_field( wp_unslash( $_GET['coupon_code'] ) );
		$additional_data = direktt_cross_sell_get_partner_and_group_by_coupon_guid( $coupon_code );
		if ( $additional_data ) {
			$coupon_id  = $additional_data['coupon_group_id'];
			$partner_id = $additional_data['partner_id'];
		} else {
			ob_start();
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The coupon code is not valid', 'direktt-cross-sell' ) . '</p></div>';
			return ob_get_clean();
		}
	} elseif ( isset( $_GET['coupon_id'] ) && isset( $_GET['partner_id'] ) ) {
		$coupon_id  = intval( $_GET['coupon_id'] );
		$partner_id = intval( $_GET['partner_id'] );
	} else {
        ob_start();
        echo '<div class="notice notice-error"><p>' . esc_html__( 'The referenced coupon does not exist or you are not authorized to manage it.', 'direktt-cross-sell' ) . '</p></div>';
		return ob_get_clean();
	}
    
	if ( ! direktt_cross_sell_user_can_validate( $partner_id ) ) {
        ob_start();
        echo '<div class="notice notice-error"><p>' . esc_html__( 'The referenced coupon does not exist or you are not authorized to manage it.', 'direktt-cross-sell' ) . '</p></div>';
        return ob_get_clean();
	}

	$notice = ''; // For status flags after redirect

	if ( isset( $coupon_code ) ) {

		$table  = $wpdb->prefix . 'direktt_cross_sell_issued';
		$coupon = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE coupon_guid = %s", $coupon_code ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Justifications for phpcs ignores:
		// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $table is built from $wpdb->prefix + literal string, $where is built from literal string + sanitized inputs.
		// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
		// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
		if ( ! $coupon ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found.', 'direktt-cross-sell' ) . '</p></div>';
		}

		$partner_post      = get_post( $coupon->partner_id );
		$coupon_group_post = get_post( $coupon->coupon_group_id );
        if ( ! $partner_post || ! $coupon_group_post ) {
            return '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found.', 'direktt-cross-sell' ) . '</p></div>';
        } else {
            if ( $partner_post->post_status !== 'publish' || $coupon_group_post->post_status !== 'publish' ) {
                return '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found.', 'direktt-cross-sell' ) . '</p></div>';
            }
        }

		$issued_date = esc_html( mysql2date( 'Y-m-d H:i:s', $coupon->coupon_time ) );
		$expires     = ( empty( $coupon->coupon_expires ) || $coupon->coupon_expires == '0000-00-00 00:00:00' )
			? esc_html__( 'No expiry', 'direktt-cross-sell' )
			: esc_html( mysql2date( 'Y-m-d H:i:s', $coupon->coupon_expires ) );

		$used_count = direktt_cross_sell_get_used_count( intval( $coupon->ID ) );
		$max_usage  = intval( get_post_meta( $coupon->coupon_group_id, 'direktt_cross_sell_max_usage', true ) );
		if ( ! $max_usage ) {
			$max_usage = 0;
		}

		$now_time   = current_time( 'mysql' );
		$is_expired = ! empty( $coupon->coupon_expires )
			&& $coupon->coupon_expires != '0000-00-00 00:00:00'
			&& $coupon->coupon_expires < $now_time;

		$disable_use = false;
		if ( $max_usage > 0 && $used_count >= $max_usage ) {
			$disable_use = true;
		}
		if ( $is_expired ) {
			$disable_use = true;
		}

		// ---- Handle POST (use coupon) -----
		if (
			isset( $_POST['direktt_cs_use_coupon'] ) &&
			isset( $_POST['direktt_cs_use_coupon_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['direktt_cs_use_coupon_nonce'] ) ), 'direktt_cs_use_coupon_action' )
		) {
			// This will redirect/exit as needed after processing
			direktt_cross_sell_process_use_coupon( $coupon );
		}

		// Show notices (using URL flag)
		if ( isset( $_GET['cross_sell_use_flag'] ) ) {
			$flag = intval( $_GET['cross_sell_use_flag'] );
			if ( $flag === 1 ) {
				$notice = '<div class="notice notice-success"><p>' . esc_html__( 'Coupon used successfully.', 'direktt-cross-sell' ) . '</p></div>';
			} elseif ( $flag === 2 ) {
				$notice = '<div class="notice notice-error"><p>' . esc_html__( 'Coupon usage limit has been reached; cannot use coupon.', 'direktt-cross-sell' ) . '</p></div>';
			} elseif ( $flag === 3 ) {
				$notice = '<div class="notice notice-error"><p>' . esc_html__( 'There was an error recording coupon usage. Please try again.', 'direktt-cross-sell' ) . '</p></div>';
			} elseif ( $flag === 4 ) {
				$notice = '<div class="notice notice-error"><p>' . esc_html__( 'Coupon has expired; cannot use coupon.', 'direktt-cross-sell' ) . '</p></div>';
			}
		}

		// --- Output ---
		ob_start();
		echo '<div id="direktt-profile-wrapper">';
		echo '<div id="direktt-profile">';
		echo '<div id="direktt-profile-data" class="direktt-profile-data-cross-sell-tool direktt-service">';
		echo wp_kses_post( $notice );
		direktt_cross_sell_display_coupon_info_table(
			array(
				'partner_post'      => $partner_post,
				'coupon_group_post' => $coupon_group_post,
				'issued_date'       => $issued_date,
				'expires'           => $expires,
				'used_count'        => $used_count,
				'max_usage'         => $max_usage,
			)
		);

		// Show "Use Coupon" button with JS confirm, if allowed
		if ( ! $disable_use ) {
			?>
			<form method="post" action="">
                <?php
				$allowed_html = wp_kses_allowed_html( 'post' );
                echo wp_kses( Direktt_Public::direktt_render_confirm_popup( 'direktt-cross-sell-confirm-use', __( 'Are you sure that you want to use this coupon for:', 'direktt-cross-sell' ) . ' ' . esc_html( $coupon_group_post->post_title ) . '?' ), $allowed_html );
                echo wp_kses( Direktt_Public::direktt_render_loader( __( 'Don\'t refresh the page', 'direktt-cross-sell' ) ), $allowed_html );
                ?>
				<input type="hidden" name="direktt_cs_use_coupon_nonce" value="<?php echo esc_attr( wp_create_nonce( 'direktt_cs_use_coupon_action' ) ); ?>">
				<input type="button" name="direktt_cs_use_coupon_btn" class="button button-primary button-large" value="<?php echo esc_attr__( 'Use Coupon', 'direktt-cross-sell' ); ?>">
			</form>
			<script>
                jQuery( document ).ready( function($) {
                    $( 'input[name="direktt_cs_use_coupon_btn"]' ).off( 'click' ).on( 'click', function(e) {
                        e.preventDefault();
                        $( '#direktt-cross-sell-confirm-use' ).fadeIn();
                    });

                    $( '#direktt-cross-sell-confirm-use .direktt-popup-no' ).off( 'click' ).on( 'click', function() {
                        event.preventDefault();
                        $( '#direktt-cross-sell-confirm-use' ).fadeOut();
                    });

                    $( '#direktt-cross-sell-confirm-use .direktt-popup-yes' ).off( 'click' ).on( 'click', function() {
                        event.preventDefault();
                        $( '#direktt-cross-sell-confirm-use' ).fadeOut();
                        $( '.direktt-loader-overlay' ).fadeIn();
                        $( 'form' ).append( '<input type="hidden" name="direktt_cs_use_coupon" value="1">' );
                        setTimeout(function() {
                            $( 'form' ).submit();
                        }, 500);
                    });
                });
			</script>
			<?php
		} elseif ( $is_expired ) {
			echo '<p class="notice notice-error"><em>' . esc_html__( 'This coupon has expired and cannot be used.', 'direktt-cross-sell' ) . '</em></p>';
		} elseif ( $disable_use ) {
			echo '<p class="notice notice-error"><em>' . esc_html__( 'This coupon has reached its usage limit.', 'direktt-cross-sell' ) . '</em></p>';
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}
}

function direktt_cross_sell_process_coupon_invalidate() {
	global $wpdb;
	$table = $wpdb->prefix . 'direktt_cross_sell_issued';

    if ( class_exists( 'Direktt_User' ) && ! Direktt_User::is_direktt_admin() ) {
		return;
	}

	if (
		isset( $_POST['direktt_cs_invalidate_coupon'] ) &&
		isset( $_POST['invalid_coupon_id'] ) &&
		isset( $_POST['direktt_cs_invalidate_coupon_nonce'] ) &&
		wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['direktt_cs_invalidate_coupon_nonce'] ) ), 'direktt_cs_invalidate_coupon_action' )
	) {
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Justifications for phpcs ignores:
			// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct database update is required for this custom plugin table; $wpdb->update() safely uses prepared statements.
			// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not needed because we want to update the table immediately with live data.
			$table,
			array( 'coupon_valid' => 0 ),
			array( 'ID' => intval( $_POST['invalid_coupon_id'] ) ),
			array( '%d' ),
			array( '%d' )
		);

        // TODO add query arg i ispis notice-a
		$redirect_url = remove_query_arg( array( 'cross_sell_invalidate_flag', 'direktt_action' ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}
}

function direktt_cross_sell_render_profile_tool() {
	// --- Show Use Coupon Screen ---
	if ( isset( $_GET['direktt_action'] ) && $_GET['direktt_action'] === 'use_coupon' && isset( $_GET['coupon_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: Not processing form submission, using query var for content rendering.

		direktt_cross_sell_render_use_coupon( intval( $_GET['coupon_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: Not processing form submission, using query var for content rendering.
		return;
	}

	// --- Show Partner Screen ---

	if ( isset( $_GET['direktt_action'] ) && $_GET['direktt_action'] === 'view_partner_coupons' && isset( $_GET['direktt_partner_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: Not processing form submission, using query var for content rendering.

		direktt_cross_sell_render_one_partner( intval( $_GET['direktt_partner_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: Not processing form submission, using query var for content rendering.
		return;
	}

	// --- Process Coupon invalidation -- invalidate_coupon

	if ( isset( $_GET['direktt_action'] ) && $_GET['direktt_action'] === 'invalidate_coupon' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: Not processing form submission, using query var for content rendering.
		direktt_cross_sell_process_coupon_invalidate();
	}

	// Partners with Coupon offering
	direktt_cross_sell_render_partners();

	// Show issued coupon list for review-capable users, if subscriptionId is present
	$subscription_id = isset( $_GET['subscriptionId'] ) ? sanitize_text_field( wp_unslash( $_GET['subscriptionId'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: Not processing form submission, using query var for content rendering.
	if ( direktt_cross_sell_user_can_review() && ! empty( $subscription_id ) ) {
		direktt_cross_sell_render_issued( $subscription_id );
	}
}

function direktt_cross_sell_user_tool() {
	global $direktt_user;
	global $wpdb;
	$table = $wpdb->prefix . 'direktt_cross_sell_issued';
	$now   = current_time( 'mysql' );
	if ( ! $direktt_user ) {
		ob_start();
		echo '<div id="direktt-profile-wrapper">';
		echo '<div id="direktt-profile">';
		echo '<div id="direktt-profile-data" class="direktt-profile-data-cross-sell-tool direktt-service">';
		echo '<div class="notice notice-error"><p>' . esc_html__( 'You must be logged in to view your coupons.', 'direktt-cross-sell' ) . '</p></div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}
	$subscription_id = $direktt_user['direktt_user_id'];

	if ( isset( $_GET['direktt_action'] ) && $_GET['direktt_action'] === 'view_coupon' && isset( $_GET['coupon_id'] ) ) {
		$coupons = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE ID = %d AND direktt_receiver_user_id = %s AND coupon_valid = 1 ", intval( $_GET['coupon_id'] ), $subscription_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.Security.NonceVerification.Recommended

		// Justifications for phpcs ignores:
		// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $table is built from $wpdb->prefix + literal string, $where is built from literal string + sanitized inputs.
		// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
		// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
		// WordPress.Security.NonceVerification.Recommended: Not a form processing, reading a query val for rendering purposes of coupon.

        ob_start();
		echo '<div id="direktt-profile-wrapper">';
		echo '<div id="direktt-profile">';
		echo '<div id="direktt-profile-data" class="direktt-profile-data-cross-sell-tool direktt-service">';

		if ( empty( $coupons ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found or you do not have permission to view it.', 'direktt-cross-sell' ) . '</p></div>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
			return ob_get_clean();
		}

		$coupon = $coupons[0];

        $partner_post = get_post( $coupon->partner_id );
        if ( ! $partner_post || $partner_post->post_status !== 'publish' ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found or you do not have permission to view it.', 'direktt-cross-sell' ) . '</p></div>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
            return ob_get_clean();
        }
        $coupon_group_post = get_post( $coupon->coupon_group_id );
        if ( ! $coupon_group_post || $coupon_group_post->post_status !== 'publish' ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found or you do not have permission to view it.', 'direktt-cross-sell' ) . '</p></div>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
			return ob_get_clean();
		}

		$qr_code_image    = get_post_meta( intval( $coupon->partner_id ), 'direktt_cross_sell_qr_code_image', true );
		$qr_code_color    = get_post_meta( intval( $coupon->partner_id ), 'direktt_cross_sell_qr_code_color', true );
		$qr_code_bg_color = get_post_meta( intval( $coupon->partner_id ), 'direktt_cross_sell_qr_code_bg_color', true );

		$check_slug     = get_option( 'direktt_cross_sell_check_slug' );
		$validation_url = site_url( $check_slug, 'https' );

		$actionObject = array(
			'action' => array(
				'type'    => 'link',
				'params'  => array(
					'url'    => $validation_url,
					'target' => 'app',
				),
				'retVars' => array(
					'coupon_code' => $coupon->coupon_guid,
				),
			),
		);

		global $enqueue_direktt_cross_sell_scripts;
		$enqueue_direktt_cross_sell_scripts = true;

		?>
		<h2 class="direktt-cross-sell-title"><?php echo esc_html( $partner_post->post_title ); ?></h2>
		<h2 class="direktt-cross-sell-title"><?php echo esc_html( $coupon_group_post->post_title ); ?></h2>
		<div class="direktt-cross-sell-qr-canvas-wrapper">
			<div id="direktt-cross-sell-qr-canvas"></div>
			<p class="direktt-cross-sell-content"><?php echo nl2br( wp_kses_post( $coupon_group_post->post_content ) ); ?></p>		
		</div>

		<script type="text/javascript">
			const qrCode = new QRCodeStyling({
				width: 350,
				height: 350,
				type: "svg",
				data: '<?php echo wp_json_encode( $actionObject ); ?>',
				image: '<?php echo $qr_code_image ? esc_js( $qr_code_image ) : ''; ?>',
				dotsOptions: {
					color: '<?php echo $qr_code_color ? esc_js( $qr_code_color ) : '#000000'; ?>',
					type: "rounded"
				},
				backgroundOptions: {
					color: '<?php echo $qr_code_bg_color ? esc_js( $qr_code_bg_color ) : '#ffffff'; ?>',
				},
				imageOptions: {
					crossOrigin: "anonymous",
					margin: 20
				}
			});

			qrCode.append(document.getElementById("direktt-cross-sell-qr-canvas"));
			/* qrCode.download({
				name: "qr",
				extension: "svg"
			});*/
		</script>

		<?php
        $back_url = remove_query_arg( array( 'coupon_id' ) );
		$back_url = add_query_arg( 'direktt_action', 'my_coupons', $back_url );
		echo '<a href="' . esc_url( $back_url ) . '" class="button button-invert direktt-cross-sell-back button-dark-gray">' . esc_html__( 'Back', 'direktt-cross-sell' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}

	if ( isset( $_GET['direktt_action'] ) && $_GET['direktt_action'] === 'view_partner_coupons' && isset( $_GET['direktt_partner_id'] ) ) {
		ob_start();
		echo '<div id="direktt-profile-wrapper">';
		echo '<div id="direktt-profile">';
		echo '<div id="direktt-profile-data" class="direktt-profile-data-cross-sell-tool direktt-service">';
		$partner_id = intval( $_GET['direktt_partner_id'] );
		if (
			isset( $_POST['direktt_cs_issue_coupon'] ) &&
			isset( $_POST['direktt_coupon_group_id'] ) &&
			isset( $_POST['direktt_cs_issue_coupon_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['direktt_cs_issue_coupon_nonce'] ) ), 'direktt_cs_issue_coupon_action' )
		) {

			$qr_code_image    = get_post_meta( intval( $partner_id ), 'direktt_cross_sell_qr_code_image', true );
			$qr_code_color    = get_post_meta( intval( $partner_id ), 'direktt_cross_sell_qr_code_color', true );
			$qr_code_bg_color = get_post_meta( intval( $partner_id ), 'direktt_cross_sell_qr_code_bg_color', true );
			$qr_code_message  = get_post_meta( intval( $_POST['direktt_coupon_group_id'] ), 'direktt_cross_sell_qr_code_message', true );

			$back_url = remove_query_arg( array( 'coupon_id', 'direktt_action' ) );
			$back_url = add_query_arg( 'direktt_action', 'view_partner_coupons', $back_url );

            $partner_post = get_post( $partner_id );
            if ( ! $partner_post || $partner_post->post_status !== 'publish' ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found or you do not have permission to view it.', 'direktt-cross-sell' ) . '</p></div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                return ob_get_clean();
            }
            $coupon_group_post = get_post( intval( $_POST['direktt_coupon_group_id'] ) );
            if ( ! $coupon_group_post || $coupon_group_post->post_status !== 'publish' ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Coupon not found or you do not have permission to view it.', 'direktt-cross-sell' ) . '</p></div>';
				echo '</div>';
				echo '</div>';
				echo '</div>';
                return ob_get_clean();
            }

			$actionObject = array(
				'action' => array(
					'type'    => 'api',
					'params'  => array(
						'actionType'     => 'issue_coupon',
						'successMessage' => 'Coupon has been succesfully issued!',
					),
					'retVars' => array(
						'partner_id'      => sanitize_text_field( $partner_id ),
						'coupon_group_id' => sanitize_text_field( wp_unslash( $_POST['direktt_coupon_group_id'] ) ),
						'issuer_id'       => $subscription_id,
					),
				),
			);

			global $enqueue_direktt_cross_sell_scripts;
			$enqueue_direktt_cross_sell_scripts = true;

			?>

            <p class="direktt-cross-sell-title"><?php echo esc_html( $partner_post->post_title ); ?></p>
            <h2 class="direktt-cross-sell-title"><?php echo esc_html( $coupon_group_post->post_title ); ?></h2>
			<div class="direktt-cross-sell-qr-canvas-wrapper">
				<div id="direktt-cross-sell-qr-canvas"></div>
				<p class="direktt-cross-sell-content"><?php echo nl2br( wp_kses_post( $coupon_group_post->post_content ) ); ?></p>		
			</div>
			<button id="direktt-cross-sell-share" class="ditektt-button button-large"><?php echo esc_html__( 'Share', 'direktt-cross-sell' ); ?></button>
			<script type="text/javascript">
				const qrCode = new QRCodeStyling({
					width: 350,
					height: 350,
					type: "svg",
					data: '<?php echo wp_json_encode( $actionObject ); ?>',
					image: '<?php echo $qr_code_image ? esc_js( $qr_code_image ) : ''; ?>',
					dotsOptions: {
						color: '<?php echo $qr_code_color ? esc_js( $qr_code_color ) : '#000000'; ?>',
						type: "rounded"
					},
					backgroundOptions: {
						color: '<?php echo $qr_code_bg_color ? esc_js( $qr_code_bg_color ) : '#ffffff'; ?>',
					},
					imageOptions: {
						crossOrigin: "anonymous",
						margin: 20
					}
				});

				qrCode.append(document.getElementById("direktt-cross-sell-qr-canvas"));

				document.getElementById("direktt-cross-sell-share").addEventListener("click", async () => {
					qrCode.getRawData("png").then(async (blob) => {
						const img = new Image();
						img.onload = async () => {
							const margin = 20; // margin in pixels
							const bgColor = '<?php echo $qr_code_bg_color ? esc_js( $qr_code_bg_color ) : '#ffffff'; ?>';
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
											text: '<?php echo esc_js( $qr_code_message ); ?>',
											files: [file]
										});
									} catch (err) {
										alert('<?php echo esc_js( __( 'Share failed:', 'direktt-cross-sell' ) ); ?> ' + err.message);
									}
								} else {
									alert('<?php echo esc_js( __( 'Your browser does not support sharing files.', 'direktt-cross-sell' ) ); ?>');
								}
							}, "image/png");
						};
						img.src = URL.createObjectURL(blob);
					});
				});
			</script>
			<?php
			
			echo '<a href="' . esc_url( $back_url ) . '" class="button button-invert direktt-cross-sell-back button-dark-gray">' . esc_html__( 'Back', 'direktt-cross-sell' ) . '</a>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
			return ob_get_clean();
		}

		$partner = get_post( $partner_id );
		if ( ! $partner || $partner->post_type !== 'direkttcspartners' || $partner->post_status !== 'publish' ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid partner selected.', 'direktt-cross-sell' ) . '</p></div>';
			$back_url = remove_query_arg( array( 'direktt_action', 'coupon_id', 'cross_sell_use_flag', 'cross_sell_invalidate_flag', 'direktt_partner_id', 'partner_id', 'cross_sell_status_flag' ) );
			echo '<a href="' . esc_url( $back_url ) . '" class="button button-invert direktt-cross-sell-back button-dark-gray">' . esc_html__( 'Back to Cross-Sell', 'direktt-cross-sell' ) . '</a>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
			return;
		}

		echo '<h2>' . esc_html( $partner->post_title ) . '</h2>';

		$groups = direktt_cross_sell_get_partner_coupon_groups( $partner_id );

		if ( empty( $groups ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'No Coupon Groups for this partner.', 'direktt-cross-sell' ) . '</p></div>';
		} else {
			?>
			<ul class="direktt-cross-sell-partner-list">
				<?php
				foreach ( $groups as $group ) {
					$max_issue   = intval( get_post_meta( $group->ID, 'direktt_cross_sell_max_issuance', true ) );
					$issue_label = ( $max_issue == 0 )
						? esc_html__( 'Unlimited', 'direktt-cross-sell' )
						: $max_issue;
					?>
					<li>
						<div class="direktt-cross-sell-title-area">
							<h3 class="direktt-cross-sell-title"><?php echo esc_html( $group->post_title ); ?></h3>
							<p class="direktt-cross-sell-data"><?php echo esc_html__( 'Issued:', 'direktt-cross-sell' ); ?> <span><?php echo esc_html( direktt_cross_sell_get_issue_count( $group->ID, $partner_id ) ); ?></span> <?php echo esc_html( '/' ); ?> <strong><?php echo esc_html( $issue_label ); ?></strong></p>
						</div>
						<form method="post" action="" style="display:inline;" class="direktt-cs-issue-form">
							<input type="hidden" name="direktt_coupon_group_id" value="<?php echo esc_attr( $group->ID ); ?>">
							<input type="hidden" name="direktt_cs_issue_coupon_nonce" value="<?php echo esc_attr( wp_create_nonce( 'direktt_cs_issue_coupon_action' ) ); ?>">
							<input type="submit" name="direktt_cs_issue_coupon" class="button button-primary button-large" value="<?php echo esc_attr__( 'Issue', 'direktt-cross-sell' ); ?>">
						</form>
					</li>
					<?php
				}
				?>
			</ul>
			<?php
		}
		$url = remove_query_arg( array( 'coupon_id', 'direktt_partner_id', 'direktt_action' ) );
		echo '<a href="' . esc_url( $url ) . '" class="button button-invert direktt-cross-sell-back button-dark-gray">' . esc_html__( 'Back', 'direktt-cross-sell' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}

	ob_start();
	echo '<div id="direktt-profile-wrapper">';
	echo '<div id="direktt-profile">';
	echo '<div id="direktt-profile-data" class="direktt-profile-data-cross-sell-tool direktt-service">';
	echo wp_kses_post( direktt_cross_sell_my_coupons() );
	$partners          = direktt_cross_sell_get_partners();
	$eligible_partners = array();
	$category_ids      = Direktt_User::get_user_categories( $direktt_user['ID'] );
	$tag_ids           = Direktt_User::get_user_tags( $direktt_user['ID'] );
	if ( class_exists( 'Direktt_User' ) && Direktt_User::is_direktt_admin() ) {
		$eligible_partners = $partners;
	} else {
		if ( empty( $category_ids ) && empty( $tag_ids ) ) {
			$partners_with_cat_tag = array();
		} else {
			$meta_query = array( 'relation' => 'OR' );

			if ( ! empty( $category_ids ) ) {
				$meta_query[] = array(
					'key'     => 'direktt_cross_sell_partner_categories',
					'value'   => $category_ids,
					'compare' => 'IN',
				);
			}

			if ( ! empty( $tag_ids ) ) {
				$meta_query[] = array(
					'key'     => 'direktt_cross_sell_partner_tags',
					'value'   => $tag_ids,
					'compare' => 'IN',
				);
			}

			$args = array(
				'post_type'      => 'direkttcspartners',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- - Justification: bounded, cached, selective query on small dataset
			);

			$partners_with_cat_tag = get_posts( $args );
		}

		if ( ! empty( $partners_with_cat_tag ) ) {
			foreach ( $partners_with_cat_tag as $partner ) {
				// uzeti kats i tags od trenutnog usera
				// pogledati da li postoji partner sa tim kats i tags izabranim
				// u eligible partners se dodaje taj partner koji ima kats i tags + svi iz njegove liste partners for who can edit
				$eligible_partners[] = array(
					'ID'    => $partner->ID,
					'title' => $partner->post_title,
				);

				// Dodati sve partnere iz liste "partners for who can edit"
				$partners_for_edit = get_post_meta( $partner->ID, 'direktt_cross_sell_partners_for_who_can_edit', false );
				if ( ! empty( $partners_for_edit ) ) {
					foreach ( $partners_for_edit as $edit_partner_id ) {
						$edit_partner = get_post( $edit_partner_id );
						if ( $edit_partner ) {
							$eligible_partners[] = array(
								'ID'    => $edit_partner->ID,
								'title' => $edit_partner->post_title,
							);
						}
					}
				}
			}
		}
	}

	if ( ! empty( $eligible_partners ) ) {
		if ( ! is_array( $eligible_partners ) ) {
			$eligible_partners = array( $eligible_partners );
		} else {
			$eligible_partners = array_unique( $eligible_partners, SORT_REGULAR );
		}
		echo '<h2>' . esc_html__( 'Issue New Coupons', 'direktt-cross-sell' ) . '</h2>';
		echo '<ul>';

		foreach ( $eligible_partners as $eligible_partner ) {
			$coupons = direktt_cross_sell_get_partner_coupon_groups( $eligible_partner['ID'] );
			if ( empty( $coupons ) ) {
				continue;
			}
			$url = add_query_arg(
				array(
					'direktt_partner_id' => intval( $eligible_partner['ID'] ),
					'direktt_action'     => 'view_partner_coupons',
				)
			);
			echo '<li><a href="' . esc_url( $url ) . '" class="direktt-button button-large">' . esc_html( $eligible_partner['title'] ) . '</a></li>';
		}
		echo '</ul>';
	}
	echo '</div>';
	echo '</div>';
	echo '</div>';
	return ob_get_clean();
}

function direktt_cross_sell_on_issue_coupon( $request ) {
	global $direktt_user;
	$direktt_receiver_user_id = $direktt_user['direktt_user_id'] ?? '';
	$coupon_group_id          = intval( $request['coupon_group_id'] ?? 0 );
	$partner_id               = intval( $request['partner_id'] ?? 0 );
	$issuer_id                = sanitize_text_field( $request['issuer_id'] ?? '' );

	global $wpdb;

	$issued = false;

	// Get coupon group properties
	$group_validity = get_post_meta( $coupon_group_id, 'direktt_cross_sell_group_validity', true );   // days (0 = no expiry)

	$table = $wpdb->prefix . 'direktt_cross_sell_issued';

	if ( ! empty( $direktt_receiver_user_id ) && ! empty( $coupon_group_id ) && ! empty( $partner_id ) && ! empty( $issuer_id ) ) {

		// coupon logic (old code, as before)
		$coupon_expires = null;
		if ( ! empty( $group_validity ) && intval( $group_validity ) > 0 ) {
			$now            = current_time( 'mysql' );
			$coupon_expires = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' + ' . intval( $group_validity ) . ' days' ) );
		}

		$max_issuance = get_post_meta( intval( $coupon_group_id ), 'direktt_cross_sell_max_issuance', true );
		$max_issuance = (string) $max_issuance === '' ? 0 : intval( $max_issuance );

		$table        = $wpdb->prefix . 'direktt_cross_sell_issued';
		$issued_count = direktt_cross_sell_get_issue_count( $coupon_group_id, $partner_id );

		if ( $max_issuance != 0 && $issued_count >= $max_issuance ) {
			$issued = 3;
		} else {

			$coupon_guid = wp_generate_uuid4();

			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database insert is required here to add a record to a custom plugin table; $wpdb->insert() is the official safe WordPress method using prepared statements and proper data escaping.
				$table,
				array(
					'partner_id'               => $partner_id,
					'coupon_group_id'          => $coupon_group_id,
					'direktt_issuer_user_id'   => $direktt_user['direktt_user_id'],
					'direktt_receiver_user_id' => $direktt_receiver_user_id,
					'coupon_expires'           => $coupon_expires,
					'coupon_guid'              => $coupon_guid,
					// coupon_time: DB default
				),
				array(
					'%d', // partner_id
					'%d', // group_id
					'%s', // issuer
					'%s', // receiver
					is_null( $coupon_expires ) ? 'NULL' : '%s',
					'%s',  // guid
				)
			);
			if ( (bool) $inserted ) {
				$issued = 1;
				// TODO Send Issuance messages
			} else {
				$issued = 2;
			}
		}
	}

	$data = array(
		'message' => esc_html__( 'Coupon issued successfully.', 'direktt-cross-sell' ),
	);
	wp_send_json_success( $data, 200 );
}

function add_direktt_cross_sell_coupon_cpt_column( $columns ) {
	$new_columns = array();
	foreach ( $columns as $key => $value ) {
		$new_columns[ $key ] = $value;
		if ( 'title' === $key ) {
			$new_columns['cross_sell_partner'] = esc_html__( 'Cross Sell Partner', 'direktt-cross-sell' );
		}
	}
	return $new_columns;
}

function display_direktt_cross_sell_coupon_cpt_column( $column, $post_id ) {
	if ( $column === 'cross_sell_partner' ) {
		$cross_sell_partner = get_post_meta( $post_id, 'direktt_cross_sell_partner_for_coupon_group', true );

		if ( $cross_sell_partner ) {
			$partner_name = get_the_title( $cross_sell_partner );
			echo esc_html( $partner_name );
		} else {
			echo esc_html__( 'No partner assigned', 'direktt-cross-sell' );
		}
	}
}

function direktt_cross_sell_partner_column_make_sortable( $columns ) {
	$columns['cross_sell_partner'] = 'cross_sell_partner';
	return $columns;
}

function direktt_cross_sell_sort_partner_column( $query ) {
	if ( ! is_admin() ) {
		return;
	}

	if ( 'cross_sell_partner' === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', 'direktt_cross_sell_partner_for_coupon_group' );
		$query->set( 'orderby', 'meta_value' );
	}
}

function direktt_cross_sell_highlight_submenu( $parent_file ) {
	global $submenu_file, $current_screen, $pagenow;

	if ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) {
		if ( $current_screen->post_type === 'direkttcspartners' ) {
			$submenu_file  = 'edit.php?post_type=direkttcspartners';
			$parent_file = 'direktt-dashboard';
		} elseif ( $current_screen->post_type === 'direkttcscoupon' ) {
			$submenu_file  = 'edit.php?post_type=direkttcscoupon';
			$parent_file = 'direktt-dashboard';
		}
	}

	return $parent_file;
}