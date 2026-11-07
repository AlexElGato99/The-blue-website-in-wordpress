<?php
/**
 * Plugin Name: Taramide performance
 * Description: Page cache (with wp-content/advanced-cache.php), one inlined + purged stylesheet per page, jQuery in the footer,
 *              loop-grid list roles removed (a11y), 480px WebP card image size. Only anonymous visitors get the optimised/cached HTML.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TM_CACHE_DIR', WP_CONTENT_DIR . '/cache/tm-page' );

/* ---------------------------------------------------------------- page cache */
function tm_cache_purge() {
	foreach ( glob( TM_CACHE_DIR . '/*.html' ) ?: array() as $f ) @unlink( $f );
	foreach ( glob( wp_upload_dir()['basedir'] . '/tm-css/*.css' ) ?: array() as $f ) @unlink( $f );
}
foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'edited_term', 'created_term', 'delete_term', 'wp_update_nav_menu', 'switch_theme', 'customize_save_after', 'elementor/core/files/clear_cache', 'elementor/editor/after_save' ) as $tm_hook ) {
	add_action( $tm_hook, 'tm_cache_purge' );
}
add_action( 'updated_option', function ( $option ) {
	// Elementor rewrites elementor_atomic_cache_validity__* on ordinary page views: those must not purge the cache
	if ( preg_match( '/^(elementor_(?!atomic_cache|.*_time$|.*_last_|.*tracker|.*_notice|.*remote_info)|polylang|rank_math|rank-math|theme_mods_|astra-settings|blogname|blogdescription|sticky_posts|page_on_front|show_on_front|permalink_structure)/', $option ) ) tm_cache_purge();
} );

function tm_request_path() { return parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ); }
function tm_cacheable() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || PHP_SAPI === 'cli' ) return false;
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! empty( $_SERVER['QUERY_STRING'] ) ) return false;
	if ( is_user_logged_in() || is_preview() || is_404() || is_search() || is_feed() || post_password_required() ) return false;
	$p = tm_request_path();
	return $p && '/' === substr( $p, -1 );
}
add_action( 'template_redirect', function () {
	if ( tm_cacheable() ) ob_start( 'tm_finish_page' );
}, 1 );

function tm_finish_page( $html ) {
	if ( 200 !== http_response_code() || false === stripos( $html, '</html>' ) || defined( 'DONOTCACHEPAGE' ) ) return $html;
	foreach ( headers_list() as $h ) if ( 0 === stripos( $h, 'content-type:' ) && false === stripos( $h, 'text/html' ) ) return $html;
	try { $html = tm_delay_js( tm_optimize_css( $html ) ); } catch ( \Throwable $e ) { return $html; }
	// safety net: if Elementor was regenerating its CSS files during this render, the page has (almost) no CSS — don't keep it
	if ( ! preg_match( '#<style id="tm-css">(.{20000,}?)</style>#s', $html ) ) return $html;
	if ( ! is_dir( TM_CACHE_DIR ) ) wp_mkdir_p( TM_CACHE_DIR );
	$file = TM_CACHE_DIR . '/' . md5( ( $_SERVER['HTTP_HOST'] ?? '' ) . tm_request_path() ) . '.html';
	$tmp  = $file . '.' . getmypid() . '.tmp';
	$data = $html . "\n<!-- tm-cache " . gmdate( 'c' ) . ' -->';
	if ( strlen( $data ) === @file_put_contents( $tmp, $data ) ) @rename( $tmp, $file ); else @unlink( $tmp ); // never leave a half-written page
	return $html;
}

