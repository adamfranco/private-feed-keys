<?php
/* 
Plugin Name: Private Feed Keys
Plugin URI: http://www.adamfranco.com/software/private-feed-keys/
Description: When using "More Privacy Options" in a multi-site installation RSS feeds for blogs marked as private require authethentication. This plugin adds a user and site specific 32bit (or 40bit) key on private blogs, creating a unique feed url for each registered on user the site. This allows feeds on private blogs to be subscribed to using feed readers that do not support authencation or on sites that do not use local authentciation.
Version: 0.1
Author: Adam Franco 
Author URI: http://www.adamfranco.com/
Licensed under the The GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html

This plugin is based on the "Feed Key" plugin (http://code.andrewhamilton.net/wordpress/plugins/feed-key/), but reworked to operate in a multi-site context where access is controlled by the "More Privacy Options" plugin. 

Primary differences from Feed Key:
 - Only adds keys to the feed URLs on private sites, not all sites in the network.
 - Keys are per site and per user, preventing exposure of the key for a single site from giving access to other sites the user can see.
 - Rather than blocking requests without a feed key, presence of a feed key authenticates feed requests as the user that matches the key. If no key is present the RSS feed request continues without authentication.
*/ 

// Install actions
register_activation_hook(__FILE__, 'private_feed_keys_install');

// Authentication actions
add_action('wp_authenticate', 'private_feed_keys_authenticate', 9, 2);
// private blog plugin checks authentication in template_redirect, so be sure to
// authenticate before it.
add_action('template_redirect', 'private_feed_keys_authenticate', 9, 2);

// Add filters to include our parameters on feed URLs for authenticated users.
add_filter('feed_link', 'private_feed_keys_filter_link');
add_filter('author_feed_link', 'private_feed_keys_filter_link');
add_filter('category_feed_link', 'private_feed_keys_filter_link');
add_filter('taxonomy_feed_link', 'private_feed_keys_filter_link');
add_filter('post_comments_feed_link', 'private_feed_keys_filter_link');

/**
 * Install hook.
 */
function private_feed_keys_install () {
	global $wpdb;
	$pfk_db_version = '0.1';
	
	$table_name = $wpdb->base_prefix . "private_feed_keys";
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
			blog_id int(11) NOT NULL,
			user_id int(11) NOT NULL,
			feed_key varchar(40) NOT NULL,
			created timestamp NOT NULL default CURRENT_TIMESTAMP,
			last_access timestamp NULL default NULL,
			num_access int(11) NOT NULL default '0',
			PRIMARY KEY  (blog_id,user_id),
			KEY feed_key (feed_key),
			KEY blog_id (blog_id,feed_key)
		);";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		add_option("private_feed_keys_db_version", $pfk_db_version);
	}
}

/**
 * Bypass other authentication if requesting a feed and a valid key is included.
 * 
 * @return void
 */
function private_feed_keys_authenticate () {
	if (is_feed() && $_GET['FEED_KEY']) {
		global $wpdb, $blog_id;
		
		$table_name = $wpdb->base_prefix . "private_feed_keys";
		$user_id = $wpdb->get_var($wpdb->prepare(
			"SELECT 
				user_id 
			FROM 
				$table_name
			WHERE 
				blog_id = %d
				AND feed_key = %s",
			$blog_id, $_GET['FEED_KEY']
		));
		
		// If we have a valid key, authenticate their user and skip later
		// authentication hooks. If not valid, continue on to other authentication hooks.
		if ($user_id) {
			remove_all_actions('wp_authenticate');
			
			// Authenticate the user.
			wp_set_current_user($user_id );
		}
	}
}

/**
 * Add our parameters to the feed link.
 * 
 * @param string $url The feed URL.
 * @param string $type The type of feed (e.g. "rss2", "atom", etc.)
 * @return string
 */
function private_feed_keys_filter_link ($url) {
	global $current_user;
	
	// Don't add feed keys for anonymous users.
	if (!$current_user->ID)
		return $url;
	
	// Don't add feed keys for publicly visible blogs
	// 
	// @todo Make this call pluggable to work with privacy modules other than
	// "More Privacy Options".
	if (intval(get_option('blog_public')) >= -1)
		return $url;
	
	if (strpos('?', $url))
		$url .= '&amp;';
	else
		$url .= '?';
	
	return $url.'FEED_KEY='.private_feed_keys_get_key();
}

/**
 * Answer a feed-key for the current user and current blog.
 * 
 * @return string
 */
function private_feed_keys_get_key () {
	global $current_user, $blog_id, $wpdb;
	if (!$current_user->ID)
		throw new Exception(__FUNCTION__.' should only be called when a user is authenticated.');
	if (!$blog_id)
		throw new Exception(__FUNCTION__.' should only be called when on a blog.');
	
	// Return the existing key if one exists
	$table_name = $wpdb->base_prefix . "private_feed_keys";
	$feed_key = $wpdb->get_var($wpdb->prepare(
		"SELECT 
			feed_key 
		FROM 
			$table_name
		WHERE 
			blog_id = %d
			AND user_id = %d",
		$blog_id, $current_user->ID
	));
	if ($feed_key)
		return $feed_key;
	
	// Generate a new key.
	$feed_key = private_feed_keys_generate();
	$result = $wpdb->insert($table_name, array('blog_id' => $blog_id, 'user_id' => $current_user->ID, 'feed_key' => $feed_key), array('%d', '%d', '%s'));
	if (!$result)
		throw new Exception("Could not insert feed key for ".$blog_id.", ".$current_user->ID);
	
	return $feed_key;
}

/**
 * Generate a new feed key.
 * 
 * @return string
 */
function private_feed_keys_generate () {
	global $current_user, $blog_id;
	return sha1(mt_rand().$blog_id.$current_user->user_login);
}