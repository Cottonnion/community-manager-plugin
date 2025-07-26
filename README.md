# Labgenz Community Management

A modern WordPress plugin for advanced community management, group invitations, and appearance customization. Built for BuddyBoss/BuddyPress-based communities.

## Features

- **Group Management**: Manage group members, invitations, and roles with a user-friendly interface.
- **Custom Appearance**: Live preview and customization of colors, typography, layout, and component styles from the admin dashboard.
- **AJAX-powered Settings**: Real-time saving and feedback for appearance and settings changes.
- **BuddyBoss/BuddyPress Integration**: Custom templates and assets for group pages, tabs, and modals.
- **Shortcodes & Widgets**: Easily embed community features anywhere.
- **Export/Import**: Export and import appearance settings as JSON.
- **Developer Friendly**: Modular codebase, hooks, and filters for extensibility.

## Installation

1. Upload the plugin to your WordPress `/wp-content/plugins/` directory.
2. Activate via the Plugins menu.
3. Access settings under **Labgenz Community** in the WordPress admin sidebar.

## Usage

- **Appearance Settings**: Go to *Labgenz Community > Appearance* to customize colors, fonts, and layout. Use the live preview to see changes instantly.
- **Group Management**: Manage group members, send invitations, and assign roles from the group page tabs.
- **Shortcodes**: Use provided shortcodes to embed community features (see documentation for details).

## Configuration

- **Settings**: Configure plugin options under *Labgenz Community > Settings*.
- **Appearance**: Customize via *Appearance* tab. Options include:
  - Primary, secondary, accent, success, warning, background, text, and border colors
  - Font family and size
  - Border radius
  - Button, table, and modal styles
- **Export/Import**: Use the buttons at the bottom of the Appearance page to export or import settings.

## Developer Notes

- **Hooks & Filters**: Extend plugin functionality using WordPress hooks and filters provided throughout the codebase.
- **AJAX**: All settings changes use secure AJAX endpoints with nonces.
- **Templates**: Custom BuddyBoss/BuddyPress templates are located in `templates/buddypress/`.
- **Assets**: Admin and public assets are managed and enqueued via the `AssetsManager` class.
- **Logs**: Debug logs are written to `src/logs/` for AJAX and template checks.

## Uninstall

- Use the standard WordPress plugin uninstall process. The plugin will clean up its options and generated files.

## Requirements

- WordPress 5.6+
- BuddyBoss or BuddyPress (for group features)
- PHP 7.4+

## Credits

- Developed by Labgenz
- [BuddyBoss](https://www.buddyboss.com/) and [BuddyPress](https://buddypress.org/) for integration

## License

GPL-2.0+

---
For detailed API reference and advanced configuration, see the `docs/` folder.
