<?php
/**
 * Plugin Name: Akotarh Insights Blog
 * Plugin URI: https://akotarhakoson.com
 * Description: Blog engine & portfolio CMS for Akotarh Akoson. Custom post type "Insights" with REST API, JWT authentication, portfolio content management, and CORS support for the static site + admin dashboard.
 * Version: 2.0.0
 * Author: Akotarh Akoson
 * Author URI: https://akotarhakoson.com
 * License: GPL v2 or later
 * Text Domain: akotarh-insights
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AKO_INSIGHTS_VERSION', '2.0.0');
define('AKO_INSIGHTS_PATH', plugin_dir_path(__FILE__));
define('AKO_INSIGHTS_URL', plugin_dir_url(__FILE__));

class Akotarh_Insights {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
        add_filter('rest_pre_serve_request', [$this, 'add_cors_headers'], 10, 4);

        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    /* ═══════════════════════════════════════
       ACTIVATION
       ═══════════════════════════════════════ */

    public function activate() {
        $this->register_post_type();
        $this->register_taxonomy();
        flush_rewrite_rules();

        if (!get_option('ako_insights_site_url')) {
            update_option('ako_insights_site_url', 'https://akotarhakoson.com');
        }

        // Generate JWT secret on first activation
        if (!get_option('ako_jwt_secret')) {
            update_option('ako_jwt_secret', wp_generate_password(64, true, true));
        }
    }

    /* ═══════════════════════════════════════
       CUSTOM POST TYPE & TAXONOMY
       ═══════════════════════════════════════ */

    public function register_post_type() {
        register_post_type('ako_insight', [
            'labels' => [
                'name'               => 'Insights',
                'singular_name'      => 'Insight',
                'add_new'            => 'Add New Insight',
                'add_new_item'       => 'Add New Insight',
                'edit_item'          => 'Edit Insight',
                'new_item'           => 'New Insight',
                'view_item'          => 'View Insight',
                'search_items'       => 'Search Insights',
                'not_found'          => 'No insights found',
                'not_found_in_trash' => 'No insights found in trash',
                'all_items'          => 'All Insights',
                'menu_name'          => 'Insights',
            ],
            'public'              => true,
            'has_archive'         => true,
            'show_in_rest'        => true,
            'rest_base'           => 'insights',
            'menu_icon'           => 'dashicons-lightbulb',
            'menu_position'       => 5,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields'],
            'rewrite'             => ['slug' => 'insights'],
            'taxonomies'          => ['insight_topic'],
        ]);
    }

    public function register_taxonomy() {
        register_taxonomy('insight_topic', 'ako_insight', [
            'labels' => [
                'name'              => 'Topics',
                'singular_name'     => 'Topic',
                'search_items'      => 'Search Topics',
                'all_items'         => 'All Topics',
                'edit_item'         => 'Edit Topic',
                'update_item'       => 'Update Topic',
                'add_new_item'      => 'Add New Topic',
                'new_item_name'     => 'New Topic Name',
                'menu_name'         => 'Topics',
            ],
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'rest_base'         => 'insight-topics',
            'public'            => true,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'topic'],
        ]);

        $default_topics = ['Cybersecurity', 'Civic Tech', 'Leadership', 'Ambazonia', 'Entrepreneurship', 'Digital Rights'];
        foreach ($default_topics as $topic) {
            if (!term_exists($topic, 'insight_topic')) {
                wp_insert_term($topic, 'insight_topic');
            }
        }
    }

    /* ═══════════════════════════════════════
       REST API ROUTES
       ═══════════════════════════════════════ */

    public function register_rest_routes() {
        // ── PUBLIC ROUTES ──
        register_rest_route('akotarh/v1', '/insights', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_insights'],
            'permission_callback' => '__return_true',
            'args'                => [
                'per_page' => ['default' => 12, 'sanitize_callback' => 'absint'],
                'page'     => ['default' => 1, 'sanitize_callback' => 'absint'],
                'topic'    => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'search'   => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('akotarh/v1', '/insights/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_single_insight'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('akotarh/v1', '/topics', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_topics'],
            'permission_callback' => '__return_true',
        ]);

        // ── AUTH ROUTES ──
        register_rest_route('akotarh/v1', '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('akotarh/v1', '/auth/verify', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_verify'],
            'permission_callback' => '__return_true',
        ]);

        // ── ADMIN INSIGHT CRUD ──
        register_rest_route('akotarh/v1', '/admin/insights', [
            'methods'             => 'GET',
            'callback'            => [$this, 'admin_get_insights'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route('akotarh/v1', '/admin/insights', [
            'methods'             => 'POST',
            'callback'            => [$this, 'admin_create_insight'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route('akotarh/v1', '/admin/insights/(?P<id>\d+)', [
            'methods'             => 'PUT,PATCH',
            'callback'            => [$this, 'admin_update_insight'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route('akotarh/v1', '/admin/insights/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'admin_delete_insight'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // ── ADMIN MEDIA UPLOAD ──
        register_rest_route('akotarh/v1', '/admin/media', [
            'methods'             => 'POST',
            'callback'            => [$this, 'admin_upload_media'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route('akotarh/v1', '/admin/media', [
            'methods'             => 'GET',
            'callback'            => [$this, 'admin_get_media'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route('akotarh/v1', '/admin/media/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'admin_delete_media'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // ── PORTFOLIO CONTENT ──
        register_rest_route('akotarh/v1', '/portfolio', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_portfolio_content'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('akotarh/v1', '/admin/portfolio', [
            'methods'             => 'PUT,PATCH',
            'callback'            => [$this, 'admin_update_portfolio'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // ── DASHBOARD STATS ──
        register_rest_route('akotarh/v1', '/admin/stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'admin_get_stats'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /* ═══════════════════════════════════════
       JWT AUTHENTICATION
       ═══════════════════════════════════════ */

    private function generate_jwt($user_id) {
        $secret  = get_option('ako_jwt_secret');
        $issued  = time();
        $expires = $issued + (7 * DAY_IN_SECONDS); // 7 days

        $header  = $this->base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64url_encode(json_encode([
            'sub' => $user_id,
            'iat' => $issued,
            'exp' => $expires,
            'iss' => get_site_url(),
        ]));

        $signature = $this->base64url_encode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        );

        return "$header.$payload.$signature";
    }

    private function verify_jwt($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        $secret = get_option('ako_jwt_secret');
        $expected_sig = $this->base64url_encode(
            hash_hmac('sha256', "$parts[0].$parts[1]", $secret, true)
        );

        if (!hash_equals($expected_sig, $parts[2])) return false;

        $payload = json_decode($this->base64url_decode($parts[1]), true);
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) return false;

        return $payload;
    }

    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public function check_auth($request) {
        $auth = $request->get_header('Authorization');
        if (!$auth || strpos($auth, 'Bearer ') !== 0) {
            return new WP_Error('unauthorized', 'Missing or invalid token', ['status' => 401]);
        }

        $token   = substr($auth, 7);
        $payload = $this->verify_jwt($token);

        if (!$payload) {
            return new WP_Error('unauthorized', 'Invalid or expired token', ['status' => 401]);
        }

        $user = get_user_by('id', $payload['sub']);
        if (!$user || !user_can($user, 'edit_posts')) {
            return new WP_Error('forbidden', 'Insufficient permissions', ['status' => 403]);
        }

        wp_set_current_user($user->ID);
        return true;
    }

    public function handle_login($request) {
        $params   = $request->get_json_params();
        $username = sanitize_text_field($params['username'] ?? '');
        $password = $params['password'] ?? '';

        if (empty($username) || empty($password)) {
            return new WP_Error('invalid_credentials', 'Username and password are required', ['status' => 400]);
        }

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return new WP_Error('invalid_credentials', 'Invalid username or password', ['status' => 401]);
        }

        if (!user_can($user, 'edit_posts')) {
            return new WP_Error('forbidden', 'This account does not have admin access', ['status' => 403]);
        }

        $token = $this->generate_jwt($user->ID);

        return new WP_REST_Response([
            'token' => $token,
            'user'  => [
                'id'           => $user->ID,
                'username'     => $user->user_login,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'avatar'       => get_avatar_url($user->ID, ['size' => 96]),
            ],
        ], 200);
    }

    public function handle_verify($request) {
        $auth = $request->get_header('Authorization');
        if (!$auth || strpos($auth, 'Bearer ') !== 0) {
            return new WP_Error('unauthorized', 'No token', ['status' => 401]);
        }

        $payload = $this->verify_jwt(substr($auth, 7));
        if (!$payload) {
            return new WP_Error('unauthorized', 'Invalid token', ['status' => 401]);
        }

        $user = get_user_by('id', $payload['sub']);
        if (!$user) {
            return new WP_Error('unauthorized', 'User not found', ['status' => 401]);
        }

        return new WP_REST_Response([
            'valid' => true,
            'user'  => [
                'id'           => $user->ID,
                'username'     => $user->user_login,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'avatar'       => get_avatar_url($user->ID, ['size' => 96]),
            ],
        ], 200);
    }

    /* ═══════════════════════════════════════
       PUBLIC API — INSIGHTS
       ═══════════════════════════════════════ */

    public function get_insights($request) {
        $args = [
            'post_type'      => 'ako_insight',
            'post_status'    => 'publish',
            'posts_per_page' => $request['per_page'],
            'paged'          => $request['page'],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if (!empty($request['topic'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'insight_topic',
                'field'    => 'slug',
                'terms'    => $request['topic'],
            ]];
        }

        if (!empty($request['search'])) {
            $args['s'] = $request['search'];
        }

        $query = new WP_Query($args);
        $posts = [];
        foreach ($query->posts as $post) {
            $posts[] = $this->format_insight($post);
        }

        return new WP_REST_Response([
            'posts'       => $posts,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'        => (int) $request['page'],
        ], 200);
    }

    public function get_single_insight($request) {
        $post = get_post($request['id']);
        if (!$post || $post->post_type !== 'ako_insight' || $post->post_status !== 'publish') {
            return new WP_Error('not_found', 'Insight not found', ['status' => 404]);
        }
        return new WP_REST_Response($this->format_insight($post, true), 200);
    }

    public function get_topics($request) {
        $terms  = get_terms(['taxonomy' => 'insight_topic', 'hide_empty' => false]);
        $topics = [];
        foreach ($terms as $term) {
            $topics[] = [
                'id'    => $term->term_id,
                'name'  => $term->name,
                'slug'  => $term->slug,
                'count' => $term->count,
            ];
        }
        return new WP_REST_Response($topics, 200);
    }

    /* ═══════════════════════════════════════
       ADMIN API — INSIGHT CRUD
       ═══════════════════════════════════════ */

    public function admin_get_insights($request) {
        $status   = sanitize_text_field($request->get_param('status') ?? 'any');
        $per_page = absint($request->get_param('per_page') ?? 20);
        $page     = absint($request->get_param('page') ?? 1);
        $search   = sanitize_text_field($request->get_param('search') ?? '');

        $args = [
            'post_type'      => 'ako_insight',
            'post_status'    => $status === 'any' ? ['publish', 'draft', 'pending', 'private'] : $status,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($search) $args['s'] = $search;

        $query = new WP_Query($args);
        $posts = [];
        foreach ($query->posts as $post) {
            $posts[] = $this->format_insight_admin($post);
        }

        return new WP_REST_Response([
            'posts'       => $posts,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'        => $page,
        ], 200);
    }

    public function admin_create_insight($request) {
        $params = $request->get_json_params();

        $post_data = [
            'post_type'    => 'ako_insight',
            'post_title'   => sanitize_text_field($params['title'] ?? ''),
            'post_content' => wp_kses_post($params['content'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($params['excerpt'] ?? ''),
            'post_status'  => sanitize_text_field($params['status'] ?? 'draft'),
            'post_author'  => get_current_user_id(),
        ];

        if (empty($post_data['post_title'])) {
            return new WP_Error('missing_title', 'Title is required', ['status' => 400]);
        }

        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Assign topics
        if (!empty($params['topics']) && is_array($params['topics'])) {
            wp_set_object_terms($post_id, array_map('intval', $params['topics']), 'insight_topic');
        }

        // Featured image
        if (!empty($params['thumbnail_id'])) {
            set_post_thumbnail($post_id, absint($params['thumbnail_id']));
        }

        // Featured flag
        if (isset($params['featured'])) {
            update_post_meta($post_id, '_ako_featured', $params['featured'] ? '1' : '');
        }

        return new WP_REST_Response($this->format_insight_admin(get_post($post_id)), 201);
    }

    public function admin_update_insight($request) {
        $post_id = absint($request['id']);
        $post    = get_post($post_id);

        if (!$post || $post->post_type !== 'ako_insight') {
            return new WP_Error('not_found', 'Insight not found', ['status' => 404]);
        }

        $params    = $request->get_json_params();
        $post_data = ['ID' => $post_id];

        if (isset($params['title']))   $post_data['post_title']   = sanitize_text_field($params['title']);
        if (isset($params['content'])) $post_data['post_content'] = wp_kses_post($params['content']);
        if (isset($params['excerpt'])) $post_data['post_excerpt'] = sanitize_textarea_field($params['excerpt']);
        if (isset($params['status']))  $post_data['post_status']  = sanitize_text_field($params['status']);

        $result = wp_update_post($post_data, true);
        if (is_wp_error($result)) return $result;

        if (isset($params['topics']) && is_array($params['topics'])) {
            wp_set_object_terms($post_id, array_map('intval', $params['topics']), 'insight_topic');
        }

        if (isset($params['thumbnail_id'])) {
            if ($params['thumbnail_id']) {
                set_post_thumbnail($post_id, absint($params['thumbnail_id']));
            } else {
                delete_post_thumbnail($post_id);
            }
        }

        if (isset($params['featured'])) {
            update_post_meta($post_id, '_ako_featured', $params['featured'] ? '1' : '');
        }

        return new WP_REST_Response($this->format_insight_admin(get_post($post_id)), 200);
    }

    public function admin_delete_insight($request) {
        $post_id = absint($request['id']);
        $post    = get_post($post_id);

        if (!$post || $post->post_type !== 'ako_insight') {
            return new WP_Error('not_found', 'Insight not found', ['status' => 404]);
        }

        $force = (bool) $request->get_param('force');
        if ($force) {
            wp_delete_post($post_id, true);
        } else {
            wp_trash_post($post_id);
        }

        return new WP_REST_Response(['deleted' => true, 'id' => $post_id], 200);
    }

    /* ═══════════════════════════════════════
       ADMIN API — MEDIA
       ═══════════════════════════════════════ */

    public function admin_upload_media($request) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new WP_Error('no_file', 'No file uploaded', ['status' => 400]);
        }

        $upload = wp_handle_upload($files['file'], ['test_form' => false]);
        if (isset($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error'], ['status' => 500]);
        }

        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        $meta      = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $meta);

        return new WP_REST_Response([
            'id'        => $attach_id,
            'url'       => $upload['url'],
            'filename'  => basename($upload['file']),
            'type'      => $upload['type'],
            'sizes'     => $this->get_image_sizes($attach_id),
        ], 201);
    }

    public function admin_get_media($request) {
        $per_page = absint($request->get_param('per_page') ?? 20);
        $page     = absint($request->get_param('page') ?? 1);

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_mime_type' => 'image',
        ];

        $query = new WP_Query($args);
        $media = [];
        foreach ($query->posts as $attachment) {
            $media[] = [
                'id'       => $attachment->ID,
                'title'    => $attachment->post_title,
                'url'      => wp_get_attachment_url($attachment->ID),
                'filename' => basename(get_attached_file($attachment->ID)),
                'type'     => $attachment->post_mime_type,
                'date'     => $attachment->post_date,
                'sizes'    => $this->get_image_sizes($attachment->ID),
            ];
        }

        return new WP_REST_Response([
            'media'       => $media,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
        ], 200);
    }

    public function admin_delete_media($request) {
        $id = absint($request['id']);
        $attachment = get_post($id);

        if (!$attachment || $attachment->post_type !== 'attachment') {
            return new WP_Error('not_found', 'Media not found', ['status' => 404]);
        }

        wp_delete_attachment($id, true);
        return new WP_REST_Response(['deleted' => true, 'id' => $id], 200);
    }

    private function get_image_sizes($attach_id) {
        $sizes = [];
        foreach (['thumbnail', 'medium', 'large', 'full'] as $size) {
            $img = wp_get_attachment_image_src($attach_id, $size);
            if ($img) {
                $sizes[$size] = ['url' => $img[0], 'width' => $img[1], 'height' => $img[2]];
            }
        }
        return $sizes;
    }

    /* ═══════════════════════════════════════
       ADMIN API — PORTFOLIO CONTENT
       ═══════════════════════════════════════ */

    public function get_portfolio_content($request) {
        $section = sanitize_text_field($request->get_param('section') ?? '');
        $content = get_option('ako_portfolio_content', []);

        if ($section && isset($content[$section])) {
            return new WP_REST_Response($content[$section], 200);
        }

        return new WP_REST_Response($content, 200);
    }

    public function admin_update_portfolio($request) {
        $params  = $request->get_json_params();
        $section = sanitize_text_field($params['section'] ?? '');
        $data    = $params['data'] ?? [];

        if (empty($section)) {
            return new WP_Error('missing_section', 'Section key required', ['status' => 400]);
        }

        $content = get_option('ako_portfolio_content', []);
        $content[$section] = $this->sanitize_portfolio_data($data);
        update_option('ako_portfolio_content', $content);

        return new WP_REST_Response([
            'updated' => true,
            'section' => $section,
            'data'    => $content[$section],
        ], 200);
    }

    private function sanitize_portfolio_data($data) {
        if (is_string($data)) return wp_kses_post($data);
        if (is_array($data)) {
            return array_map([$this, 'sanitize_portfolio_data'], $data);
        }
        return $data;
    }

    /* ═══════════════════════════════════════
       ADMIN API — DASHBOARD STATS
       ═══════════════════════════════════════ */

    public function admin_get_stats($request) {
        $published = wp_count_posts('ako_insight');
        $topics    = wp_count_terms(['taxonomy' => 'insight_topic']);
        $media_count = wp_count_posts('attachment');

        $recent = new WP_Query([
            'post_type'      => 'ako_insight',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $recent_posts = [];
        foreach ($recent->posts as $post) {
            $recent_posts[] = [
                'id'           => $post->ID,
                'title'        => $post->post_title,
                'date_display' => get_the_date('M j, Y', $post),
                'status'       => $post->post_status,
            ];
        }

        return new WP_REST_Response([
            'total_published' => (int) ($published->publish ?? 0),
            'total_drafts'    => (int) ($published->draft ?? 0),
            'total_topics'    => is_wp_error($topics) ? 0 : (int) $topics,
            'total_media'     => (int) ($media_count->inherit ?? 0),
            'recent_posts'    => $recent_posts,
        ], 200);
    }

    /* ═══════════════════════════════════════
       HELPERS
       ═══════════════════════════════════════ */

    private function format_insight($post, $full = false) {
        $topics = wp_get_post_terms($post->ID, 'insight_topic', ['fields' => 'all']);
        $topic_list = array_map(function ($t) {
            return ['name' => $t->name, 'slug' => $t->slug];
        }, $topics);

        $reading_time = $this->estimate_reading_time($post->post_content);
        $thumbnail_url = has_post_thumbnail($post->ID) ? get_the_post_thumbnail_url($post->ID, 'large') : '';

        $data = [
            'id'           => $post->ID,
            'title'        => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
            'slug'         => $post->post_name,
            'excerpt'      => html_entity_decode(
                !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags($post->post_content), 35),
                ENT_QUOTES, 'UTF-8'
            ),
            'date'         => $post->post_date,
            'date_display' => get_the_date('M j, Y', $post),
            'year'         => get_the_date('Y', $post),
            'topics'       => $topic_list,
            'reading_time' => $reading_time,
            'thumbnail'    => $thumbnail_url,
            'featured'     => (bool) get_post_meta($post->ID, '_ako_featured', true),
        ];

        if ($full) {
            $data['content'] = apply_filters('the_content', $post->post_content);
        }

        return $data;
    }

    private function format_insight_admin($post) {
        $data = $this->format_insight($post, true);
        $data['status']        = $post->post_status;
        $data['raw_content']   = $post->post_content;
        $data['thumbnail_id']  = get_post_thumbnail_id($post->ID) ?: null;
        $data['topic_ids']     = wp_get_object_terms($post->ID, 'insight_topic', ['fields' => 'ids']);
        return $data;
    }

    private function estimate_reading_time($content) {
        $word_count = str_word_count(wp_strip_all_tags($content));
        $minutes = max(1, ceil($word_count / 230));
        return $minutes . ' min read';
    }

    /* ═══════════════════════════════════════
       CORS
       ═══════════════════════════════════════ */

    public function add_cors_headers($served, $result, $request, $server) {
        $origin  = get_http_origin();
        $allowed = get_option('ako_insights_site_url', 'https://akotarhakoson.com');
        $allowed_origins = array_map('trim', explode(',', $allowed));
        $allowed_origins[] = 'http://localhost:8091';
        $allowed_origins[] = 'http://localhost:3000';

        if (in_array($origin, $allowed_origins, true) || in_array('*', $allowed_origins, true)) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
        } else {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($allowed_origins[0]));
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');

        return $served;
    }

    /* ═══════════════════════════════════════
       WP ADMIN SETTINGS PAGE
       ═══════════════════════════════════════ */

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=ako_insight',
            'Insights Settings',
            'Settings',
            'manage_options',
            'ako-insights-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('ako_insights_settings', 'ako_insights_site_url', [
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'https://akotarhakoson.com',
        ]);
        register_setting('ako_insights_settings', 'ako_insights_per_page', [
            'sanitize_callback' => 'absint',
            'default'           => 12,
        ]);

        add_settings_section('ako_insights_main', 'API Configuration', function () {
            echo '<p>Configure how the Insights API serves your static portfolio site and admin dashboard.</p>';
        }, 'ako-insights-settings');

        add_settings_field('ako_insights_site_url', 'Allowed Origins (CORS)', function () {
            $val = get_option('ako_insights_site_url', 'https://akotarhakoson.com');
            echo '<input type="text" name="ako_insights_site_url" value="' . esc_attr($val) . '" class="regular-text" />';
            echo '<p class="description">Comma-separated URLs allowed to access the API. Include your portfolio domain, admin dashboard URL, and localhost for development.</p>';
        }, 'ako-insights-settings', 'ako_insights_main');

        add_settings_field('ako_insights_per_page', 'Posts Per Page', function () {
            $val = get_option('ako_insights_per_page', 12);
            echo '<input type="number" name="ako_insights_per_page" value="' . esc_attr($val) . '" min="1" max="50" />';
        }, 'ako-insights-settings', 'ako_insights_main');
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;
        $api_url = rest_url('akotarh/v1/');
        ?>
        <div class="wrap ako-settings">
            <h1>Insights Settings</h1>
            <div class="ako-api-info" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px;margin:20px 0">
                <h3 style="margin-top:0;color:#14532d">API Base URL</h3>
                <code style="background:#fff;padding:8px 16px;border-radius:4px;display:block;font-size:14px;border:1px solid #e2e8f0"><?php echo esc_html($api_url); ?></code>
                <h4 style="color:#14532d;margin-top:16px">Public Endpoints:</h4>
                <ul style="color:#4a5568">
                    <li><code>GET /insights</code> — List published insights</li>
                    <li><code>GET /insights/{id}</code> — Single insight with full content</li>
                    <li><code>GET /topics</code> — List all topics</li>
                </ul>
                <h4 style="color:#14532d;margin-top:16px">Admin Endpoints (require JWT):</h4>
                <ul style="color:#4a5568">
                    <li><code>POST /auth/login</code> — Authenticate &amp; get token</li>
                    <li><code>GET|POST /admin/insights</code> — List/Create insights</li>
                    <li><code>PUT|DELETE /admin/insights/{id}</code> — Update/Delete insight</li>
                    <li><code>GET|POST /admin/media</code> — List/Upload media</li>
                    <li><code>GET|PUT /admin/portfolio</code> — Portfolio content</li>
                    <li><code>GET /admin/stats</code> — Dashboard statistics</li>
                </ul>
            </div>
            <form method="post" action="options.php">
                <?php settings_fields('ako_insights_settings'); do_settings_sections('ako-insights-settings'); submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function admin_styles($hook) {
        if (strpos($hook, 'ako-insights') === false) return;
        wp_add_inline_style('wp-admin', '
            .ako-settings .form-table th { font-weight: 600; }
            .ako-settings code { background: #f1f5f9; padding: 2px 6px; border-radius: 3px; }
        ');
    }
}

Akotarh_Insights::get_instance();
