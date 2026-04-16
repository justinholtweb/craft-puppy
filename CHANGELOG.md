# Changelog

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
