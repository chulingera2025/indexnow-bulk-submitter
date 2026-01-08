<?php
/**
 * Plugin Name:       IndexNow Bulk Submitter
 * Plugin URI:        https://github.com/chulingera2025/indexnow-bulk-submitter
 * Description:       批量提交sitemap中的URL到IndexNow，适用于已安装IndexNow插件但需要提交历史文章的场景
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Author:            chulingera2025
 * Author URI:        https://github.com/chulingera2025
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       indexnow-bulk-submitter
 * Domain Path:       /languages
 * Requires Plugins:  indexnow
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'INDEXNOW_BULK_SUBMITTER_VERSION', '1.0.0' );
define( 'INDEXNOW_BULK_SUBMITTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once INDEXNOW_BULK_SUBMITTER_PLUGIN_DIR . 'includes/class-sitemap-parser.php';
require_once INDEXNOW_BULK_SUBMITTER_PLUGIN_DIR . 'admin/class-admin-page.php';

function indexnow_bulk_submitter_init() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! is_plugin_active( 'indexnow/indexnow-url-submission.php' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="error"><p>';
			echo esc_html__( '警告: IndexNow Bulk Submitter 需要 IndexNow 插件才能正常工作。请先安装并激活 IndexNow 插件。', 'indexnow-bulk-submitter' );
			echo '</p></div>';
		} );
		return;
	}

	if ( is_admin() ) {
		new IndexNow_Bulk_Submitter_Admin();
	}
}
add_action( 'plugins_loaded', 'indexnow_bulk_submitter_init' );

function indexnow_bulk_submitter_check_dependencies() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! is_plugin_active( 'indexnow/indexnow-url-submission.php' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			__( '此插件需要先安装并激活 IndexNow 插件才能使用。', 'indexnow-bulk-submitter' ),
			__( '插件依赖错误', 'indexnow-bulk-submitter' ),
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, 'indexnow_bulk_submitter_check_dependencies' );
