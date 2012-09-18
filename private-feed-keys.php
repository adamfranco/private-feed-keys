<?php
/* 
Plugin Name: Private Feed Keys
Plugin URI: https://github.com/adamfranco/private-feed-keys
Description: Allows subscription to RSS feeds on private blogs that require authentication. Works with "More Privacy Options" on multi-site installs.
Version: 1.1
Author: Adam Franco 
Author URI: http://www.adamfranco.com/

This plugin allows users to subscribe to feeds requiring authentication. When using [More Privacy Options](http://wordpress.org/extend/plugins/more-privacy-options/) in a multi-site installation RSS feeds for blogs marked as private require authentication. This plugin adds a user and site specific 40-character key on private blogs, creating a unique feed URL for each registered on user the blog. This allows feeds on private blogs to be subscribed to using feed readers that do not support authentication. As or on sites that do not use local authentication.

This plugin is similar in concept to the [Feed Key](http://code.andrewhamilton.net/wordpress/plugins/feed-key/) plugin, but designed from the ground up to operate in a multi-site context where access is controlled by the [More Privacy Options](http://wordpress.org/extend/plugins/more-privacy-options/) plugin.

Primary differences from Feed Key:

* Only adds keys to the feed URLs on private sites, not all sites in the network.
* Keys are per site and per user, preventing exposure of the key for a single site from giving access to other sites the user can see.
* Presence of a feed key authenticates feed requests as the user that matches the key rather than blocking requests that don't include a feed key.
* If no key is present the RSS feed request continues without interference for handling by other authentication hooks.
* Access control is still determined by other authentication plugins, ensuring that if a user is removed as a subscriber of a private blog, access to the feed will be denied.
* Users can revoke their own keys on a per-blog basis.

Licensed under the [GNU General Public License 2.0 (GPL)](http://www.gnu.org/licenses/gpl.html)

*/ 

// Install actions
register_activation_hook(__FILE__, 'private_feed_keys_install');

// Authentication actions
add_action('wp_authenticate', 'private_feed_keys_authenticate', 9, 2);
// The More Privacy Options plugin checks authentication in send_headers, so be sure to
// authenticate before it.
add_action('send_headers', 'private_feed_keys_authenticate', 9, 2);

// Add filters to include our parameters on feed URLs for authenticated users.
add_filter('feed_link', 'private_feed_keys_filter_link');
add_filter('author_feed_link', 'private_feed_keys_filter_link');
add_filter('category_feed_link', 'private_feed_keys_filter_link');
add_filter('taxonomy_feed_link', 'private_feed_keys_filter_link');
add_filter('post_comments_feed_link', 'private_feed_keys_filter_link');

