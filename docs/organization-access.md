# Organization Access Feature

This feature allows users to request access to create organizations and provides an admin interface for managing these requests.

## Features

### Frontend Features
- **Header Button**: "Request Organization Access" button appears in the site header for logged-in users
- **SweetAlert Form**: Beautiful form modal powered by SweetAlert2 for submitting requests
- **Status Display**: Users can see their current request status
- **Token-based Access**: Approved users get a secure token to access the create-group page

### Admin Features
- **Admin Dashboard**: Complete admin interface for managing organization requests
- **Request Management**: View, approve, or reject organization access requests
- **Email Notifications**: Automatic email notifications for admins and users
- **Bulk Actions**: Process multiple requests at once
- **Filtering**: View requests by status (pending, approved, rejected)

## How It Works

### User Flow
1. User clicks "Request Organization Access" button in header
2. User fills out the organization request form
3. Request is submitted and stored in user meta
4. Admin receives email notification
5. Admin reviews and approves/rejects the request
6. User receives email with approval/rejection
7. If approved, user gets a token-based link to create their organization

### Admin Flow
1. Admin receives email notification of new request
2. Admin goes to "Organization Requests" page in admin dashboard
3. Admin reviews request details
4. Admin approves or rejects with optional note
5. User receives email notification of decision

## Installation

The feature is automatically loaded when the plugin is activated. No additional setup required.

## Usage

### For Users
- **Button Location**: The button automatically appears in the header for logged-in users
- **Shortcode**: Use `[labgenz_org_access_status]` to display request status on any page
- **Form Fields**:
  - Organization Name (required)
  - Organization Type (required)
  - Description (required)
  - Website URL (optional)
  - Contact Email (required)
  - Phone Number (optional)
  - Justification (required)

### For Admins
- **Admin Menu**: Go to "Labgenz Community" → "Organization Requests"
- **View Requests**: See all requests organized by status
- **Process Requests**: Approve or reject requests with optional admin notes
- **Bulk Actions**: Process multiple requests at once

## Database Storage

The feature uses WordPress user meta to store:
- `_labgenz_org_access_request_data`: Complete request information
- `_labgenz_org_access_token`: Security token for approved access
- `_labgenz_org_access_status`: Current request status (pending/approved/rejected)

## Security Features

- **Nonce Protection**: All AJAX requests are nonce-protected
- **Token Expiration**: Access tokens expire after 7 days
- **Email Verification**: Token verification includes email matching
- **Permission Checks**: Admin actions require `manage_options` capability

## Email Notifications

### Admin Notification (New Request)
- Sent to site admin when new request is submitted
- Contains user details and request information
- Includes direct link to admin dashboard

### User Approval Email
- Sent when request is approved
- Contains secure link to create organization
- Link expires in 7 days

### User Rejection Email
- Sent when request is rejected
- Includes admin note if provided
- Explains rejection reason

## Customization

### Styling
- **Frontend CSS**: `/public/assets/css/organization-access.css`
- **Admin CSS**: `/public/assets/css/organization-access-admin.css`

### JavaScript
- **Frontend JS**: `/public/assets/js/organization-access.js`
- **Admin JS**: `/public/assets/js/organization-access-admin.js`

### Button Placement
The button tries to find the best header location automatically. You can customize the placement by modifying the `headerSelectors` array in the JavaScript file.

## Troubleshooting

### Button Not Appearing
- Check if user is logged in
- Verify SweetAlert2 is loaded
- Check browser console for JavaScript errors

### Form Not Submitting
- Verify all required fields are filled
- Check AJAX endpoint is accessible
- Ensure nonce is valid

### Admin Page Not Loading
- Verify user has `manage_options` capability
- Check if menu is registered correctly
- Ensure all required assets are loading

## File Structure

```
src/
├── Core/
│   └── OrganizationAccess.php          # Main functionality
├── Admin/
│   └── OrganizationAccessAdmin.php     # Admin interface
├── Public/
│   └── OrganizationAccessPublic.php    # Frontend interface
public/assets/
├── css/
│   ├── organization-access.css         # Frontend styles
│   └── organization-access-admin.css   # Admin styles
└── js/
    ├── organization-access.js          # Frontend JavaScript
    └── organization-access-admin.js    # Admin JavaScript
```

## Constants

- `OrganizationAccess::REQUEST_DATA_META_KEY`: User meta key for request data
- `OrganizationAccess::TOKEN_META_KEY`: User meta key for access token
- `OrganizationAccess::STATUS_META_KEY`: User meta key for request status
- `OrganizationAccess::TOKEN_EXPIRATION`: Token expiration time (7 days)

## Hooks and Filters

### Actions
- `wp_ajax_labgenz_submit_org_access_request`: Handle form submission
- `wp_ajax_labgenz_process_org_access_request`: Handle admin actions

### Filters
- None currently, but can be added for customization

## Future Enhancements

- Custom form fields
- Request categories
- Advanced filtering
- Request history
- Bulk import/export
- API endpoints
