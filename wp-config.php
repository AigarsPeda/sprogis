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
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define( 'AUTH_KEY',          'H4G=3c-JJ+aZ)UyNsMz+wOaB&_zL^%&TeOnm<}mgL8cVW-u|3Hwf~Juh/&~ZV>6X' );
define( 'SECURE_AUTH_KEY',   '!9|~R:lN*r*/|Bu-L)8}3yO=ndSl ZkK&<GgVz?tnqVM=ZAs8%)AO<f6n3#iuTuJ' );
define( 'LOGGED_IN_KEY',     'wW00O`eK`u#.C~GVk&Ga,n;iYY0{SJEVy{43dZdxF>@+h3C !LNzot2p)`mXxm~$' );
define( 'NONCE_KEY',         '=qp,M3$BJfAz.#W{W>DKJN<70g|<);^$]Qu(jG5Z6-=huEnMJ>m]#J7^uPePfLWX' );
define( 'AUTH_SALT',         'p?vxZjM(T/H:rerbL^,<@tpChwgj[{y)!WN:Kg@=E%9PUwfHr[zvVx#^_dNkIz*Z' );
define( 'SECURE_AUTH_SALT',  'w/>#A<v)R)|5&rle&IKr=jt9Yog}VlroK9$^.c!,G5gyJXi}SuGW_xp-<_WsmWY@' );
define( 'LOGGED_IN_SALT',    'm7]TRbs%~6*G<s{VBP/C6.1v #8T .J[=`u *Trc4]L&fa`_Z<:/7GreM1hqL}[L' );
define( 'NONCE_SALT',        '+8R7E.P&11eaUS:PyIb7RncB`TTCe-Q:6F&~nj.g7<6+}S?YO~Q?}lmEU,qeq<DY' );
define( 'WP_CACHE_KEY_SALT', 'z^WE3[r8+.NTN9K1r=/ymNa1VbpjQy5H-/tf<GYzbY]m$/p53=a4.T6(s_cdj?#)' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
