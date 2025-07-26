#!/bin/bash

# PHPCBF (PHP Code Beautifier and Fixer) Script for WordPress Plugin
# Usage: ./phpcs-fix.sh [file_or_directory]
# Example: ./phpcs-fix.sh src/Subscriptions/SubscriptionHandler.php

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Set the project root
PROJECT_ROOT="$SCRIPT_DIR"

# Default to fixing the entire src directory if no argument provided
TARGET="${1:-src/}"

# Check if target exists
if [ ! -e "$PROJECT_ROOT/$TARGET" ]; then
    echo "Error: File or directory '$TARGET' not found!"
    echo "Usage: $0 [file_or_directory]"
    echo "Example: $0 src/Subscriptions/SubscriptionHandler.php"
    exit 1
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Running PHPCBF (auto-fix) on: $TARGET${NC}"
echo "=================================="

# Run PHPCBF
cd "$PROJECT_ROOT"
./vendor/bin/phpcbf "$TARGET"

# Check the exit code
if [ $? -eq 0 ]; then
    echo -e "\n${GREEN}✅ No fixable issues found or all issues fixed!${NC}"
else
    echo -e "\n${YELLOW}⚠️  Some issues were fixed. Run './phpcs-check.sh $TARGET' to see remaining issues.${NC}"
fi
