# API Reference

## Overview

Labgenz Community Management provides several extensibility points for developers, including WordPress hooks, filters, AJAX endpoints, and PHP classes. This document outlines the main APIs and how to use them.

---

## PHP Classes

- **\LABGENZ_CM\Core\LabgenzCmLoader**: Main plugin loader and component manager.
- **\LABGENZ_CM\Core\AssetsManager**: Handles registration and enqueueing of admin/public assets.
- **\LABGENZ_CM\Core\Settings**: Manages plugin options and settings.
- **\LABGENZ_CM\Core\AppearanceSettingsHandler**: Handles appearance settings.
- **\LABGENZ_Core\AjaxHandler**: Handles registering ajax actions and security checks
- **\LABGENZ_CM\Core\InviteHandler**: Manages group invitations and related AJAX actions.

---

## AJAX Endpoints

All AJAX endpoints are registered with security nonces. Example endpoints:

- `labgenz_save_appearance_settings`
- `labgenz_reset_appearance_settings`
- `labgenz_cm_save_menu_settings`
- `labgenz_invite_user`

Use `admin-ajax.php` and pass the required nonce for each action.

---

## Hooks & Filters

- `labgenz_cm_loaded`: Fires after all plugin components are loaded.
- `bp_located_template`: Used to override BuddyBoss/BuddyPress templates.

You can add your own hooks or listen to these in your custom code.

---

## Shortcodes & Widgets

Shortcodes and widgets are available for embedding community features. See the source code in `src/public/Shortcodes.php` and `src/public/Widgets.php` for details.

---

For more details, explore the PHPDoc comments in each class or contact the plugin author.
