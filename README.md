# WP REST Endpoint Manager

Complete WordPress REST API Management Suite with visual UI and custom code support.

## Features

### 🎯 Core Capabilities
- **Dynamic REST Endpoints**: Create custom API endpoints via admin UI or code
- **Controller Classes**: Write PHP controllers with Monaco editor integration
- **JSON Schema Validation**: Visual schema builder and request/response validation
- **Ingest Webhooks**: Receive incoming webhooks with full ETL pipeline
- **Dispatch Webhooks**: Send outgoing webhooks with queue and retry logic
- **Comprehensive Logging**: All activity logged via WordPress comments
- **API Tester**: Built-in Postman-like interface for testing endpoints

### 🔒 Security
- API Key authentication
- JWT token support structure
- OAuth fallback
- Rate limiting per endpoint
- IP whitelisting for webhooks
- Secure PHP code execution sandbox

### ⚡ Performance
- Response caching for GET requests
- Async webhook dispatch via Action Scheduler (or wp-cron)
- Exponential backoff retry logic
- Rate limiting with configurable limits

### 📊 Admin UI
- Dashboard with statistics and recent activity
- Visual endpoint builder
- Log viewer with filtering and CSV export
- API tester with cURL generation
- Settings page for global configuration

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'REST Manager' in the admin menu
4. Start creating endpoints!

## Usage

### Creating a REST Endpoint

1. Go to **REST Manager > REST Endpoints**
2. Click **Add New**
3. Configure your endpoint:
   - **Namespace**: e.g., `my-api/v1`
   - **Route**: e.g., `/users` or `/users/(?P<id>\d+)`
   - **HTTP Methods**: Select allowed methods (GET, POST, etc.)
   - **Callback Type**:
     - **Proxy**: Forward to external URL
     - **Controller**: Use custom PHP class
     - **Inline**: Execute inline PHP code
     - **Transform**: Data transformation only

### Creating a Controller

1. Go to **REST Manager > Controllers**
2. Click **Add New**
3. Write your PHP class in the Monaco editor:

```php
<?php
class My_User_Controller {
    public function get( $request ) {
        $users = get_users();
        return new WP_REST_Response( $users, 200 );
    }
    
    public function post( $request ) {
        $data = $request->get_json_params();
        // Create user logic
        return new WP_REST_Response( [ 'success' => true ], 201 );
    }
}
```

### Creating an Ingest Webhook

1. Go to **REST Manager > Ingest Webhooks**
2. Click **Add New**
3. Configure webhook:
   - Webhook slug will generate URL: `https://yoursite.com/wp-json/rem/v1/ingest/your-slug`
   - Set authentication token
   - Configure data mapping (transform incoming data)
   - Set WordPress actions to trigger

### Creating a Dispatch Webhook

1. Go to **REST Manager > Dispatch Webhooks**
2. Click **Add New**
3. Configure:
   - Target URL
   - HTTP method
   - Payload template with placeholders: `{{post.title}}`, `{{user.email}}`
   - WordPress events to trigger on

### Testing Endpoints

1. Go to **REST Manager > API Tester**
2. Select or enter endpoint URL
3. Set HTTP method and headers
4. Add request body (JSON)
5. Click **Send Request**
6. View formatted response

## Template Placeholders

Use in dispatch webhook payloads:

- `{{post.title}}` - Post title
- `{{post.content}}` - Post content
- `{{user.email}}` - User email
- `{{site.url}}` - Site URL
- `{{event_data.custom}}` - Custom event data

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Optional: Action Scheduler (for better dispatch reliability)

## Developer Hooks

### Actions

```php
// After ingest webhook received
do_action( 'wp_rem_ingest_received', $webhook_id, $mapped_data, $raw_data );

// After dispatch webhook sent
do_action( 'wp_rem_dispatch_sent', $webhook_id, $request_data, $response_data, $is_success );
```

### Filters

```php
// Custom data transformation
add_filter( 'wp_rem_data_mapper_transform_custom', function( $value ) {
    return strtoupper( $value );
} );
```

## License

GPL v2 or later

## Author

Your Name - https://github.com/dexit

## Credits

Inspired by:
- WP_Custom_API (MVC architecture)
- webhook-router (Visual UI approach)
- Action Scheduler (Queue management)
- WP Queue (Async processing patterns)
