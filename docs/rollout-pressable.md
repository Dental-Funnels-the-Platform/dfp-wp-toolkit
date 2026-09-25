# Rollout on Pressable

Pressable doesn't support .htaccess or custom nginx rules, so this is done in PHP.

1. Test on one staging site: copy `mu-plugins/dfp-hide-author.php` to
   `wp-content/mu-plugins/`. Remove it again before step 2.
2. In My Pressable > MU Plugins, add the raw URL and tick "Existing sites" and
   "Future sites".
3. Purge the edge cache and object cache on each site.
4. Run the dry run as the bookmark "DFP – Display Names Audit (Dry Run)", then
   the apply as "DFP – Display Names Fix (Apply)".
5. Check while logged out:
   - `/?author=1` redirects home
   - `/wp-json/wp/v2/users` shows no emails
   - `/wp-json/oembed/1.0/embed?url=<page>` has no `author_name` for pages
6. To update: bump `Version:` in the plugin header and
   `DIM_AUTHOR_PRIVACY_VERSION`, add a CHANGELOG entry, push to main, then click
   Update on the MU plugin in Pressable.
