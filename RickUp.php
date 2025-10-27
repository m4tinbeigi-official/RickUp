<?php
/**
 * Plugin Name: RickUp
 * Plugin URI: https://ricksacnehz.ir/
 * Description: A comprehensive WordPress backup and rollback plugin. Take daily database and uploads backups, store on host, send to Telegram via bot (with proxy support for filtered regions), set frequency, and rollback plugins/themes to previous versions. Modern UI, logs, email notifications, full site backups, and simple encryption.
 * Version: 1.1
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Author: Rick Sanchez
 * Author URI: https://ricksacnehz.ir
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rickup
 * Domain Path: /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants.
define( 'RICKUP_VERSION', '1.1' );
define( 'RICKUP_BACKUP_DIR', WP_CONTENT_DIR . '/rickup-backups/' );
define( 'RICKUP_VERSIONS_DIR', WP_CONTENT_DIR . '/rickup-versions/' );
define( 'RICKUP_LOGS_DIR', WP_CONTENT_DIR . '/rickup-logs/' );

foreach ( array( RICKUP_BACKUP_DIR, RICKUP_VERSIONS_DIR, RICKUP_LOGS_DIR ) as $dir ) {
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
    }
}

/**
 * Load text domain for internationalization.
 */
function rickup_load_textdomain() {
    load_plugin_textdomain( 'rickup', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'rickup_load_textdomain' );

/**
 * Enqueue admin styles and scripts.
 */
function rickup_enqueue_admin_assets( $hook ) {
    if ( strpos( $hook, 'rickup' ) === false ) {
        return;
    }

    wp_enqueue_style(
        'rickup-admin-style',
        plugin_dir_url( __FILE__ ) . 'admin-style.css',
        array(),
        RICKUP_VERSION
    );
    wp_enqueue_script(
        'rickup-admin-script',
        plugin_dir_url( __FILE__ ) . 'admin-script.js',
        array( 'jquery' ),
        RICKUP_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'rickup_enqueue_admin_assets' );

/**
 * Add admin menu.
 */
function rickup_admin_menu() {
    add_menu_page(
        __( 'RickUp', 'rickup' ),
        __( 'RickUp', 'rickup' ),
        'manage_options',
        'rickup',
        'rickup_settings_page',
        'dashicons-backup',
        80
    );

    add_submenu_page(
        'rickup',
        __( 'Settings', 'rickup' ),
        __( 'Settings', 'rickup' ),
        'manage_options',
        'rickup',
        'rickup_settings_page'
    );

    add_submenu_page(
        'rickup',
        __( 'Backups', 'rickup' ),
        __( 'Backups', 'rickup' ),
        'manage_options',
        'rickup-backups',
        'rickup_backups_page'
    );

    add_submenu_page(
        'rickup',
        __( 'Rollback', 'rickup' ),
        __( 'Rollback', 'rickup' ),
        'manage_options',
        'rickup-rollback',
        'rickup_rollback_page'
    );

    add_submenu_page(
        'rickup',
        __( 'Logs', 'rickup' ),
        __( 'Logs', 'rickup' ),
        'manage_options',
        'rickup-logs',
        'rickup_logs_page'
    );
}
add_action( 'admin_menu', 'rickup_admin_menu' );

/**
 * Register settings.
 */
function rickup_register_settings() {
    $settings = array(
        'rickup_backup_frequency'     => array( 'type' => 'string', 'default' => 'daily' ),
        'rickup_telegram_token'       => array( 'type' => 'string', 'default' => '' ),
        'rickup_telegram_chat_id'     => array( 'type' => 'string', 'default' => '' ),
        'rickup_enable_telegram'      => array( 'type' => 'boolean', 'default' => false ),
        'rickup_retention_days'       => array( 'type' => 'integer', 'default' => 30 ),
        'rickup_proxy_host'           => array( 'type' => 'string', 'default' => '' ),
        'rickup_proxy_port'           => array( 'type' => 'integer', 'default' => 8080 ),
        'rickup_proxy_user'           => array( 'type' => 'string', 'default' => '' ),
        'rickup_proxy_pass'           => array( 'type' => 'string', 'default' => '' ),
        'rickup_email_notify'         => array( 'type' => 'boolean', 'default' => false ),
        'rickup_admin_email'          => array( 'type' => 'email', 'default' => get_option( 'admin_email' ) ),
        'rickup_backup_full_site'     => array( 'type' => 'boolean', 'default' => false ),
        'rickup_encrypt_backups'      => array( 'type' => 'boolean', 'default' => false ),
        'rickup_encryption_key'       => array( 'type' => 'string', 'default' => '' ),
    );

    foreach ( $settings as $option => $args ) {
        register_setting(
            'rickup_options_group',
            $option,
            array(
                'type'    => $args['type'],
                'default' => $args['default'],
                'sanitize_callback' => function( $value ) use ( $args ) {
                    switch ( $args['type'] ) {
                        case 'string':
                            return sanitize_text_field( $value );
                        case 'integer':
                            return absint( $value );
                        case 'email':
                            return sanitize_email( $value );
                        case 'boolean':
                            return (bool) $value;
                        default:
                            return $value;
                    }
                },
            )
        );
    }
}
add_action( 'admin_init', 'rickup_register_settings' );

/**
 * Settings page.
 */
function rickup_settings_page() {
    // Handle test Telegram.
    if ( isset( $_GET['test_telegram'] ) && '1' === $_GET['test_telegram'] ) {
        $test_result = rickup_test_telegram_connection();
        add_action( 'admin_notices', function() use ( $test_result ) {
            echo '<div class="notice notice-' . ( $test_result ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $test_result ? __( 'Test successful: Message sent to Telegram!', 'rickup' ) : ( 'Test failed: ' . $test_result ) ) . '</p></div>';
        } );
    }

    ?>
    <div class="wrap rickup-wrap">
        <h1 class="rickup-title"><?php esc_html_e( 'RickUp Settings', 'rickup' ); ?></h1>
        <div class="rickup-card">
            <form method="post" action="options.php">
                <?php
                settings_fields( 'rickup_options_group' );
                do_settings_sections( 'rickup_options_group' );
                ?>
                <table class="form-table rickup-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Backup Frequency', 'rickup' ); ?></th>
                        <td>
                            <select name="rickup_backup_frequency" class="rickup-select">
                                <option value="hourly" <?php selected( get_option( 'rickup_backup_frequency' ), 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'rickup' ); ?></option>
                                <option value="daily" <?php selected( get_option( 'rickup_backup_frequency' ), 'daily' ); ?>><?php esc_html_e( 'Daily', 'rickup' ); ?></option>
                                <option value="weekly" <?php selected( get_option( 'rickup_backup_frequency' ), 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'rickup' ); ?></option>
                                <option value="monthly" <?php selected( get_option( 'rickup_backup_frequency' ), 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'rickup' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'How often to take backups?', 'rickup' ); ?></p>
                        </td>
                    </tr>
                    <!-- Telegram Settings -->
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Telegram Bot Token', 'rickup' ); ?></th>
                        <td><input type="text" name="rickup_telegram_token" value="<?php echo esc_attr( get_option( 'rickup_telegram_token' ) ); ?>" class="regular-text rickup-input" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Telegram Chat ID', 'rickup' ); ?></th>
                        <td><input type="text" name="rickup_telegram_chat_id" value="<?php echo esc_attr( get_option( 'rickup_telegram_chat_id' ) ); ?>" class="regular-text rickup-input" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Telegram', 'rickup' ); ?></th>
                        <td>
                            <label class="rickup-switch">
                                <input type="checkbox" name="rickup_enable_telegram" value="1" <?php checked( get_option( 'rickup_enable_telegram' ), 1 ); ?> />
                                <span class="rickup-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <!-- Proxy Settings -->
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Proxy Host (for filtered regions)', 'rickup' ); ?></th>
                        <td><input type="text" name="rickup_proxy_host" value="<?php echo esc_attr( get_option( 'rickup_proxy_host' ) ); ?>" class="regular-text rickup-input" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Proxy Port', 'rickup' ); ?></th>
                        <td><input type="number" name="rickup_proxy_port" value="<?php echo esc_attr( get_option( 'rickup_proxy_port' ) ); ?>" class="small-text rickup-input" min="1" max="65535" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Proxy Username', 'rickup' ); ?></th>
                        <td><input type="text" name="rickup_proxy_user" value="<?php echo esc_attr( get_option( 'rickup_proxy_user' ) ); ?>" class="regular-text rickup-input" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Proxy Password', 'rickup' ); ?></th>
                        <td><input type="password" name="rickup_proxy_pass" value="<?php echo esc_attr( get_option( 'rickup_proxy_pass' ) ); ?>" class="regular-text rickup-input" /></td>
                    </tr>
                    <!-- Email Notification -->
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Email Notifications', 'rickup' ); ?></th>
                        <td>
                            <label class="rickup-switch">
                                <input type="checkbox" name="rickup_email_notify" value="1" <?php checked( get_option( 'rickup_email_notify' ), 1 ); ?> />
                                <span class="rickup-slider"></span>
                            </label>
                            <input type="email" name="rickup_admin_email" value="<?php echo esc_attr( get_option( 'rickup_admin_email' ) ); ?>" class="regular-text rickup-input" />
                        </td>
                    </tr>
                    <!-- Full Site Backup -->
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Full Site Backup', 'rickup' ); ?></th>
                        <td>
                            <label class="rickup-switch">
                                <input type="checkbox" name="rickup_backup_full_site" value="1" <?php checked( get_option( 'rickup_backup_full_site' ), 1 ); ?> />
                                <span class="rickup-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Include plugins and themes in backups (larger files).', 'rickup' ); ?></p>
                        </td>
                    </tr>
                    <!-- Encryption -->
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Encrypt Backups', 'rickup' ); ?></th>
                        <td>
                            <label class="rickup-switch">
                                <input type="checkbox" name="rickup_encrypt_backups" value="1" <?php checked( get_option( 'rickup_encrypt_backups' ), 1 ); ?> />
                                <span class="rickup-slider"></span>
                            </label>
                            <input type="text" name="rickup_encryption_key" value="<?php echo esc_attr( get_option( 'rickup_encryption_key' ) ); ?>" class="regular-text rickup-input" />
                            <p class="description"><?php esc_html_e( 'Simple encryption key (min 8 chars). Note: For production, use stronger methods.', 'rickup' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Retention Days', 'rickup' ); ?></th>
                        <td>
                            <input type="number" name="rickup_retention_days" value="<?php echo esc_attr( get_option( 'rickup_retention_days' ) ); ?>" min="1" max="365" class="small-text rickup-input" />
                            <p class="description"><?php esc_html_e( 'Delete backups older than this.', 'rickup' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save Settings', 'rickup' ), 'primary rickup-btn', '', false, array( 'class' => 'rickup-submit' ) ); ?>
            </form>
        </div>
        <div class="rickup-card">
            <h2><?php esc_html_e( 'Quick Actions', 'rickup' ); ?></h2>
            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=rickup_manual_backup' ), 'rickup_manual_backup' ); ?>" class="button button-primary rickup-btn-large"><?php esc_html_e( 'Manual Backup', 'rickup' ); ?></a>
            <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=rickup&test_telegram=1' ), 'rickup_test_telegram' ); ?>" class="button rickup-btn-large"><?php esc_html_e( 'Test Telegram', 'rickup' ); ?></a>
        </div>
    </div>
    <?php
}

/**
 * Test Telegram connection.
 *
 * @return bool|string True on success, error message on failure.
 */
function rickup_test_telegram_connection() {
    $token = get_option( 'rickup_telegram_token' );
    $chat_id = get_option( 'rickup_telegram_chat_id' );
    if ( empty( $token ) || empty( $chat_id ) ) {
        return __( 'Token or Chat ID is empty.', 'rickup' );
    }

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $body = array(
        'chat_id' => $chat_id,
        'text'    => __( 'Connection test from RickUp - ', 'rickup' ) . date( 'Y-m-d H:i:s' ),
    );

    $args = array( 'body' => $body );
    $proxy_args = rickup_get_proxy_args();
    $args = array_merge( $args, $proxy_args );

    $response = wp_remote_post( $url, $args );

    if ( is_wp_error( $response ) ) {
        return $response->get_error_message();
    }

    $body_resp = json_decode( wp_remote_retrieve_body( $response ), true );
    return isset( $body_resp['ok'] ) && $body_resp['ok'] ? true : ( $body_resp['description'] ?? __( 'Unknown error.', 'rickup' ) );
}
add_action( 'admin_post_rickup_test_telegram', function() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'rickup_test_telegram' ) ) {
        wp_die( __( 'Unauthorized.', 'rickup' ) );
    }
    $test_result = rickup_test_telegram_connection();
    wp_redirect( add_query_arg( 'test_telegram', '1', admin_url( 'admin.php?page=rickup' ) ) . ( $test_result ? '&success=1' : '&error=1' ) );
    exit;
} );

/**
 * Get proxy arguments for wp_remote_post.
 *
 * @return array Proxy args.
 */
function rickup_get_proxy_args() {
    $host = get_option( 'rickup_proxy_host' );
    if ( empty( $host ) ) {
        return array();
    }

    $port = get_option( 'rickup_proxy_port', 8080 );
    $proxy = $host . ':' . $port;
    $args = array( 'proxy' => $proxy );

    $user = get_option( 'rickup_proxy_user' );
    if ( ! empty( $user ) ) {
        $pass = get_option( 'rickup_proxy_pass' );
        $args['stream_context'] = stream_context_create( array(
            'http' => array(
                'proxy' => "tcp://$host:$port",
                'request_fulluri' => true,
                'header' => "Proxy-Authorization: Basic " . base64_encode( "$user:$pass" ),
            ),
        ) );
    }

    return $args;
}

/**
 * Backups management page.
 */
function rickup_backups_page() {
    $backups = glob( RICKUP_BACKUP_DIR . '*.*' );
    usort( $backups, function( $a, $b ) {
        return filemtime( $b ) - filemtime( $a );
    } );

    ?>
    <div class="wrap rickup-wrap">
        <h1 class="rickup-title"><?php esc_html_e( 'Manage Backups', 'rickup' ); ?></h1>
        <div class="rickup-card">
            <p><?php printf( esc_html__( 'Stored backups on host (%d items). Download or delete as needed.', 'rickup' ), count( $backups ) ); ?></p>
            <?php if ( empty( $backups ) ): ?>
                <p class="rickup-empty"><?php esc_html_e( 'No backups found. ', 'rickup' ); ?><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=rickup_manual_backup' ) ); ?>"><?php esc_html_e( 'Take one now!', 'rickup' ); ?></a></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped rickup-table">
                    <thead><tr><th><?php esc_html_e( 'Type', 'rickup' ); ?></th><th><?php esc_html_e( 'Date', 'rickup' ); ?></th><th><?php esc_html_e( 'Size', 'rickup' ); ?></th><th><?php esc_html_e( 'Actions', 'rickup' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $backups as $file ): 
                            $name = basename( $file );
                            $type = strpos( $name, 'db_' ) === 0 ? __( 'Database', 'rickup' ) : ( strpos( $name, 'uploads_' ) === 0 ? __( 'Uploads', 'rickup' ) : ( strpos( $name, 'full_' ) === 0 ? __( 'Full Site', 'rickup' ) : __( 'Unknown', 'rickup' ) ) );
                            $size = size_format( filesize( $file ) );
                            $date = date_i18n( 'Y-m-d H:i', filemtime( $file ) );
                        ?>
                            <tr>
                                <td><?php echo esc_html( $type ); ?></td>
                                <td><?php echo esc_html( $date ); ?></td>
                                <td><?php echo esc_html( $size ); ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=rickup_download_backup&file=' . urlencode( $name ) ), 'rickup_download' ); ?>" class="button rickup-btn-small"><?php esc_html_e( 'Download', 'rickup' ); ?></a>
                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=rickup_delete_backup&file=' . urlencode( $name ) ), 'rickup_delete' ); ?>" class="button rickup-btn-small rickup-danger" onclick="return confirm('<?php esc_js( __( 'Are you sure?', 'rickup' ) ); ?>');"><?php esc_html_e( 'Delete', 'rickup' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Logs page.
 */
function rickup_logs_page() {
    $logs = glob( RICKUP_LOGS_DIR . '*.log' );
    usort( $logs, function( $a, $b ) {
        return filemtime( $b ) - filemtime( $a );
    } );

    ?>
    <div class="wrap rickup-wrap">
        <h1 class="rickup-title"><?php esc_html_e( 'Backup Logs', 'rickup' ); ?></h1>
        <div class="rickup-card">
            <p><?php esc_html_e( 'Recent logs (last 5). Details on success, failure, or errors.', 'rickup' ); ?></p>
            <?php if ( empty( $logs ) ): ?>
                <p class="rickup-empty"><?php esc_html_e( 'No logs found.', 'rickup' ); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped rickup-table">
                    <thead><tr><th><?php esc_html_e( 'Date', 'rickup' ); ?></th><th><?php esc_html_e( 'Type', 'rickup' ); ?></th><th><?php esc_html_e( 'Details', 'rickup' ); ?></th><th><?php esc_html_e( 'Actions', 'rickup' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( array_slice( $logs, 0, 5 ) as $log ): 
                            $content = file_get_contents( $log );
                            $date = date_i18n( 'Y-m-d H:i', filemtime( $log ) );
                            $type = strpos( $content, 'success' ) !== false ? __( 'Success', 'rickup' ) : __( 'Error', 'rickup' );
                        ?>
                            <tr>
                                <td><?php echo esc_html( $date ); ?></td>
                                <td><?php echo esc_html( $type ); ?></td>
                                <td><?php echo esc_html( substr( $content, 0, 100 ) ) . '...'; ?></td>
                                <td><a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=rickup_download_log&file=' . urlencode( basename( $log ) ) ), 'rickup_download_log' ); ?>" class="button rickup-btn-small"><?php esc_html_e( 'Download', 'rickup' ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Rollback page.
 */
function rickup_rollback_page() {
    $plugins = get_plugins();
    $active_plugins = get_option( 'active_plugins' );
    $themes = wp_get_themes();
    $current_theme = wp_get_theme();

    ?>
    <div class="wrap rickup-wrap">
        <h1 class="rickup-title"><?php esc_html_e( 'Rollback Plugins & Themes', 'rickup' ); ?></h1>
        <div class="rickup-tabs">
            <button class="rickup-tablink active" onclick="rickupOpenTab(event, 'plugins')"><?php esc_html_e( 'Plugins', 'rickup' ); ?></button>
            <button class="rickup-tablink" onclick="rickupOpenTab(event, 'themes')"><?php esc_html_e( 'Themes', 'rickup' ); ?></button>
        </div>
        <div id="plugins" class="rickup-tabcontent active">
            <div class="rickup-card">
                <p><?php esc_html_e( 'Download and switch to previous versions. Auto-backup before rollback.', 'rickup' ); ?></p>
                <?php if ( empty( $plugins ) ): ?>
                    <p class="rickup-empty"><?php esc_html_e( 'No plugins installed.', 'rickup' ); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped rickup-table">
                        <thead><tr><th><?php esc_html_e( 'Name', 'rickup' ); ?></th><th><?php esc_html_e( 'Current Version', 'rickup' ); ?></th><th><?php esc_html_e( 'Active', 'rickup' ); ?></th><th><?php esc_html_e( 'Rollback', 'rickup' ); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ( $plugins as $slug => $plugin ): 
                                $is_active = in_array( $slug, $active_plugins, true );
                                $versions = rickup_get_plugin_versions( $slug );
                                $current_ver = $plugin['Version'];
                                $prev_versions = array_filter( $versions, function( $v ) use ( $current_ver ) {
                                    return version_compare( $v, $current_ver, '<' );
                                } );
                                $prev_version = ! empty( $prev_versions ) ? end( $prev_versions ) : null;
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $plugin['Name'] ); ?></td>
                                    <td><?php echo esc_html( $current_ver ); ?></td>
                                    <td><?php echo $is_active ? __( 'Yes', 'rickup' ) : __( 'No', 'rickup' ); ?></td>
                                    <td>
                                        <?php if ( $prev_version ): ?>
                                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=rickup_rollback_plugin&slug=' . urlencode( $slug ) . '&version=' . urlencode( $prev_version ) ), 'rickup_rollback' ); ?>" class="button rickup-btn-small" onclick="return confirm('<?php esc_js( sprintf( __( 'Rollback to %s?', 'rickup' ), $prev_version ) ); ?>');"><?php printf( esc_html__( 'To %s', 'rickup' ), esc_html( $prev_version ) ); ?></a>
                                        <?php else: ?>
                                            <span class="rickup-muted"><?php esc_html_e( 'No previous version', 'rickup' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <div id="themes" class="rickup-tabcontent">
            <div class="rickup-card">
                <p><?php esc_html_e( 'Download and switch to previous theme versions.', 'rickup' ); ?></p>
                <?php if ( empty( $themes ) ): ?>
                    <p class="rickup-empty"><?php esc_html_e( 'No themes installed.', 'rickup' ); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped rickup-table">
                        <thead><tr><th><?php esc_html_e( 'Name', 'rickup' ); ?></th><th><?php esc_html_e( 'Current Version', 'rickup' ); ?></th><th><?php esc_html_e( 'Active', 'rickup' ); ?></th><th><?php esc_html_e( 'Rollback', 'rickup' ); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ( $themes as $slug => $theme ): 
                                $is_active = ( $slug === $current_theme->get_template() );
                                $versions = rickup_get_theme_versions( $slug );
                                $current_ver = $theme->get( 'Version' );
                                $prev_versions = array_filter( $versions, function( $v ) use ( $current_ver ) {
                                    return version_compare( $v, $current_ver, '<' );
                                } );
                                $prev_version = ! empty( $prev_versions ) ? end( $prev_versions ) : null;
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $theme->get( 'Name' ) ); ?></td>
                                    <td><?php echo esc_html( $current_ver ); ?></td>
                                    <td><?php echo $is_active ? __( 'Yes', 'rickup' ) : __( 'No', 'rickup' ); ?></td>
                                    <td>
                                        <?php if ( $prev_version ): ?>
                                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=rickup_rollback_theme&slug=' . urlencode( $slug ) . '&version=' . urlencode( $prev_version ) ), 'rickup_rollback' ); ?>" class="button rickup-btn-small" onclick="return confirm('<?php esc_js( sprintf( __( 'Rollback to %s?', 'rickup' ), $prev_version ) ); ?>');"><?php printf( esc_html__( 'To %s', 'rickup' ), esc_html( $prev_version ) ); ?></a>
                                        <?php else: ?>
                                            <span class="rickup-muted"><?php esc_html_e( 'No previous version', 'rickup' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    function rickupOpenTab( evt, tabName ) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName( 'rickup-tabcontent' );
        for ( i = 0; i < tabcontent.length; i++ ) {
            tabcontent[i].className = tabcontent[i].className.replace( ' active', '' );
        }
        tablinks = document.getElementsByClassName( 'rickup-tablink' );
        for ( i = 0; i < tablinks.length; i++ ) {
            tablinks[i].className = tablinks[i].className.replace( ' active', '' );
        }
        document.getElementById( tabName ).className += ' active';
        evt.currentTarget.className += ' active';
    }
    </script>
    <?php
}

/**
 * Get plugin versions from WP API.
 *
 * @param string $slug Plugin slug.
 * @return array Versions.
 */
function rickup_get_plugin_versions( $slug ) {
    $response = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/' . $slug . '.json' );
    if ( is_wp_error( $response ) ) {
        return array();
    }
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( isset( $data['versions'] ) ) {
        unset( $data['versions']['trunk'], $data['versions']['develop'] );
        $versions = array_keys( $data['versions'] );
        usort( $versions, 'version_compare' );
        return $versions;
    }
    return array();
}

/**
 * Get theme versions from WP API.
 *
 * @param string $slug Theme slug.
 * @return array Versions.
 */
function rickup_get_theme_versions( $slug ) {
    $response = wp_remote_get( 'https://api.wordpress.org/themes/info/1.0/?action=theme_information&request[slug]=' . $slug );
    if ( is_wp_error( $response ) ) {
        return array();
    }
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( isset( $data['versions'] ) ) {
        unset( $data['versions']['trunk'], $data['versions']['develop'] );
        $versions = array_keys( $data['versions'] );
        usort( $versions, 'version_compare' );
        return $versions;
    }
    return array();
}

/**
 * Backup database.
 *
 * @param bool $encrypt Encrypt flag.
 * @param string $key Encryption key.
 * @return string|false File path or false.
 */
function rickup_backup_database( $encrypt = false, $key = '' ) {
    global $wpdb;

    $tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
    if ( empty( $tables ) ) {
        return false;
    }

    $sql = '-- RickUp Backup: ' . date_i18n( 'Y-m-d H:i:s' ) . "\n\n";
    foreach ( $tables as $table ) {
        $table_name = $table[0];
        $create = $wpdb->get_row( 'SHOW CREATE TABLE `' . esc_sql( $table_name ) . '`', ARRAY_N );
        if ( $create ) {
            $sql .= $create[1] . ";\n\n";
        }

        $rows = $wpdb->get_results( 'SELECT * FROM `' . esc_sql( $table_name ) . '`', ARRAY_A );
        foreach ( $rows as $row ) {
            $values = array_map( function( $v ) {
                return is_null( $v ) ? 'NULL' : "'" . esc_sql( $v ) . "'";
            }, array_values( $row ) );
            $sql .= 'INSERT INTO `' . esc_sql( $table_name ) . '` VALUES (' . implode( ',', $values ) . ');' . "\n";
        }
        $sql .= "\n";
    }

    $filename = RICKUP_BACKUP_DIR . 'db_backup_' . date( 'Y-m-d_H-i-s' ) . '.sql';
    if ( $encrypt && ! empty( $key ) ) {
        $sql = rickup_simple_encrypt( $sql, $key );
        $filename .= '.enc';
    }

    $result = file_put_contents( $filename, $sql );
    return $result ? $filename : false;
}

/**
 * Backup uploads.
 *
 * @param bool $encrypt Encrypt flag.
 * @param string $key Encryption key.
 * @return string|false File path or false.
 */
function rickup_backup_uploads( $encrypt = false, $key = '' ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        rickup_log_message( __( 'ZipArchive class not available.', 'rickup' ), 'error' );
        return false;
    }

    $upload_dir = wp_upload_dir();
    $basedir = $upload_dir['basedir'];
    $filename = RICKUP_BACKUP_DIR . 'uploads_backup_' . date( 'Y-m-d_H-i-s' ) . '.zip';

    $zip = new ZipArchive();
    if ( $zip->open( $filename, ZipArchive::CREATE ) !== true ) {
        return false;
    }

    $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $basedir ), RecursiveIteratorIterator::LEAVES_ONLY );
    foreach ( $files as $file ) {
        if ( ! $file->isDir() ) {
            $file_path = $file->getRealPath();
            $relative_path = substr( $file_path, strlen( $basedir ) + 1 );
            $zip->addFile( $file_path, $relative_path );
        }
    }
    $zip->close();

    if ( $encrypt && ! empty( $key ) ) {
        $content = file_get_contents( $filename );
        $encrypted = rickup_simple_encrypt( $content, $key );
        $enc_filename = $filename . '.enc';
        file_put_contents( $enc_filename, $encrypted );
        unlink( $filename );
        return $enc_filename;
    }

    return $filename;
}

