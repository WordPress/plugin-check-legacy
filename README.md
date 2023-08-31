Plugin Check
===============
* Contributors: 
* Requires at least: 6.2
* Tested up to: 6.2
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

Plugin Check is a tool for the WordPress.org plugins review team.

## Description #

Plugin Check is a tool for the WordPress.org plugins review team.
This is intended as a generic tool that is not at all intended on being complete.

Setup steps:
 - `npm install`
 - `npm run wp-env start`
 - `npm run setup:tools`

Commands:
 - `npm run wp-env start`
 - `npm test`

## Changelog ##

= [1.0.0] TBD =

* Feature - Enable modification of the PHP Binary path used by the plugin with `PLUGIN_CHECK_PHP_BIN` constant.
* Tweak - Disallow functions `move_uploaded_file`, `passthru`, `proc_open` - Props alexsanford at [#50](https://github.com/WordPress/plugin-check/pull/50)
