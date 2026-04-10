# Navbar Component Documentation

## Overview
The navbar component has been refactored into a centralized, reusable file to improve maintainability and consistency across all admin pages.

## Files

### New File Created
- **`pages/navbar.php`** - Centralized navbar component containing:
  - Sidebar with navigation links
  - Top bar with CIG title and notification bell
  - Notification panel with optional notification list

### Modified Files (7 pages updated)
1. `pages/dashboard.php` - Updated to use navbar.php
2. `pages/submissions.php` - Updated to use navbar.php
3. `pages/review.php` - Updated to use navbar.php
4. `pages/archive.php` - Updated to use navbar.php
5. `pages/organizations.php` - Updated to use navbar.php
6. `pages/reports.php` - Updated to use navbar.php
7. `pages/index.php` - Updated to use navbar.php

### Related Files (Used)
- `assets/css/navbar.css` - Navbar styling (already linked on all pages)
- `js/navbar.js` - Navbar JavaScript functionality (already linked on all pages)

## How It Works

### Including the Navbar
In each page, before the navbar component is needed, set the page identifier and include the navbar:

```php
<?php 
$current_page = 'dashboard'; // or 'submissions', 'review', 'archive', 'organizations', 'reports'
$user_name = $user['full_name'] ?? '';
$unread_count = $unread_count ?? 0; // Optional, defaults to 0
$notifications = $notifications ?? []; // Optional, defaults to empty array
?>
<?php include 'navbar.php'; ?>
```

### Parameters

The navbar component accepts the following optional parameters:

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `$current_page` | string | Current page identifier to highlight active nav link | '' |
| `$user_name` | string | User's full name displayed in top-right | '' |
| `$unread_count` | int | Number of unread notifications to display in badge | 0 |
| `$notifications` | array | Array of notification objects to display | [] |

### Current Page Values
Use one of these values for the `$current_page` variable to highlight the active navigation item:
- `'dashboard'`
- `'submissions'`
- `'review'`
- `'archive'`
- `'organizations'`
- `'reports'`

### Notification Structure
If passing notifications, use this structure for each notification:

```php
[
    'type' => 'submission|approval|warning', // Determines the icon
    'title' => 'Notification Title',
    'message' => 'Notification message text',
    'created_at' => '2026-02-20 10:30:00' // For timeAgo() function
]
```

## Benefits

1. **DRY (Don't Repeat Yourself)** - Navbar code is no longer duplicated across 7 pages
2. **Easy Maintenance** - Changes to navbar structure only need to be made in one file
3. **Consistency** - All pages have identical navbar structure and styling
4. **Flexibility** - Optional parameters allow customization per page (active link, user name, notifications)
5. **CSS & JS Already Linked** - All pages already have navbar.css and navbar.js linked

## Implementation Notes

- The navbar component closes the `</div>` tags for sidebar and main only after the notification panel
- The page content divs (`<div class="page active">`) come after the navbar in each page
- Each page properly closes all HTML tags (main, sidebar, body, html) at the end of the file
- The active navigation link is automatically set based on the `$current_page` parameter

## File Structure

```
pages/
├── navbar.php (NEW - centralized navbar)
├── dashboard.php (UPDATED - includes navbar.php)
├── submissions.php (UPDATED - includes navbar.php)
├── review.php (UPDATED - includes navbar.php)
├── archive.php (UPDATED - includes navbar.php)
├── organizations.php (UPDATED - includes navbar.php)
├── reports.php (UPDATED - includes navbar.php)
└── index.php (UPDATED - includes navbar.php)

assets/css/
└── navbar.css (EXISTING - used by navbar.php)

js/
└── navbar.js (EXISTING - used by navbar.php)
```

## Testing Checklist

- [x] Navbar displays correctly on all pages
- [x] Active navigation link highlights correctly based on current page
- [x] Notification bell is clickable and shows/hides notification panel
- [x] User name displays in top-right when provided
- [x] Logo links to dashboard.php
- [x] All navigation links work correctly
- [x] Logout button works
- [x] CSS styling is applied correctly
- [x] JavaScript functionality works (toggleNotificationPanel)

## Future Enhancements

- Consider creating mobile-responsive menu toggle
- Add user profile dropdown menu
- Implement search functionality in navbar
- Add breadcrumb navigation
