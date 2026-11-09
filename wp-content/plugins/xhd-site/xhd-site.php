<?php
/**
 * Plugin Name: XtremeHD Site
 * Description: Site helpers for the XtremeHD IPTV build: clean front-end (Astra CSS off — layout and styling come from Elementor), original URL routes (/checkout/N, /payment/N), legacy link redirects.
 * Version: 1.0.0
 * Author: Xtremehdiptv
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* Front-end: the site is fully built with Elementor (Theme Builder header/footer + pages), so Astra's own stylesheet is not needed. */
add_action( 'wp_enqueue_scripts', function () {
	foreach ( array( 'astra-theme-css', 'astra-addon-css', 'astra-google-fonts', 'astra-menu-animation' ) as $h ) { wp_dequeue_style( $h ); wp_deregister_style( $h ); }
}, 999 );
add_filter( 'astra_dynamic_theme_css', '__return_empty_string', 999 );

/* Fonts (as on the original) + the small behaviour script (menu, FAQ, pricing tabs, reveal, cookie banner). */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'xhd-fonts', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap', array(), null );
	wp_enqueue_script( 'xhd-site', plugins_url( 'assets/xhd.js', __FILE__ ), array(), filemtime( __DIR__ . '/assets/xhd.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
} );
add_filter( 'wp_resource_hints', function ( $urls, $rel ) {
	if ( 'preconnect' === $rel ) { $urls[] = 'https://fonts.googleapis.com'; $urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' ); }
	return $urls;
}, 10, 2 );

/* Body classes used by the ported stylesheet: every page gets "xg" + its style group (home, inner, channels, reseller,
 * login, checkout — the original site had one stylesheet per group). Group = page meta _xhd_group (default inner). */
add_filter( 'body_class', function ( $c ) {
	$g = 'inner';
	if ( is_front_page() ) $g = 'home';
	elseif ( is_singular() ) { $m = get_post_meta( get_queried_object_id(), '_xhd_group', true ); if ( $m ) $g = $m; }
	$c[] = 'xg'; $c[] = 'xg-' . sanitize_html_class( $g );
	if ( is_singular() && ( $extra = get_post_meta( get_queried_object_id(), '_xhd_body_class', true ) ) ) foreach ( explode( ' ', $extra ) as $x ) $c[] = sanitize_html_class( $x );
	return $c;
} );

/* Routes kept from the original site: /checkout/<plan-id>/ shows the plan's checkout page (pages live at /checkout/plan-<id>/),
 * /payment/<plan-id> redirects to it (as on the original). */
add_action( 'init', function () {
	add_rewrite_rule( '^checkout/([0-9]+)/?$', 'index.php?pagename=checkout/plan-$matches[1]', 'top' );
} );
add_action( 'template_redirect', function () {
	$path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	$base = trim( parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( $base && 0 === strpos( $path, $base ) ) $path = trim( substr( $path, strlen( $base ) ), '/' );
	if ( preg_match( '#^payment/([0-9]+)$#', $path, $m ) ) { wp_safe_redirect( home_url( '/checkout/' . $m[1] . '/' ), 302 ); exit; }
	if ( 'checkout' === $path ) { wp_safe_redirect( home_url( '/xtremehd-iptv-pricing/' ), 302 ); exit; } // no plan chosen yet
	// short links used in parts of the original footer/buttons
	$legacy = array( 'pricing' => 'xtremehd-iptv-pricing', 'channels' => 'xtremehd-iptv-channels', 'trial' => 'xtremehd-iptv-trial', 'contact-us' => 'xtremehd-iptv-contact-us', 'xtremehd-iptv-home' => '' );
	if ( isset( $legacy[ $path ] ) ) { wp_safe_redirect( home_url( '/' . ( $legacy[ $path ] ? $legacy[ $path ] . '/' : '' ) ), 301 ); exit; }
} );
/* Image widgets: put the original <img> class, inline style and width/height (stored as data-img* custom attributes on
 * the widget) onto the <img> itself, so the original stylesheet sizes images exactly like on the original site. */
add_filter( 'elementor/image_size/get_attachment_image_html', function ( $html, $settings ) {
	if ( empty( $settings['_attributes'] ) || false === strpos( $settings['_attributes'], 'data-img' ) ) return $html;
	$d = array();
	foreach ( explode( "\n", $settings['_attributes'] ) as $line ) { $p = explode( '|', $line, 2 ); if ( 2 === count( $p ) ) $d[ trim( $p[0] ) ] = trim( $p[1] ); }
	if ( ! empty( $d['data-imgcls'] ) ) {
		$cls = esc_attr( $d['data-imgcls'] );
		$html = preg_match( '/<img[^>]*\sclass="/', $html ) ? preg_replace( '/(<img[^>]*\sclass=")/', '$1' . $cls . ' ', $html, 1 ) : preg_replace( '/<img\b/', '<img class="' . $cls . '"', $html, 1 );
	}
	if ( ! empty( $d['data-imgstyle'] ) ) $html = preg_replace( '/<img\b/', '<img style="' . esc_attr( $d['data-imgstyle'] ) . '"', $html, 1 );
	if ( ! empty( $d['data-imgwh'] ) && preg_match( '/^(\d+)x(\d+)$/', $d['data-imgwh'], $m ) ) {
		$html = preg_replace( '/\s(width|height)="\d+"/', '', $html );
		$html = preg_replace( '/<img\b/', '<img width="' . $m[1] . '" height="' . $m[2] . '"', $html, 1 );
		$html = preg_replace( '/\ssizes="[^"]*"/', ' sizes="(max-width: ' . $m[1] . 'px) 100vw, ' . $m[1] . 'px"', $html );
	}
	if ( ! empty( $d['data-imgeager'] ) ) { $html = str_replace( ' loading="lazy"', '', $html ); $html = preg_replace( '/<img\b/', '<img fetchpriority="high"', $html, 1 ); }
	return $html;
}, 10, 2 );

/* SEO: the original page titles, descriptions, robots and social tags (page meta _xhd_seo). */
function xhd_seo() { return is_singular() ? (array) get_post_meta( get_queried_object_id(), '_xhd_seo', true ) : array(); }
add_filter( 'pre_get_document_title', function ( $t ) { $s = xhd_seo(); return ! empty( $s['title'] ) ? $s['title'] : $t; }, 20 );
add_filter( 'wp_robots', function ( $r ) {
	$s = xhd_seo();
	if ( ! empty( $s['robots'] ) && false !== strpos( $s['robots'], 'noindex' ) ) { $r['noindex'] = true; $r['nofollow'] = true; unset( $r['max-image-preview'] ); }
	elseif ( ! empty( $s['robots'] ) ) { $r['index'] = true; $r['follow'] = true; $r['max-image-preview'] = 'large'; $r['max-snippet'] = '-1'; $r['max-video-preview'] = '-1'; }
	return $r;
} );
add_action( 'wp_head', function () {
	$s = xhd_seo(); if ( empty( $s['title'] ) ) return;
	$logo = (int) get_theme_mod( 'custom_logo' ) ?: (int) ( get_option( 'xhd_logo_id' ) );
	$img = $logo ? wp_get_attachment_url( $logo ) : '';
	$url = get_permalink( get_queried_object_id() );
	if ( ! empty( $s['desc'] ) ) printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $s['desc'] ) );
	foreach ( array( 'og:type' => 'website', 'og:title' => $s['title'], 'og:description' => $s['desc'] ?? '', 'og:url' => $url, 'og:site_name' => get_bloginfo( 'name' ), 'og:image' => $img, 'og:locale' => 'en' ) as $p => $v ) if ( $v ) printf( "<meta property=\"%s\" content=\"%s\">\n", esc_attr( $p ), esc_attr( $v ) );
	foreach ( array( 'twitter:card' => 'summary_large_image', 'twitter:title' => $s['title'], 'twitter:description' => $s['desc'] ?? '', 'twitter:image' => $img ) as $p => $v ) if ( $v ) printf( "<meta name=\"%s\" content=\"%s\">\n", esc_attr( $p ), esc_attr( $v ) );
}, 2 );

/* The canonical URL of a plan checkout is /checkout/<id>/ (not /checkout/plan-<id>/). */
add_filter( 'page_link', function ( $link, $id ) {
	$p = get_post( $id );
	if ( $p && $p->post_parent && preg_match( '/^plan-([0-9]+)$/', $p->post_name, $m ) && 'checkout' === get_post_field( 'post_name', $p->post_parent ) ) return home_url( '/checkout/' . $m[1] . '/' );
	return $link;
}, 10, 2 );
