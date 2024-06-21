<?php
/*
Plugin Name: MikroTik Data Receiver
Description: Menerima data dari MikroTik dan menampilkan data di halaman administrator.
Version: 1.0
Author: unggul@ahliweb.co.id - https://ahliweb.co.id 
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MikroTik_Data_Receiver {

    private $token = 'xxx'; // Replace with your secure token

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_menu', array($this, 'register_admin_page'));
        register_activation_hook(__FILE__, array($this, 'create_database_table'));
    }

    public function register_routes() {
        register_rest_route('mikrotik/v1', '/data', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_mikrotik_data'),
        ));
    }

    public function handle_mikrotik_data(WP_REST_Request $request) {
        $token = $request->get_param('token');

        // Token yang di-generate sebelumnya
        $expected_token = $this->token;

        // Validasi token
        if ($token !== $expected_token) {
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
        global $wpdb;
        $table_name = $wpdb->prefix . 'mikrotik_data';
        $wpdb->insert($table_name, array(
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
        $table_name = $wpdb->prefix . 'mikrotik_data';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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
        $table_name = $wpdb->prefix . 'mikrotik_data';
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $last_day = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE time > DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $last_week = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE time > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $last_month = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE time > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $last_year = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE time > DATE_SUB(NOW(), INTERVAL 1 YEAR)");

        $data = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT 100");

        echo '<div class="wrap">';
        echo '<h1>MikroTik Data</h1>';
        echo '<p>By AhliWeb.co.id</p><hr />';
        echo '<h2>Rekapitulasi</h2>';
        echo '<ul>';
        echo '<li>Jumlah 1 hari terakhir: ' . $last_day . '</li>';
        echo '<li>Jumlah 7 hari terakhir: ' . $last_week . '</li>';
        echo '<li>Jumlah 30 hari terakhir: ' . $last_month . '</li>';
        echo '<li>Jumlah 1 tahun terakhir: ' . $last_year . '</li>';
        echo '<li>Total: ' . $total . '</li>';
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

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
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
        echo '<a class="page-numbers ' . $active . '" href="' . get_permalink() . '?paged=' . $i . '">' . $i . '</a>';
    }
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('mikrotik_data', 'mikrotik_data_shortcode');
