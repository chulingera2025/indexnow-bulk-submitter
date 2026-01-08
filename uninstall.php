<?php
/**
 * 插件卸载清理
 *
 * @package IndexNow_Bulk_Submitter
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_indexnow_bulk_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_indexnow_bulk_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_indexnow_rate_limit_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_indexnow_rate_limit_%'" );