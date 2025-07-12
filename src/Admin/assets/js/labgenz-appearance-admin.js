/**
 * Labgenz Community Management - Appearance Settings Handler
 * Handles real-time preview and AJAX saving of appearance settings
 */

(function($) {
    'use strict';

    class LabgenzAppearanceManager {
        constructor() {
            this.init();
            this.bindEvents();
            this.loadCurrentSettings();
        }

        init() {
            this.form = $('#labgenz-appearance-form');
            this.previewContainer = $('#labgenz-preview-container');
            this.messagesContainer = $('#labgenz-appearance-messages');
            this.currentSettings = {};
            
            // Font family mappings
            this.fontFamilies = {
                'system': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                'arial': 'Arial, sans-serif',
                'helvetica': 'Helvetica, Arial, sans-serif',
                'georgia': 'Georgia, serif',
                'times': '"Times New Roman", Times, serif',
                'roboto': '"Roboto", sans-serif',
                'opensans': '"Open Sans", sans-serif',
                'lato': '"Lato", sans-serif'
            };
        }

        bindEvents() {
            // Tab switching
            $('.nav-tab').on('click', this.handleTabSwitch.bind(this));
            
            // Real-time preview updates
            this.form.on('input change', 'input, select', this.updatePreview.bind(this));
            
            // Form submission
            this.form.on('submit', this.saveSettings.bind(this));
            
            // Reset to defaults
            $('#labgenz-reset-appearance').on('click', this.resetToDefaults.bind(this));
            
            // Export settings
            $('#labgenz-export-appearance').on('click', this.exportSettings.bind(this));
            
            // Import settings
            $('#labgenz-import-appearance').on('click', this.importSettings.bind(this));
            $('#labgenz-import-file').on('change', this.handleImportFile.bind(this));
            
            // Range slider updates
            $('.range-slider').on('input', this.updateRangeValue.bind(this));
            
            // Load Google Fonts when needed
            $('select[name="font_family"]').on('change', this.loadGoogleFont.bind(this));
        }

        handleTabSwitch(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const targetTab = $tab.data('tab');
            
            // Update tab navigation
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Update tab content
            $('.labgenz-tab-content').removeClass('active');
            $('#' + targetTab).addClass('active');
        }

        updateRangeValue(e) {
            const $slider = $(e.target);
            const $valueSpan = $slider.siblings('.range-value');
            $valueSpan.text($slider.val() + 'px');
        }

        loadCurrentSettings() {
            // Get current form values
            const formData = new FormData(this.form[0]);
            this.currentSettings = {};
            
            for (let [key, value] of formData.entries()) {
                this.currentSettings[key] = value;
            }
            
            this.updatePreview();
        }

        updatePreview() {
            // Get current form values
            const settings = this.getFormValues();
            
            // Generate CSS custom properties
            const cssVars = this.generateCSSVariables(settings);
            
            // Apply to preview container
            this.applyStylesToPreview(cssVars, settings);
            
            // Update component-specific styles
            this.updateComponentStyles(settings);
        }

        getFormValues() {
            const settings = {};
            this.form.find('input, select').each(function() {
                const $el = $(this);
                const name = $el.attr('name');
                // Only include allowed settings, skip nonce and _wp_http_referer
                if (
                    name &&
                    name !== 'labgenz_appearance_nonce' &&
                    name !== '_wp_http_referer'
                ) {
                    settings[name] = $el.val();
                }
            });
            return settings;
        }

        generateCSSVariables(settings) {
            return {
                '--labgenz-primary-color': settings.primary_color || '#3498db',
                '--labgenz-secondary-color': settings.secondary_color || '#2c3e50',
                '--labgenz-accent-color': settings.accent_color || '#e74c3c',
                '--labgenz-success-color': settings.success_color || '#27ae60',
                '--labgenz-warning-color': settings.warning_color || '#f39c12',
                '--labgenz-bg-color': settings.background_color || '#ffffff',
                '--labgenz-text-color': settings.text_color || '#2c3e50',
                '--labgenz-border-color': settings.border_color || '#e0e0e0',
                '--labgenz-font-family': this.fontFamilies[settings.font_family] || this.fontFamilies.system,
                '--labgenz-font-size': (settings.font_size || '14') + 'px',
                '--labgenz-border-radius': (settings.border_radius || '4') + 'px'
            };
        }

        applyStylesToPreview(cssVars, settings) {
            // Apply CSS custom properties
            let cssText = '';
            for (const [property, value] of Object.entries(cssVars)) {
                cssText += `${property}: ${value}; `;
            }
            
            this.previewContainer[0].style.cssText = cssText;
            
            // Apply component-specific classes
            this.applyComponentClasses(settings);
        }

        applyComponentClasses(settings) {
            const $preview = this.previewContainer;
            
            // Remove existing style classes
            $preview.find('.labgenz-btn').removeClass('btn-modern btn-classic btn-minimal btn-bold');
            $preview.find('.labgenz-table').removeClass('table-modern table-classic table-minimal table-striped');
            
            // Apply button styles
            if (settings.button_style) {
                $preview.find('.labgenz-btn').addClass(`btn-${settings.button_style}`);
            }
            
            // Apply table styles
            if (settings.table_style) {
                $preview.find('.labgenz-table').addClass(`table-${settings.table_style}`);
            }
        }

        updateComponentStyles(settings) {
            // Dynamic CSS for component styles
            let dynamicCSS = '';
            
            // Button styles
            switch (settings.button_style) {
                case 'classic':
                    dynamicCSS += `
                        .labgenz-btn.btn-classic {
                            border: 2px solid;
                            background: transparent !important;
                            font-weight: bold;
                        }
                        .labgenz-btn-primary.btn-classic { color: var(--labgenz-primary-color) !important; border-color: var(--labgenz-primary-color); }
                        .labgenz-btn-secondary.btn-classic { color: var(--labgenz-secondary-color) !important; border-color: var(--labgenz-secondary-color); }
                        .labgenz-btn-accent.btn-classic { color: var(--labgenz-accent-color) !important; border-color: var(--labgenz-accent-color); }
                    `;
                    break;
                case 'bold':
                    dynamicCSS += `
                        .labgenz-btn.btn-bold {
                            font-weight: 900;
                            text-transform: uppercase;
                            letter-spacing: 1px;
                            padding: 12px 24px;
                            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                        }
                    `;
                    break;
            }
            
            // Table styles
            switch (settings.table_style) {
                case 'classic':
                    dynamicCSS += `
                        .labgenz-table.table-classic {
                            border: 2px solid var(--labgenz-border-color);
                        }
                        .labgenz-table.table-classic th,
                        .labgenz-table.table-classic td {
                            border: 1px solid var(--labgenz-border-color);
                        }
                        .labgenz-table.table-classic th {
                            background: var(--labgenz-bg-color) !important;
                            color: var(--labgenz-text-color) !important;
                            border-bottom: 2px solid var(--labgenz-secondary-color);
                        }
                    `;
                    break;
                case 'minimal':
                    dynamicCSS += `
                        .labgenz-table.table-minimal {
                            border: none;
                        }
                        .labgenz-table.table-minimal th {
                            background: transparent !important;
                            color: var(--labgenz-text-color) !important;
                            border-bottom: 2px solid var(--labgenz-primary-color);
                            border-top: none;
                            border-left: none;
                            border-right: none;
                        }
                        .labgenz-table.table-minimal td {
                            border-left: none;
                            border-right: none;
                            border-top: none;
                        }
                    `;
                    break;
                case 'striped':
                    dynamicCSS += `
                        .labgenz-table.table-striped tbody tr:nth-child(even) {
                            background: rgba(0,0,0,0.05);
                        }
                    `;
                    break;
            }
            
            // Update or create dynamic style element
            this.updateDynamicStyles(dynamicCSS);
        }

        updateDynamicStyles(css) {
            let $styleEl = $('#labgenz-dynamic-styles');
            if (!$styleEl.length) {
                $styleEl = $('<style id="labgenz-dynamic-styles"></style>').appendTo('head');
            }
            $styleEl.text(css);
        }

        loadGoogleFont(e) {
            const fontFamily = $(e.target).val();
            const googleFonts = ['roboto', 'opensans', 'lato'];
            
            if (googleFonts.includes(fontFamily)) {
                const fontName = fontFamily.charAt(0).toUpperCase() + fontFamily.slice(1);
                const googleFontUrl = `https://fonts.googleapis.com/css2?family=${fontName.replace(' ', '+')}:wght@300;400;500;600;700&display=swap`;
                
                // Check if font is already loaded
                if (!$(`link[href*="${fontName.replace(' ', '+')}"]`).length) {
                    $('<link>').attr({
                        rel: 'stylesheet',
                        href: googleFontUrl
                    }).appendTo('head');
                }
            }
        }

        saveSettings(e) {
            e.preventDefault();
            const settings = this.getFormValues();
            const $submitBtn = this.form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            // Show loading state
            $submitBtn.prop('disabled', true).text('Saving...');
            this.clearMessages();
            // AJAX request to save settings
            $.ajax({
                url: labgenz_appearance_admin_data.ajaxurl,
                type: 'POST',
                data: {
                    action: 'labgenz_save_appearance_settings',
                    nonce: labgenz_appearance_admin_data.nonce,
                    settings: settings
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('Settings saved successfully!', 'success');
                        this.currentSettings = settings;
                        // Apply settings to actual plugin interface
                        this.applySettingsToPlugin(settings);
                    } else {
                        this.showMessage(response.data || 'Error saving settings.', 'error');
                    }
                },
                error: () => {
                    this.showMessage('Network error. Please try again.', 'error');
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        }

        applySettingsToPlugin(settings) {
            // Generate CSS for the entire plugin
            const cssVars = this.generateCSSVariables(settings);
            let pluginCSS = ':root {\n';
            
            for (const [property, value] of Object.entries(cssVars)) {
                pluginCSS += `  ${property}: ${value};\n`;
            }
            pluginCSS += '}\n';
            
            // Add component-specific styles
            pluginCSS += this.generateComponentCSS(settings);
            
            // Update or create plugin style element
            let $pluginStyleEl = $('#labgenz-plugin-styles');
            if (!$pluginStyleEl.length) {
                $pluginStyleEl = $('<style id="labgenz-plugin-styles"></style>').appendTo('head');
            }
            $pluginStyleEl.text(pluginCSS);
        }

        generateComponentCSS(settings) {
            let css = '';
            
            // Base plugin styles
            css += `
                .labgenz-community-management {
                    font-family: var(--labgenz-font-family);
                    font-size: var(--labgenz-font-size);
                    color: var(--labgenz-text-color);
                }
                
                .labgenz-community-management .labgenz-modal {
                    background: var(--labgenz-bg-color);
                    border: 1px solid var(--labgenz-border-color);
                    border-radius: var(--labgenz-border-radius);
                }
                
                .labgenz-community-management .members-table {
                    font-family: var(--labgenz-font-family);
                    font-size: var(--labgenz-font-size);
                }
                
                .labgenz-community-management .members-table th {
                    background: var(--labgenz-secondary-color);
                    color: white;
                }
                
                .labgenz-community-management .members-table td {
                    border-bottom: 1px solid var(--labgenz-border-color);
                }
                
                .labgenz-community-management .status-badge.status-active {
                    background: var(--labgenz-success-color);
                }
                
                .labgenz-community-management .status-badge.status-pending {
                    background: var(--labgenz-warning-color);
                }
                
                .labgenz-community-management .remove-link,
                .labgenz-community-management .cancel-link {
                    color: var(--labgenz-accent-color);
                    border-color: var(--labgenz-accent-color);
                }
                
                .labgenz-community-management button,
                .labgenz-community-management .button {
                    border-radius: var(--labgenz-border-radius);
                    font-family: var(--labgenz-font-family);
                }
                
                .labgenz-community-management #labgenz-show-invite-popup {
                    background: var(--labgenz-primary-color);
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: var(--labgenz-border-radius);
                    cursor: pointer;
                }
            `;
            
            // Button styles
            if (settings.button_style === 'classic') {
                css += `
                    .labgenz-community-management button,
                    .labgenz-community-management .button {
                        border: 2px solid;
                        background: transparent !important;
                        font-weight: bold;
                    }
                    .labgenz-community-management #labgenz-show-invite-popup {
                        color: var(--labgenz-primary-color) !important;
                        border-color: var(--labgenz-primary-color);
                    }
                `;
            } else if (settings.button_style === 'minimal') {
                css += `
                    .labgenz-community-management button,
                    .labgenz-community-management .button {
                        background: transparent !important;
                        border: none;
                        text-decoration: underline;
                        padding: 4px 8px;
                    }
                `;
            } else if (settings.button_style === 'bold') {
                css += `
                    .labgenz-community-management button,
                    .labgenz-community-management .button {
                        font-weight: 900;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                        padding: 12px 24px;
                        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                    }
                `;
            }
            
            // Table styles
            if (settings.table_style === 'classic') {
                css += `
                    .labgenz-community-management .members-table {
                        border: 2px solid var(--labgenz-border-color);
                    }
                    .labgenz-community-management .members-table th,
                    .labgenz-community-management .members-table td {
                        border: 1px solid var(--labgenz-border-color);
                    }
                    .labgenz-community-management .members-table th {
                        background: var(--labgenz-bg-color) !important;
                        color: var(--labgenz-text-color) !important;
                        border-bottom: 2px solid var(--labgenz-secondary-color);
                    }
                `;
            } else if (settings.table_style === 'minimal') {
                css += `
                    .labgenz-community-management .members-table {
                        border: none;
                    }
                    .labgenz-community-management .members-table th {
                        background: transparent !important;
                        color: var(--labgenz-text-color) !important;
                        border-bottom: 2px solid var(--labgenz-primary-color);
                        border-top: none;
                        border-left: none;
                        border-right: none;
                    }
                    .labgenz-community-management .members-table td {
                        border-left: none;
                        border-right: none;
                        border-top: none;
                    }
                `;
            } else if (settings.table_style === 'striped') {
                css += `
                    .labgenz-community-management .members-table tbody tr:nth-child(even) {
                        background: rgba(0,0,0,0.05);
                    }
                `;
            }
            
            return css;
        }

        resetToDefaults(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to reset all appearance settings to defaults? This cannot be undone.')) {
                return;
            }
            
            const defaults = {
                primary_color: '#3498db',
                secondary_color: '#2c3e50',
                accent_color: '#e74c3c',
                success_color: '#27ae60',
                warning_color: '#f39c12',
                background_color: '#ffffff',
                text_color: '#2c3e50',
                border_color: '#e0e0e0',
                font_family: 'system',
                font_size: '14',
                border_radius: '4',
                button_style: 'modern',
                table_style: 'modern',
                modal_style: 'modern'
            };
            
            // Update form fields
            for (const [key, value] of Object.entries(defaults)) {
                this.form.find(`[name="${key}"]`).val(value);
            }
            
            // Update range value displays
            this.form.find('.range-slider').trigger('input');
            
            // Update preview
            this.updatePreview();
            
            this.showMessage('Settings reset to defaults. Click "Save Appearance Settings" to apply changes.', 'info');
        }

        exportSettings() {
            const settings = this.getFormValues();
            const exportData = {
                version: '1.0',
                settings: settings,
                exported_at: new Date().toISOString()
            };
            
            const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `labgenz-appearance-settings-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            this.showMessage('Settings exported successfully!', 'success');
        }

        importSettings() {
            $('#labgenz-import-file').click();
        }

        handleImportFile(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const importData = JSON.parse(e.target.result);
                    
                    if (!importData.settings) {
                        throw new Error('Invalid file format');
                    }
                    
                    // Validate and apply settings
                    for (const [key, value] of Object.entries(importData.settings)) {
                        const $field = this.form.find(`[name="${key}"]`);
                        if ($field.length) {
                            $field.val(value);
                        }
                    }
                    
                    // Update range value displays
                    this.form.find('.range-slider').trigger('input');
                    
                    // Update preview
                    this.updatePreview();
                    
                    this.showMessage('Settings imported successfully! Click "Save Appearance Settings" to apply changes.', 'success');
                    
                } catch (error) {
                    this.showMessage('Error importing settings: Invalid file format.', 'error');
                }
            };
            
            reader.readAsText(file);
            
            // Reset file input
            $(e.target).val('');
        }

        showMessage(message, type = 'info') {
            const alertClass = type === 'error' ? 'notice-error' : (type === 'success' ? 'notice-success' : 'notice-info');
            const messageHtml = `<div class="notice ${alertClass} is-dismissible"><p>${message}</p></div>`;
            
            this.messagesContainer.html(messageHtml);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => {
                    this.messagesContainer.find('.notice').fadeOut();
                }, 3000);
            }
        }

        clearMessages() {
            this.messagesContainer.empty();
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new LabgenzAppearanceManager();
    });
})(jQuery);