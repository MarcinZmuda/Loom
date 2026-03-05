<?php
/**
 * LOOM Uninstall  -  by Marcin Żmuda
 *
 * Removes all plugin data when user clicks "Delete" in plugin manager.
 * Links inserted into post_content are NOT removed (they're clean HTML).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}loom_index" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}loom_links" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}loom_log" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}loom_clusters" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}loom_rejections" );

// 2. Delete options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'loom\_%'" );

// 3. Delete post meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_loom\_%'" );

// 4. Delete user meta.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\_loom\_%'" );

// 5. Delete transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_loom\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_loom\_%'" );