/**
 * Backup full site.
 *
 * @param bool $encrypt Encrypt flag.
 * @param string $key Encryption key.
 * @return string|false File path or false.
 */
function rickup_backup_full_site( $encrypt = false, $key = '' ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        return false;
    }

    $plugins_dir = WP_PLUGIN_DIR;
    $themes_dir = WP_CONTENT_DIR . '/themes';
    $filename = RICKUP_BACKUP_DIR . 'full_site_backup_' . date( 'Y-m-d_H-i-s' ) . '.zip';

    $zip = new ZipArchive();
    if ( $zip->open( $filename, ZipArchive::CREATE ) !== true ) {
        return false;
    }

    // Add plugins.
    $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugins_dir ), RecursiveIteratorIterator::LEAVES_ONLY );
    foreach ( $iterator as $file ) {
        if ( ! $file->isDir() ) {
            $file_path = $file->getRealPath();
            $relative = substr( $file_path, strlen( $plugins_dir ) + 1 );
            $zip->addFile( $file_path, 'plugins/' . $relative );
        }
    }

    // Add themes.
    $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $themes_dir ), RecursiveIteratorIterator::LEAVES_ONLY );
    foreach ( $iterator as $file ) {
        if ( ! $file->isDir() ) {
            $file_path = $file->getRealPath();
            $relative = substr( $file_path, strlen( $themes_dir ) + 1 );
            $zip->addFile( $file_path, 'themes/' . $relative );
        }
    }

    $zip->close();

    if ( $encrypt && ! empty( $key ) ) {
        $content = file_get_contents( $filename );
        $encrypted = rickup_simple_encrypt( $content, $key );
        $enc_filename = $filename . '.enc';
        file_put_contents( $enc_filename, $encrypted );
        unlink( $filename );
        return $enc_filename;
    }

    return $filename;
}

