#!/bin/bash

# PHPCS Aliases for WordPress Plugin Development
# Source this file in your .bashrc or .zshrc, or run: source phpcs-aliases.sh

# Get the directory where this script is located
PLUGIN_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# PHPCS Check - Usage: cs [file_or_directory]
cs() {
    if [ -z "$1" ]; then
        echo "Usage: cs <file_or_directory>"
        echo "Example: cs src/Subscriptions/SubscriptionHandler.php"
        return 1
    fi
    
    cd "$PLUGIN_DIR"
    ./phpcs-check.sh "$1"
}

# PHPCS Fix - Usage: fix [file_or_directory]
fix() {
    if [ -z "$1" ]; then
        echo "Usage: fix <file_or_directory>"
        echo "Example: fix src/Subscriptions/SubscriptionHandler.php"
        return 1
    fi
    
    cd "$PLUGIN_DIR"
    ./phpcs-fix.sh "$1"
}

# PHPCS Check entire src directory - Usage: cs-all
cs-all() {
    cd "$PLUGIN_DIR"
    ./phpcs-check.sh src/
}

# PHPCS Fix entire src directory - Usage: fix-all
fix-all() {
    cd "$PLUGIN_DIR"
    ./phpcs-fix.sh src/
}

# Quick check for common files
cs-sub() {
    cd "$PLUGIN_DIR"
    ./phpcs-check.sh src/Subscriptions/SubscriptionHandler.php
}

cs-wc() {
    cd "$PLUGIN_DIR"
    ./phpcs-check.sh src/Core/WooCommerceHelper.php
}

echo "PHPCS aliases loaded!"
echo "Available commands:"
echo "  cs <file>          - Check coding standards"
echo "  fix <file>         - Auto-fix coding standards"
echo "  cs-all             - Check entire src directory"
echo "  fix-all            - Fix entire src directory"
echo "  cs-sub             - Check SubscriptionHandler.php"
echo "  cs-wc              - Check WooCommerceHelper.php"
