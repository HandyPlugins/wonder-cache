=== Wonder Cache ===
Contributors: m_uysl, handyplugins
Tags: wondercache, cache, speed, performance, batcache
Requires at least: 4.7
Requires PHP: 5.6
Tested up to: 6.6
Stable tag: 0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://handyplugins.co/donate/

A simple yet powerful caching plugin. It powers and abilities include: superhuman strength and durability.

== Description ==

Wonder Cache is a dead-simple yet powerful caching plugin that includes superhuman strength and durability.
It's forked from [Batcache](https://github.com/Automattic/batcache) the major difference is; Wonder Cache uses server disk space instead of the persistent object cache.


Wonder Cache is aimed at preventing a flood of traffic from breaking your site. It does this by serving old pages to new users.
This reduces the demand on the web server CPU and the database. It also means some people may see a page that is a few minutes old.
However this only applies to people who have not interacted with your web site before.
Once they have logged in or left a comment they will always get fresh pages.

If you like our Wonder Cache, then consider checking out our other projects:

* <a href="https://poweredcache.com/" rel="friend">Powered Cache</a> – Caching and Optimization for WordPress – Easily Improve PageSpeed & Web Vitals Score.
* <a href="https://handyplugins.co/magic-login-pro/" rel="friend">Magic Login Pro</a> – Easy, secure, and passwordless authentication for WordPress.
* <a href="https://handyplugins.co/easy-text-to-speech/" rel="friend">Easy Text-to-Speech for WordPress</a> – Transform your textual content into high-quality synthesized speech with Amazon Polly.
* <a href="https://handyplugins.co/handywriter/" rel="friend">Handywriter</a> – AI-powered writing assistant that can help you create content for your WordPress.
* <a href="https://handyplugins.co/paddlepress-pro/" rel="friend">PaddlePress PRO</a> – Paddle Plugin for WordPress.
* <a href="https://handyplugins.co/wp-accessibility-toolkit/" rel="friend">WP Accessibility Toolkit</a> – A collection of tools to help you make your WordPress more accessible.


= Contributing & Bug Report =

Bug reports and pull requests are welcome on [Github](https://github.com/HandyPlugins/wonder-cache).

== Installation ==

= From within WordPress =
1. Visit 'Plugins > Add New'
2. Search for 'Wonder Cache'
3. Activate Wonder Cache from your Plugins page.
4. That's all.

= Manually =
1. Upload the `wonder-cache` folder to the `/wp-content/plugins/` directory
2. Activate the Wonder Cache plugin through the 'Plugins' menu in WordPress
3. That's all.


== Frequently Asked Questions ==

= Should I use this? =

There are tons of caching plugin on the repository. Wonder Cache is one of simplest and compatible with multisite.
If you don't want to deal with settings or running a multisite on shared hosting or where the persistent object caching is not available, give it a try.


= Why was this written? =

First of all, you need to understand why Batcache had written.

> Batcache was written to help WordPress.com cope with the massive and prolonged traffic spike on Gizmodo's live blog during Apple events. Live blogs were famous for failing under the load of traffic. Gizmodo's live blog stays up because of Batcache.

But, Batcache depends on the object caching and it's not simple to install as a plugin for the end-user. So, Wonder Cache is a fork of Batcache that written for almost same purpose.


= Which one is better? Batcache or Wonder Cache? =

Depends on the situation. If you are running your website on multiple servers and able to configure memcached pool, go with Batcache.
Other than, if you are running on single server or maybe shared server and don't have much memory; use Wonder Cache.


= Where the name comes from? =

It comes from Wonder Woman.


= Is it fastest caching solution? =

Nope! If you are able to configure; go with varnish.
If you are good at the server, you can use nginx micro caching or page caching that supports apache rewrite. (e.g [Powered Cache](https://wordpress.org/plugins/powered-cache/) )
There is always vice/versa when you bring a new tool or adding complexity.



== Changelog ==

= 0.6 (August 07, 2024) =
- Make sure deactivation doesn't cause fatal error when advanced-cache.php overwritten by other plugins.
- Tested with WordPress 6.6

= 0.5 (Oct 25, 2023) =
- Vary the cache when only JSON is accepted.
- PHP 8.2: Enable AllowDynamicProperties in wondercache class.
- Tested with WordPress 6.4.

= 0.4 (Feb 8, 2022) =
- Fix: Never cache a request with X-WP-Nonce header
- Opt-in CORS GET request cache
- Increase timer sensitivity in debug info
- Small tweaks & refactoring
- Tested with WP 5.9

= 0.3 (Sep 2, 2021) =
- Updated & improved coding standards
- Tested with WP 5.8
- Added composer support

= 0.2.1 (Dec 8, 2020) =
- update author info
- tested with WP 5.6

= 0.2.0 (Oct 6, 2019) =
- clean-up cache directory on deactivation
- admin bar button added for flushing cache

= 0.1.0 =
- Initial release
