<?php
/*
Plugin Name: MikroTik Data Receiver
Description: Menerima data dari MikroTik dan menampilkan data di halaman administrator.
Version: 1.1
Author: unggul@ahliweb.co.id - https://ahliweb.co.id 
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MikroTik_Data_Receiver {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_menu', array($this, 'register_admin_page'));
        register_activation_hook(__FILE__, array($this, 'create_database_table'));
        register_activation_hook(__FILE__, array($this, 'insert_initial_token'));
    }

    public function register_routes() {
        register_rest_route('mikrotik/v1', '/data', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_mikrotik_data'),
        ));
    }

    public function handle_mikrotik_data(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mikrotik_token';
        $stored_token = wp_cache_get('mikrotik_token');
        if (false === $stored_token) {
            $stored_token = $wpdb->get_var($wpdb->prepare("SELECT token FROM $table_name LIMIT 1"));
            wp_cache_set('mikrotik_token', $stored_token);
        }
        
        $token = $request->get_param('token');

        // Validasi token
        if ($token !== $stored_token) {
            return new WP_REST_Response('Unauthorized', 401);
        }

        $username = sanitize_text_field($request->get_param('username'));
        $ip = sanitize_text_field($request->get_param('ip'));
        $mac = sanitize_text_field($request->get_param('mac'));
        $upload = intval($request->get_param('upload'));
        $download = intval($request->get_param('download'));
        $duration = intval($request->get_param('duration'));
        $interface = sanitize_text_field($request->get_param('interface'));
        $status = sanitize_text_field($request->get_param('status'));

        // Simpan data di database WordPress
        $data_table_name = $wpdb->prefix . 'mikrotik_data';
        $wpdb->insert($data_table_name, array(
            'username' => $username,
            'ip' => $ip,
            'mac' => $mac,
            'upload' => $upload,
            'download' => $download,
            'duration' => $duration,
            'interface' => $interface,
            'status' => $status,
            'time' => current_time('mysql')
        ));

        return new WP_REST_Response('Data received', 200);
    }

    public function create_database_table() {
        global $wpdb;
        $data_table_name = $wpdb->prefix . 'mikrotik_data';
        $token_table_name = $wpdb->prefix . 'mikrotik_token';
        $charset_collate = $wpdb->get_charset_collate();

        $sql_data_table = "CREATE TABLE $data_table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            username varchar(255) NOT NULL,
            ip varchar(255) NOT NULL,
            mac varchar(255) NOT NULL,
            upload bigint(20) NOT NULL,
            download bigint(20) NOT NULL,
            duration bigint(20) NOT NULL,
            interface varchar(255) NOT NULL,
            status varchar(255) NOT NULL,
            time datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql_token_table = "CREATE TABLE $token_table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token varchar(255) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_data_table);
        dbDelta($sql_token_table);
    }

    public function insert_initial_token() {
        global $wpdb;
        $token_table_name = $wpdb->prefix . 'mikrotik_token';
        $default_token = 'xxx'; // change with your sha1 token

        // Insert initial token if not exists
        if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $token_table_name")) == 0) {
            $wpdb->insert($token_table_name, array('token' => $default_token));
        }
    }

    public function register_admin_page() {
        add_menu_page(
            'MikroTik Data',
            'MikroTik Data',
            'manage_options',
            'mikrotik-data',
            array($this, 'admin_page_content'),
            'dashicons-chart-bar',
            6
        );
    }

    public function admin_page_content() {
        global $wpdb;
        $data_table_name = $wpdb->prefix . 'mikrotik_data';
        $token_table_name = $wpdb->prefix . 'mikrotik_token';

        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $data_table_name"));
        $last_day = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $data_table_name WHERE time > DATE_SUB(NOW(), INTERVAL 1 DAY)"));
        $last_week = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $data_table_name WHERE time > DATE_SUB(NOW(), INTERVAL 7 DAY)"));
        $last_month = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $data_table_name WHERE time > DATE_SUB(NOW(), INTERVAL 30 DAY)"));
        $last_year = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $data_table_name WHERE time > DATE_SUB(NOW(), INTERVAL 1 YEAR)"));

        $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM $data_table_name ORDER BY time DESC LIMIT 100"));
        $stored_token = wp_cache_get('mikrotik_token');
        if (false === $stored_token) {
            $stored_token = $wpdb->get_var($wpdb->prepare("SELECT token FROM $token_table_name LIMIT 1"));
            wp_cache_set('mikrotik_token', $stored_token);
        }

        if (isset($_POST['new_token']) && check_admin_referer('update_token_nonce')) {
            $new_token = sanitize_text_field($_POST['new_token']);
            $wpdb->update($token_table_name, array('token' => $new_token), array('id' => 1));
            wp_cache_set('mikrotik_token', $new_token);
            $stored_token = $new_token;
            echo '<div id="message" class="updated notice is-dismissible"><p>Token updated successfully.</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>MikroTik Data</h1>';
        echo '<p>Version 1.1 - By AhliWeb.co.id</p><hr />';
        echo '<h2>Rekapitulasi</h2>';
        echo '<ul>';
        echo '<li>Jumlah 1 hari terakhir: ' . esc_html($last_day) . '</li>';
        echo '<li>Jumlah 7 hari terakhir: ' . esc_html($last_week) . '</li>';
        echo '<li>Jumlah 30 hari terakhir: ' . esc_html($last_month) . '</li>';
        echo '<li>Jumlah 1 tahun terakhir: ' . esc_html($last_year) . '</li>';
        echo '<li>Total: ' . esc_html($total) . '</li>';
        echo '</ul>';

        echo '<h2>Data Terbaru</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Username</th><th>IP</th><th>MAC</th><th>Upload</th><th>Download</th><th>Duration</th><th>Interface</th><th>Status</th><th>Time</th></tr></thead>';
        echo '<tbody>';
        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row->username) . '</td>';
            echo '<td>' . esc_html($row->ip) . '</td>';
            echo '<td>' . esc_html($row->mac) . '</td>';
            echo '<td>' . esc_html($row->upload) . '</td>';
            echo '<td>' . esc_html($row->download) . '</td>';
            echo '<td>' . esc_html($row->duration) . '</td>';
            echo '<td>' . esc_html($row->interface) . '</td>';
            echo '<td>' . esc_html($row->status) . '</td>';
            echo '<td>' . esc_html($row->time) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<h2>Update Token</h2>';
        echo '<form method="post" action="">';
        wp_nonce_field('update_token_nonce');
        echo '<table class="form-table">';
        echo '<tr valign="top">';
        echo '<th scope="row">Current Token</th>';
        echo '<td><input type="text" name="new_token" value="' . esc_attr($stored_token) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';
        echo '<p class="submit"><input type="submit" class="button-primary" value="Update Token" /></p>';
        echo '</form>';
        
        echo '</div>';
    }
}

new MikroTik_Data_Receiver();

function mikrotik_data_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mikrotik_data';

    $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name"));
    $total_pages = ceil($total_items / $limit);

    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY time DESC LIMIT %d OFFSET %d", $limit, $offset));

    ob_start();
    echo '<table><tr><th>Username</th><th>IP</th><th>MAC</th><th>Upload</th><th>Download</th><th>Duration</th><th>Interface</th><th>Status</th><th>Time</th></tr>';
    foreach ($results as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($row->username) . '</td>';
        echo '<td>' . esc_html($row->ip) . '</td>';
        echo '<td>' . esc_html($row->mac) . '</td>';
        echo '<td>' . esc_html($row->upload) . '</td>';
        echo '<td>' . esc_html($row->download) . '</td>';
        echo '<td>' . esc_html($row->duration) . '</td>';
        echo '<td>' . esc_html($row->interface) . '</td>';
        echo '<td>' . esc_html($row->status) . '</td>';
        echo '<td>' . esc_html($row->time) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    // Pagination
    echo '<div class="pagination">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = ($i == $page) ? 'active' : '';
        echo '<a class="page-numbers ' . esc_html($active) . '" href="' . esc_url(get_permalink()) . '?paged=' . esc_html($i) . '">' . esc_html($i) . '</a>';
    }
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('mikrotik_data', 'mikrotik_data_shortcode');

?>
