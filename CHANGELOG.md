# Changelog

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.0] - 2026-09-25

### Added

- `mu-plugins/dfp-hide-author.php`: hides author info from logged-out visitors
  (`?author=N`, author archives, REST user list, oEmbed, Rank Math/Yoast author
  schema, page bylines, author sitemaps) and replaces email/username display
  names with the site title. Blog posts keep their author.
- `wp-cli/fix-display-names-*.txt`: dry-run and apply commands.