// User key listing and revocation
add_action('show_user_profile', 'private_feed_keys_edit_user_profile');
add_action('edit_user_profile', 'private_feed_keys_edit_user_profile');
add_action('personal_options_update', 'private_feed_keys_update_user_profile');
add_action('edit_user_profile_update', 'private_feed_keys_update_user_profile');


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
	if (!empty($_GET['FEED_KEY']))
		$feed_key = $_GET['FEED_KEY'];
	else if (!empty($_GET['feed_key']))
		$feed_key = $_GET['feed_key'];
	else
		$feed_key = null;
	
	if (empty($feed_key) || $current_user->ID)
		return;
	
	global $wpdb, $blog_id, $current_user, $wp;
	// The global $wp_query object isn't ready yet, so we'll just use our own for the is_feed test.
	$query = new WP_Query();
	$query->query($wp->query_vars);
	if ($query->is_feed()) {
		
		$table_name = $wpdb->base_prefix . "private_feed_keys";
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT 
				user_id, num_access
			FROM 
				$table_name
			WHERE 
				blog_id = %d
				AND feed_key = %s",
			$blog_id, $feed_key
		));
		if ($row) {
			$user_id = $row->user_id;
			$num_access = intval($row->num_access);
		} else {
			return;
		}
		
		// If we have a valid key, authenticate their user and skip later
		// authentication hooks. If not valid, continue on to other authentication hooks.
		if ($user_id) {
			remove_all_actions('wp_authenticate');
			
			// Authenticate the user.
			wp_set_current_user($user_id );
			
			// Update our counter.
			$wpdb->query($wpdb->prepare(
				"UPDATE
					$table_name
				SET
					last_access = NOW(),
					num_access = %d
				WHERE 
					blog_id = %d
					AND feed_key = %s",
				$num_access + 1, $blog_id, $feed_key
			));
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

/**
 * Add our keys to the user profile screen.
 * 
 * @return void
 */
function private_feed_keys_edit_user_profile ($user) {
	global $wpdb;
	
	print "\n<h3>"._("Private Feed Keys")."</h3>";
	
	if (isset($_SESSION['private_feed_keys_revoked'])) {
		if (is_wp_error($_SESSION['private_feed_keys_revoked'])) {
			print "\n<div class='error'><p>";
			print $_SESSION['private_feed_keys_revoked']->get_error_message();
			print "\n</p></div>";
		} else {
			print "\n<div class='updated'>\n\t<p><strong>";
			print $_SESSION['private_feed_keys_revoked'];
			if ($_SESSION['private_feed_keys_revoked'] > 1)
				print " Feed Keys revoked."; 
			else
				print " Feed Key revoked.";
			print "</strong></p>\n</div>";
		}
		unset($_SESSION['private_feed_keys_revoked']);
	}
	
	print "\n<p class='description'>"._("Below are feed keys that allow you to subscribe to private blogs. A separate key has been generated for each private blog you have visited.")."</p>";
	print "\n<p class='description'>"._("If you have accidentally shared a feed URL for one of these blogs with someone else, you can revoke its key to prevent unauthorized access. Please note that if you revoke a key you will have to resubscribe to any feeds from that blog.")."</p>";
	print "\n<table class='form-table'>";
	print "\n<thead>";
	print "\n\t<tr>";
	print "\n\t\t<th>Blog</th>";
	print "\n\t\t<th>Feed Key</th>";
	print "\n\t\t<th>Date Created</th>";
	print "\n\t\t<th>Last Access</th>";
	print "\n\t\t<th># Accessed</th>";
	print "\n\t\t<th>Actions</th>";
	print "\n\t</tr>";
	print "\n</thead>";
	print "\n<tbody>";
	
	$table_name = $wpdb->base_prefix . "private_feed_keys";
	$results = $wpdb->get_results($wpdb->prepare(
		"SELECT 
			* 
		FROM 
			$table_name 
		WHERE 
			user_id = %d
			AND num_access > 0
		ORDER BY
			last_access
		",
		$user->ID
	));
	if (empty($results)) {
		print "\n\t<tr><td colspan='6' style='text-align: center; font-weight: bold;'>"._("You have no Feed Keys in use.")."</td></tr>";
	} else {
		foreach ($results as $row) {
			$details = get_blog_details($row->blog_id, true);
			print "\n\t<tr>";
			print "\n\t\t<td><a href='".$details->siteurl."' target='_blank'>".$details->blogname."</a></td>";
			print "\n\t\t<td>".$row->feed_key."</td>";
			print "\n\t\t<td>".$row->created."</td>";
			print "\n\t\t<td>".$row->last_access."</td>";
			print "\n\t\t<td>".$row->num_access."</td>";
			print "\n\t\t<td><input type='checkbox' name='private_feed_keys_revoke' value='".$row->blog_id."'/> revoke key</td>";
			print "\n\t</tr>";
		}
	}	
	print "\n</tbody>";
	print "\n</table>";
}

/**
 * Revoke any keys chosen in user-profile saving.
 * 
 * @return boolean
 */
function private_feed_keys_update_user_profile ($user_id) {
	global $wpdb;
	
	if (!current_user_can('edit_user', $user_id))
		return false;
	
	if (isset($_POST['private_feed_keys_revoke'])) {
		if (is_array($_POST['private_feed_keys_revoke'])) {
			$blog_ids = $_POST['private_feed_keys_revoke'];
			foreach ($blog_ids as $key => $val) {
				$blog_ids[$key] = intval($val);
			}
		} else {
			$blog_ids = array(intval($_POST['private_feed_keys_revoke']));
		}
		if (!count($blog_ids))
			return false;
		
		// Delete the keys
		$table_name = $wpdb->base_prefix . "private_feed_keys";
		$numRevoked = $wpdb->query($wpdb->prepare(
			"DELETE FROM $table_name
			WHERE
				user_id = %d
				AND blog_id IN (".implode(',', $blog_ids).")",
			$user_id
		));
		
		if ($numRevoked) {
			$_SESSION['private_feed_keys_revoked'] = $numRevoked;
			return true;
		} else {
			$_SESSION['private_feed_keys_revoked'] = new WP_Error('private_feed_keys_revoke_failed', __("Failed to revoke keys."));
			return false;
		}
	}
}
