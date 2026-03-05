<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Loom_Bulk {

	public static function init() {}

	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'loom' ) );
		}

		$has_key = ! empty( Loom_DB::get_api_key() );
		$posts   = Loom_DB::get_all_index_rows();
		$filter  = sanitize_text_field( $_GET['filter'] ?? '' );
		?>
		<div class="wrap loom-wrap">
			<div class="loom-header">
				<div class="loom-logo">
					<svg viewBox="0 0 32 32" class="loom-logo-svg"><circle cx="16" cy="16" r="14" fill="none" stroke="#008080" stroke-width="2"/><path d="M10 10h4v12h-4zM16 16a6 6 0 1 1 0 .1" fill="none" stroke="#008080" stroke-width="2" stroke-linecap="round"/></svg>
					<span class="loom-logo-text">LOOM</span>
				</div>
				<span class="loom-subtitle"><?php esc_html_e( 'Bulk Mode', 'loom' ); ?></span>
			</div>

			<?php if ( ! $has_key ) : ?>
				<div class="loom-card loom-card-cta">
					<p>⚠️ <?php esc_html_e( 'Dodaj klucz API w ustawieniach.', 'loom' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=loom-settings' ) ); ?>" class="button"><?php esc_html_e( 'Ustawienia ->', 'loom' ); ?></a></p>
				</div>
			<?php endif; ?>

			<?php if ( $has_key ) : ?>
			<div class="loom-card" style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
				<strong><?php esc_html_e( 'Akcje grupowe:', 'loom' ); ?></strong>
				<button type="button" class="button button-primary" id="loom-auto-selected">
					⚡ <?php esc_html_e( 'Auto-podlinkuj zaznaczone', 'loom' ); ?>
				</button>
				<button type="button" class="button" id="loom-auto-all-orphans">
					⚡ <?php esc_html_e( 'Auto-podlinkuj wszystkie orphany', 'loom' ); ?>
				</button>
				<span id="loom-batch-auto-status" class="loom-status-text"></span>
				<div id="loom-batch-auto-progress" style="display:none; width:100%;">
					<div class="loom-progress-bar"><div class="loom-progress-fill" id="loom-batch-auto-fill"></div></div>
					<p id="loom-batch-auto-text"></p>
					<div id="loom-batch-auto-log" class="loom-auto-log"></div>
				</div>
			</div>
			<?php endif; ?>

			<div class="loom-filters">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=loom-bulk' ) ); ?>" class="loom-filter-tab <?php echo empty( $filter ) ? 'active' : ''; ?>">
					<?php esc_html_e( 'Wszystkie', 'loom' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=loom-bulk&filter=orphans' ) ); ?>" class="loom-filter-tab <?php echo $filter === 'orphans' ? 'active' : ''; ?>">
					<?php esc_html_e( 'Orphany', 'loom' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=loom-bulk&filter=no_embedding' ) ); ?>" class="loom-filter-tab <?php echo $filter === 'no_embedding' ? 'active' : ''; ?>">
					<?php esc_html_e( 'Bez embeddingu', 'loom' ); ?>
				</a>
			</div>

			<table class="wp-list-table widefat fixed striped loom-table">
				<thead>
					<tr>
						<th style="width:4%"><input type="checkbox" id="loom-check-all"></th>
						<th style="width:34%"><?php esc_html_e( 'Tytuł', 'loom' ); ?></th>
						<th style="width:6%"><?php esc_html_e( 'IN', 'loom' ); ?></th>
						<th style="width:6%"><?php esc_html_e( 'OUT', 'loom' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Status', 'loom' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Embedding', 'loom' ); ?></th>
						<th style="width:20%"><?php esc_html_e( 'Akcja', 'loom' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $posts as $row ) :
					if ( $filter === 'orphans' && empty( $row['is_orphan'] ) ) continue;
					if ( $filter === 'no_embedding' && ! empty( $row['embedding'] ) ) continue;

					$in = intval( $row['incoming_links_count'] );

					if ( $row['is_orphan'] ) {
						$status = '🔴 Orphan';
					} elseif ( $in < 3 ) {
						$status = '🟡 Słaby';
					} else {
						$status = '🟢 OK';
					}

					$has_emb = ! empty( $row['embedding'] );
					$pid     = esc_attr( $row['post_id'] );
					$is_orphan_attr = ! empty( $row['is_orphan'] ) ? '1' : '0';
				?>
					<tr data-post-id="<?php echo esc_attr( $pid ); ?>" data-orphan="<?php echo esc_attr( $is_orphan_attr ); ?>">
						<td><input type="checkbox" class="loom-row-check" value="<?php echo esc_attr( $pid ); ?>" <?php echo esc_attr( $has_emb ? '' : 'disabled' ); ?>></td>
						<td><strong><?php echo esc_html( $row['post_title'] ); ?></strong></td>
						<td><?php echo esc_html( $row['incoming_links_count'] ); ?></td>
						<td><?php echo esc_html( $row['outgoing_links_count'] ); ?></td>
						<td><?php echo esc_html( $status ); ?></td>
						<td><?php echo esc_html( $has_emb ? '✅' : '❌' ); ?></td>
						<td>
							<?php if ( $has_key && $has_emb ) : ?>
								<button class="button button-small loom-podlinkuj-btn" data-post-id="<?php echo esc_attr( $pid ); ?>">
									🔗 <?php esc_html_e( 'Podlinkuj', 'loom' ); ?>
								</button>
								<button class="button button-small loom-auto-btn" data-post-id="<?php echo esc_attr( $pid ); ?>" title="<?php esc_attr_e( 'Jednym kliknięciem  -  wstawia linki high+medium bez pytania', 'loom' ); ?>">
									⚡ <?php esc_html_e( 'Auto', 'loom' ); ?>
								</button>
							<?php elseif ( $has_key && ! $has_emb ) : ?>
								<span class="description"><?php esc_html_e( 'Brak embeddingu', 'loom' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr class="loom-inline-panel" id="loom-panel-<?php echo esc_attr( $pid ); ?>" style="display:none;">
						<td colspan="7"><div class="loom-inline-results"></div></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div id="loom-suggestions-panel" style="display:none;"></div>
		</div>
		<?php
	}
}
