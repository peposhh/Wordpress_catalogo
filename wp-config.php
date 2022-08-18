<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'catalogo' );

/** Database username */
define( 'DB_USER', 'catalogo' );

/** Database password */
define( 'DB_PASSWORD', 'qxe#90E00Hn#' );

/** Database hostname */
define( 'DB_HOST', 'localhost:3307' );

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
define( 'AUTH_KEY',         '& 7N9$=^L,X<:{lx/-2.6(%x:bUe@<!OS%_Vhbvh)#^9qjJ9gqikz&9f0-LQ-B2D' );
define( 'SECURE_AUTH_KEY',  'ZyHygGS67A,sLTVn;Gp_4 %1tHD-JQWDdRY?z!0t5(5eLrpf=m{J6:S> QQdAKPp' );
define( 'LOGGED_IN_KEY',    'Pz|P>~x@LB`v5f,`BJ~PN=>Fr(50bS&#rne%i4Qpw<%vWN*^#2,wxqLs;>{r}x|/' );
define( 'NONCE_KEY',        '&vxMCr)L8,zmqp+t8y`x|n[6YC_]Y%q+wC$=:Cq:]kMt<{RcLdwQvn tt{j,<L0|' );
define( 'AUTH_SALT',        'o -@duTYYly%jG5GoNKHDXXwgi|/WtK$]@>,kZi2<K[aDwBSGE%:AIvaz{ET+rpz' );
define( 'SECURE_AUTH_SALT', 's $L+;^PA4}>!H6@vY#U&L1ffkG^@!|;31a7QW$v_jjr}tdD5&+t6JG@pyWY{nj]' );
define( 'LOGGED_IN_SALT',   'VB}(-QY?NO?G|IyRQgEj}Ahdvx5*!p~vw]-dp/]x&Hl%Vdjov5OhB]Hm1N=A{eFP' );
define( 'NONCE_SALT',       'G?-R/=/0qtx6~()Tqd[?9f?+@28elfP2(L% LxfVs~/iWc&D4}h:5HJ`jc<%EjDj' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_catalogo';

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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
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
