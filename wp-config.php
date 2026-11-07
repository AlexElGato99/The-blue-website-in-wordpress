<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpresscanada' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'Qw`$,9Ap@sH^_#FD }r`HUJKzWNI3o !&@z`>/6cQpq%rQi0~XDCYKca/%d{}C(<' );
define( 'SECURE_AUTH_KEY',  'zv$h<7tNM8TkvMM5pFQj]R1FH<ly6(TSN $wx-s5kl`]fT@6?+-F3B-J^y8B!58-' );
define( 'LOGGED_IN_KEY',    '<LcmBG4W|cAe9YnzG(-kiyd,Iwg3dRGg. LwV_s?g3oP@&|@tYZ}6qY2QY<c)e+m' );
define( 'NONCE_KEY',        'Wp=87Z7bHTM)!Q);+Cu!;kXli/Lg076vr]SI-;7F oUeA0!rkxnccffeeQP1J|<J' );
define( 'AUTH_SALT',        'XX l*[aSR$rvmFAE<=mxl=5]`QIn@-nU$P}o1sS##AK),iu&,zyf=+d^]^U>W;Yy' );
define( 'SECURE_AUTH_SALT', '+y_Cr&Iy::*eJH]:PQvY7^snD)9bo.G.*ebh]MXv$r8]Wi,@`QF}i ]Kx$$?Fxoh' );
define( 'LOGGED_IN_SALT',   'K*!Nf4fVaSdbVtk:S)qjZ0XOyKe/a,lSoB4YFM: T(MR2Ci<;rYZW[nYcA)}c##Z' );
define( 'NONCE_SALT',       'pV/0?e;lkYRPnV[U 5^gFm?aP2oxw#_2UH+mA(FVOoK3-2/~q-yiD,p;cNGUhEmE' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
