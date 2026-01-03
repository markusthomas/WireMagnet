# WireMagnet

A ProcessWire module to manage lead magnets, capture email addresses, and securely deliver files via temporary download links.

## Features

- **Automated Form Handling**: Automatically intercepts form submissions before the page is rendered.
- **Secure File Delivery**: Generates unique, temporary download tokens (valid for 24 hours) to prevent direct file access.
- **Email Automation**: Sends an email to the subscriber with the secure download link.
- **Double Opt-In (DOI)**: Optional requirement for users to confirm their email before receiving the download.
- **File Attachments**: Option to attach the file directly to the email instead of sending a link.
- **AJAX Submission**: Seamless form experience using Alpine.js (configurable).
- **Lead Archiving**: Stores subscriber details (Email, Magnet ID, IP, Timestamp) in a custom database table (`leads_archive`).
- **System Logging**: Logs new leads to the ProcessWire system logs (`leads`).
- **Admin Interface**: Dedicated page under **Setup > Lead Magnets** to view and manage leads.
- **CSV Export**: Export all captured leads to a CSV file via the admin interface.
- **CSRF Protection**: Built-in security for form submissions.
- **Default Styling**: Includes basic CSS for the form (responsive and clean).
- **Translatable**: All frontend and backend texts are translatable.
- **Easy Integration**: Render the subscription form with a single method call.

## Installation

1.  Copy the `LeadMagnetManager` folder into your `site/modules/` directory.
2.  Login to the ProcessWire Admin.
3.  Go to **Modules > Refresh**.
4.  Click **Install** next to "WireMagnet".

## Configuration

You can configure the module settings directly in the ProcessWire Admin (**Modules > Site > Lead Magnet Manager > Configure**):

1.  **Sender Email Address**: The email address the download emails are sent from (default: `noreply@yoursite.com`).
2.  **Email Subject**: The subject line of the download email.
3.  **Enable Double Opt-In (DOI)**: If enabled, users must confirm their email address via a link sent to them before they receive the final download email.
4.  **Attach File to Email**: If enabled, the file is attached directly to the email. _Note: Large files might be rejected by mail servers._
5.  **Load Alpine.js**: If enabled, the module loads Alpine.js from a CDN to handle AJAX form submissions. Uncheck this if you already include Alpine.js in your site.
6.  **Form Button Text**: Customize the text on the submit button (default: "Get Free Download").

## Usage

### 1. Setup the Lead Magnet Page

1.  The module automatically creates a template named **lead-magnet** and a field named **lead_file**.
2.  Create a new Page using the `lead-magnet` template.
3.  Upload your file (PDF, Zip, etc.) to the `lead_file` field on that page.

_Note: You can also use any other template and file field. Just make sure to pass the field name to the render method (see below)._

### 2. Render the Form in your Template

In your template file (e.g., `lead-magnet.php`), use the following code to render the subscription form:

```php
<?php
// Render the subscription form (default field: 'lead_file')
// The module automatically handles success/error messages and styling.
echo $modules->get('WireMagnet')->renderForm($page);

// OR: Render for a specific field (e.g., if you have multiple magnets or custom field names)
// echo $modules->get('WireMagnet')->renderForm($page, 'my_custom_file_field');

// OR: Override the button text manually
// echo $modules->get('WireMagnet')->renderForm($page, 'lead_file', 'Send me the PDF!');
?>
```

### 3. How it Works

1.  The user enters their email address.
2.  The module intercepts the POST request.
3.  The lead is saved to the `leads_archive` database table.
4.  A unique token is generated and stored in `lead_tokens`.
5.  An email is sent to the user with a link like `https://yoursite.com/lead-download/{token}`.
6.  When clicked, the module validates the token and expiry (24h) and serves the file securely using `wireSendFile()`.

## Database Tables

The module automatically creates two tables:

- `leads_archive`: Stores the history of all leads.
- `lead_tokens`: Stores active download tokens and their expiry times.

## Notes
