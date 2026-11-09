<?php
/**
 * Plugin Name: XHD Live Events
 * Description: Live football matches from football-data.org (default: the current UEFA Champions League season), refreshed automatically. Shows the hero "This Week's Matches" ticker and the "Premium Events" match slider as Elementor widgets or shortcodes ([xhd_live_ticker], [xhd_live_events]). Settings: Live Events in the admin menu.
 * Version: 1.0.0
 * Author: Xtremehdiptv
 * Text Domain: xhd-live
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'XHD_LIVE_FILE', __FILE__ );
define( 'XHD_LIVE_VER', '1.0.0' );

final class XHD_Live {
	const OPT = 'xhd_live_settings';
	const DATA = 'xhd_live_data';
	const LOG = 'xhd_live_log';
	const CRON = 'xhd_live_refresh';
	const API = 'https://api.football-data.org/v4/';

	/** Competitions available on the football-data.org free plan. */
	public static function competitions() {
		return array(
			'CL' => 'UEFA Champions League', 'PL' => 'Premier League', 'PD' => 'La Liga (Primera División)', 'SA' => 'Serie A',
			'BL1' => 'Bundesliga', 'FL1' => 'Ligue 1', 'DED' => 'Eredivisie', 'PPL' => 'Primeira Liga', 'ELC' => 'Championship',
			'BSA' => 'Campeonato Brasileiro Série A', 'EC' => 'European Championship', 'WC' => 'FIFA World Cup',
		);
	}
	public static function defaults() {
		return array(
			'api_key' => '', 'competition' => 'CL', 'days_back' => 2, 'days_ahead' => 7, 'max' => 10, 'refresh' => 15,
			'live_refresh' => 2, 'fallback_next' => 1, 'show_finished' => 1, 'hidden' => array(), 'pinned' => array(), 'custom' => array(),
			'ticker_title' => "This Week's Matches", 'empty_text' => 'No matches scheduled this week.', 'live_label' => 'Live', 'ft_label' => 'FT',
		);
	}
	public static function opt( $k = null ) {
		$o = wp_parse_args( get_option( self::OPT, array() ), self::defaults() );
		return null === $k ? $o : ( $o[ $k ] ?? null );
	}

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( self::CRON, array( __CLASS__, 'cron_refresh' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_shortcode( 'xhd_live_ticker', fn( $a ) => self::render_ticker( shortcode_atts( array( 'title' => '', 'max' => 0, 'interval' => 4000 ), $a ) ) );
		add_shortcode( 'xhd_live_events', fn( $a ) => self::render_events( shortcode_atts( array( 'badge' => 'Unlock Matches', 'overlay' => 'Watch today’s matches by activating your subscription.', 'max' => 0, 'interval' => 4000 ), $a ) ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ) );
		add_action( 'elementor/elements/categories_registered', fn( $m ) => $m->add_category( 'xhd', array( 'title' => 'XtremeHD', 'icon' => 'eicon-play' ) ) );
		if ( is_admin() ) require_once __DIR__ . '/includes/admin.php';
	}

	/* ------------------------------------------------------------------ refresh */
	public static function schedules( $s ) {
		foreach ( array( 5, 10, 15, 30, 60 ) as $m ) $s[ 'xhd_live_' . $m ] = array( 'interval' => $m * 60, 'display' => sprintf( 'Every %d minutes (Live Events)', $m ) );
		return $s;
	}
	public static function ensure_cron() {
		$want = 'xhd_live_' . (int) self::opt( 'refresh' );
		$ev = wp_get_scheduled_event( self::CRON );
		if ( $ev && $ev->schedule !== $want ) { wp_clear_scheduled_hook( self::CRON ); $ev = false; }
		if ( ! $ev ) wp_schedule_event( time() + 60, $want, self::CRON );
	}
	public static function cron_refresh() { self::refresh( 'cron' ); }

	/** Fetch the competition's current-season matches and store them. Returns true or WP_Error. */
	public static function refresh( $source = 'manual' ) {
		$o = self::opt();
		if ( ! $o['api_key'] ) return self::log( new WP_Error( 'no_key', 'No football-data.org API key set.' ), $source );
		if ( get_transient( 'xhd_live_lock' ) && 'manual' !== $source ) return true; // another refresh is running / just ran
		set_transient( 'xhd_live_lock', 1, 50 );
		$code = preg_replace( '/[^A-Z0-9]/', '', strtoupper( $o['competition'] ) ) ?: 'CL';
		// No "season" parameter → football-data.org returns the competition's CURRENT season.
		$res = wp_remote_get( self::API . "competitions/$code/matches", array( 'timeout' => 20, 'headers' => array( 'X-Auth-Token' => $o['api_key'], 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $res ) ) return self::log( $res, $source );
		$http = wp_remote_retrieve_response_code( $res ); $body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $http || ! is_array( $body ) || ! isset( $body['matches'] ) ) {
			$msg = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : 'HTTP ' . $http;
			return self::log( new WP_Error( 'api', $msg ), $source );
		}
		$comp = $body['competition'] ?? array(); $season = $body['filters']['season'] ?? ( $body['matches'][0]['season']['startDate'] ?? '' );
		$matches = array();
		foreach ( $body['matches'] as $m ) {
			$matches[] = array(
				'id' => (int) $m['id'], 'utc' => $m['utcDate'], 'status' => $m['status'], 'matchday' => $m['matchday'] ?? null, 'stage' => $m['stage'] ?? '',
				'home' => self::team( $m['homeTeam'] ?? array() ), 'away' => self::team( $m['awayTeam'] ?? array() ),
				'sh' => $m['score']['fullTime']['home'] ?? null, 'sa' => $m['score']['fullTime']['away'] ?? null,
			);
		}
		update_option( self::DATA, array(
			'fetched' => time(), 'competition' => array( 'code' => $code, 'name' => $comp['name'] ?? ( self::competitions()[ $code ] ?? $code ), 'emblem' => $comp['emblem'] ?? '' ),
			'season' => is_array( $season ) ? $season : array( 'label' => (string) $season ), 'matches' => $matches,
		), false );
		self::log( true, $source, count( $matches ) . ' matches' );
		self::schedule_live_boost( $matches );
		return true;
	}
	private static function team( $t ) { return array( 'id' => $t['id'] ?? 0, 'name' => $t['shortName'] ?? ( $t['name'] ?? 'TBD' ), 'full' => $t['name'] ?? 'TBD', 'crest' => $t['crest'] ?? '' ); }
	/** While a match is live (or about to start) refresh every few minutes instead of the normal interval. */
	private static function schedule_live_boost( $matches ) {
		$now = time(); $soon = false;
		foreach ( $matches as $m ) {
			$t = strtotime( $m['utc'] );
			if ( in_array( $m['status'], array( 'IN_PLAY', 'PAUSED', 'LIVE' ), true ) || ( $t > $now - 3 * HOUR_IN_SECONDS && $t < $now + 15 * MINUTE_IN_SECONDS && 'FINISHED' !== $m['status'] ) ) { $soon = true; break; }
		}
		if ( $soon && ! wp_next_scheduled( self::CRON . '_boost' ) ) wp_schedule_single_event( $now + max( 1, (int) self::opt( 'live_refresh' ) ) * 60, self::CRON . '_boost' );
	}
	private static function log( $r, $source, $note = '' ) {
		$log = get_option( self::LOG, array() );
		array_unshift( $log, array( 't' => time(), 'ok' => ! is_wp_error( $r ), 'msg' => is_wp_error( $r ) ? $r->get_error_message() : $note, 'src' => $source ) );
		update_option( self::LOG, array_slice( $log, 0, 15 ), false );
		return $r;
	}

	/* ------------------------------------------------------------------ data */
	public static function is_live( $s ) { return in_array( $s, array( 'IN_PLAY', 'PAUSED', 'LIVE' ), true ); }
	/** Matches to display: live first, then upcoming (soonest first), then recent results; pinned matches on top. */
	public static function matches( $limit = 0 ) {
		$o = self::opt(); $data = get_option( self::DATA, array() ); $limit = $limit ?: (int) $o['max'];
		$comp = $data['competition'] ?? array( 'name' => self::competitions()[ $o['competition'] ] ?? '', 'emblem' => '' );
		$all = array();
		foreach ( $data['matches'] ?? array() as $m ) { if ( in_array( (string) $m['id'], array_map( 'strval', (array) $o['hidden'] ), true ) ) continue; $m['league'] = $comp['name']; $m['emblem'] = $comp['emblem']; $all[] = $m; }
		foreach ( (array) $o['custom'] as $i => $c ) {
			if ( empty( $c['home'] ) || empty( $c['when'] ) ) continue;
			$t = strtotime( get_gmt_from_date( $c['when'] ) . ' UTC' ); $now = time();
			$all[] = array( 'id' => 'c' . $i, 'utc' => gmdate( 'c', $t ), 'status' => $t > $now ? 'TIMED' : ( $now - $t < 2 * HOUR_IN_SECONDS ? 'LIVE' : 'FINISHED' ),
				'home' => array( 'name' => $c['home'], 'crest' => $c['home_logo'] ?? '' ), 'away' => array( 'name' => $c['away'] ?? '', 'crest' => $c['away_logo'] ?? '' ),
				'sh' => null, 'sa' => null, 'league' => $c['label'] ?: $comp['name'], 'emblem' => $c['label_logo'] ?? '', 'custom' => true );
		}
		$now = time(); $back = $now - (int) $o['days_back'] * DAY_IN_SECONDS; $ahead = $now + (int) $o['days_ahead'] * DAY_IN_SECONDS;
		$live = $up = $done = $later = array();
		foreach ( $all as $m ) {
			$t = strtotime( $m['utc'] );
			if ( self::is_live( $m['status'] ) ) $live[] = $m;
			elseif ( in_array( $m['status'], array( 'SCHEDULED', 'TIMED' ), true ) ) { if ( $t <= $ahead && $t >= $now - 3 * HOUR_IN_SECONDS ) $up[] = $m; elseif ( $t > $ahead ) $later[] = $m; }
			elseif ( 'FINISHED' === $m['status'] && $t >= $back && $o['show_finished'] ) $done[] = $m;
		}
		$asc = fn( $a, $b ) => strcmp( $a['utc'], $b['utc'] );
		usort( $live, $asc ); usort( $up, $asc ); usort( $later, $asc ); usort( $done, fn( $a, $b ) => strcmp( $b['utc'], $a['utc'] ) );
		$list = array_merge( $live, $up, $done );
		if ( ! $live && ! $up && $o['fallback_next'] && $later ) { // between match weeks: show the next match day
			$first = substr( $later[0]['utc'], 0, 10 ); $nextday = array_filter( $later, fn( $m ) => strtotime( $m['utc'] ) < strtotime( $first ) + 3 * DAY_IN_SECONDS );
			$list = array_merge( array_values( $nextday ), $done );
		}
		$pinned = array_map( 'strval', (array) $o['pinned'] );
		if ( $pinned ) usort( $list, fn( $a, $b ) => (int) in_array( (string) $b['id'], $pinned, true ) - (int) in_array( (string) $a['id'], $pinned, true ) );
		return array_slice( $list, 0, max( 1, $limit ) );
	}

	/* ------------------------------------------------------------------ output */
	public static function assets() {
		wp_register_script( 'xhd-live', plugins_url( 'assets/live.js', __FILE__ ), array(), filemtime( __DIR__ . '/assets/live.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script( 'xhd-live', 'XHD_LIVE', array( 'rest' => esc_url_raw( rest_url( 'xhd-live/v1/matches' ) ), 'poll' => 60 ) );
	}
	private static function status( $m ) {
		$o = self::opt();
		if ( self::is_live( $m['status'] ) ) return array( 'live', $o['live_label'] );
		if ( 'FINISHED' === $m['status'] ) return array( 'finished', $o['ft_label'] );
		return array( 'upcoming', wp_date( 'H:i', strtotime( $m['utc'] ) ) );
	}
	private static function score( $m ) { return ( null === $m['sh'] || null === $m['sa'] ) ? '' : $m['sh'] . ' - ' . $m['sa']; }
	private static function crest( $url, $alt, $size, $lazy = true ) {
		if ( ! $url ) return '<span class="xhd-crest-ph" style="width:' . $size . 'px;height:' . $size . 'px" aria-hidden="true"></span>';
		return sprintf( '<img src="%s" alt="%s" width="%d" height="%d"%s decoding="async" referrerpolicy="no-referrer">', esc_url( $url ), esc_attr( $alt ), $size, $size, $lazy ? ' loading="lazy"' : '' );
	}
	const ICON_PLAY = '<svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
	const ICON_CAL = '<svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';

	/** Hero "This Week's Matches" ticker (markup/classes of the original site). */
	public static function render_ticker( $a = array() ) {
		wp_enqueue_script( 'xhd-live' );
		$o = self::opt(); $title = ! empty( $a['title'] ) ? $a['title'] : $o['ticker_title'];
		$list = self::matches( (int) ( $a['max'] ?? 0 ) );
		$today = wp_date( 'Y-m-d' );
		ob_start(); ?>
<div class="hero-ticker" data-xhd-live="ticker" data-interval="<?php echo (int) ( $a['interval'] ?? 4000 ); ?>">
	<div class="hero-ticker-head"><span class="live-dot"></span><?php echo self::ICON_PLAY; ?> <?php echo esc_html( $title ); ?></div>
	<?php if ( ! $list ) : ?>
	<div class="hero-ticker-empty"><?php echo self::ICON_CAL; ?><div><?php echo esc_html( $o['empty_text'] ); ?></div></div>
	<?php else : ?>
	<div class="hero-ticker-body">
	<?php foreach ( $list as $i => $m ) : $t = strtotime( $m['utc'] ); list( $st, $label ) = self::status( $m ); $score = self::score( $m ); ?>
		<div class="hero-slide<?php echo 0 === $i ? ' active' : ''; ?>" data-slide="<?php echo (int) $i; ?>" data-mid="<?php echo esc_attr( $m['id'] ); ?>">
			<div class="hero-slide-day"><?php echo self::ICON_CAL; ?> <?php echo esc_html( wp_date( 'l · j M', $t ) ); ?><?php if ( wp_date( 'Y-m-d', $t ) === $today ) echo ' <span class="day-today">' . esc_html__( 'Today', 'xhd-live' ) . '</span>'; ?></div>
			<div class="hero-slide-matchup">
				<div class="hero-slide-team"><?php echo self::crest( $m['home']['crest'], $m['home']['name'], 64, $i > 0 ); ?><span><?php echo esc_html( $m['home']['name'] ); ?></span></div>
				<div class="hero-slide-center">
					<?php if ( $score && 'upcoming' !== $st ) : ?><span class="hero-slide-score"><?php echo esc_html( $score ); ?></span><?php else : ?><span class="hero-slide-vs">VS</span><?php endif; ?>
					<span class="hero-slide-status st-<?php echo esc_attr( $st ); ?>"><?php echo esc_html( $label ); ?></span>
				</div>
				<div class="hero-slide-team"><?php echo self::crest( $m['away']['crest'], $m['away']['name'], 64, $i > 0 ); ?><span><?php echo esc_html( $m['away']['name'] ); ?></span></div>
			</div>
			<div class="hero-slide-league"><?php if ( $m['emblem'] ) echo self::crest( $m['emblem'], $m['league'], 16, $i > 0 ); ?> <?php echo esc_html( $m['league'] ); ?></div>
		</div>
	<?php endforeach; ?>
	</div>
	<?php if ( count( $list ) > 1 ) : ?>
	<div class="hero-ticker-nav">
		<?php foreach ( $list as $i => $m ) : ?><button class="dot<?php echo 0 === $i ? ' active' : ''; ?>" type="button" data-goto="<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( sprintf( 'Go to slide %d', $i + 1 ) ); ?>"></button><?php endforeach; ?>
	</div>
	<?php endif; endif; ?>
</div>
		<?php return trim( ob_get_clean() );
	}

	/** "Premium Events & Football Matches" match slider (markup/classes of the original site). */
	public static function render_events( $a = array() ) {
		wp_enqueue_script( 'xhd-live' );
		$list = self::matches( (int) ( $a['max'] ?? 0 ) );
		ob_start(); ?>
<div class="live-broadcast rv d2" data-xhd-live="events" data-interval="<?php echo (int) ( $a['interval'] ?? 4000 ); ?>">
	<div class="live-video" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f0f7fc 0%,#e4f0f9 50%,#d6ecfa 100%);">
		<?php if ( ! empty( $a['badge'] ) ) : ?><div class="live-badge-tag"><span class="live-badge-dot"></span><?php echo esc_html( $a['badge'] ); ?></div><?php endif; ?>
		<?php if ( ! empty( $a['overlay'] ) ) : ?><div class="live-overlay-tag"><?php echo esc_html( $a['overlay'] ); ?></div><?php endif; ?>
		<div style="position:absolute;inset:0;overflow:hidden;z-index:5">
			<div class="live-match-track" style="position:relative;width:100%;height:100%">
			<?php foreach ( $list as $i => $m ) : list( $st, $label ) = self::status( $m ); $score = self::score( $m ); ?>
				<div class="live-match-slide<?php echo 0 === $i ? ' active' : ''; ?>" data-mid="<?php echo esc_attr( $m['id'] ); ?>">
					<div class="live-match-team"><?php echo self::crest( $m['home']['crest'], $m['home']['name'], 88 ); ?><span class="live-match-team-name"><?php echo esc_html( $m['home']['name'] ); ?></span></div>
					<div class="live-match-center">
						<?php if ( $score && 'upcoming' !== $st ) : ?><span class="live-match-score"><?php echo esc_html( $score ); ?></span><?php else : ?><span class="live-match-vs">VS</span><?php endif; ?>
						<span class="live-match-status m-<?php echo esc_attr( $st ); ?>"><?php echo esc_html( 'upcoming' === $st ? wp_date( 'D j M · H:i', strtotime( $m['utc'] ) ) : $label ); ?></span>
						<span class="live-match-league"><?php echo esc_html( $m['league'] ); ?></span>
					</div>
					<div class="live-match-team"><?php echo self::crest( $m['away']['crest'], $m['away']['name'], 88 ); ?><span class="live-match-team-name"><?php echo esc_html( $m['away']['name'] ); ?></span></div>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
		<?php return trim( ob_get_clean() );
	}

	/* ------------------------------------------------------------------ REST (live score polling) */
	public static function rest() {
		register_rest_route( 'xhd-live/v1', '/matches', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function () {
			$data = get_option( self::DATA, array() );
			// keep scores fresh while someone is watching a live match (lock prevents API floods)
			$live = array_filter( self::matches(), fn( $m ) => self::is_live( $m['status'] ) );
			if ( $live && ( time() - (int) ( $data['fetched'] ?? 0 ) ) > max( 1, (int) self::opt( 'live_refresh' ) ) * 60 ) self::refresh( 'visitor' );
			$out = array();
			foreach ( self::matches() as $m ) { list( $st, $label ) = self::status( $m ); $out[] = array( 'id' => (string) $m['id'], 'st' => $st, 'label' => $label, 'score' => self::score( $m ) ); }
			return rest_ensure_response( array( 'matches' => $out, 'fetched' => (int) ( get_option( self::DATA, array() )['fetched'] ?? 0 ) ) );
		} ) );
	}

	/* ------------------------------------------------------------------ Elementor */
	public static function widgets( $manager ) {
		require_once __DIR__ . '/includes/widgets.php';
		$manager->register( new XHD_Live_Ticker_Widget() );
		$manager->register( new XHD_Live_Events_Widget() );
	}
}
add_action( 'plugins_loaded', array( 'XHD_Live', 'init' ) );
add_action( XHD_Live::CRON . '_boost', fn() => XHD_Live::refresh( 'live' ) );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( XHD_Live::CRON ); wp_clear_scheduled_hook( XHD_Live::CRON . '_boost' ); } );
