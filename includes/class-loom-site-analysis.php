<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Loom_Site_Analysis {

	public static function calculate_click_depths() {
		global $wpdb;
		$idx = Loom_DB::index_table();
		$lnk = Loom_DB::links_table();
		$homepage_id = intval( get_option( 'page_on_front', 0 ) );
		$wpdb->query( "UPDATE {$idx} SET click_depth = NULL" );
		if ( ! $homepage_id ) $homepage_id = intval( get_option( 'page_for_posts', 0 ) );
		if ( $homepage_id ) $wpdb->update( $idx, array( 'click_depth' => 0 ), array( 'post_id' => $homepage_id ) );

		for ( $depth = 0; $depth < 10; $depth++ ) {
			$affected = $wpdb->query( $wpdb->prepare(
				"UPDATE {$idx} i
				 JOIN {$lnk} l ON l.target_post_id = i.post_id
				 JOIN {$idx} src ON l.source_post_id = src.post_id AND src.click_depth = %d
				 SET i.click_depth = %d
				 WHERE i.click_depth IS NULL",
				$depth, $depth + 1
			) );
			if ( $affected === 0 ) break;
		}
		self::assign_tiers();
	}

	private static function assign_tiers() {
		global $wpdb;
		$idx = Loom_DB::index_table();
		$wpdb->query( "UPDATE {$idx} SET site_tier = 3" );
		$hid = intval( get_option( 'page_on_front', 0 ) );
		if ( $hid ) $wpdb->update( $idx, array( 'site_tier' => 0 ), array( 'post_id' => $hid ) );
		$wpdb->query( "UPDATE {$idx} SET site_tier = 1 WHERE click_depth = 1 AND post_type = 'page'" );
		$wpdb->query( "UPDATE {$idx} SET site_tier = 2 WHERE site_tier = 3 AND click_depth <= 2 AND incoming_links_count >= 8" );
	}

	public static function check_cannibalization( $anchor_text, $intended_target_id ) {
		global $wpdb;
		$lnk = Loom_DB::links_table();
		$idx = Loom_DB::index_table();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT l.target_post_id, i.post_title, COUNT(*) AS link_count
			 FROM {$lnk} l JOIN {$idx} i ON l.target_post_id = i.post_id
			 WHERE l.anchor_text = %s AND l.target_post_id != %d AND l.target_post_id > 0
			 GROUP BY l.target_post_id ORDER BY link_count DESC LIMIT 1",
			$anchor_text, $intended_target_id
		), ARRAY_A );
	}

	public static function anchor_distribution( $target_post_id ) {
		global $wpdb;
		$anchors = $wpdb->get_results( $wpdb->prepare(
			"SELECT anchor_text, COUNT(*) AS cnt
			 FROM " . Loom_DB::links_table() . "
			 WHERE target_post_id = %d AND anchor_text != ''
			 GROUP BY anchor_text ORDER BY cnt DESC",
			$target_post_id
		), ARRAY_A );
		$total = array_sum( array_column( $anchors, 'cnt' ) );
		$warnings = array();
		if ( ! empty( $anchors ) && $total >= 3 ) {
			$top_pct = round( ( intval( $anchors[0]['cnt'] ) / $total ) * 100 );
			if ( $top_pct > 50 ) $warnings[] = 'dominant';
		}
		return array( 'total' => $total, 'anchors' => $anchors, 'warnings' => $warnings );
	}

	public static function format_for_prompt( $target_post_id ) {
		$out = '';
		$target_post_id = intval( $target_post_id );
		if ( $target_post_id <= 0 ) return $out;

		$row = Loom_DB::get_index_row( $target_post_id );

		// Keywords.
		if ( ! empty( $row['focus_keywords'] ) ) {
			$kws = json_decode( $row['focus_keywords'], true );
			if ( is_array( $kws ) && ! empty( $kws ) ) {
				$labels = array();
				foreach ( $kws as $k ) {
					$labels[] = '"' . $k['phrase'] . '" (' . $k['type'] . ')';
				}
				$out .= '   🎯 Keywords: ' . implode( ', ', $labels ) . "\n";
			}
		}

		// Anchors.
		$dist = self::anchor_distribution( $target_post_id );
		if ( $dist['total'] > 0 ) {
			$parts = array();
			foreach ( array_slice( $dist['anchors'], 0, 4 ) as $a ) {
				$parts[] = '"' . $a['anchor_text'] . '" (' . $a['cnt'] . 'x)';
			}
			$out .= '   🔗 Existing anchors: ' . implode( ', ', $parts ) . "\n";
			if ( in_array( 'dominant', $dist['warnings'], true ) ) {
				$out .= "   ⚠️ Over-optimized: dominant anchor  -  use different variant\n";
			}
		}

		return $out;
	}

	// ── Rejections ──

	public static function reject_suggestion( $post_id, $target_post_id, $anchor_text ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'loom_rejections', array(
			'post_id'        => absint( $post_id ),
			'target_post_id' => absint( $target_post_id ),
			'anchor_text'    => sanitize_text_field( $anchor_text ),
			'rejected_at'    => current_time( 'mysql' ),
		) );
	}

	public static function get_rejected_targets( $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'loom_rejections';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			return array(); // Table doesn't exist yet.
		}
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT target_post_id FROM {$table}
			 WHERE post_id = %d GROUP BY target_post_id HAVING COUNT(*) >= 3",
			$post_id
		) );
	}

	public static function is_rejected( $post_id, $target_post_id, $anchor_text ) {
		global $wpdb;
		$table = $wpdb->prefix . 'loom_rejections';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			return false;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table}
			 WHERE post_id = %d AND target_post_id = %d AND anchor_text = %s",
			$post_id, $target_post_id, $anchor_text
		) );
	}

	public static function ajax_reject() {
		check_ajax_referer( 'loom_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Forbidden', 403 );
		$post_id   = absint( $_POST['post_id'] ?? 0 );
		$target_id = absint( $_POST['target_id'] ?? 0 );
		$anchor    = sanitize_text_field( $_POST['anchor_text'] ?? '' );
		if ( $post_id && $target_id ) {
			self::reject_suggestion( $post_id, $target_id, $anchor );
		}
		wp_send_json_success();
	}

	public static function ajax_recalc_depth() {
		check_ajax_referer( 'loom_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
		self::calculate_click_depths();
		wp_send_json_success( __( 'Click depth przeliczony.', 'loom' ) );
	}
}
