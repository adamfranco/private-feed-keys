=== Plugin Name ===
Contributors: adamfranco
Tags: rss, feed, feeds, feedkey, key, access, privacy, private, multisite, members only
Requires at least: 3.0
Tested up to: 4.0
Stable tag: 1.1.2

Allows subscription to RSS feeds on private blogs that require authentication. Works with "More Privacy Options" on multi-site installs.

== Description ==

This plugin allows users to subscribe to feeds requiring authentication. When using [More Privacy Options](http://wordpress.org/extend/plugins/more-privacy-options/) in a multi-site installation RSS feeds for blogs marked as private require authentication. This plugin adds a user and site specific 40-character key on private blogs, creating a unique feed URL for each registered on user the blog. This allows feeds on private blogs to be subscribed to using feed readers that do not support authentication. As well, this allows subscription on sites where local HTTP authentication of feeds is not possible, such as those that use CAS or OpenId to authenticate users.

This plugin is similar in concept to the [Feed Key](http://code.andrewhamilton.net/wordpress/plugins/feed-key/) plugin, but designed from the ground up to operate in a multi-site context where access is controlled by the [More Privacy Options](http://wordpress.org/extend/plugins/more-privacy-options/) plugin.

Primary differences from Feed Key:

* Only adds keys to the feed URLs on private sites, not all sites in the network.
* Keys are per site and per user, preventing exposure of the key for a single site from giving access to other sites the user can see.
* Presence of a feed key authenticates feed requests as the user that matches the key rather than blocking requests that don't include a feed key.
* If no key is present the RSS feed request continues without interference for handling by other authentication hooks.
* Access control is still determined by other authentication plugins, ensuring that if a user is removed as a subscriber of a private blog, access to the feed will be denied.
* Users can revoke their own keys on a per-blog basis.

Contributors and developers, please submit issues and pull-requests via [the Github repository](https://github.com/adamfranco/private-feed-keys).

Licensed under the [GNU General Public License 2.0 (GPL)](http://www.gnu.org/licenses/gpl.html)

== Installation ==

= Standard installation =

1. Install and network-activate the [More Privacy Options](http://wordpress.org/extend/plugins/more-privacy-options/) plugin
1. Download the `private-feed-keys.x.x.zip` file to your `wp-content/plugins/` directory and unzip it.
1. Network-activate the plugin through the SuperAdmin-Plugins menu in WordPress
1. Feed keys will be added to feed urls when individual blog privacy options are set to "Subscribers Only" or greater on the Settings-Privacy page
1. Users (and network admins) can revoke their keys from individual blogs via the users' profile page.

= Developer installation =

In place of step #2 above, `cd wp-content/plugins/` then run `git clone https://github.com/adamfranco/private-feed-keys.git` .

== Frequently Asked Questions ==

= If someone finds out my key, can they use it to access my other sites on the network? =

No, keys are per-user and per-site.

= I removed a subscriber from my private site, will they still see updates? =

No, the feed keys just authenticate the user, they still are checked against the subscriber list by [More Privacy Options](http://wordpress.org/extend/plugins/more-privacy-options/) before showing them content.

= Will my feed key let me edit without logging in? =

No, the key only grants access to feeds, nothing more.

= I found a bug, how can I report it? =

Please create an issue at the [project space on Github](https://github.com/adamfranco/private-feed-keys/issues). If you have a fix for your issue, please submit it as a [pull request](https://help.github.com/articles/using-pull-requests/) via Github.

== Screenshots ==

1. The user profile page with a listing of the user's feed keys and the ability to revoke them.

== Changelog ==


= 1.1.2 =
* Fixed needle/haystack mis-ordering [found by lupolio](https://wordpress.org/support/topic/smal-bug-fix?replies=1
).

= 1.1.1 =
* No code changes, just updated the README.txt to reflect support of WordPress 4.0 and link to Github.

= 1.1 =
* [Fix](https://github.com/adamfranco/private-feed-keys/issues/1) to work with [More Privacy Options](http://wordpress.org/extend/plugins/more-privacy-options/) 3.2.1.5 and up. 

= 1.0 =
* Initial Release.