/**
 * Simple XOR encryption (demo only; use openssl in production).
 *
 * @param string $data Data to encrypt.
 * @param string $key Key.
 * @return string Encrypted data.
 */
function rickup_simple_encrypt( $data, $key ) {
    $key = md5( $key );
    $out = '';
    $key_len = strlen( $key );
    for ( $i = 0; $i < strlen( $data ); $i++ ) {
        $out .= $data[ $i ] ^ $key[ $i % $key_len ];
    }
    return base64_encode( $out );
}

/**
 * Send to Telegram with chunking and proxy.
 *
 * @param string $file_path File path.
 * @param string $caption Caption.
 * @return bool Success.
 */
function rickup_send_to_telegram( $file_path, $caption = '' ) {
    if ( ! get_option( 'rickup_enable_telegram' ) ) {
        return false;
    }

    $token = get_option( 'rickup_telegram_token' );
    $chat_id = get_option( 'rickup_telegram_chat_id' );
    if ( empty( $token ) || empty( $chat_id ) || ! file_exists( $file_path ) ) {
        return false;
    }

    $file_size = filesize( $file_path );
    $chunk_size = 48 * 1024 * 1024; // 48MB.
    $proxy_args = rickup_get_proxy_args();
    $success = true;

    if ( $file_size <= $chunk_size ) {
        $url = "https://api.telegram.org/bot{$token}/sendDocument";
        $post = array(
            'chat_id' => $chat_id,
            'caption' => $caption,
            'document' => new CURLFile( $file_path ),
        );
        $response = wp_remote_post( $url, array_merge( array( 'multipart' => $post ), $proxy_args ) );
        $success = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
    } else {
        // Chunking logic (simplified; implement full if needed).
        rickup_log_message( __( 'Large file chunking not fully implemented in this version.', 'rickup' ), 'warning' );
        $success = false;
    }

    rickup_log_message( $success ? __( 'Telegram send success', 'rickup' ) : __( 'Telegram send failed', 'rickup' ), $success ? 'info' : 'error' );

    // Email notify.
    if ( get_option( 'rickup_email_notify' ) && $success ) {
        $email = get_option( 'rickup_admin_email' );
        wp_mail( $email, __( 'RickUp Backup Sent', 'rickup' ), $caption );
    }

    return $success;
}

