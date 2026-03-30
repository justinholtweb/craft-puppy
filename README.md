# Puppy for Craft CMS 5

A lightweight control panel companion that follows editors through their session. Puppy displays a draggable, collapsible overlay with a live trail of visited pages, edited elements, and quick links back to recently worked-on locations.

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later

## Installation

Open your terminal and run:

```bash
composer require justinholtweb/craft-puppy
php craft plugin/install puppy
```

Or install via the Craft control panel under **Settings > Plugins**.

## Features

### Session Trail

Puppy records where you go in the control panel and normalizes routes into readable labels:

- `/admin/dashboard` &rarr; Dashboard
- `/admin/entries/blog` &rarr; Entries: Blog
- `/admin/entries/blog/123-spring-launch` &rarr; Spring Launch
- `/admin/assets/images` &rarr; Assets: Images
- `/admin/globals/footerSettings` &rarr; Global: Footer Settings

### Edit Tracking

Puppy listens for element save events on the backend and records them separately from page visits. Each edit captures the element type, title, action (saved or created), and section/volume context.

### Floating Panel

The overlay sits on top of the control panel and includes:

- **Trail tab** &mdash; the last 25 pages visited, with clickable links back
- **Edits tab** &mdash; elements you saved or created during the session
- **Stats tab** &mdash; pages visited, items edited, and session duration

### Collapse and Drag

- Collapse to a small badge showing the paw icon and item count
- Drag the panel anywhere on screen
- Position and state are remembered across page loads via localStorage

### Pause and Clear

- Pause tracking at any time without hiding the panel
- Clear the session trail to start fresh

## How It Works

**Frontend:** A JavaScript module loads on every CP page. It detects the current route, normalizes it into a human-readable label, and sends it to the plugin backend. The panel polls for updates every 10 seconds to pick up backend-recorded edits.

**Backend:** The plugin stores trail and edit data in the PHP session. Element save events are captured via Craft's `Element::EVENT_AFTER_SAVE` and recorded automatically. No database tables are created.

## Storage

All data is stored in the PHP session and disappears when the session ends. UI preferences (panel position, collapsed state, active tab, paused state) are stored in the browser's localStorage.

## Configuration

Puppy works out of the box with no configuration. Install and go.

## Plugin Structure

```
src/
├── Plugin.php                          # Registers asset bundle and event listeners
├── assetbundles/puppy/PuppyAsset.php   # Injects JS and CSS into the CP
├── controllers/SessionController.php   # Endpoints: get-trail, record-visit, clear
├── models/TrailItem.php                # Data model for trail and edit items
├── services/Trail.php                  # Session-based trail storage and normalization
└── resources/
    ├── js/puppy.js                     # Floating panel, drag, tabs, route parsing
    └── css/puppy.css                   # Panel styles
```

## Roadmap

Phase 2:

- Pin favorite items during a session
- Search within the session trail
- Filter by viewed vs. edited
- Auto-group related pages

Phase 3:

- Team mode for admins to see active editors
- Handoff mode for sharing what you worked on
- Optional daily activity digest

## License

See [LICENSE.md](LICENSE.md).
