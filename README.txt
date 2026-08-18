=== Elementor Restriction Manager ===
Contributors: sflwa
Tags: elementor, wp-cli, display-conditions, migration, restrict-for-elementor
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automate the migration from the legacy "Restrict for Elementor" plugin to Elementor's native Display Conditions using WP-CLI.

== Description ==

Elementor Restriction Manager is a lightweight developer tool (usable as an Must-Use / MU-Plugin or standard plugin) that provides custom WP-CLI commands to locate, back up, migrate, and clean up legacy restriction meta data across your WordPress site.

It scans `_elementor_data` inside `wp_postmeta`, detects legacy `restrict_for_elementor_*` keys (including orphaned metadata), maps them 1:1 to native Elementor Display Conditions (`e_display_conditions`), and automatically creates safety backups.

= Features =
* **Scan & Detect:** Find all pages, posts, and specific widget IDs still utilizing legacy restrictions or carrying orphaned plugin settings.
* **1:1 Migration:** Converts roles (`user_role`), logged-in status (`logged_in_users`), and logged-out status (`logged_out_users`) to native Elementor Display Conditions.
* **Automated Backups:** Automatically clones `_elementor_data` to `_elementor_data_backup_legacy` prior to running updates.
* **Cache Management:** Automatically flushes post-specific and global Elementor CSS caches upon migration or rollback.
* **Safety Rollback:** One-command restoration to revert changes if needed.

== Installation ==

= As a Must-Use (MU) Plugin (Recommended) =
1. Download `elementor-restriction-manager.php`.
2. Upload the file directly to your site's `wp-content/mu-plugins/` directory via SSH, FTP, or WP-CLI.
3. No activation is required; the WP-CLI commands will instantly be available.

= As a Standard Plugin =
1. Download or clone this repository into `wp-content/plugins/elementor-restriction-manager`.
2. Activate the plugin via the WordPress Admin dashboard or via WP-CLI:
   `wp plugin activate elementor-restriction-manager`

== WP-CLI Commands & Usage ==

= 1. Scan for Legacy Restrictions =
Locates all posts and specific Elementor elements containing legacy restrictions or orphaned settings:
`wp find-restrictions`

= 2. Dry Run (Preview Migration) =
Test the migration process without modifying the database:
`wp migrate-elementor-restrictions --dry-run`

= 3. Execute Migration =
Converts legacy restrictions to Elementor's native Display Conditions, cleans up old metadata keys, clears Elementor CSS cache, and creates an automatic backup:
`wp migrate-elementor-restrictions`

*Optional Flags:*
* `--skip-backup` : Runs the migration without saving a backup postmeta key.

= 4. Rollback / Undo Migration =
Restores the original `_elementor_data` state from `_elementor_data_backup_legacy`:
`wp rollback-elementor-restrictions`

== Post-Migration Cleanup ==

Once you have verified that your elements display correctly under native Elementor Display Conditions, you can safely deactivate and remove the legacy plugin:

`wp plugin deactivate restrict-for-elementor`
`wp plugin delete restrict-for-elementor`

To remove the temporary backup meta keys created during migration, run:
`wp db query "DELETE FROM $(wp db prefix)postmeta WHERE meta_key = '_elementor_data_backup_legacy';"`

== Changelog ==

= 1.0.0 =
* Initial release with WP-CLI scan, migration, cleanup, and rollback support.
* Full 1:1 mapping for user roles, logged-in users, and guest conditions.
* Orphaned setting key removal support.