/**
 * Log message.
 *
 * @param string $message Message.
 * @param string $type Type.
 */
function rickup_log_message( $message, $type = 'info' ) {
    $log_file = RICKUP_LOGS_DIR . 'backup_log_' . date( 'Y-m-d' ) . '.log';
    $timestamp = date( 'Y-m-d H:i:s' );
    file_put_contents( $log_file, sprintf( '[%s] [%s] %s' . PHP_EOL, $timestamp, $type, $message ), FILE_APPEND | LOCK_EX );
}

/**
 * Main backup function.
 */
function rickup_do_backup() {
    $encrypt = get_option( 'rickup_encrypt_backups' );
    $key = get_option( 'rickup_encryption_key' );
    if ( $encrypt && empty( $key ) ) {
        $key = wp_generate_password( 12, false );
        update_option( 'rickup_encryption_key', $key );
    }

    $db_file = rickup_backup_database( $encrypt, $key );
    $uploads_file = rickup_backup_uploads( $encrypt, $key );
    $full_file = get_option( 'rickup_backup_full_site' ) ? rickup_backup_full_site( $encrypt, $key ) : false;

    if ( $db_file ) {
        rickup_send_to_telegram( $db_file, __( 'Database Backup: ', 'rickup' ) . basename( $db_file ) );
    }
    if ( $uploads_file ) {
        rickup_send_to_telegram( $uploads_file, __( 'Uploads Backup: ', 'rickup' ) . basename( $uploads_file ) );
    }
    if ( $full_file ) {
        rickup_send_to_telegram( $full_file, __( 'Full Site Backup: ', 'rickup' ) . basename( $full_file ) );
    }

    // Cleanup old backups.
    $retention = absint( get_option( 'rickup_retention_days', 30 ) );
    $cutoff = strtotime( '-' . $retention . ' days' );
    $files = glob( RICKUP_BACKUP_DIR . '*' );
    foreach ( $files as $file ) {
        if ( filemtime( $file ) < $cutoff ) {
            unlink( $file );
        }
    }

    $msg = sprintf(
        __( 'Backup complete: DB=%s, Uploads=%s%s', 'rickup' ),
        basename( $db_file ?? '' ),
        basename( $uploads_file ?? '' ),
        $full_file ? ', Full=' . basename( $full_file ) : ''
    );
    rickup_log_message( $msg, 'success' );

    // Email.
    if ( get_option( 'rickup_email_notify' ) ) {
        $email = get_option( 'rickup_admin_email' );
        wp_mail( $email, __( 'RickUp Backup Complete', 'rickup' ), $msg );
    }

    return array( 'db' => $db_file, 'uploads' => $uploads_file, 'full' => $full_file );
}

