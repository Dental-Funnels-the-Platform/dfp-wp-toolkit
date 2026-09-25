# AGENTS.md

- This repo is public: never commit secrets, API keys, passwords, client data, or
  client URLs.
- Each mu-plugin is one self-contained PHP file with a full plugin header,
  `defined( 'ABSPATH' ) || exit;`, `dim_`-prefixed functions, and no Composer.
- Must run on PHP 7.4 to 8.4 and not break wp-admin, REST for logged-in users, or
  sites without Rank Math/Yoast.
- Any plugin change: bump `Version:` and the version constant, add a CHANGELOG
  entry.
- Run `php -l` on every PHP file before committing.
- WP-CLI helpers: ASCII only, safe to rerun, always with a dry-run version.
