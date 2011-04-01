Plugin Name: Private Feed Keys
Plugin URI: https://github.com/adamfranco/private-feed-keys
Description: Allows subscription of feeds requiring authentication. When using "More Privacy
Options" in a multi-site installation RSS feeds for blogs marked as private require
authethentication. This plugin adds a user and site specific 32bit (or 40bit) key on private
blogs, creating a unique feed url for each registered on user the site. This allows feeds on
private blogs to be subscribed to using feed readers that do not support authencation or on
sites that do not use local authentciation.
Version: 0.1
Author: Adam Franco 
Author URI: http://www.adamfranco.com/
Licensed under the The GNU General Public License 2.0 (GPL)
http://www.gnu.org/licenses/gpl.html

This plugin is similar in concept to the "Feed Key" plugin
(http://code.andrewhamilton.net/wordpress/plugins/feed-key/), but designed from the ground up
to operate in a multi-site context where access is controlled by the "More Privacy Options"
plugin.

Primary differences from Feed Key:
 - Only adds keys to the feed URLs on private sites, not all sites in the network.
 - Keys are per site and per user, preventing exposure of the key for a single site from
   giving access to other sites the user can see.
 - Presence of a feed key authenticates feed requests as the user that matches the key rather
   than blocking requests that don't include a feed key.
 - If no key is present the RSS feed request continues without interference for handling by
   other authentication hooks.
 - Access control is still determined by other authentication plugins, ensuring that if a user
   is removed as a subscriber of a private blog, access to the feed will be denied.
 - Users can revoke their own keys on a per-blog basis.