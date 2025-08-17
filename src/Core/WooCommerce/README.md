# WooCommerce Helper Classes

This directory contains specialized helper classes for WooCommerce functionality used in the Labgenz Community Management plugin.

## Organization

The WooCommerce helpers are organized into these specialized classes:

1. **SubscriptionProcessor** - Handles subscription-related processing for WooCommerce orders
2. **OrderStatusManager** - Manages order status changes and tracking
3. **OrderStatusLogger** - Logs order status changes with detailed information
4. **CheckoutHandler** - Manages checkout flow customizations
5. **PaymentGatewayManager** - Filters and manages payment gateway availability
6. **UserAccountCreator** - Creates user accounts from WooCommerce orders

## Usage

These classes are designed to be used through the main `WooCommerceHelper` facade class, which maintains backward compatibility while delegating to these specialized classes.

```php
// Example using the facade (recommended for most use cases)
$wc_helper = WooCommerceHelper::get_instance();
$wc_helper->some_method();

// Direct usage of specialized classes
\LABGENZ_CM\Core\WooCommerce\SubscriptionProcessor::some_static_method();
```

## Implementation Notes

Each helper class handles a specific domain of functionality related to WooCommerce, which helps maintain the Single Responsibility Principle and improves code organization and testability.