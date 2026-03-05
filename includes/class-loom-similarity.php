<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Loom_Similarity  -  Vector similarity engine for LOOM.
 *
 * Optimizations vs naive cosine:
 * - Stage 1 (64D prefix): full cosine (prefix is NOT unit-normalized after truncation)
 * - Stage 2 (512D full):  dot product only (OpenAI embeddings ARE unit-normalized)
 * - JSON decode cache: each embedding parsed once per request
 * - Minimum similarity threshold from settings (dead results filtered out)
 */
class Loom_Similarity {

	/** @var array<string, array> Static cache: JSON string -> decoded array. */
	private static $decode_cache = array();

	/* ================================================================
	   SIMILARITY METRICS
	   ================================================================ */

	/**
	 * Cosine similarity  -  for NON-normalized vectors (e.g. truncated prefixes).
	 *
	 * Formula: dot(A,B) / (|A| × |B|)
	 * Use when vectors may not be unit length (truncated Matryoshka prefixes).
	 *
	 * @param array    $a    Vector A.
	 * @param array    $b    Vector B.
	 * @param int|null $dims Dimensions to use (null = all).
	 * @return float         Similarity in [-1, 1].
	 */
	public static function cosine( $a, $b, $dims = null ) {
		$dot  = 0.0;
		$magA = 0.0;
		$magB = 0.0;
		$len  = $dims ?? min( count( $a ), count( $b ) );

		for ( $i = 0; $i < $len; $i++ ) {
			$va    = $a[ $i ] ?? 0;
			$vb    = $b[ $i ] ?? 0;
			$dot  += $va * $vb;
			$magA += $va * $va;
			$magB += $vb * $vb;
		}

		$magA = sqrt( $magA );
		$magB = sqrt( $magB );

		if ( $magA == 0 || $magB == 0 ) {
			return 0.0;
		}

		return $dot / ( $magA * $magB );
	}

	/**
	 * Dot product  -  for UNIT-NORMALIZED vectors (OpenAI embeddings with `dimensions` param).
	 *
	 * When |A| = |B| = 1.0: dot(A,B) = cosine(A,B).
	 * Skips 2× sqrt() + 2N× multiply + 1× divide = ~40% faster.
	 *
	 * @param array    $a    Unit-normalized vector A.
	 * @param array    $b    Unit-normalized vector B.
	 * @param int|null $dims Dimensions to use (null = all).
	 * @return float         Similarity in [-1, 1].
	 */
	public static function dot_product( $a, $b, $dims = null ) {
		$dot = 0.0;
		$len = $dims ?? min( count( $a ), count( $b ) );

		for ( $i = 0; $i < $len; $i++ ) {
			$dot += ( $a[ $i ] ?? 0 ) * ( $b[ $i ] ?? 0 );
		}

		return $dot;
	}

	/* ================================================================
	   TWO-STAGE SIMILARITY SEARCH
	   ================================================================ */