/* ---------------------------------------------------------------- CSS: combine, purge unused, inline */
function tm_url_to_path( $url ) {
	$u = preg_replace( '/[?#].*$/', '', html_entity_decode( $url ) );
	if ( 0 === strpos( $u, '//' ) ) $u = 'http:' . $u;
	$site = site_url( '/' ); $rel_site = wp_make_link_relative( $site );
	if ( 0 === strpos( $u, $site ) ) $rel = substr( $u, strlen( $site ) );
	elseif ( 0 === strpos( $u, $rel_site ) ) $rel = substr( $u, strlen( $rel_site ) );
	else return null;
	$p = ABSPATH . $rel;
	return ( '.css' === substr( $p, -4 ) && is_file( $p ) ) ? $p : null;
}
function tm_rebase_urls( $css, $url ) {
	$base = wp_make_link_relative( preg_replace( '#[^/]*$#', '', preg_replace( '/[?#].*$/', '', html_entity_decode( $url ) ) ) );
	return preg_replace_callback( '/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', function ( $m ) use ( $base ) {
		$u = trim( $m[2] );
		if ( preg_match( '#^(data:|https?:|//|/|\#|%23)#i', $u ) ) return $m[0];
		$cut = strcspn( $u, '?#' ); $path = substr( $u, 0, $cut ); $suffix = substr( $u, $cut );
		$segs = array();
		foreach ( explode( '/', $base . $path ) as $s ) { if ( '..' === $s ) array_pop( $segs ); elseif ( '.' !== $s ) $segs[] = $s; }
		return 'url(' . $m[1] . implode( '/', $segs ) . $suffix . $m[1] . ')';
	}, $css );
}
function tm_split_selectors( $sel ) {
	$out = array(); $depth = 0; $cur = '';
	for ( $i = 0, $n = strlen( $sel ); $i < $n; $i++ ) {
		$c = $sel[ $i ];
		if ( '(' === $c || '[' === $c ) $depth++; elseif ( ')' === $c || ']' === $c ) $depth--;
		if ( ',' === $c && 0 === $depth ) { $out[] = trim( $cur ); $cur = ''; } else $cur .= $c;
	}
	if ( '' !== trim( $cur ) ) $out[] = trim( $cur );
	return $out;
}
// classes/ids that JavaScript adds after load, or that depend on device/state: always keep
const TM_SAFE = '/^(swiper|elementor-sticky|elementor-motion|animated|elementor-invisible|e-lazyloaded|e-n-|e-con|elementor-active|active|e--|is-|has-|cpel-|elementor-menu-toggle|elementor-nav-menu|e-off-canvas|dialog|elementor-loading|sm-|highlighted|elementor-item|elementor-sub-item|menu-|e-transform|elementor-form|elementor-field|elementor-message|elementor-error|elementor-help|e-form|loaded|open|show|visible|fade|elementor-lightbox|elementor-slideshow|rtl|ltr|elementor-device|elementor-editor|elementor-edit|e-loop|elementor-pagination|page-numbers|current|elementor-counter|elementor-image-carousel|elementor-swiper|skip-link|screen-reader|elementor-hidden|elementor-screen-only|e-contact|elementor-background|elementor-widget-empty|elementor-element-overlay|elementor-ken-burns|elementor-animation|elementor-align|e-off-canvas|elementor-location|elementor-popup|elementor-toc|elementor-tab|elementor-grid|elementor-button|elementor-icon|tm-)/';
function tm_sel_used( $s, $cls, $ids ) {
	if ( ! empty( $GLOBALS['tm_strict'] ) ) return tm_sel_used_strict( $s, $cls, $ids );
	if ( false !== strpos( $s, '\\' ) ) return true;
	$plain = preg_replace( '/\((?:[^()]++|(?R))*\)/', '', $s ); // ignore :not(...), :is(...), :nth-child(...) arguments
	$plain = preg_replace( '/\[[^\]]*\]/', '', $plain );     // and attribute selectors
	if ( preg_match_all( '/\.(-?[_a-zA-Z][\w-]*)/', $plain, $m ) ) foreach ( $m[1] as $c ) if ( ! isset( $cls[ $c ] ) && ! preg_match( TM_SAFE, $c ) ) return false;
	// ids created by JS (Elementor's #elementor-device-mode probe, lightbox, popups, off-canvas…) are kept
	if ( preg_match_all( '/#(-?[_a-zA-Z][\w-]*)/', $plain, $m ) ) foreach ( $m[1] as $c ) if ( ! isset( $ids[ $c ] ) && ! preg_match( '/^(elementor-|e-|off-canvas|wpadminbar|tm-)/', $c ) ) return false;
	return true;
}
// first-paint version: JS has not run yet, so nothing JS-added (states, swiper, sticky…) is needed; :hover/:focus etc. are dropped too
function tm_sel_used_strict( $s, $cls, $ids ) {
	if ( preg_match( '/:(hover|focus|active|focus-visible|focus-within|visited)\b/', $s ) ) return false;
	$plain = preg_replace( '/\((?:[^()]++|(?R))*\)/', '', $s );
	$plain = preg_replace( '/\[[^\]]*\]/', '', $plain );
	if ( preg_match_all( '/\.(-?[_a-zA-Z][\w-]*)/', $plain, $m ) ) foreach ( $m[1] as $c ) if ( ! isset( $cls[ $c ] ) ) return false;
	if ( preg_match_all( '/#(-?[_a-zA-Z][\w-]*)/', $plain, $m ) ) foreach ( $m[1] as $c ) if ( ! isset( $ids[ $c ] ) ) return false;
	return true;
}
function tm_purge_block( $css, $cls, $ids ) {
	$out = ''; $i = 0; $n = strlen( $css );
	while ( $i < $n ) {
		$b = strpos( $css, '{', $i ); if ( false === $b ) { $out .= substr( $css, $i ); break; }
		$sel = substr( $css, $i, $b - $i );
		$depth = 1; $j = $b + 1;
		while ( $j < $n && $depth ) {
			$ch = $css[ $j ];
			if ( '{' === $ch ) $depth++; elseif ( '}' === $ch ) $depth--;
			elseif ( '"' === $ch || "'" === $ch ) { $j++; while ( $j < $n && $css[ $j ] !== $ch ) { if ( '\\' === $css[ $j ] ) $j++; $j++; } }
			$j++;
		}
		$body = substr( $css, $b + 1, $j - $b - 2 ); $i = $j;
		if ( false !== ( $sc = strrpos( $sel, ';' ) ) ) { $out .= substr( $sel, 0, $sc + 1 ); $sel = substr( $sel, $sc + 1 ); }
		$sel = trim( $sel ); if ( '' === $sel ) continue;
		if ( '@' === $sel[0] ) {
			if ( preg_match( '/^@(media|supports|layer|container|document)/i', $sel ) ) { $inner = tm_purge_block( $body, $cls, $ids ); if ( '' !== trim( $inner ) ) $out .= $sel . '{' . $inner . '}'; }
			else $out .= $sel . '{' . $body . '}';
			continue;
		}
		$keep = array(); foreach ( tm_split_selectors( $sel ) as $s ) if ( tm_sel_used( $s, $cls, $ids ) ) $keep[] = $s;
		if ( $keep ) $out .= implode( ',', $keep ) . '{' . $body . '}';
	}
	return $out;
}
function tm_minify_css( $css ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$css = preg_replace( '/\s+/', ' ', $css );
	$css = preg_replace( '/\s*([{};,])\s*/', '$1', $css );
	$css = preg_replace( '/:\s+/', ':', $css );
	return str_replace( ';}', '}', trim( $css ) );
}
function tm_optimize_css( $html ) {
	$hp = stripos( $html, '</head>' ); if ( ! $hp ) return $html;
	$head = substr( $html, 0, $hp ); $rest = substr( $html, $hp );
	if ( ! preg_match_all( '#<link\b[^>]*\brel=[\'"]stylesheet[\'"][^>]*>|<style\b[^>]*>.*?</style>#is', $head, $m, PREG_OFFSET_CAPTURE ) ) return $html;
	$css = ''; $remove = array();
	foreach ( $m[0] as list( $tag, $off ) ) {
		if ( preg_match( '#\bmedia=[\'"]\s*print#i', $tag ) ) continue;
		if ( 0 === stripos( $tag, '<link' ) ) {
			if ( ! preg_match( '#\bhref=[\'"]([^\'"]+)#', $tag, $h ) ) continue;
			$path = tm_url_to_path( $h[1] ); if ( ! $path ) continue; // external stylesheet: leave it
			$c = file_get_contents( $path ); if ( false === $c ) continue;
			$c = tm_rebase_urls( $c, $h[1] );
		} else {
			if ( preg_match( '#\btype=[\'"](?!text/css)#i', $tag ) ) continue;
			$c = preg_replace( '#^<style\b[^>]*>|</style>$#is', '', $tag );
		}
		if ( preg_match( '#\bmedia=[\'"]([^\'"]+)#i', $tag, $md ) && ! in_array( strtolower( trim( $md[1] ) ), array( 'all', 'screen', '' ), true ) ) $c = '@media ' . $md[1] . '{' . $c . '}';
		$css .= $c . "\n"; $remove[] = array( $off, strlen( $tag ) );
	}
	if ( ! $remove ) return $html;
	$tokens = function ( $fragment ) {
		$cls = array(); $ids = array();
		if ( preg_match_all( '/\sclass=(["\'])(.*?)\1/s', $fragment, $cm ) ) foreach ( $cm[2] as $list ) foreach ( preg_split( '/\s+/', $list ) as $c ) if ( '' !== $c ) $cls[ $c ] = true;
		if ( preg_match_all( '/\sid=(["\'])(.*?)\1/', $fragment, $im ) ) foreach ( $im[2] as $c ) $ids[ $c ] = true;
		return array( $cls, $ids );
	};
	$swap = function ( $c ) { return preg_replace_callback( '/@font-face\{[^}]*\}/i', function ( $f ) {
		$r = preg_replace( '/font-display:\s*(auto|block)/i', 'font-display:swap', $f[0] );
		return false === stripos( $r, 'font-display' ) ? substr( $r, 0, -1 ) . ';font-display:swap}' : $r;
	}, $c ); };
	$css = tm_minify_css( preg_replace( '#/\*.*?\*/#s', '', $css ) );
	list( $cls, $ids ) = $tokens( $html );
	$full = $swap( tm_purge_block( $css, $cls, $ids ) );

	// Critical part: rules used by everything up to the end of the first 3 top-level sections of the main content
	// (header, wrappers and those sections). It is inlined; the full stylesheet loads without blocking rendering.
	$crit_html = tm_above_fold_html( $html );
	// Measured 2026-11-07: loading the rest async re-styles the whole page late (3 s style task on mobile, CLS up to 0.7),
	// so the split is off; one inlined stylesheet scores better. Kept for reference: set TM_CSS_SPLIT to true to try again.
	if ( defined( 'TM_CSS_SPLIT' ) && TM_CSS_SPLIT && $crit_html && strlen( $full ) > 60000 ) {
		list( $ccls, $cids ) = $tokens( $crit_html );
		$GLOBALS['tm_strict'] = true; $critical = $swap( tm_purge_block( $css, $ccls, $cids ) ); $GLOBALS['tm_strict'] = false;
		// @font-face rules for families the first screen does not use are left to the full stylesheet
		$critical = preg_replace( '/@font-face\{(?:(?!\}).)*unicode-range:[^}]*U\+(0400|0370|1F00|0102|0460|2DE0|A640)[^}]*\}/i', '', $critical );
		$dir = wp_upload_dir(); $name = 'tm-css/' . md5( $full ) . '.css';
		if ( ! is_file( $dir['basedir'] . '/' . $name ) ) { wp_mkdir_p( $dir['basedir'] . '/tm-css' ); $tmp = $dir['basedir'] . '/' . $name . '.' . getmypid(); if ( strlen( $full ) === @file_put_contents( $tmp, $full ) ) @rename( $tmp, $dir['basedir'] . '/' . $name ); else @unlink( $tmp ); }
		if ( is_file( $dir['basedir'] . '/' . $name ) ) {
			$href = esc_url( wp_make_link_relative( $dir['baseurl'] . '/' . $name ) );
			$out_tag = '<style id="tm-css">' . str_replace( '</style', '<\/style', $critical ) . '</style>'
				. '<link rel="stylesheet" id="tm-css-full" href="' . $href . '" media="print" onload="this.media=\'all\'"><noscript><link rel="stylesheet" href="' . $href . '"></noscript>';
		}
	}
	if ( empty( $out_tag ) ) $out_tag = '<style id="tm-css">' . str_replace( '</style', '<\/style', $full ) . '</style>';
	for ( $k = count( $remove ) - 1; $k >= 0; $k-- ) {
		list( $o, $l ) = $remove[ $k ];
		$head = substr_replace( $head, 0 === $k ? $out_tag : '', $o, $l );
	}
	return $head . $rest;
}
/** HTML from the top of the page to the end of the 3rd top-level section of the main Elementor document (or '' if not found). */
function tm_above_fold_html( $html ) {
	if ( ! preg_match( '/data-elementor-type="(wp-page|single-post|single-page|single|archive|wp-post|loop-item)"/', $html, $m, PREG_OFFSET_CAPTURE ) ) return '';
	$start = $m[0][1];
	if ( ! preg_match_all( '/<div\b[^>]*class="[^"]*\be-parent\b[^"]*"/', $html, $p, PREG_OFFSET_CAPTURE, $start ) ) return '';
	return count( $p[0] ) > 3 ? substr( $html, 0, $p[0][3][1] ) : '';
}

