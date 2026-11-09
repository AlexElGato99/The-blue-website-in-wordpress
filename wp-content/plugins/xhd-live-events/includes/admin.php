<?php
/** Admin screen: WP Admin → Live Events. */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
	add_menu_page( 'Live Events', 'Live Events', 'manage_options', 'xhd-live', 'xhd_live_admin_page', 'dashicons-awards', 58 );
} );
add_filter( 'plugin_action_links_' . plugin_basename( XHD_LIVE_FILE ), function ( $l ) { array_unshift( $l, '<a href="' . esc_url( admin_url( 'admin.php?page=xhd-live' ) ) . '">Settings</a>' ); return $l; } );

/* Save settings */
add_action( 'admin_post_xhd_live_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed' );
	check_admin_referer( 'xhd_live_save' );
	$in = wp_unslash( $_POST );
	$o = XHD_Live::opt();
	$key = trim( sanitize_text_field( $in['api_key'] ?? '' ) );
	if ( '' !== $key && false === strpos( $key, '•' ) ) $o['api_key'] = $key;           // masked value = unchanged
	if ( ! empty( $in['clear_key'] ) ) $o['api_key'] = '';
	$o['competition'] = array_key_exists( $in['competition'] ?? '', XHD_Live::competitions() ) ? $in['competition'] : 'CL';
	foreach ( array( 'days_back' => array( 0, 30 ), 'days_ahead' => array( 1, 60 ), 'max' => array( 1, 30 ), 'live_refresh' => array( 1, 30 ) ) as $k => list( $min, $max ) ) $o[ $k ] = min( $max, max( $min, (int) ( $in[ $k ] ?? $o[ $k ] ) ) );
	$o['refresh'] = in_array( (int) ( $in['refresh'] ?? 15 ), array( 5, 10, 15, 30, 60 ), true ) ? (int) $in['refresh'] : 15;
	$o['fallback_next'] = empty( $in['fallback_next'] ) ? 0 : 1;
	$o['show_finished'] = empty( $in['show_finished'] ) ? 0 : 1;
	foreach ( array( 'ticker_title', 'empty_text', 'live_label', 'ft_label' ) as $k ) $o[ $k ] = sanitize_text_field( $in[ $k ] ?? $o[ $k ] );
	$o['hidden'] = array_values( array_map( 'sanitize_text_field', (array) ( $in['hidden'] ?? array() ) ) );
	$o['pinned'] = array_values( array_map( 'sanitize_text_field', (array) ( $in['pinned'] ?? array() ) ) );
	$custom = array();
	foreach ( (array) ( $in['custom'] ?? array() ) as $c ) {
		if ( empty( $c['home'] ) || empty( $c['when'] ) ) continue;
		$custom[] = array( 'home' => sanitize_text_field( $c['home'] ), 'away' => sanitize_text_field( $c['away'] ?? '' ), 'home_logo' => esc_url_raw( $c['home_logo'] ?? '' ),
			'away_logo' => esc_url_raw( $c['away_logo'] ?? '' ), 'when' => sanitize_text_field( $c['when'] ), 'label' => sanitize_text_field( $c['label'] ?? '' ) );
	}
	$o['custom'] = $custom;
	$changed_comp = XHD_Live::opt( 'competition' ) !== $o['competition'];
	update_option( XHD_Live::OPT, $o, false );
	XHD_Live::ensure_cron();
	$msg = 'saved';
	if ( $changed_comp || ! empty( $in['refresh_now'] ) || ( $o['api_key'] && ! get_option( XHD_Live::DATA ) ) ) { $r = XHD_Live::refresh( 'manual' ); $msg = is_wp_error( $r ) ? 'error' : 'refreshed'; }
	wp_safe_redirect( admin_url( 'admin.php?page=xhd-live&msg=' . $msg ) ); exit;
} );
add_action( 'admin_post_xhd_live_refresh', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed' );
	check_admin_referer( 'xhd_live_refresh' );
	$r = XHD_Live::refresh( 'manual' );
	wp_safe_redirect( admin_url( 'admin.php?page=xhd-live&msg=' . ( is_wp_error( $r ) ? 'error' : 'refreshed' ) ) ); exit;
} );

