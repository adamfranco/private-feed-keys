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