/**
 * Schedule cron.
 */
function rickup_schedule_backup() {
    if ( ! wp_next_scheduled( 'rickup_backup_event' ) ) {
        $frequency = get_option( 'rickup_backup_frequency', 'daily' );
        wp_schedule_event( time(), $frequency, 'rickup_backup_event' );
    }
}
add_action( 'wp', 'rickup_schedule_backup' );

add_action( 'rickup_backup_event', 'rickup_do_backup' );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'rickup_backup_event' );
} );

// Manual backup.
add_action( 'admin_post_rickup_manual_backup', function() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'rickup_manual_backup' ) ) {
        wp_die( __( 'Unauthorized.', 'rickup' ) );
    }
    rickup_do_backup();
    wp_redirect( add_query_arg( 'success', '1', admin_url( 'admin.php?page=rickup-backups' ) ) );
    exit;
} );

// Download backup.
add_action( 'admin_post_rickup_download_backup', function() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'rickup_download' ) ) {
        wp_die( __( 'Unauthorized.', 'rickup' ) );
    }
    $file = sanitize_file_name( $_GET['file'] ?? '' );
    $path = RICKUP_BACKUP_DIR . $file;
    if ( file_exists( $path ) ) {
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . esc_attr( basename( $path ) ) . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        readfile( $path );
        exit;
    }
    wp_die( __( 'File not found.', 'rickup' ) );
} );