function xhd_live_admin_page() {
	$o = XHD_Live::opt(); $data = get_option( XHD_Live::DATA, array() ); $log = get_option( XHD_Live::LOG, array() );
	$shown = array_map( fn( $m ) => (string) $m['id'], XHD_Live::matches() );
	$next = wp_next_scheduled( XHD_Live::CRON );
	$fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	$msg = sanitize_key( $_GET['msg'] ?? '' );
	?>
<div class="wrap xhd-live-admin">
	<h1>Live Events <span style="font-size:13px;font-weight:400;color:#646970">football-data.org · <?php echo esc_html( $data['competition']['name'] ?? XHD_Live::competitions()[ $o['competition'] ] ); ?></span></h1>
	<?php if ( 'saved' === $msg ) echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>'; ?>
	<?php if ( 'refreshed' === $msg ) echo '<div class="notice notice-success is-dismissible"><p>Matches refreshed from football-data.org.</p></div>'; ?>
	<?php if ( 'error' === $msg && $log ) echo '<div class="notice notice-error"><p>Refresh failed: ' . esc_html( $log[0]['msg'] ) . '</p></div>'; ?>
	<?php if ( ! $o['api_key'] ) : ?><div class="notice notice-warning"><p><b>Add your football-data.org API key</b> to load matches. Get a free key at <a href="https://www.football-data.org/client/register" target="_blank" rel="noopener">football-data.org/client/register</a> (free plan: Champions League and top leagues, 10 requests/minute).</p></div><?php endif; ?>

	<style>
		.xhd-live-admin .card{max-width:none;padding:8px 20px 16px;margin-top:16px}
		.xhd-live-admin .xl-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:16px}
		.xhd-live-admin table.widefat td,.xhd-live-admin table.widefat th{vertical-align:middle}
		.xhd-live-admin .xl-crest{width:22px;height:22px;object-fit:contain;vertical-align:middle;margin-right:6px}
		.xhd-live-admin .xl-st{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;color:#fff;background:#2B8FD1}
		.xhd-live-admin .xl-st.live{background:#dc2626}.xhd-live-admin .xl-st.finished{background:#6b7280}
		.xhd-live-admin tr.xl-shown td:first-child{box-shadow:inset 3px 0 #2B8FD1}
		.xhd-live-admin .xl-custom input{width:100%}
		.xhd-live-admin .xl-matches{max-height:520px;overflow:auto}
	</style>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="xhd_live_save"><?php wp_nonce_field( 'xhd_live_save' ); ?>
		<div class="xl-grid">
		<div class="card">
			<h2>Source</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="xl-key">API key</label></th><td>
					<input id="xl-key" name="api_key" type="password" class="regular-text" autocomplete="off" value="<?php echo $o['api_key'] ? esc_attr( str_repeat( '•', 12 ) . substr( $o['api_key'], -4 ) ) : ''; ?>" placeholder="football-data.org API token">
					<?php if ( $o['api_key'] ) : ?><br><label><input type="checkbox" name="clear_key" value="1"> Remove the saved key</label><?php endif; ?>
					<p class="description">Stored in the WordPress database, never shown on the site.</p></td></tr>
				<tr><th><label for="xl-comp">Competition</label></th><td><select id="xl-comp" name="competition"><?php foreach ( XHD_Live::competitions() as $k => $n ) printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $o['competition'], $k, false ), esc_html( $n ) ); ?></select>
					<p class="description">Always the competition's <b>current season</b> (football-data.org returns the running season automatically).</p></td></tr>
				<tr><th>Auto refresh</th><td><select name="refresh"><?php foreach ( array( 5, 10, 15, 30, 60 ) as $m ) printf( '<option value="%d"%s>Every %d minutes</option>', $m, selected( (int) $o['refresh'], $m, false ), $m ); ?></select>
					&nbsp; during live matches every <input name="live_refresh" type="number" min="1" max="30" value="<?php echo (int) $o['live_refresh']; ?>" style="width:60px"> min</td></tr>
			</table>
		</div>
		<div class="card">
			<h2>Display</h2>
			<table class="form-table" role="presentation">
				<tr><th>Matches shown</th><td><input name="max" type="number" min="1" max="30" value="<?php echo (int) $o['max']; ?>" style="width:70px"> max · last <input name="days_back" type="number" min="0" max="30" value="<?php echo (int) $o['days_back']; ?>" style="width:60px"> days of results · next <input name="days_ahead" type="number" min="1" max="60" value="<?php echo (int) $o['days_ahead']; ?>" style="width:60px"> days</td></tr>
				<tr><th>Options</th><td><label><input type="checkbox" name="show_finished" value="1" <?php checked( $o['show_finished'] ); ?>> Show recent results (final scores)</label><br>
					<label><input type="checkbox" name="fallback_next" value="1" <?php checked( $o['fallback_next'] ); ?>> When no match is in the window, show the next match day</label></td></tr>
				<tr><th><label for="xl-title">Ticker title</label></th><td><input id="xl-title" name="ticker_title" class="regular-text" value="<?php echo esc_attr( $o['ticker_title'] ); ?>"></td></tr>
				<tr><th><label for="xl-empty">No matches text</label></th><td><input id="xl-empty" name="empty_text" class="regular-text" value="<?php echo esc_attr( $o['empty_text'] ); ?>"></td></tr>
				<tr><th>Labels</th><td>Live <input name="live_label" value="<?php echo esc_attr( $o['live_label'] ); ?>" style="width:90px"> &nbsp; Finished <input name="ft_label" value="<?php echo esc_attr( $o['ft_label'] ); ?>" style="width:90px"></td></tr>
			</table>
		</div>
		</div>

		<div class="card">
			<h2 style="display:flex;justify-content:space-between;align-items:center">Matches <span style="font-size:12px;font-weight:400;color:#646970"><?php
				echo $data ? 'Season ' . esc_html( ( $data['season']['startDate'] ?? '' ) ? substr( $data['season']['startDate'], 0, 4 ) . '/' . substr( $data['season']['endDate'] ?? '', 0, 4 ) : '' ) . ' · ' . count( $data['matches'] ?? array() ) . ' matches · updated ' . esc_html( human_time_diff( (int) $data['fetched'] ) ) . ' ago' : 'Not loaded yet'; ?></span></h2>
			<p class="description">Blue mark = currently shown on the site. <b>Hide</b> removes a match, <b>Pin</b> shows it first.</p>
			<div class="xl-matches">
			<table class="widefat striped">
				<thead><tr><th style="width:150px">Date</th><th>Match</th><th style="width:110px">Status</th><th style="width:60px">Hide</th><th style="width:60px">Pin</th></tr></thead>
				<tbody>
				<?php
				$ms = $data['matches'] ?? array(); $now = time();
				// list: everything from 7 days ago onwards (the full season is stored)
				$ms = array_values( array_filter( $ms, fn( $m ) => strtotime( $m['utc'] ) > $now - 7 * DAY_IN_SECONDS ) );
				usort( $ms, fn( $a, $b ) => strcmp( $a['utc'], $b['utc'] ) );
				if ( ! $ms ) echo '<tr><td colspan="5">No matches loaded.</td></tr>';
				foreach ( array_slice( $ms, 0, 80 ) as $m ) :
					$st = XHD_Live::is_live( $m['status'] ) ? 'live' : ( 'FINISHED' === $m['status'] ? 'finished' : 'upcoming' ); $id = (string) $m['id']; ?>
					<tr class="<?php echo in_array( $id, $shown, true ) ? 'xl-shown' : ''; ?>">
						<td><?php echo esc_html( wp_date( 'D j M · H:i', strtotime( $m['utc'] ) ) ); ?></td>
						<td><?php if ( $m['home']['crest'] ) echo '<img class="xl-crest" src="' . esc_url( $m['home']['crest'] ) . '" alt="" referrerpolicy="no-referrer">'; echo esc_html( $m['home']['name'] ); ?> <b><?php echo null !== $m['sh'] ? esc_html( $m['sh'] . ' - ' . $m['sa'] ) : 'vs'; ?></b> <?php echo esc_html( $m['away']['name'] ); if ( $m['away']['crest'] ) echo ' <img class="xl-crest" src="' . esc_url( $m['away']['crest'] ) . '" alt="" referrerpolicy="no-referrer">'; ?>
							<span style="color:#646970"> · <?php echo esc_html( ucwords( strtolower( str_replace( '_', ' ', $m['stage'] ) ) ) . ( $m['matchday'] ? ' · MD ' . $m['matchday'] : '' ) ); ?></span></td>
						<td><span class="xl-st <?php echo esc_attr( $st ); ?>"><?php echo esc_html( ucfirst( strtolower( str_replace( '_', ' ', $m['status'] ) ) ) ); ?></span></td>
						<td><input type="checkbox" name="hidden[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, array_map( 'strval', (array) $o['hidden'] ), true ) ); ?>></td>
						<td><input type="checkbox" name="pinned[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, array_map( 'strval', (array) $o['pinned'] ), true ) ); ?>></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div>

		<div class="card">
			<h2>Custom events</h2>
			<p class="description">Add your own events (boxing, UFC, a friendly …). They are mixed with the API matches by date. Logo = image URL (Media Library → copy URL).</p>
			<table class="widefat xl-custom">
				<thead><tr><th>Home / fighter 1</th><th>Logo URL</th><th>Away / fighter 2</th><th>Logo URL</th><th style="width:190px">Date &amp; time (site time)</th><th>Label</th></tr></thead>
				<tbody id="xl-rows">
				<?php $rows = (array) $o['custom']; $rows[] = array(); foreach ( $rows as $i => $c ) : ?>
					<tr>
						<td><input name="custom[<?php echo (int) $i; ?>][home]" value="<?php echo esc_attr( $c['home'] ?? '' ); ?>"></td>
						<td><input name="custom[<?php echo (int) $i; ?>][home_logo]" value="<?php echo esc_attr( $c['home_logo'] ?? '' ); ?>"></td>
						<td><input name="custom[<?php echo (int) $i; ?>][away]" value="<?php echo esc_attr( $c['away'] ?? '' ); ?>"></td>
						<td><input name="custom[<?php echo (int) $i; ?>][away_logo]" value="<?php echo esc_attr( $c['away_logo'] ?? '' ); ?>"></td>
						<td><input type="datetime-local" name="custom[<?php echo (int) $i; ?>][when]" value="<?php echo esc_attr( $c['when'] ?? '' ); ?>"></td>
						<td><input name="custom[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $c['label'] ?? '' ); ?>" placeholder="e.g. UFC 300"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="xl-add">+ Add event</button> <span class="description">To delete an event, clear its first field and save.</span></p>
		</div>

		<p class="submit"><button class="button button-primary button-hero">Save settings</button> &nbsp; <label><input type="checkbox" name="refresh_now" value="1"> and refresh matches now</label></p>
	</form>

	<div class="xl-grid">
	<div class="card">
		<h2>Status</h2>
		<p>Last update: <b><?php echo ! empty( $data['fetched'] ) ? esc_html( wp_date( $fmt, (int) $data['fetched'] ) ) : '—'; ?></b><br>
		Next automatic refresh: <b><?php echo $next ? esc_html( wp_date( $fmt, $next ) ) : '—'; ?></b> (WP-Cron)</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="xhd_live_refresh"><?php wp_nonce_field( 'xhd_live_refresh' ); ?><button class="button button-secondary" <?php disabled( ! $o['api_key'] ); ?>>Refresh now</button></form>
		<p class="description">Show on any page: Elementor widgets <b>Live Matches Ticker</b> / <b>Live Matches Slider</b> (category XtremeHD) or shortcodes <code>[xhd_live_ticker]</code> <code>[xhd_live_events]</code>.</p>
	</div>
	<div class="card">
		<h2>Recent activity</h2>
		<table class="widefat striped"><tbody>
		<?php if ( ! $log ) echo '<tr><td>Nothing yet.</td></tr>'; foreach ( $log as $l ) : ?>
			<tr><td style="width:150px"><?php echo esc_html( wp_date( 'j M H:i:s', $l['t'] ) ); ?></td><td><?php echo $l['ok'] ? '✅' : '❌'; ?> <?php echo esc_html( $l['msg'] ); ?></td><td style="width:70px;color:#646970"><?php echo esc_html( $l['src'] ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
	</div>
</div>
<script>
document.getElementById('xl-add').addEventListener('click', function () {
	var tb = document.getElementById('xl-rows'), last = tb.lastElementChild, n = tb.children.length, row = last.cloneNode(true);
	row.querySelectorAll('input').forEach(function (i) { i.value = ''; i.name = i.name.replace(/custom\[\d+\]/, 'custom[' + n + ']'); });
	tb.appendChild(row);
});
</script>
	<?php
}
