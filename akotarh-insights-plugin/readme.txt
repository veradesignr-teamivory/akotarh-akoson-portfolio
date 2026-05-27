=== Akotarh Insights Blog & Portfolio CMS ===
Contributors: akotarhakoson
Tags: blog, insights, rest api, headless cms, portfolio, dashboard
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 2.0.0
License: GPLv2 or later

Blog engine & portfolio CMS for Akotarh Akoson's website. Creates an "Insights" custom post type with topics, featured images, JWT authentication, portfolio content management, media uploads, and a public REST API with CORS support. Includes a standalone admin dashboard.

== Installation ==

1. Upload the `akotarh-insights-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Insights > Settings to configure your API endpoint and allowed origins
4. Start adding insights from Insights > Add New (or use the admin dashboard)
5. Update the `API_BASE` constant in both `insights.html` and `admin/index.html`

== Admin Dashboard ==

A standalone HTML admin dashboard is included at `admin/index.html`. It connects to WordPress via the REST API and provides:

* Login with WordPress credentials (JWT authentication)
* Dashboard overview with statistics
* Create, edit, and delete insights with a rich text editor
* Media library with drag-and-drop image uploads
* Portfolio content management (edit sections across all pages)
* Topic filtering and status management

To set up the dashboard:
1. Host `admin/index.html` on your static site (e.g., GitHub Pages)
2. Set the `API_BASE` constant to your WordPress site URL
3. Add your dashboard URL to the CORS allowed origins in Insights > Settings

== API Endpoints ==

=== Public (no auth required) ===
* `GET /wp-json/akotarh/v1/insights` - List insights (params: topic, search, page, per_page)
* `GET /wp-json/akotarh/v1/insights/{id}` - Single insight with full HTML content
* `GET /wp-json/akotarh/v1/topics` - List all topics
* `GET /wp-json/akotarh/v1/portfolio` - Get portfolio content

=== Auth ===
* `POST /wp-json/akotarh/v1/auth/login` - Authenticate and get JWT token
* `GET /wp-json/akotarh/v1/auth/verify` - Verify token validity

=== Admin (JWT required) ===
* `GET /wp-json/akotarh/v1/admin/insights` - List all insights (incl. drafts)
* `POST /wp-json/akotarh/v1/admin/insights` - Create new insight
* `PUT /wp-json/akotarh/v1/admin/insights/{id}` - Update insight
* `DELETE /wp-json/akotarh/v1/admin/insights/{id}` - Delete insight
* `GET /wp-json/akotarh/v1/admin/media` - List media files
* `POST /wp-json/akotarh/v1/admin/media` - Upload image
* `DELETE /wp-json/akotarh/v1/admin/media/{id}` - Delete media
* `PUT /wp-json/akotarh/v1/admin/portfolio` - Update portfolio content
* `GET /wp-json/akotarh/v1/admin/stats` - Dashboard statistics

== Setup for Static Site ==

In your `insights.html` and `admin/index.html`, set the API_BASE:

`const API_BASE = 'https://your-wordpress-site.com/wp-json/akotarh/v1';`
