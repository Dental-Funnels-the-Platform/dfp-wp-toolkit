# WP-CLI

Fix unsafe display names (emails / login names).

- `fix-display-names-dry-run.txt`: lists what would change, changes nothing.
- `fix-display-names-apply.txt`: applies the change.

Check afterwards:

```
wp user list --fields=ID,user_login,display_name
```

Notes:

- Safe to run more than once: a second run finds nothing to change.
- Changes only display_name and nickname. Logins, emails, passwords and roles are
  untouched; no emails are sent.
- A site title with "&" is saved as "&amp;" in the database. That's normal
  WordPress behavior and it shows as "&" on the site.
- Run it on each site, e.g. as a Pressable bulk WP-CLI command, after checking the
  dry run on one site.
