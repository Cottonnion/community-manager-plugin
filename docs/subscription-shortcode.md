# Using the Subscription Details Shortcode

The subscription details shortcode allows members to view their current subscription information, including expiry date, status, and subscription benefits.

## Basic Usage

Simply add the shortcode to any page where you want to display the subscription details:

```
[labgenz_subscription_details]
```

## Features

- Displays subscription plan name, status, and expiry date
- Shows days remaining until subscription expires
- Shows warning when subscription is about to expire (15 days or less)
- Lists all subscription benefits with visual indicators
- Renewal button (currently shows a placeholder message)

## Example Page Setup

1. Create a new page in WordPress called "My Subscription"
2. Add the shortcode to the page content
3. Publish the page and add it to your menu

## Styling

The shortcode includes responsive CSS styling that adapts to different screen sizes. The colors and styling match the rest of the plugin's aesthetic.

## Security

- Only logged-in users can view their own subscription details
- Non-logged-in users will see a message prompting them to log in
- Users without active subscriptions will see a notification
