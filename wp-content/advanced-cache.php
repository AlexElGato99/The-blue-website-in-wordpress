<?php
/**
 * Taramide page cache (drop-in, loaded by WordPress when WP_CACHE is true).
 * Serves stored HTML to anonymous GET visitors without booting WordPress.
 * Files are written by mu-plugins/taramide-performance.php and purged on every content save.
 */
if ( defined( 'WP_CLI' ) || PHP_SAPI === 'cli' ) return;
if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! empty( $_SERVER['QUERY_STRING'] ) ) return;
foreach ( array_keys( $_COOKIE ) as $tm_c ) if ( preg_match( '/^(wordpress_logged_in|wordpress_sec|wp-postpass|comment_author)/', $tm_c ) ) return;
$tm_path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
if ( ! $tm_path || '/' !== substr( $tm_path, -1 ) || preg_match( '#/(wp-admin|wp-json|wp-content|wp-includes)/#', $tm_path ) ) return;
$tm_file = WP_CONTENT_DIR . '/cache/tm-page/' . md5( ( $_SERVER['HTTP_HOST'] ?? '' ) . $tm_path ) . '.html';
if ( is_file( $tm_file ) && ( time() - filemtime( $tm_file ) ) < 36000 ) {
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'X-TM-Cache: HIT' );
	header( 'Cache-Control: no-cache' );
	readfile( $tm_file );
	exit;
}