	/**
	 * Two-stage Matryoshka similarity search.
	 *
	 * Stage 1: Fast pre-filter on 64D prefix using cosine (prefix not unit-normalized).
	 * Stage 2: Precise ranking on full 512D using dot product (unit-normalized by OpenAI).
	 *
	 * Applies minimum similarity threshold from settings to filter irrelevant results.
	 *
	 * @param array $source_embedding  512D unit-normalized vector.
	 * @param array $all_targets       Rows from loom_index with 'embedding' JSON.
	 * @param int   $prefilter_top     Candidates to keep after stage 1.
	 * @param int   $final_top         Final results to return.
	 * @return array                   Targets with 'cosine_similarity', sorted desc.
	 */
	public static function find_similar( $source_embedding, $all_targets, $prefilter_top = 50, $final_top = 15 ) {

		// Get minimum similarity threshold from settings (R2).
		$settings      = Loom_DB::get_settings();
		$min_threshold = floatval( $settings['min_similarity'] ?? 0.3 );

		$candidates = array();

		// ── Stage 1: fast pre-filter with first 64 dimensions ──
		// 64D prefix is NOT unit-normalized (truncated from 512D unit vector).
		// Must use full cosine formula (divides by magnitudes).
		foreach ( $all_targets as $target ) {
			$emb = self::decode_embedding( $target['embedding'] ?? '' );
			if ( empty( $emb ) ) {
				continue;
			}

			$quick_sim = self::cosine( $source_embedding, $emb, 64 );

			// Early rejection: if 64D similarity is very low, skip entirely.
			// 64D cosine is a rough estimate  -  use threshold * 0.6 as generous cutoff.
			if ( $quick_sim < $min_threshold * 0.5 ) {
				continue;
			}

			$candidates[] = array(
				'data'      => $target,
				'embedding' => $emb,
				'quick_sim' => $quick_sim,
			);
		}

		// Release original targets to free RAM.
		unset( $all_targets );

		// Sort by quick similarity, keep top N.
		usort( $candidates, function ( $a, $b ) {
			return $b['quick_sim'] <=> $a['quick_sim'];
		} );
		$top_candidates = array_slice( $candidates, 0, $prefilter_top );
		unset( $candidates );

		// ── Stage 2: precise similarity on full 512D ──
		// Full 512D vectors ARE unit-normalized by OpenAI API.
		// Use dot_product (= cosine for unit vectors, but ~40% faster).
		$results = array();
		foreach ( $top_candidates as $c ) {
			$full_sim = self::dot_product( $source_embedding, $c['embedding'], 512 );
			unset( $c['embedding'] ); // Free 4KB per candidate.

			// Apply minimum threshold (R2): reject irrelevant matches.
			if ( $full_sim < $min_threshold ) {
				continue;
			}

			$row = $c['data'];
			$row['cosine_similarity'] = round( $full_sim, 4 );
			unset( $row['embedding'] ); // Don't carry raw embedding forward.
			$results[] = $row;
		}
		unset( $top_candidates );

		// Sort by full similarity descending.
		usort( $results, function ( $a, $b ) {
			return $b['cosine_similarity'] <=> $a['cosine_similarity'];
		} );

		return array_slice( $results, 0, $final_top );
	}

	/* ================================================================
	   HELPERS
	   ================================================================ */

	/**
	 * Decode embedding JSON with per-request static cache (R4).
	 *
	 * Avoids parsing the same 4KB JSON string multiple times per request.
	 *
	 * @param string|array $raw JSON string or already decoded array.
	 * @return array|null       Decoded vector or null on failure.
	 */
	private static function decode_embedding( $raw ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || $raw === '' ) {
			return null;
		}

		// Cache key: first 64 chars is enough to uniquely identify (embeddings differ from char 1).
		$cache_key = substr( $raw, 0, 64 );
		if ( isset( self::$decode_cache[ $cache_key ] ) ) {
			return self::$decode_cache[ $cache_key ];
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return null;
		}

		self::$decode_cache[ $cache_key ] = $decoded;
		return $decoded;
	}

	/**
	 * L2-normalize a vector to unit length.
	 *
	 * Useful for re-normalizing truncated Matryoshka prefixes.
	 *
	 * @param array $vector Input vector.
	 * @return array         Unit-normalized vector.
	 */
	public static function l2_normalize( $vector ) {
		$sum = 0.0;
		foreach ( $vector as $v ) {
			$sum += $v * $v;
		}
		$norm = sqrt( $sum );
		if ( $norm == 0 ) {
			return $vector;
		}
		return array_map( function ( $v ) use ( $norm ) {
			return $v / $norm;
		}, $vector );
	}

	/**
	 * Reset decode cache (call between batch operations if needed).
	 */
	public static function reset_cache() {
		self::$decode_cache = array();
	}

	/**
	 * Public wrapper for decode_embedding (needed by paragraph matching).
	 *
	 * @param string|array $raw JSON string or array.
	 * @return array|null
	 */
	public static function decode_embedding_public( $raw ) {
		return self::decode_embedding( $raw );
	}
}
