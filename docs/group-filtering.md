# Group Filtering in Labgenz Community Management

This document explains how group filtering works in the Labgenz Community Management plugin.

## Overview

The `GroupFiltersHandler` class provides functionality to filter groups in the BuddyPress/BuddyBoss groups directory so that users only see groups they have access to. This includes:

1. Groups the user is a member of
2. Parent groups of the user's groups

## How It Works

The filtering is implemented through two WordPress filters:

1. `bp_ajax_querystring` - For AJAX-based group queries (e.g., when filtering or paginating)
2. `bp_groups_get_groups_args` - For direct BP groups queries

When a user views the groups directory, the plugin modifies the query to only include groups the user is a member of and any parent groups of those groups.

## Implementation Details

### Group Relationships

The plugin respects BuddyBoss's hierarchical group structure. If a user is a member of a child group, they'll also see the parent group in their groups directory.

### User Access Logic

Access to a group is determined by:

1. Is the user a member of this group? OR
2. Is the user a member of a child group of this group?

Administrators see all groups regardless of membership status.

### Helper Class

The `GroupHelpers` class provides utility methods for working with groups:

- `get_user_accessible_group_ids()` - Gets all groups a user can access
- `get_parent_group_id()` - Gets the parent group for a given group
- `get_child_group_ids()` - Gets all child groups for a given parent group
- `can_user_access_group()` - Checks if a user can access a specific group

## Customization

### Filter Hook: `labgenz_cm_user_accessible_group_ids`

You can use this filter to modify which groups a user can access:

```php
add_filter('labgenz_cm_user_accessible_group_ids', function($group_ids, $user_id) {
    // Add or remove group IDs as needed
    return $group_ids;
}, 10, 2);
```

## Debugging

When `SCRIPT_DEBUG` is enabled in wp-config.php, the plugin will output debug information at the bottom of group directory pages, showing:

- User's groups
- Parent groups 
- All accessible groups

## Example Use Cases

1. **Organization Hierarchy**: If you have groups representing departments in an organization, with sub-groups for teams, managers who are members of the department groups can see all team groups.

2. **Educational Institutions**: A school might have grade-level groups with classroom sub-groups, allowing teachers and administrators to access all relevant groups.

3. **Multi-level Marketing**: Upline members can see their downline groups without needing to be direct members of each group.
