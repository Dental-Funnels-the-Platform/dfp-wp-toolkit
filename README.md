# dfp-wp-toolkit

Shared WordPress helpers from DFP, the website department of Dental Implant
Machine: must-use plugins and WP-CLI commands for sites hosted on Pressable.

This repo is public because Pressable's MU Plugins feature installs plugins from a
public raw URL.

## Layout

```
mu-plugins/   must-use plugins (one PHP file each)
wp-cli/       WP-CLI commands (dry run + apply)
docs/         rollout notes
```

## mu-plugins/dfp-hide-author.php

Hides author info from logged-out visitors:

- `?author=N` lookups (redirect to home)
- author archives
- REST API user list
- oEmbed author data
- Rank Math / Yoast author schema and "Written by" tags
- theme bylines on pages
- author sitemaps

Safety net: display names that are an email or username are replaced with the
site title. Blog posts keep their author.

Raw URL:

```
https://raw.githubusercontent.com/Dental-Funnels-the-Platform/dfp-wp-toolkit/main/mu-plugins/dfp-hide-author.php
```

## wp-cli/fix-display-names

Replaces display names that are an email or username with the site title.
Run the dry run first, then apply. See [wp-cli/README.md](wp-cli/README.md).
