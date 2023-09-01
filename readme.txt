Plugin Check
===============
* Contributors: dd32, davidperezgar, bordoni
* Requires at least: 6.2
* Tested up to: 6.3
* Stable tag: 1.0.0
* License: GPLv2 or later
* Requires PHP: 7.2
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

Plugin Check is a tool from the WordPress.org plugin review team, it provides an initial check of whether your plugin meets our requirements for hosting.

== Description ==

Plugin Check is a tool from the WordPress.org plugin review team.
It provides an initial check of whether your plugin meets our requirements for hosting.

Development occurs within https://github.com/WordPress/plugin-check/, please submit PRs and Bug Reports there.

== Changelog ==

= [1.0.0] TBD =

* Feature - Enable modification of the PHP Binary path used by the plugin with `PLUGIN_CHECK_PHP_BIN` constant.
* Tweak - Disallow functions `move_uploaded_file`, `passthru`, `proc_open` - Props alexsanford at [#50](https://github.com/WordPress/plugin-check/pull/50)