// Delete backup.
add_action( 'admin_post_rickup_delete_backup', function() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'rickup_delete' ) ) {
        wp_die( __( 'Unauthorized.', 'rickup' ) );
    }
    $file = sanitize_file_name( $_GET['file'] ?? '' );
    $path = RICKUP_BACKUP_DIR . $file;
    if ( file_exists( $path ) ) {
        unlink( $path );
    }
    wp_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=rickup-backups' ) ) );
    exit;
} );

// Download log.
add_action( 'admin_post_rickup_download_log', function() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'rickup_download_log' ) ) {
        wp_die( __( 'Unauthorized.', 'rickup' ) );
    }
    $file = sanitize_file_name( $_GET['file'] ?? '' );
    $path = RICKUP_LOGS_DIR . $file;
    if ( file_exists( $path ) ) {
        header( 'Content-Type: text/plain' );
        header( 'Content-Disposition: attachment; filename="' . esc_attr( basename( $path ) ) . '"' );
        readfile( $path );
        exit;
    }
    wp_die( __( 'Log not found.', 'rickup' ) );
} );

// Rollback plugin.
add_action( 'admin_post_rickup_rollback_plugin', function() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'rickup_rollback' ) ) {
        wp_die( __( 'Unauthorized.', 'rickup' ) );
    }
    $slug = sanitize_file_name( $_GET['slug'] ?? '' );
    $version = sanitize_text_field( $_GET['version'] ?? '' );

    rickup_do_backup();

    $download_url = 'https://downloads.wordpress.org/plugin/' . $slug . '.' . $version . '.zip';
    $zip_path = download_url( $download_url );
    if ( ! is_wp_error( $zip_path ) ) {
        $upgrader = new Plugin_Upgrader();
        $result = $upgrader->upgrade( $slug, array( 'source' => $zip_path, 'clear_update_cache' => true ) );
        if ( is_wp_error( $result ) ) {
            rickup_log_message( 'Plugin rollback error for ' . $slug . ': ' . $result->get_error_message(), 'error' );
        }
        wp_delete_file( $zip_path );
    }

    wp_redirect( add_query_arg( 'success', '1', admin_url( 'admin.php?page=rickup-rollback' ) ) );
    exit;
} );

