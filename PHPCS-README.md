# PHPCS WordPress Coding Standards Setup

This project is configured with PHP CodeSniffer (PHPCS) and WordPress-Extra coding standards for maintaining consistent code quality.

## Available Commands

### Quick Commands
- `./cs [file]` - Check coding standards for a file or directory
- `./fix [file]` - Auto-fix coding standard violations

### Full Commands
- `./phpcs-check.sh [file]` - Detailed PHPCS check
- `./phpcs-fix.sh [file]` - Detailed PHPCS auto-fix

## Examples

### Check a single file
```bash
./cs src/Subscriptions/SubscriptionHandler.php
```

### Auto-fix a single file
```bash
./fix src/Subscriptions/SubscriptionHandler.php
```

### Check entire src directory
```bash
./cs src/
```

### Auto-fix entire src directory
```bash
./fix src/
```

### Check specific directories
```bash
./cs src/Core/
./cs src/Subscriptions/
```

## What Gets Checked

The PHPCS configuration checks for:
- ✅ **WordPress-Extra** coding standards
- ✅ **PHPCompatibility** for PHP 8.0+
- ✅ **WordPress-Docs** for proper documentation
- ✅ **WordPress-Security** for security best practices

## Common Issues and Fixes

### Auto-fixable Issues
- Indentation (tabs vs spaces)
- Line spacing
- Brace placement
- Function call spacing
- Array formatting

### Manual Fix Required
- Function naming conventions
- Variable naming conventions
- Documentation blocks
- Security violations

## Workflow

1. **Before committing code:**
   ```bash
   ./cs src/
   ```

2. **Auto-fix what you can:**
   ```bash
   ./fix src/
   ```

3. **Check remaining issues:**
   ```bash
   ./cs src/
   ```

4. **Manually fix remaining issues**

## Configuration

- **Config file**: `phpcs.xml`
- **Standards**: WordPress-Extra, PHPCompatibility, WordPress-Docs, WordPress-Security
- **PHP version**: 8.0+
- **Extensions**: `.php`

## Integration

These commands work from the plugin root directory. The scripts automatically:
- Use the correct coding standards
- Apply the right configuration
- Show colored output for better readability
- Provide helpful tips and suggestions

## Troubleshooting

If you encounter issues:
1. Make sure you're in the plugin root directory
2. Check that the scripts are executable: `chmod +x cs fix phpcs-*.sh`
3. Run `composer install` to ensure dependencies are installed
4. Check that vendor/bin/phpcs exists
