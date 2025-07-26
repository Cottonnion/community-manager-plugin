#!/bin/bash
# Quick PHPCS check script - Usage: ./cs [file]
cd "$(dirname "$0")"
./phpcs-check.sh "${1:-src/}"
