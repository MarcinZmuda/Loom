<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Loom_Metabox {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$settings = Loom_DB::get_settings();
		foreach ( ( $settings['post_types'] ?? array( 'post', 'page' ) ) as $type ) {
			add_meta_box( 'loom-metabox', '<span style="color:#0d9488">🔗</span> LOOM', array( __CLASS__, 'render' ), $type, 'normal', 'high' );
		}
	}

	public static function render( $post ) {
		$has_key  = ! empty( Loom_DB::get_api_key() );
		$row      = Loom_DB::get_index_row( $post->ID );
		$in       = $row ? intval( $row['incoming_links_count'] ) : 0;
		$out      = $row ? intval( $row['outgoing_links_count'] ) : 0;
		$depth    = ( $row && $row['click_depth'] !== null ) ? intval( $row['click_depth'] ) : null;
		$word_ct  = $row ? intval( $row['word_count'] ) : 0;
		$has_emb  = $row && ! empty( $row['embedding'] );

		// PageRank percentile.
		$pr       = $row ? floatval( $row['internal_pagerank'] ?? 0 ) : 0;
		$pr_pctl  = '';
		if ( $pr > 0 && $row ) {
			global $wpdb;
			$idx    = Loom_DB::index_table();
			$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$idx} WHERE internal_pagerank IS NOT NULL" );
			$higher = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$idx} WHERE internal_pagerank > %f", $pr ) );
			$pr_pctl = $total > 0 ? round( ( 1 - $higher / $total ) * 100 ) : '';
		}

		// GSC data.
		$gsc_pos   = $row ? floatval( $row['gsc_position'] ?? 0 ) : 0;
		$gsc_impr  = $row ? intval( $row['gsc_impressions'] ?? 0 ) : 0;
		$gsc_ctr   = $row ? floatval( $row['gsc_ctr'] ?? 0 ) : 0;
		$gsc_clicks = $row ? intval( $row['gsc_clicks'] ?? 0 ) : 0;
		$striking  = $row && ! empty( $row['is_striking_distance'] );
		$is_mp     = $row && ! empty( $row['is_money_page'] );

		// Status.
		if ( ! $row )                { $st_cls = 'neutral'; $st_txt = __( 'Nieskanowany', 'loom' ); }
		elseif ( $row['is_orphan'] ) { $st_cls = 'bad';     $st_txt = __( 'Orphan', 'loom' ); }
		elseif ( $in < 3 )           { $st_cls = 'warn';    $st_txt = __( 'Słaby', 'loom' ); }
		else                         { $st_cls = 'ok';      $st_txt = __( 'OK', 'loom' ); }

		if ( $word_ct < 500 ) $ideal = '3–5'; elseif ( $word_ct < 1500 ) $ideal = '5–7'; else $ideal = '7–10';

		$links_out = $row ? Loom_DB::get_links_from( $post->ID ) : array();
		$links_in  = $row ? Loom_DB::get_links_to( $post->ID ) : array();
		?>
		<div class="loom-metabox-inner" data-post-id="<?php echo esc_attr( $post->ID ); ?>">

		<!-- Metrics grid -->
		<div class="loom-mb-metrics">
			<div class="loom-mb-cell"><div class="loom-mb-val"><?php echo esc_html( $in ); ?></div><div class="loom-mb-lbl">IN</div></div>
			<div class="loom-mb-cell"><div class="loom-mb-val"><?php echo esc_html( $out ); ?></div><div class="loom-mb-lbl">OUT</div></div>
			<div class="loom-mb-cell"><div class="loom-mb-val"><?php echo esc_html( $depth !== null ? $depth : ' - ' ); ?></div><div class="loom-mb-lbl">Depth</div></div>
			<div class="loom-mb-cell"><div class="loom-mb-val" style="font-size:14px"><?php echo $pr > 0 ? esc_html( number_format( $pr * 100, 1 ) ) : ' - '; ?></div><div class="loom-mb-lbl">PR<?php echo $pr_pctl ? ' (top ' . esc_html( $pr_pctl ) . '%)' : ''; ?></div></div>
			<?php if ( $gsc_pos > 0 ) : ?>
			<div class="loom-mb-cell"><div class="loom-mb-val" style="color:<?php echo $gsc_pos <= 10 ? 'var(--ok)' : ( $gsc_pos <= 20 ? 'var(--purple)' : '' ); ?>"><?php echo esc_html( number_format( $gsc_pos, 1 ) ); ?></div><div class="loom-mb-lbl">GSC Pos</div></div>
			<div class="loom-mb-cell"><div class="loom-mb-val" style="font-size:14px"><?php echo esc_html( number_format( $gsc_impr ) ); ?></div><div class="loom-mb-lbl">Impr/28d</div></div>
			<div class="loom-mb-cell"><div class="loom-mb-val" style="font-size:14px"><?php echo esc_html( number_format( $gsc_ctr * 100, 1 ) ); ?>%</div><div class="loom-mb-lbl">CTR</div></div>
			<div class="loom-mb-cell"><div class="loom-mb-val"><?php echo esc_html( $gsc_clicks ); ?></div><div class="loom-mb-lbl">Clicks</div></div>
			<?php endif; ?>
			<div class="loom-mb-cell">
				<span class="loom-badge loom-b-<?php echo esc_attr( $st_cls ); ?>"><?php echo esc_html( $st_txt ); ?></span>
				<?php if ( $striking ) : ?><br><span class="loom-badge loom-b-striking" style="margin-top:3px">🎯 Striking</span><?php endif; ?>
				<?php if ( $is_mp ) : ?><br><span class="loom-badge loom-b-money" style="margin-top:3px">⭐ Money</span><?php endif; ?>
			</div>
		</div>

		<?php // Keywords
		$kw_data = ! empty( $row['focus_keywords'] ) ? json_decode( $row['focus_keywords'], true ) : array();
		if ( ! empty( $kw_data ) ) : ?>
		<div style="margin-bottom:10px">
			<small class="loom-muted" style="font-size:10px;font-weight:600">🎯 KEYWORDS</small>
			<div class="loom-kw-list">
				<?php foreach ( $kw_data as $kw ) : ?>
				<span class="loom-kw-badge loom-kw-<?php echo esc_attr( $kw['source'] ?? 'title' ); ?>">
					<?php echo $kw['type'] === 'primary' ? '★ ' : ''; ?><?php echo esc_html( $kw['phrase'] ); ?>
					<span style="opacity:.5;font-size:9px;margin-left:2px"><?php echo esc_html( $kw['source'] ?? '' ); ?></span>
				</span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php // Anchor distribution (for incoming links)
		if ( $row && $in > 0 ) :
			$anchor_dist = Loom_DB::get_anchor_distribution( $post->ID );
			if ( ! empty( $anchor_dist ) ) : ?>
		<div style="margin-bottom:10px">
			<small class="loom-muted" style="font-size:10px;font-weight:600">🔗 ROZKŁAD ANCHORÓW IN</small>
			<div style="margin-top:4px">
				<?php foreach ( array_slice( $anchor_dist, 0, 5 ) as $ad ) :
					$pct = intval( $ad['percent'] );
					$bar_cls = $pct >= 40 ? 'loom-progress-fill-bad' : ( $pct >= 25 ? 'loom-progress-fill-warn' : '' );
				?>
				<div class="loom-anchor-row">
					<span class="loom-anchor-text"><?php echo esc_html( mb_substr( $ad['anchor'], 0, 25 ) ); ?></span>
					<div class="loom-anchor-bar"><div class="loom-progress" style="height:4px"><div class="loom-progress-fill <?php echo esc_attr( $bar_cls ); ?>" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div></div>
					<span class="loom-anchor-pct"><?php echo esc_html( $ad['count'] ); ?>x (<?php echo esc_html( $pct ); ?>%)</span>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php // Actions ?>
		<?php if ( $has_key ) : ?>
		<div class="loom-mb-actions">
			<button type="button" class="button button-primary loom-btn-main" id="loom-metabox-podlinkuj">🔗 <?php esc_html_e( 'Podlinkuj', 'loom' ); ?></button>
			<button type="button" class="button loom-btn-auto" id="loom-metabox-auto" title="<?php esc_attr_e( 'Auto: high+medium', 'loom' ); ?>">⚡ Auto</button>
			<span class="spinner" id="loom-metabox-spinner"></span>
		</div>
		<?php else : ?>
		<div class="loom-mb-nokey">⚠️ <?php esc_html_e( 'Dodaj klucz API w', 'loom' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=loom-settings' ) ); ?>"><?php esc_html_e( 'ustawieniach', 'loom' ); ?></a></div>
		<?php endif; ?>

		<?php // Outgoing links ?>
		<?php if ( ! empty( $links_out ) ) : ?>
		<details class="loom-mb-section" open>
			<summary class="loom-mb-stitle">↗️ <?php printf( esc_html__( 'Linki OUT (%d)', 'loom' ), count( $links_out ) ); ?></summary>
			<table class="loom-mb-table"><thead><tr>
				<th>Anchor</th><th>Target</th><th>Pozycja</th><th>Źródło</th><th>Match</th><th>Stan</th>
			</tr></thead><tbody>
			<?php foreach ( $links_out as $l ) :
				$tp    = $l['target_post_id'] > 0 ? get_post( $l['target_post_id'] ) : null;
				$tname = $tp ? mb_substr( $tp->post_title, 0, 30 ) : __( '(zewn.)', 'loom' );
				$pos_map = array( 'top' => '⭐⭐⭐', 'middle' => '⭐⭐', 'bottom' => '⭐' );
				$pos   = $pos_map[ $l['link_position'] ] ?? $l['link_position'];
				$src   = $l['is_plugin_generated'] ? '<span class="loom-badge loom-b-loom">LOOM</span>' : '<span class="loom-badge loom-b-neutral">Ręczny</span>';
				$hp    = $l['is_broken'] ? '<span class="loom-badge loom-b-bad">❌</span>' : ( $l['is_nofollow'] ? '<span class="loom-badge loom-b-warn">NF</span>' : '<span class="loom-badge loom-b-ok">OK</span>' );
				$ms    = '';
				if ( $l['anchor_match_score'] !== null ) {
					$mval = floatval( $l['anchor_match_score'] );
					$mc = $mval >= 0.6 ? 'ok' : ( $mval >= 0.4 ? 'warn' : 'bad' );
					$ms = '<span class="loom-badge loom-b-' . $mc . '">' . number_format( $mval, 2 ) . '</span>';
				}
			?>
			<tr>
				<td><span class="loom-code"><?php echo esc_html( mb_substr( $l['anchor_text'], 0, 30 ) ); ?></span></td>
				<td><?php if ( $tp ) : ?><a href="<?php echo esc_url( get_edit_post_link( $tp->ID ) ); ?>" class="loom-link"><?php echo esc_html( $tname ); ?></a><?php else : echo esc_html( $tname ); endif; ?></td>
				<td style="white-space:nowrap"><?php echo esc_html( $pos ); ?></td>
				<td><?php echo wp_kses_post( $src ); ?></td>
				<td><?php echo $ms ? wp_kses_post( $ms ) : ' - '; ?></td>
				<td><?php echo wp_kses_post( $hp ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody></table>
		</details>
		<?php endif; ?>

		<?php // Incoming links ?>
		<?php if ( ! empty( $links_in ) ) : ?>
		<details class="loom-mb-section">
			<summary class="loom-mb-stitle">↙️ <?php printf( esc_html__( 'Linki IN (%d)', 'loom' ), count( $links_in ) ); ?></summary>
			<table class="loom-mb-table"><thead><tr>
				<th>Ze strony</th><th>Anchor</th><th>Pozycja</th><th>Źródło</th>
			</tr></thead><tbody>
			<?php foreach ( $links_in as $l ) :
				$sp    = $l['source_post_id'] > 0 ? get_post( $l['source_post_id'] ) : null;
				$sname = $sp ? mb_substr( $sp->post_title, 0, 30 ) : '(?)';
				$pos_map = array( 'top' => '⭐⭐⭐', 'middle' => '⭐⭐', 'bottom' => '⭐' );
				$pos   = $pos_map[ $l['link_position'] ] ?? '';
				$src   = $l['is_plugin_generated'] ? '<span class="loom-badge loom-b-loom">LOOM</span>' : '<span class="loom-badge loom-b-neutral">Ręczny</span>';
			?>
			<tr>
				<td><?php if ( $sp ) : ?><a href="<?php echo esc_url( get_edit_post_link( $sp->ID ) ); ?>" class="loom-link"><?php echo esc_html( $sname ); ?></a><?php else : echo esc_html( $sname ); endif; ?></td>
				<td><span class="loom-code"><?php echo esc_html( mb_substr( $l['anchor_text'], 0, 30 ) ); ?></span></td>
				<td style="white-space:nowrap"><?php echo esc_html( $pos ); ?></td>
				<td><?php echo wp_kses_post( $src ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody></table>
		</details>
		<?php endif; ?>

		<?php if ( $row && empty( $links_out ) && empty( $links_in ) ) : ?>
		<p class="loom-muted" style="text-align:center;padding:12px 0;font-size:12px">
			<?php esc_html_e( 'Brak linków wewnętrznych. Kliknij „Podlinkuj".', 'loom' ); ?>
		</p>
		<?php endif; ?>

		<div id="loom-metabox-results" style="display:none;"></div>
		</div>
		<?php
	}
}
