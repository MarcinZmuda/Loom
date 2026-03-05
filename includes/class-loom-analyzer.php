<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Loom_Analyzer {

	/**
	 * Enrich validated suggestions with Reasonable Surfer score and position data.
	 *
	 * @param array $suggestions Validated suggestions from Suggester.
	 * @param int   $total_paras Total paragraph count in the article.
	 * @return array             Enriched suggestions.
	 */
	public static function enrich_suggestions( $suggestions, $total_paras ) {
		foreach ( $suggestions as &$s ) {
			$para_num = intval( $s['paragraph_number'] );
			$percent  = $total_paras > 0
				? round( ( $para_num / $total_paras ) * 100 )
				: 50;

			// Reasonable Surfer zone.
			if ( $percent <= 30 ) {
				$zone  = 'top';
				$stars = 3;
				$label = 'Góra strony ⭐⭐⭐';
			} elseif ( $percent <= 70 ) {
				$zone  = 'middle';
				$stars = 2;
				$label = 'Środek ⭐⭐';
			} else {
				$zone  = 'bottom';
				$stars = 1;
				$label = 'Dół strony ⭐';
			}

			$s['surfer_zone']      = $zone;
			$s['surfer_stars']     = $stars;
			$s['surfer_label']     = $label;
			$s['position_percent'] = $percent;
		}
		unset( $s );

		return $suggestions;
	}

	/**
	 * Calculate anchor mismatch score between anchor text and target page.
	 * Uses embedding similarity.
	 *
	 * @param string $anchor_text
	 * @param string $target_text  Target title + first 200 words.
	 * @param string $api_key
	 * @return float|null          0.0–1.0 or null on failure.
	 */
	public static function anchor_mismatch_score( $anchor_text, $target_text, $api_key = '' ) {
		$anchor_emb = Loom_OpenAI::get_embedding( $anchor_text, $api_key );
		if ( is_wp_error( $anchor_emb ) ) return null;

		$target_emb = Loom_OpenAI::get_embedding( $target_text, $api_key );
		if ( is_wp_error( $target_emb ) ) return null;

		return round( Loom_Similarity::cosine( $anchor_emb, $target_emb ), 2 );
	}

	/**
	 * Get mismatch label and CSS class.
	 */
	public static function mismatch_label( $score ) {
		if ( $score === null ) {
			return array( 'label' => ' - ', 'class' => 'loom-match-unknown' );
		}
		if ( $score >= 0.6 ) {
			return array( 'label' => '✅ Spójny (' . $score . ')', 'class' => 'loom-match-ok' );
		}
		if ( $score >= 0.4 ) {
			return array( 'label' => '⚠️ Ostrożnie (' . $score . ')', 'class' => 'loom-match-warn' );
		}
		return array( 'label' => '❌ Niezgodny (' . $score . ')', 'class' => 'loom-match-bad' );
	}
}
