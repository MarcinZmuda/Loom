# Changelog

All notable changes to LOOM are documented in this file.

## [2.2.0]  -  2026-03-05

### Added
- Google Search Console integration (Service Account JWT auth  -  paste JSON, done)
- Striking distance detection  -  pages at position 5-20 get automatic priority boost
- 9th composite dimension: GSC boost (position, impressions, CTR)
- Money page system  -  mark conversion pages, track link goals, anchor diversity monitoring
- Force-directed graph visualization with 7 node types (hub, normal, orphan, dead-end, bridge, striking, money)
- 6-tab dashboard: Overview, Money Pages, Striking Distance, Graph, Posts, Settings
- Interactive weight sliders for all 9 scoring dimensions with live normalization
- Per-post metabox with GSC metrics, keyword sources, anchor distribution bars
- One-click removal of ALL LOOM-inserted links (Settings -> Danger Zone)
- Keyword enrichment from GSC real search queries (layer 4)
- Link velocity dimension (replaces equity  -  measures link acquisition rate vs page age)
- Pillar page detection from loom_clusters table
- Upgrade migration with automatic weight re-normalization
- WordPress admin notices moved above LOOM dashboard (no overlap)

### Fixed
- `get_all_with_embeddings()` was missing 9 columns  -  money page and GSC data never reached composite scoring
- Inline embedding used different formula than batch (title×1 vs title×3)
- `$incoming` undefined in `format_for_prompt()`  -  deficit always equaled goal
- `cluster_boost` exceeded 0.0-1.0 range (was 1.5 for pillar pages)
- `is_pillar` was dead code  -  column didn't exist in loom_index
- Triple duplicate DROP TABLE in uninstall.php
- Money toggle used wrong AJAX variable (`ajaxurl` instead of `loom_ajax.ajaxurl`)

### Changed
- GSC auth: OAuth2 (6 steps) -> Service Account (paste JSON, 1 step)
- Equity dimension replaced with Link Velocity (no longer duplicates orphan signal)
- Default weights rebalanced for 9 dimensions (sum = 1.00)
- CSS redesigned: DM Sans font, teal palette, new badge system
- Dashboard completely rewritten with tabbed interface