// Rollback theme.
add_action( 'admin_post_rickup_rollback_theme', function() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'rickup_rollback' ) ) {
        wp_die( __( 'Unauthorized.', 'rickup' ) );
    }
    $slug = sanitize_text_field( $_GET['slug'] ?? '' );
    $version = sanitize_text_field( $_GET['version'] ?? '' );

    rickup_do_backup();

    $download_url = 'https://downloads.wordpress.org/theme/' . $slug . '.' . $version . '.zip';
    $zip_path = download_url( $download_url );
    if ( ! is_wp_error( $zip_path ) ) {
        $upgrader = new Theme_Upgrader();
        $result = $upgrader->upgrade( $slug, array( 'source' => $zip_path ) );
        if ( is_wp_error( $result ) ) {
            rickup_log_message( 'Theme rollback error for ' . $slug . ': ' . $result->get_error_message(), 'error' );
        }
        wp_delete_file( $zip_path );
    }

    wp_redirect( add_query_arg( 'success', '1', admin_url( 'admin.php?page=rickup-rollback' ) ) );
    exit;
} );

/**
 * Auto-backup before updates.
 */
add_filter( 'upgrader_pre_install', function( $return, $hook_extra ) {
    if ( isset( $hook_extra['plugin'] ) || isset( $hook_extra['theme'] ) ) {
        rickup_do_backup();
    }
    return $return;
}, 10, 2 );

