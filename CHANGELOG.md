# Changelog

## 5.0.5 - 2026-07-22

### Fixed

- The Edits tab no longer fills up with autosaves. Provisional and explicit drafts fire `Element::EVENT_AFTER_SAVE` every few seconds while an entry is open in the CP; only saves of the canonical element are recorded now.
- Saving an element on a multi-site install recorded one edit per propagated site. Propagated saves are now skipped, so a save shows up once.
- Bulk resave jobs no longer flood the Edits tab with elements the editor never touched.
- `PuppyConfig.actionUrl` was hardcoded to `/actions/puppy/session`, which was wrong for sites using a custom `actionTrigger` or a subfolder install. It's now built with `UrlHelper::actionUrl()`.
- Trail items are now held to the length limits `TrailItem` declares. Labels and context are truncated to 255 characters (multibyte-safe) and items with a URL over 2,048 characters are skipped, so an oversized value can't bloat the session.
- Corrected the `developerUrl` in `composer.json`.

### Added

- DDEV environment and a Codeception test suite covering the Trail service, TrailItem model, SessionController and the element-save listener.

## 5.0.4 - 2026-04-15

### Fixed

- Puppy panel now stays fully visible within the viewport. Added edge-aware clamping during drag, on window resize, on initial load, and when toggling collapsed/expanded state.

## 5.0.3 - 2026-04-15

### Changed

- Puppy panel header paw logo (🐾) now renders in white to match the header text, using a `brightness(0) invert(1)` filter on `.puppy-logo`.

## 5.0.2 - 2026-04-10

### Fixed

- Login-page guard (the overlay fix): In src/Plugin.php, the asset bundle and PuppyConfig JS are now skipped when Craft::$app->getUser()->getIsGuest() is true. This is checked inside the EVENT_BEFORE_RENDER_TEMPLATE handler (not init()), so identity is resolved by then. Login, forgot-password, set-password, verify-email all render as guest, so Puppy stays hidden. The element-save listener also bails for guests.

### Added
- Craft coding-guideline fixes (verified against the 5.x docs):

- src/Plugin.php — added @property-read Trail $trail docblock so $this->trail is IDE-typed (Craft guideline for app-component-style access). Reordered the early-return (console check first), and added trailing commas on multi-line arguments per PSR-12/Craft style.
- src/controllers/SessionController.php — added $this->requireAcceptsJson() to all three actions, matching the guideline "JSON-only actions require requireAcceptsJson()". The JS already sends Accept: application/json, so no frontend change needed.

## 5.0.1 - 2026-03-24

### Fixed

- Corrected license to Craft license

## 5.0.0 - 2026-03-24

### Added

- Initial release of Craft Puppy for Craft CMS 5.