/* ---------------------------------------------------------------- JS: run page scripts on the first interaction */
// Page HTML is fully server-rendered; Elementor's JS (sticky header, carousels, counters, accordion, off-canvas) starts on the
// first scroll / touch / key / pointer move. Clicks made while scripts load are replayed once they are ready.
function tm_delay_js( $html ) {
	$n = 0;
	$html = preg_replace_callback( '#<script\b([^>]*)>(.*?)</script>#is', function ( $m ) use ( &$n ) {
		$attrs = $m[1];
		if ( preg_match( '#\btype=[\'"](?!text/javascript|module)[^\'"]+#i', $attrs ) ) return $m[0]; // JSON-LD, templates…
		if ( preg_match( '#\bid=[\'"]tm-#', $attrs ) || false !== stripos( $attrs, 'data-tm-keep' ) ) return $m[0];
		$n++;
		$attrs = preg_replace( '#\stype=[\'"][^\'"]*[\'"]#i', '', $attrs );
		$attrs = preg_replace( '#\s(async|defer)(=[\'"][^\'"]*[\'"])?#i', '', $attrs );
		$attrs = preg_replace( '#\ssrc=#i', ' data-tm-src=', $attrs );
		return '<script type="text/tm-delay"' . $attrs . '>' . $m[2] . '</script>';
	}, $html );
	if ( ! $n ) return $html;
	$loader = <<<'JS'
<script id="tm-delay-loader">(function(){var started=false,ready=false,queue=[],ev=["keydown","mousedown","mousemove","touchstart","wheel","scroll","pointerdown"];
function start(e){if(started)return;started=true;ev.forEach(function(t){removeEventListener(t,start,{passive:true})});run([].slice.call(document.querySelectorAll('script[type="text/tm-delay"]')));}
var held=false;function hold(){if(!held&&window.jQuery&&jQuery.holdReady){jQuery.holdReady(true);held=true;}}
function run(list){hold();if(!list.length){if(held)jQuery.holdReady(false);document.dispatchEvent(new Event("DOMContentLoaded",{bubbles:true}));dispatchEvent(new Event("load"));setTimeout(function(){ready=true;queue.forEach(function(el){el.click()});queue=[];},120);return;}
var old=list.shift(),s=document.createElement("script");[].slice.call(old.attributes).forEach(function(a){if(a.name!=="type"&&a.name!=="data-tm-src")s.setAttribute(a.name,a.value)});
var src=old.getAttribute("data-tm-src");if(src){s.src=src;s.onload=s.onerror=function(){run(list)};old.parentNode.replaceChild(s,old);}else{s.text=old.text;old.parentNode.replaceChild(s,old);run(list);}}
document.addEventListener("click",function(e){if(ready)return;var a=e.target.closest&&e.target.closest('a[href^="#elementor-action"],.elementor-menu-toggle,.e-n-accordion-item-title,button,[role="button"]');if(a){e.preventDefault();e.stopPropagation();queue.push(a);start();}},true);
ev.forEach(function(t){addEventListener(t,start,{passive:true})});})();</script>
JS;
	return str_ireplace( '</body>', $loader . '</body>', $html );
}

