# Rollout on Pressable

Pressable doesn't support .htaccess or custom nginx rules, so this is done in PHP.

Raw URL:
https://raw.githubusercontent.com/Dental-Funnels-the-Platform/dfp-wp-toolkit/main/mu-plugins/dfp-hide-author.php

1. Test on one staging site: copy `mu-plugins/dfp-hide-author.php` to
   `wp-content/mu-plugins/`. Delete it before step 2, otherwise the site loads
   the plugin twice and crashes.
2. In My Pressable > MU Plugins, add the raw URL and tick "Existing sites" and
   "Future sites".
3. Run `wp-cli/fix-display-names-apply.txt` on each site (Pressable bookmark
   "DFP – Display Names Fix (Apply)").
   Note: to preview first, run `wp-cli/fix-display-names-dry-run.txt`.
4. Purge the edge cache and object cache on each site.
5. Check while logged out:
   - `/?author=1` redirects home
   - `/wp-json/wp/v2/users` shows no emails or usernames as names
   - `/wp-json/oembed/1.0/embed?url=<page>` has no `author_name` for pages
6. To update: bump `Version:` in the plugin header and
   `DIM_AUTHOR_PRIVACY_VERSION`, add a CHANGELOG entry, push to main, then click
   Update on the MU plugin in Pressable.
