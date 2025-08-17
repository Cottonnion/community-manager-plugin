<?php
/**
 * Template for displaying the members map
 *
 * @package Labgenz_Community_Management
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Get current group
$group = groups_get_current_group();
?>

<div class="members-map-container">
    
    <div id="members-map-container" style="width: 100%; height: 500px; border: 1px solid #ddd; background: #f9f9f9; position: relative; display: block !important; visibility: visible !important; z-index: 1;">
        <div id="map-init-indicator" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255, 255, 255, 0.9); padding: 10px; border-radius: 4px; z-index: 1000;">
            Map is initializing...
        </div>
    </div>
    
    <div class="members-map-legend">
        <ul>
            <li><span class="legend-admin"></span> <?php _e('Group Leader', 'buddyboss'); ?></li>
            <li><span class="legend-mod"></span> <?php _e('Group Moderator', 'buddyboss'); ?></li>
            <li><span class="legend-member"></span> <?php _e('Group Member', 'buddyboss'); ?></li>
        </ul>
    </div>
    
    <div class="members-map-footer">
        <p class="members-map-note"><?php _e('Note: Only members who have added their location will appear on the map.', 'buddyboss'); ?></p>
    </div>
</div>

<script>
// Debug information
console.log('Members Map Template Loaded');
console.log('MembersMapData:', typeof MembersMapData !== 'undefined' ? MembersMapData : 'Not Available');
console.log('Leaflet available:', typeof L !== 'undefined');
console.log('jQuery available:', typeof jQuery !== 'undefined');

// Manual fallback for MembersMapData if not available
if (typeof MembersMapData === 'undefined') {
    console.log('Creating fallback MembersMapData');
    window.MembersMapData = {
        ajaxurl: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
        group_id: <?php echo bp_get_current_group_id(); ?>,
        nonce: '<?php echo wp_create_nonce( 'members-map-nonce' ); ?>'
    };
    console.log('Fallback MembersMapData created:', window.MembersMapData);
}
</script>

<style>
/* Basic styles for the map container and legend */
.members-map-container {
    margin-bottom: 30px;
}

.members-map-header {
    margin-bottom: 20px;
}

/* Critical map container styles with !important to override theme conflicts */
#members-map {
    width: 100% !important;
    height: 500px !important;
    position: relative !important;
    display: block !important;
    visibility: visible !important;
    z-index: 1 !important;
    border: 1px solid #ddd !important;
    background: #f9f9f9 !important;
}

/* Ensure Leaflet controls are visible */
.leaflet-control-container {
    position: relative !important;
    z-index: 1000 !important;
}

/* Ensure map tiles are visible */
.leaflet-tile-container {
    opacity: 1 !important;
}

/* Ensure the map pane is visible */
.leaflet-map-pane {
    z-index: 1 !important;
}

.members-map-legend {
    margin-top: 15px;
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
}

.members-map-legend ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
}

.members-map-legend li {
    margin-right: 20px;
    display: flex;
    align-items: center;
}

.members-map-legend span {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    margin-right: 5px;
}

.legend-admin {
    background-color: #e74c3c;
}

.legend-mod {
    background-color: #3498db;
}

.legend-member {
    background-color: #2ecc71;
}

.members-map-footer {
    margin-top: 20px;
}

.members-map-note {
    font-style: italic;
    color: #666;
}

/* Leaflet popup customization */
.member-popup {
    display: flex;
    align-items: center;
}

.member-avatar {
    margin-right: 10px;
}

.member-avatar img {
    border-radius: 50%;
    width: 50px;
    height: 50px;
}

.member-info h4 {
    margin: 0 0 5px;
}

.member-location {
    margin: 0;
    font-style: italic;
}

/* Loading indicator */
.map-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.8);
    padding: 10px 15px;
    border-radius: 4px;
    z-index: 1000;
}

.map-no-data, .map-error {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.8);
    padding: 10px 15px;
    border-radius: 4px;
    z-index: 1000;
}

.map-error {
    color: #e74c3c;
}
</style>