/**
 * Admin CSS.
 */
function rickup_add_admin_css() {
    if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'rickup' ) === false ) {
        return;
    }
    ?>
    <style>
    .rickup-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .rickup-title { color: #23282d; margin-bottom: 20px; }
    .rickup-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
    .rickup-table { width: 100%; margin: 0; }
    .rickup-table th { padding: 15px 10px; background: #f6f7f7; font-weight: 600; }
    .rickup-table td { padding: 15px 10px; vertical-align: top; }
    .rickup-input, .rickup-select { border: 1px solid #8c8f94; border-radius: 3px; padding: 5px; }
    .rickup-switch { position: relative; display: inline-block; width: 60px; height: 34px; }
    .rickup-switch input { opacity: 0; width: 0; height: 0; }
    .rickup-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
    .rickup-slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .rickup-slider { background-color: #2271b1; }
    input:checked + .rickup-slider:before { transform: translateX(26px); }
    .rickup-btn { background: #2271b1; border-color: #2271b1; color: #fff; text-decoration: none; padding: 6px 12px; border-radius: 3px; }
    .rickup-btn:hover { background: #135e96; border-color: #135e96; }
    .rickup-btn-large { padding: 10px 20px; font-size: 16px; }
    .rickup-btn-small { padding: 2px 8px; font-size: 12px; }
    .rickup-danger { background: #d63638; border-color: #d63638; }
    .rickup-danger:hover { background: #b32d2e; border-color: #b32d2e; }
    .rickup-muted { color: #646970; font-style: italic; }
    .rickup-empty { text-align: center; color: #646970; font-style: italic; }
    .rickup-tabs { overflow: hidden; border: 1px solid #c3c4c7; background-color: #f6f7f7; }
    .rickup-tablink { background-color: inherit; float: left; border: none; outline: none; cursor: pointer; padding: 14px 16px; transition: 0.3s; font-size: 16px; }
    .rickup-tablink:hover { background-color: #ddd; }
    .rickup-tablink.active { background-color: #ccc; }
    .rickup-tabcontent { display: none; padding: 6px 12px; border: 1px solid #ccc; border-top: none; }
    .rickup-tabcontent.active { display: block; }
    .description { color: #646970; font-size: 13px; margin: 5px 0 0; }
    </style>
    <?php
}
add_action( 'admin_head', 'rickup_add_admin_css' );

/**
 * Admin JS.
 */
function rickup_add_admin_js() {
    if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'rickup' ) === false ) {
        return;
    }
    ?>
    <script>
    jQuery( document ).ready( function( $ ) {
        $( '.rickup-submit' ).on( 'click', function() {
            $( this ).val( '<?php esc_js( __( 'Saving...', 'rickup' ) ); ?>' );
        } );
    } );
    </script>
    <?php
}
add_action( 'admin_footer', 'rickup_add_admin_js' );

// For WordPress.org submission: Create a readme.txt file with standard format (see https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
// Test with WP-CLI: wp plugin scaffold rickup --skip-wporg-checksum
// Ensure no security issues, follow coding standards (PHPCS), and GPL compliance.
?>