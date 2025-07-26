#!/bin/bash

# PHPCS Check Script for WordPress Plugin
# Usage: ./phpcs-check.sh [file_or_directory]
# Example: ./phpcs-check.sh src/Subscriptions/SubscriptionHandler.php

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Set the project root
PROJECT_ROOT="$SCRIPT_DIR"

# Default to checking the entire src directory if no argument provided
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

echo -e "${YELLOW}Running PHPCS on: $TARGET${NC}"
echo "=================================="

# Run PHPCS
cd "$PROJECT_ROOT"
./vendor/bin/phpcs "$TARGET"

# Check the exit code
if [ $? -eq 0 ]; then
    echo -e "\n${GREEN}✅ No coding standard violations found!${NC}"
else
    echo -e "\n${RED}❌ Coding standard violations found above.${NC}"
    echo -e "${YELLOW}💡 Tip: Run './phpcs-fix.sh $TARGET' to auto-fix some issues${NC}"
fi
