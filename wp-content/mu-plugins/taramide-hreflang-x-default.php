<?php
/**
 * Plugin Name: Taramide – hreflang x-default
 * Description: Adds <link rel="alternate" hreflang="x-default"> pointing to the English version of each page (Polylang free only does this on the home page when the default language has a URL prefix).
 */
add_filter( 'pll_rel_hreflang_attributes', function ( $hreflangs ) {
	if ( isset( $hreflangs['x-default'] ) ) return $hreflangs;
	foreach ( array( 'en', 'en-US', 'en_US' ) as $k ) {
		if ( ! empty( $hreflangs[ $k ] ) ) { $hreflangs['x-default'] = $hreflangs[ $k ]; break; }
	}
	return $hreflangs;
} );