/* ---------------------------------------------------------------- fonts: preload the heading font (hero text is the LCP) */
add_action( 'wp_head', function () {
	$f = content_url( 'uploads/taramide-fonts/fraunces-latin.woff2' );
	if ( is_file( WP_CONTENT_DIR . '/uploads/taramide-fonts/fraunces-latin.woff2' ) ) echo '<link rel="preload" href="' . esc_url( $f ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
}, 1 );

/* ---------------------------------------------------------------- scripts: jQuery to the footer, no jQuery Migrate */
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( is_admin() || ! isset( $scripts->registered['jquery'] ) ) return;
	$scripts->registered['jquery']->deps = array_values( array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) ) );
} );
add_action( 'wp_enqueue_scripts', function () {
	foreach ( array( 'jquery', 'jquery-core' ) as $h ) wp_scripts()->add_data( $h, 'group', 1 );
}, 999 );

/* ---------------------------------------------------------------- a11y: loop grid is not a list of list items */
add_filter( 'elementor/widget/render_content', function ( $content, $widget ) {
	if ( 'loop-grid' === $widget->get_name() ) $content = str_replace( array( ' role="list"', ' role="listitem"' ), '', $content );
	return $content;
}, 10, 2 );

/* ---------------------------------------------------------------- images: 480px card size, WebP for new JPEG sub-sizes */
add_action( 'after_setup_theme', function () { add_image_size( 'tm-card', 480, 0 ); } );
add_filter( 'image_size_names_choose', function ( $s ) { return $s + array( 'tm-card' => 'Card (480px)' ); } );
add_filter( 'image_editor_output_format', function ( $f ) { $f['image/jpeg'] = 'image/webp'; return $f; } );
