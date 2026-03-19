<?php

/**
 * Kate522 Notifier for DingTalk Admin Class
 */
class Ding_Pusher_Admin {
    const EXPORT_MAX_ROWS = 5000;
    const BULK_MAX_ROWS = 200;
    const EXPORT_RATE_LIMIT_SECONDS = 10;
    const RECORD_DELETED_META_KEY = '_dtpwp_record_deleted';

    /**
     * Singleton instance
     *
     * @var Ding_Pusher_Admin|null
     */
    private static $instance = null;

    /**
     * Settings options
     *
     * @var array
     */
    private $settings = array();

    /**
     * Get singleton instance
     *
     * @return Ding_Pusher_Admin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->settings = wp_parse_args(
            get_option( DTPWP_OPTION_NAME, array() ),
            $this->get_default_settings()
        );

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'wp_ajax_dtpwp_test_message', array( $this, 'ajax_test_message' ) );
        add_action( 'wp_ajax_dtpwp_mark_as_sent', array( $this, 'ajax_mark_as_sent' ) );
        add_action( 'wp_ajax_dtpwp_bulk_update_records', array( $this, 'ajax_bulk_update_records' ) );
        add_action( 'wp_ajax_dtpwp_prepare_export', array( $this, 'ajax_prepare_export' ) );
        add_action( 'wp_ajax_dtpwp_clear_sent_records', array( $this, 'ajax_clear_sent_records' ) );
        add_action( 'admin_post_dtpwp_export_records', array( $this, 'handle_export_records' ) );
        add_action( 'admin_post_dtpwp_download_export', array( $this, 'handle_download_export' ) );
    }

    /**
     * Default settings
     *
     * @return array
     */
    private function get_default_settings() {
        return array(
            'webhook_url' => '',
            'security_type' => 'keyword',
            'security_keyword' => array(),
            'security_secret' => '',
            'security_ip_whitelist' => array(),
            'message_type' => 'text',
            'custom_message' => '',
            'post_template' => "[New Post]\nTitle: {title}\nAuthor: {author}\nLink: {link}",
            'user_template' => "[New User Registration]\nUsername: {username}\nEmail: {email}\nRegistration Time: {register_time}",
            'enable_new_post' => 1,
            'enable_post_update' => 0,
            'enable_custom_post_type' => array(),
            'enable_new_user' => 1,
            'push_interval' => 5,
            'retry_count' => 3,
            'retry_interval' => 10,
            'deduplicate_days' => 30,
            'theme_color' => '#2563eb',
            'preview_preset' => 'clean',
            'enable_advanced_features' => 0,
            'advanced_mode' => 'smart',
            'enable_nested_feature' => 0,
            'nested_feature_note' => '',
        );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $parent_slug = 'kate522-notifier-for-dingtalk';

        add_menu_page(
            'Kate522 Notifier for DingTalk',
            'Kate522 Notifier for DingTalk',
            'manage_options',
            $parent_slug,
            array( $this, 'render_settings_page' ),
            'dashicons-email-alt',
            80
        );

        add_submenu_page(
            $parent_slug,
            'Settings',
            'Settings',
            'manage_options',
            $parent_slug,
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            $parent_slug,
            'Push Records',
            'Push Records',
            'manage_options',
            'kate522-notifier-for-dingtalk-records',
            array( $this, 'render_records_page' )
        );

        add_submenu_page(
            $parent_slug,
            'Help',
            'Help',
            'manage_options',
            'kate522-notifier-for-dingtalk-help',
            array( $this, 'render_help_page' )
        );
    }

    /**
     * Register Settings
     */
    public function register_settings() {
        register_setting(
            'dtpwp_settings',
            DTPWP_OPTION_NAME,
            array( $this, 'validate_settings' )
        );
    }

    /**
     * Validate Settings
     *
     * @param array $input Raw input
     * @return array
     */
    public function validate_settings( $input ) {
        $input = is_array( $input ) ? $input : array();
        $valid = array();

        $webhook_url = isset( $input['webhook_url'] ) ? trim( sanitize_text_field( $input['webhook_url'] ) ) : '';
        if ( ! empty( $webhook_url ) && ! preg_match( '/^https:\/\/oapi\.dingtalk\.com\/robot\/send/', $webhook_url ) ) {
            add_settings_error( 'dtpwp_settings', 'invalid_webhook', 'Webhook URL format is incorrect, please check.' );
        }
        $valid['webhook_url'] = $webhook_url;

        $valid['security_type'] = isset( $input['security_type'] ) ? sanitize_text_field( $input['security_type'] ) : 'keyword';
        $valid['security_keyword'] = isset( $input['security_keyword'] ) && is_array( $input['security_keyword'] )
            ? array_values( array_filter( array_map( 'sanitize_text_field', $input['security_keyword'] ), 'strlen' ) )
            : array();
        $valid['security_secret'] = isset( $input['security_secret'] ) ? trim( sanitize_text_field( $input['security_secret'] ) ) : '';
        $valid['security_ip_whitelist'] = isset( $input['security_ip_whitelist'] ) && is_array( $input['security_ip_whitelist'] )
            ? array_values( array_filter( array_map( 'sanitize_text_field', $input['security_ip_whitelist'] ), 'strlen' ) )
            : array();

        $valid['message_type'] = isset( $input['message_type'] ) ? sanitize_text_field( $input['message_type'] ) : 'text';
        $valid['custom_message'] = isset( $input['custom_message'] ) ? sanitize_textarea_field( $input['custom_message'] ) : '';
        $valid['post_template'] = isset( $input['post_template'] ) ? sanitize_textarea_field( $input['post_template'] ) : '';
        $valid['user_template'] = isset( $input['user_template'] ) ? sanitize_textarea_field( $input['user_template'] ) : '';

        $valid['enable_new_post'] = isset( $input['enable_new_post'] ) ? 1 : 0;
        $valid['enable_post_update'] = isset( $input['enable_post_update'] ) ? 1 : 0;
        $valid['enable_custom_post_type'] = isset( $input['enable_custom_post_type'] ) && is_array( $input['enable_custom_post_type'] )
            ? array_values( array_filter( array_map( 'sanitize_text_field', $input['enable_custom_post_type'] ), 'strlen' ) )
            : array();
        $valid['enable_new_user'] = isset( $input['enable_new_user'] ) ? 1 : 0;

        $valid['push_interval'] = isset( $input['push_interval'] ) ? absint( $input['push_interval'] ) : 5;
        $valid['push_interval'] = max( 1, min( 60, $valid['push_interval'] ) );

        $valid['retry_count'] = isset( $input['retry_count'] ) ? absint( $input['retry_count'] ) : 3;
        $valid['retry_count'] = max( 1, min( 10, $valid['retry_count'] ) );

        $valid['retry_interval'] = isset( $input['retry_interval'] ) ? absint( $input['retry_interval'] ) : 10;
        $valid['retry_interval'] = max( 1, min( 60, $valid['retry_interval'] ) );

        $valid['deduplicate_days'] = isset( $input['deduplicate_days'] ) ? absint( $input['deduplicate_days'] ) : 30;
        $valid['deduplicate_days'] = max( 1, min( 365, $valid['deduplicate_days'] ) );

        $valid['theme_color'] = isset( $input['theme_color'] ) ? sanitize_hex_color( $input['theme_color'] ) : '#2563eb';
        if ( empty( $valid['theme_color'] ) ) {
            $valid['theme_color'] = '#2563eb';
        }
        $valid['preview_preset'] = isset( $input['preview_preset'] ) ? sanitize_text_field( $input['preview_preset'] ) : 'clean';
        $valid['enable_advanced_features'] = isset( $input['enable_advanced_features'] ) ? 1 : 0;
        $valid['advanced_mode'] = isset( $input['advanced_mode'] ) ? sanitize_text_field( $input['advanced_mode'] ) : 'smart';
        $valid['enable_nested_feature'] = isset( $input['enable_nested_feature'] ) ? 1 : 0;
        $valid['nested_feature_note'] = isset( $input['nested_feature_note'] ) ? sanitize_text_field( $input['nested_feature_note'] ) : '';


        // Update scheduled task
        wp_clear_scheduled_hook( 'dtpwp_check_new_content' );

        $schedule = $valid['push_interval'] . 'm';
        $schedules = wp_get_schedules();
        if ( ! isset( $schedules[ $schedule ] ) ) {
            $schedule = isset( $schedules['hourly'] ) ? 'hourly' : key( $schedules );
        }

        if ( $schedule && ! wp_next_scheduled( 'dtpwp_check_new_content' ) ) {
            wp_schedule_event( time(), $schedule, 'dtpwp_check_new_content' );
        }

        return $valid;
    }

    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        $settings = $this->settings;
        include DTPWP_PLUGIN_DIR . 'admin/views/settings-ui.php';
    }

    /**
     * Render Push Records Page
     */
    public function render_records_page() {
        $per_page = $this->get_records_per_page();
        $paged = $this->get_records_paged();
        $query = new WP_Query( $this->get_records_query_args( $per_page, $paged ) );
        $total_records = (int) $query->found_posts;
        $total_pages = (int) $query->max_num_pages;
        ?>
        <div class="wrap dtpwp-records" data-per-page="<?php echo esc_attr( $per_page ); ?>">
            <?php $this->render_records_header( $total_records ); ?>

            <?php if ( $query->have_posts() ) : ?>
                <?php $this->render_records_toolbar( $per_page ); ?>
                <?php $this->render_records_table( $query ); ?>
                <?php $this->render_records_pagination( $total_pages, $paged, $per_page ); ?>
            <?php else : ?>
                <?php $this->render_records_empty(); ?>
            <?php endif; ?>
        </div>
        <?php
        wp_reset_postdata();
    }

    private function get_records_per_page() {
        $allowed = $this->get_allowed_per_page();
        $per_page = isset( $_GET['dtpwp_per_page'] ) ? absint( $_GET['dtpwp_per_page'] ) : 20;
        if ( ! in_array( $per_page, $allowed, true ) ) {
            $per_page = 20;
        }
        return $per_page;
    }

    private function get_allowed_per_page() {
        return array( 10, 20, 50, 100 );
    }

    private function get_records_paged() {
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        return max( 1, $paged );
    }

    private function get_records_query_args( $posts_per_page, $paged, $post_ids = array() ) {
        $args = array(
            'post_type' => 'any',
            'posts_per_page' => $posts_per_page,
            'paged' => $paged,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => DTPWP_SENT_META_KEY,
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => self::RECORD_DELETED_META_KEY,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        if ( ! empty( $post_ids ) ) {
            $args['post__in'] = $post_ids;
            $args['orderby'] = 'post__in';
        }

        return $args;
    }

    private function render_records_header( $total_records ) {
        ?>
        <div class="dtpwp-records-header">
            <div>
                <h1><?php esc_html_e('Push Records'); ?></h1>
                <p><?php esc_html_e('View pushed post records, message content and push time.'); ?></p>
            </div>
            <div class="dtpwp-records-actions">
                <span class="dtpwp-records-count">
                    <?php esc_html_e('Total'); ?>
                    <strong id="dtpwp-records-count"><?php echo esc_html( $total_records ); ?></strong>
                    <?php esc_html_e('records'); ?>
                </span>
                <button type="button" class="button dtpwp-button-danger" id="dtpwp-clear-records"><?php esc_html_e('Clear All Records'); ?></button>
            </div>
        </div>
        <?php
    }

    private function render_records_toolbar( $per_page ) {
        ?>
        <div class="dtpwp-records-toolbar">
            <div class="dtpwp-records-bulk">
                <label for="dtpwp-bulk-action"><?php esc_html_e('Bulk Actions'); ?></label>
                <select id="dtpwp-bulk-action">
                    <option value=""><?php esc_html_e('Please Select'); ?></option>
                    <option value="mark_not_sent"><?php esc_html_e('Unmark'); ?></option>
                    <option value="delete_record"><?php esc_html_e('Delete Record'); ?></option>
                </select>
                <button type="button" class="button button-secondary" id="dtpwp-apply-bulk"><?php esc_html_e('Apply'); ?></button>
            </div>
            <div class="dtpwp-records-filter">
                <label for="dtpwp-per-page"><?php esc_html_e('Per Page'); ?></label>
                <select id="dtpwp-per-page">
                    <?php foreach ( $this->get_allowed_per_page() as $size ) : ?>
                        <option value="<?php echo esc_attr( $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( $size ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="dtpwp-records-export">
                <label for="dtpwp-export-format"><?php esc_html_e('Export Format'); ?></label>
                <select id="dtpwp-export-format">
                    <option value="csv"><?php esc_html_e('CSV'); ?></option>
                    <option value="xlsx"><?php esc_html_e('XLSX'); ?></option>
                </select>
                <button type="button" class="button button-secondary" id="dtpwp-export-selected"><?php esc_html_e('Export Selected'); ?></button>
                <button type="button" class="button button-secondary" id="dtpwp-export-all"><?php esc_html_e('Export All'); ?></button>
                <details class="dtpwp-export-fields">
                    <summary><?php esc_html_e('Export Fields'); ?></summary>
                    <div class="dtpwp-export-fields-panel">
                        <label><input type="checkbox" value="id" checked />ID</label>
                        <label><input type="checkbox" value="title" checked /><?php esc_html_e('Title'); ?></label>
                        <label><input type="checkbox" value="author" checked /><?php esc_html_e('Author'); ?></label>
                        <label><input type="checkbox" value="sent_time" checked /><?php esc_html_e('Push Time'); ?></label>
                        <label><input type="checkbox" value="message" checked /><?php esc_html_e('Message Content'); ?></label>
                        <label><input type="checkbox" value="link" checked /><?php esc_html_e('Link'); ?></label>
                        <div class="dtpwp-export-fields-actions">
                            <button type="button" class="button button-link dtpwp-export-select-all"><?php esc_html_e('Select All'); ?></button>
                            <button type="button" class="button button-link dtpwp-export-clear"><?php esc_html_e('Clear'); ?></button>
                        </div>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }

    private function render_records_table( WP_Query $query ) {
        ?>
        <div class="dtpwp-records-table">
            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th>
                        <th scope="col" class="manage-column column-title"><?php esc_html_e('Post Title'); ?></th>
                        <th scope="col" class="manage-column column-author"><?php esc_html_e('Author'); ?></th>
                        <th scope="col" class="manage-column column-date"><?php esc_html_e('Push Time'); ?></th>
                        <th scope="col" class="manage-column column-actions"><?php esc_html_e('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ( $query->have_posts() ) : ?>
                        <?php
                        $query->the_post();
                        $post_id = get_the_ID();
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( $post_id ); ?>" />
                            </th>
                            <td class="column-title">
                                <div class="dtpwp-record-title">
                                    <strong>
                                        <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php the_title(); ?></a>
                                    </strong>
                                    <span class="dtpwp-record-link">
                                        <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View Post'); ?></a>
                                    </span>
                                </div>
                                <div class="dtpwp-sent-message">
                                    <strong><?php esc_html_e('Sent Content'); ?></strong>
                                    <pre><?php echo esc_html( get_post_meta( $post_id, '_dtpwp_sent_message', true ) ); ?></pre>
                                </div>
                            </td>
                            <td class="column-author">
                                <span class="dtpwp-record-meta"><?php the_author(); ?></span>
                            </td>
                            <td class="column-date">
                                <span class="dtpwp-record-meta"><?php echo esc_html( get_post_meta( $post_id, '_dtpwp_sent_time', true ) ); ?></span>
                            </td>
                            <td class="column-actions">
                                <button type="button" class="button button-link-delete dtpwp-mark-as-not-sent" data-post-id="<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e('Unmark'); ?></button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_records_pagination( $total_pages, $paged, $per_page ) {
        if ( $total_pages <= 1 ) {
            return;
        }

        $base = add_query_arg(
            array(
                'paged' => '%#%',
                'dtpwp_per_page' => $per_page,
            )
        );

        $links = paginate_links(
            array(
                'base' => $base,
                'format' => '',
                'current' => $paged,
                'total' => $total_pages,
                'prev_text' => 'Previous',
                'next_text' => 'Next',
                'type' => 'array',
            )
        );

        if ( empty( $links ) ) {
            return;
        }
        ?>
        <div class="dtpwp-records-pagination">
            <?php foreach ( $links as $link ) : ?>
                <?php echo wp_kses_post( $link ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_records_empty() {
        ?>
        <div class="dtpwp-records-empty">
            <h3><?php esc_html_e('No Push Records'); ?></h3>
            <p><?php esc_html_e('Records will appear here after posts are pushed.'); ?></p>
        </div>
        <?php
    }

    /**
     * Render Help Page
     */
    public function render_help_page() {
        ?>
        <div class="wrap dtpwp-help">
            <div class="dtpwp-help-hero">
                <div>
                    <h1><?php esc_html_e('Help & Tutorial'); ?></h1>
                    <p><?php esc_html_e('This page summarizes common configuration steps, template descriptions, and troubleshooting checklists to help you configure faster.'); ?></p>
                </div>
                <div class="dtpwp-help-hero__actions">
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=kate522-notifier-for-dingtalk' ) ); ?>">
                        <?php esc_html_e('Open Settings'); ?>
                    </a>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=kate522-notifier-for-dingtalk-records' ) ); ?>">
                        <?php esc_html_e('View Records'); ?>
                    </a>
                </div>
            </div>

            <div class="dtpwp-help-grid">
                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e('Quick Start'); ?></h2>
                    <ol>
                        <li><?php esc_html_e('Create a custom bot in the DingTalk group.'); ?></li>
                        <li><?php esc_html_e('Select keyword / signature / IP whitelist based on bot security settings.'); ?></li>
                        <li><?php esc_html_e('Copy the bot Webhook URL, paste it into the settings page, and save.'); ?></li>
                        <li><?php esc_html_e('Click "Send Test Message" to verify the configuration.'); ?></li>
                        <li><?php esc_html_e('Enable trigger scenarios and templates, observe push records.'); ?></li>
                    </ol>
                </section>

                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e('Configuration Checklist'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('Webhook: Fill in the bot Webhook URL and confirm it is accessible.'); ?></li>
                        <li><?php esc_html_e('Security: Select keyword / signature / IP whitelist according to bot security settings.'); ?></li>
                        <li><?php esc_html_e('Trigger Scenarios: Select new post, update, or new user registration.'); ?></li>
                        <li><?php esc_html_e('Message Template: Adjust text, link, or Markdown templates as needed.'); ?></li>
                        <li><?php esc_html_e('Save and send a test message to verify the configuration.'); ?></li>
                    </ul>
                </section>

                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e('Templates & Placeholders'); ?></h2>
                    <div class="dtpwp-help-columns">
                        <div>
                            <h3><?php esc_html_e('Post Template'); ?></h3>
                            <p><?php esc_html_e('Available Placeholders: '); ?></p>
                            <p class="dtpwp-help-tags">
                                <code>{title}</code>
                                <code>{author}</code>
                                <code>{link}</code>
                                <code>{excerpt}</code>
                                <code>{category}</code>
                                <code>{categories}</code>
                                <code>{date}</code>
                                <code>{publish_time}</code>
                                <code>{post_date}</code>
                                <code>{post_type}</code>
                            </p>
                        </div>
                        <div>
                            <h3><?php esc_html_e('User Template'); ?></h3>
                            <p><?php esc_html_e('Available Placeholders: '); ?></p>
                            <p class="dtpwp-help-tags">
                                <code>{username}</code>
                                <code>{email}</code>
                                <code>{register_time}</code>
                            </p>
                        </div>
                    </div>
                    <p class="dtpwp-help-note"><?php esc_html_e('Tip: Templates support line breaks, actual messages will preserve them.'); ?></p>
                </section>

                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e('Trigger Scenarios'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('New Post Publishing'); ?></li>
                        <li><?php esc_html_e('Post Update Push'); ?></li>
                        <li><?php esc_html_e('New User Registration'); ?></li>
                        <li><?php esc_html_e('Enabled Custom Post Types'); ?></li>
                    </ul>
                </section>

                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e('Export & Records'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('Export generates CSV or XLSX files, saved for 24 hours by default then auto-cleaned.'); ?></li>
                        <li><?php esc_html_e('XLSX requires ZipArchive or PclZip on the server, if unavailable export CSV instead.'); ?></li>
                        <li><?php esc_html_e('Records page shows only pushed posts; if no records, trigger a push first.'); ?></li>
                    </ul>
                </section>

                <section class="dtpwp-help-card">
                    <h2><?php esc_html_e('Troubleshooting Checklist'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('Test message failed: Check Webhook, security settings, and server network.'); ?></li>
                        <li><?php esc_html_e('New post not pushed: Confirm trigger is enabled, post status is published, and check WP-Cron.'); ?></li>
                        <li><?php esc_html_e('Export failed: Check upload directory permissions or use CSV instead.'); ?></li>
                        <li><?php esc_html_e('Frequent trigger limit: Export has rate limits, try again later.'); ?></li>
                    </ul>
                </section>

                <section class="dtpwp-help-card dtpwp-help-faq">
                    <h2><?php esc_html_e('FAQ'); ?></h2>
                    <details>
                        <summary><?php esc_html_e('How to configure keyword security?'); ?></summary>
                        <p><?php esc_html_e('Add keywords in DingTalk bot security settings, select "Keyword" in plugin security method and fill in the same keywords.'); ?></p>
                    </details>
                    <details>
                        <summary><?php esc_html_e('How to use signature?'); ?></summary>
                        <p><?php esc_html_e('Select "Signature", fill in the bot secret, the plugin will automatically generate the signature.'); ?></p>
                    </details>
                    <details>
                        <summary><?php esc_html_e('Why are there no push records?'); ?></summary>
                        <p><?php esc_html_e('Records are generated only after successful push, trigger a push first and check the logs.'); ?></p>
                    </details>
                </section>
            </div>
        </div>
        <?php
    }

    /**
     * Load styles and scripts
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'kate522-notifier-for-dingtalk' ) ) {
            return;
        }

        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_style( 'wp-color-picker' );

        wp_enqueue_script(
            'dtpwp-admin-script',
            DTPWP_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ),
            DTPWP_VERSION,
            true
        );

        wp_localize_script(
            'dtpwp-admin-script',
            'dtpwp_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'dtpwp_nonce' ),
                'settings_updated' => ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) ? 1 : 0,
                'export_url' => admin_url( 'admin-post.php' ),
                'export_nonce' => wp_create_nonce( 'dtpwp_export_records' ),
                'export_max' => self::EXPORT_MAX_ROWS,
                'xlsx_available' => $this->is_xlsx_available() ? 1 : 0,
                'i18n' => array(
                    'webhook_not_configured' => 'Webhook: Not Configured',
                    'webhook_configured' => 'Webhook: Configured',
                    'webhook_prefix' => 'Webhook: ',
                    'expand' => 'Expand',
                    'collapse' => 'Collapse',
                    'site_notice' => 'Site Notification',
                    'custom_message' => 'Custom Message',
                    'empty_message' => 'No message content set',
                    'message_notice' => 'Message Notification',
                    'markdown_notice' => '# Markdown Notification',
                    'example_title' => 'Title: Sample Content',
                    'example_author' => 'Author: Admin',
                    'example_status' => 'Status: Published',
                    'nested_disabled' => 'Nested: Not Enabled',
                    'nested_prefix' => 'Nested: ',
                    'nested_enabled' => 'Enabled',
                    'message_type_text' => 'Text Message',
                    'message_type_link' => 'Link Message',
                    'message_type_markdown' => 'Markdown Message',
                    'preset_clean' => 'Clean',
                    'preset_compact' => 'Compact',
                    'preset_bold' => 'Bold',
                    'preset_prefix' => 'Preset: ',
                    'advanced_enabled' => 'Advanced Features: On',
                    'advanced_disabled' => 'Advanced Features: Off',
                    /* translators: %d: minutes */
                    'push_interval_format' => 'Interval: %d minutes',
                    'delete' => 'Delete',
                    'saving_settings' => 'Saving settings...',
                    'settings_saved' => 'Settings saved',
                    'sending' => 'Sending...',
                    'test_response_invalid' => 'Test message response format invalid',
                    'test_send_failed' => 'Test message failed to send, please check network connection.',
                    'bulk_action_required' => 'Please select bulk action type first.',
                    'bulk_records_required' => 'Please select records to perform action on.',
                    'bulk_confirm_delete' => 'Are you sure you want to delete selected records? This action cannot be undone.',
                    'bulk_confirm_unmark' => 'Are you sure you want to unmark selected records?',
                    'processing' => 'Processing...',
                    'record_deleted' => 'Record deleted',
                    'record_unmarked' => 'Record unmarked',
                    'bulk_failed' => 'Bulk action failed',
                    'apply' => 'Apply',
                    'export_field_required' => 'Please select at least one export field.',
                    'export_too_many' => 'Too many records, please select records to export.',
                    'export_generating' => 'Generating...',
                    'export_ready' => 'Export ready, starting download.',
                    'export_failed' => 'Export failed, please try again.',
                    'export_all' => 'Export All',
                    'export_selected' => 'Export Selected',
                    'export_records_required' => 'Please select records to export.',
                    'xlsx_unavailable' => 'XLSX export requires the PHP ZipArchive extension. Switched to CSV.',
                    'mark_confirm' => 'Are you sure you want to unmark this post?',
                    'action_failed' => 'Action failed',
                    'mark_cancel' => 'Unmark',
                    'clear_confirm' => 'Are you sure you want to clear all push records? This action cannot be undone.',
                    'clearing' => 'Clearing...',
                    'clear_failed' => 'Clear failed',
                ),
            )
        );

        wp_enqueue_style(
            'dtpwp-admin-style',
            DTPWP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DTPWP_VERSION
        );
    }

    /**
     * AJAX: Send Test Message
     */
    public function ajax_test_message() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $settings = wp_parse_args( get_option( DTPWP_OPTION_NAME, array() ), $this->get_default_settings() );
        $raw_settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

        $test_settings = $settings;
        $test_settings['webhook_url'] = isset( $raw_settings['webhook_url'] ) ? trim( esc_url_raw( $raw_settings['webhook_url'] ) ) : $settings['webhook_url'];
        $test_settings['security_type'] = isset( $raw_settings['security_type'] ) ? sanitize_text_field( $raw_settings['security_type'] ) : $settings['security_type'];
        $test_settings['security_secret'] = isset( $raw_settings['security_secret'] ) ? trim( sanitize_text_field( $raw_settings['security_secret'] ) ) : $settings['security_secret'];
        $test_settings['message_type'] = isset( $raw_settings['message_type'] ) ? sanitize_text_field( $raw_settings['message_type'] ) : $settings['message_type'];

        if ( empty( $test_settings['webhook_url'] ) ) {
            wp_send_json_error( array( 'message' => 'Please fill in the Webhook URL first.' ) );
        }

        if ( ! preg_match( '/^https:\/\/oapi\.dingtalk\.com\/robot\/send/', $test_settings['webhook_url'] ) ) {
            wp_send_json_error( array( 'message' => 'Webhook URL format is incorrect, please check.' ) );
        }

        $message = 'This is a test message from Kate522 Notifier for DingTalk plugin.';
        $core = Ding_Pusher_Core::get_instance();
        $success = $core->send_dingtalk_message( $message, $test_settings );

        if ( $success ) {
            wp_send_json_success( array( 'message' => 'Test message sent successfully.' ) );
        }

        wp_send_json_error( array( 'message' => 'Test message failed to send, please check the configuration.' ) );
    }

    /**
     * AJAX: Unmark or mark as sent
     */
    public function ajax_mark_as_sent() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $mark_as = isset( $_POST['mark_as'] ) ? sanitize_text_field( wp_unslash( $_POST['mark_as'] ) ) : 'sent';

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Invalid post ID.' ) );
        }

        if ( 'not_sent' === $mark_as ) {
            delete_post_meta( $post_id, DTPWP_SENT_META_KEY );
            delete_post_meta( $post_id, '_dtpwp_sent_time' );
            delete_post_meta( $post_id, '_dtpwp_sent_message' );
            delete_post_meta( $post_id, self::RECORD_DELETED_META_KEY );
            wp_send_json_success( array( 'message' => 'Unmarked.' ) );
        }

        delete_post_meta( $post_id, self::RECORD_DELETED_META_KEY );
        update_post_meta( $post_id, DTPWP_SENT_META_KEY, 1 );
        update_post_meta( $post_id, '_dtpwp_sent_time', current_time( 'mysql' ) );
        wp_send_json_success( array( 'message' => 'Marked successfully.' ) );
    }

    /**
     * AJAX: Clear push records
     */
    public function ajax_clear_sent_records() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        global $wpdb;
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => DTPWP_SENT_META_KEY ), array( '%s' ) );
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_dtpwp_sent_time' ), array( '%s' ) );
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_dtpwp_sent_message' ), array( '%s' ) );
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => self::RECORD_DELETED_META_KEY ), array( '%s' ) );
        delete_option( 'dtpwp_sent_title_hashes' );

        wp_send_json_success( array( 'message' => 'All records have been cleared.' ) );
    }

    /**
     * AJAX: Bulk update push records
     */
    public function ajax_bulk_update_records() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
        $post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
            ? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) )
            : array();
        $post_ids = array_filter( $post_ids );

        if ( empty( $post_ids ) ) {
            wp_send_json_error( array( 'message' => 'Please select records first.' ) );
        }

        if ( count( $post_ids ) > self::BULK_MAX_ROWS ) {
            wp_send_json_error( array( 'message' => 'Maximum 200 records per batch, please process in batches.' ) );
        }

        if ( ! in_array( $action, array( 'mark_not_sent', 'delete_record' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid bulk action.' ) );
        }

        foreach ( $post_ids as $post_id ) {
            if ( 'delete_record' === $action ) {
                update_post_meta( $post_id, self::RECORD_DELETED_META_KEY, 1 );
                continue;
            }

            delete_post_meta( $post_id, DTPWP_SENT_META_KEY );
            delete_post_meta( $post_id, '_dtpwp_sent_time' );
            delete_post_meta( $post_id, '_dtpwp_sent_message' );
            delete_post_meta( $post_id, self::RECORD_DELETED_META_KEY );
        }

        wp_send_json_success( array( 'message' => 'Bulk action completed.' ) );
    }

    /**
     * AJAX: Generate export file
     */
    public function ajax_prepare_export() {
        check_ajax_referer( 'dtpwp_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';
        $format = in_array( $format, array( 'csv', 'xlsx' ), true ) ? $format : 'csv';

        $post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
            ? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) )
            : array();
        $post_ids = array_filter( $post_ids );

        $requested_fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fields'] ) )
            : array();
        $fields = $this->sanitize_export_fields( $requested_fields );

        if ( ! $this->acquire_export_lock() ) {
            wp_send_json_error( array( 'message' => 'Export too frequent, please try again later.' ) );
        }

        $error_message = '';
        $rows = $this->build_export_rows( $post_ids, $fields, $error_message );
        if ( $error_message ) {
            $this->release_export_lock();
            wp_send_json_error( array( 'message' => $error_message ) );
        }

        $file = $this->prepare_export_file( $rows, $format, $error_message );
        if ( ! $file ) {
            $this->release_export_lock();
            wp_send_json_error( array( 'message' => $error_message ? $error_message : 'Export failed, please try again later.' ) );
        }

        $token = $this->create_export_download_token( $file );
        if ( ! $token ) {
            @unlink( $file['path'] );
            wp_send_json_error( array( 'message' => 'Export failed, please try again later.' ) );
        }

        $download_url = add_query_arg(
            array(
                'action' => 'dtpwp_download_export',
                'token' => rawurlencode( $token ),
                'nonce' => wp_create_nonce( 'dtpwp_download_export' ),
            ),
            admin_url( 'admin-post.php' )
        );

        wp_send_json_success(
            array(
                'download_url' => $download_url,
                'filename' => $file['filename'],
            )
        );
    }

    /**
     * Export push records CSV
     */
    public function handle_export_records() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'dtpwp_export_records' ) ) {
            wp_die( 'Invalid request.' );
        }

        $format = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : 'csv';
        $format = in_array( $format, array( 'csv', 'xlsx' ), true ) ? $format : 'csv';

        $post_ids = isset( $_GET['post_ids'] ) && is_array( $_GET['post_ids'] )
            ? array_map( 'absint', wp_unslash( $_GET['post_ids'] ) )
            : array();
        $post_ids = array_filter( $post_ids );

        $requested_fields = isset( $_GET['fields'] ) && is_array( $_GET['fields'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_GET['fields'] ) )
            : array();
        $fields = $this->sanitize_export_fields( $requested_fields );

        if ( ! $this->acquire_export_lock() ) {
            wp_die( 'Export too frequent, please try again later.' );
        }

        $error_message = '';
        $rows = $this->build_export_rows( $post_ids, $fields, $error_message );
        if ( $error_message ) {
            $this->release_export_lock();
            wp_die( $error_message );
        }

        if ( 'xlsx' === $format ) {
            $this->output_records_xlsx( $rows );
            exit;
        }

        $this->output_records_csv( $rows );
        exit;
    }

    private function sanitize_export_fields( $requested_fields ) {
        $allowed_fields = $this->get_export_allowed_fields();
        $fields = array();

        foreach ( $requested_fields as $field ) {
            if ( isset( $allowed_fields[ $field ] ) ) {
                $fields[] = $field;
            }
        }

        if ( empty( $fields ) ) {
            $fields = array_keys( $allowed_fields );
        }

        return $fields;
    }

    private function create_export_download_token( $file ) {
        if ( empty( $file['path'] ) || empty( $file['filename'] ) ) {
            return '';
        }

        $token = wp_generate_password( 32, false, false );
        set_transient(
            'dtpwp_export_token_' . $token,
            array(
                'path' => $file['path'],
                'filename' => $file['filename'],
                'created' => time(),
                'user_id' => get_current_user_id(),
            ),
            DAY_IN_SECONDS
        );

        return $token;
    }

    public function handle_download_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'dtpwp_download_export' ) ) {
            wp_die( 'Invalid request.' );
        }

        $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        if ( '' === $token ) {
            wp_die( 'Download link is invalid or has expired.' );
        }

        $data = get_transient( 'dtpwp_export_token_' . $token );
        delete_transient( 'dtpwp_export_token_' . $token );
        if ( ! is_array( $data ) || empty( $data['path'] ) || empty( $data['filename'] ) ) {
            wp_die( 'Download link is invalid or has expired.' );
        }

        $uploads = wp_upload_dir();
        $base_dir = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . 'dtpwp-exports' );
        $path = wp_normalize_path( $data['path'] );
        if ( 0 !== strpos( $path, $base_dir ) || ! file_exists( $path ) || ! is_readable( $path ) ) {
            wp_die( 'Export file does not exist or cannot be read.' );
        }

        $filename = sanitize_file_name( $data['filename'] );
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        $mime = 'csv' === $ext ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Content-Length: ' . filesize( $path ) );

        readfile( $path );
        @unlink( $path );
        exit;
    }

    private function get_export_allowed_fields() {
        return array(
            'id' => 'ID',
            'title' => 'Title',
            'author' => 'Author',
            'sent_time' => 'Push Time',
            'message' => 'Message Content',
            'link' => 'Link',
        );
    }

    private function build_export_rows( $post_ids, $fields, &$error_message ) {
        $error_message = '';
        $max_rows = self::EXPORT_MAX_ROWS;

        if ( ! empty( $post_ids ) && count( $post_ids ) > $max_rows ) {
            $error_message = 'Too many records to export, please reduce selection or export in batches.';
            return array();
        }

        $args = $this->get_records_query_args( $max_rows + 1, 1, $post_ids );
        $args['no_found_rows'] = true;
        $query = new WP_Query( $args );

        if ( $query->post_count > $max_rows ) {
            $error_message = 'Too many records to export, please narrow the scope or export selected records.';
            wp_reset_postdata();
            return array();
        }

        $allowed_fields = $this->get_export_allowed_fields();
        $rows = array();
        $header = array();
        foreach ( $fields as $field ) {
            $header[] = $allowed_fields[ $field ];
        }
        $rows[] = $header;

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                $author = get_the_author();
                $sent_time = get_post_meta( $post_id, '_dtpwp_sent_time', true );
                $sent_message = get_post_meta( $post_id, '_dtpwp_sent_message', true );
                $link = get_permalink( $post_id );

                $data = array(
                    'id' => $post_id,
                    'title' => html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ),
                    'author' => $author,
                    'sent_time' => $sent_time,
                    'message' => $sent_message,
                    'link' => $link,
                );

                $row = array();
                foreach ( $fields as $field ) {
                    $row[] = isset( $data[ $field ] ) ? $data[ $field ] : '';
                }
                $rows[] = $row;
            }
            wp_reset_postdata();
        }

        return $rows;
    }

    private function acquire_export_lock() {
        $user_id = get_current_user_id();
        $key = 'dtpwp_export_lock_' . $user_id;
        if ( get_transient( $key ) ) {
            return false;
        }
        set_transient( $key, time(), self::EXPORT_RATE_LIMIT_SECONDS );
        return true;
    }

    private function release_export_lock() {
        $user_id = get_current_user_id();
        $key = 'dtpwp_export_lock_' . $user_id;
        delete_transient( $key );
    }

    private function is_xlsx_available() {
        if ( class_exists( 'ZipArchive' ) ) {
            return true;
        }

        if ( class_exists( 'PclZip' ) ) {
            return true;
        }

        $pclzip_path = ABSPATH . 'wp-admin/includes/class-pclzip.php';
        if ( file_exists( $pclzip_path ) ) {
            require_once $pclzip_path;
        }

        return class_exists( 'PclZip' );
    }

    private function delete_temp_dir( $dir ) {
        if ( empty( $dir ) || ! is_dir( $dir ) ) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $items as $item ) {
            if ( $item->isDir() ) {
                @rmdir( $item->getPathname() );
            } else {
                @unlink( $item->getPathname() );
            }
        }

        @rmdir( $dir );
    }

    private function prepare_export_file( $rows, $format, &$error_message ) {
        $error_message = '';
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            $error_message = 'Unable to write to upload directory, please check server permissions.';
            return false;
        }

        $dir = trailingslashit( $uploads['basedir'] ) . 'dtpwp-exports';
        if ( ! wp_mkdir_p( $dir ) ) {
            $error_message = 'Unable to create export directory.';
            return false;
        }

        $this->cleanup_export_files( $dir );

        $filename = 'kate522-notifier-for-dingtalk-records-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_uuid4() . '.' . $format;
        $path = trailingslashit( $dir ) . $filename;

        if ( 'xlsx' === $format ) {
            if ( ! $this->generate_xlsx_file( $rows, $path, $error_message ) ) {
                return false;
            }
        } elseif ( ! $this->write_csv_file( $rows, $path, $error_message ) ) {
            return false;
        }

        return array(
            'path' => $path,
            'filename' => $filename,
        );
    }

    private function cleanup_export_files( $dir ) {
        $files = glob( trailingslashit( $dir ) . 'kate522-notifier-for-dingtalk-records-*' );
        if ( empty( $files ) ) {
            return;
        }

        $expire = time() - DAY_IN_SECONDS;
        foreach ( $files as $file ) {
            if ( is_file( $file ) && filemtime( $file ) < $expire ) {
                @unlink( $file );
            }
        }
    }

    private function output_records_csv( $rows ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename=kate522-notifier-for-dingtalk-records.csv' );

        echo "\xEF\xBB\xBF";

        $output = fopen( 'php://output', 'w' );
        foreach ( $rows as $row ) {
            fputcsv( $output, $row );
        }
        fclose( $output );
    }

    private function write_csv_file( $rows, $path, &$error_message ) {
        $error_message = '';
        $handle = fopen( $path, 'wb' );
        if ( ! $handle ) {
            $error_message = 'Unable to write CSV file.';
            return false;
        }

        fwrite( $handle, "\xEF\xBB\xBF" );
        foreach ( $rows as $row ) {
            fputcsv( $handle, $row );
        }
        fclose( $handle );
        return true;
    }

    private function generate_xlsx_file( $rows, $file_path, &$error_message ) {
        $error_message = '';

        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $file_path, ZipArchive::OVERWRITE ) ) {
                $error_message = 'Failed to generate XLSX file.';
                return false;
            }

            $zip->addFromString( '[Content_Types].xml', $this->get_xlsx_content_types() );
            $zip->addFromString( '_rels/.rels', $this->get_xlsx_root_rels() );
            $zip->addFromString( 'xl/workbook.xml', $this->get_xlsx_workbook() );
            $zip->addFromString( 'xl/_rels/workbook.xml.rels', $this->get_xlsx_workbook_rels() );
            $zip->addFromString( 'xl/styles.xml', $this->get_xlsx_styles() );
            $zip->addFromString( 'xl/worksheets/sheet1.xml', $this->get_xlsx_sheet( $rows ) );
            $zip->close();

            return true;
        }

        return $this->generate_xlsx_file_with_pclzip( $rows, $file_path, $error_message );
    }

    private function generate_xlsx_file_with_pclzip( $rows, $file_path, &$error_message ) {
        $error_message = '';
        $generic_error = 'XLSX export failed. Please enable PHP ZipArchive or PclZip.';

        if ( ! class_exists( 'PclZip' ) ) {
            $pclzip_path = ABSPATH . 'wp-admin/includes/class-pclzip.php';
            if ( file_exists( $pclzip_path ) ) {
                require_once $pclzip_path;
            }
        }

        if ( ! class_exists( 'PclZip' ) ) {
            $error_message = $generic_error;
            return false;
        }

        $temp_dir = trailingslashit( get_temp_dir() ) . 'dtpwp-xlsx-' . wp_generate_uuid4();
        if ( ! wp_mkdir_p( $temp_dir ) ) {
            $error_message = $generic_error;
            return false;
        }

        $files = array(
            '[Content_Types].xml' => $this->get_xlsx_content_types(),
            '_rels/.rels' => $this->get_xlsx_root_rels(),
            'xl/workbook.xml' => $this->get_xlsx_workbook(),
            'xl/_rels/workbook.xml.rels' => $this->get_xlsx_workbook_rels(),
            'xl/styles.xml' => $this->get_xlsx_styles(),
            'xl/worksheets/sheet1.xml' => $this->get_xlsx_sheet( $rows ),
        );

        foreach ( $files as $relative_path => $content ) {
            $target = $temp_dir . '/' . $relative_path;
            $dir = dirname( $target );
            if ( ! wp_mkdir_p( $dir ) ) {
                $error_message = $generic_error;
                $this->delete_temp_dir( $temp_dir );
                return false;
            }

            if ( false === file_put_contents( $target, $content ) ) {
                $error_message = $generic_error;
                $this->delete_temp_dir( $temp_dir );
                return false;
            }
        }

        $zip = new PclZip( $file_path );
        $result = $zip->create( $temp_dir, PCLZIP_OPT_REMOVE_PATH, $temp_dir );

        $this->delete_temp_dir( $temp_dir );

        if ( 0 === $result ) {
            $error_message = 'Failed to generate XLSX file.';
            return false;
        }

        return true;
    }

    /**
     * Output XLSX
     *
     * @param array $rows Row data
     */
    private function output_records_xlsx( $rows ) {
        $temp_file = wp_tempnam( 'dtpwp-records' );
        if ( ! $temp_file ) {
            wp_die( 'Unable to create temporary file.' );
        }

        $error_message = '';
        if ( ! $this->generate_xlsx_file( $rows, $temp_file, $error_message ) ) {
            wp_die( $error_message ? $error_message : 'Unable to create XLSX file.' );
        }

        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename=kate522-notifier-for-dingtalk-records.xlsx' );
        header( 'Content-Length: ' . filesize( $temp_file ) );

        readfile( $temp_file );
        @unlink( $temp_file );
    }

    private function get_xlsx_content_types() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>';
    }

    private function get_xlsx_root_rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    private function get_xlsx_workbook() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Records" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>';
    }

    private function get_xlsx_workbook_rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>';
    }

    private function get_xlsx_styles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>' .
            '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>' .
            '</styleSheet>';
    }

    private function get_xlsx_sheet( $rows ) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $row_index = 1;
        foreach ( $rows as $row ) {
            $xml .= '<row r="' . $row_index . '">';
            $col_index = 1;
            foreach ( $row as $cell ) {
                $cell_ref = $this->xlsx_column_letter( $col_index ) . $row_index;
                $value = htmlspecialchars( (string) $cell, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
                $xml .= '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . $value . '</t></is></c>';
                $col_index++;
            }
            $xml .= '</row>';
            $row_index++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function xlsx_column_letter( $index ) {
        $letters = '';
        while ( $index > 0 ) {
            $index--;
            $letters = chr( 65 + ( $index % 26 ) ) . $letters;
            $index = (int) ( $index / 26 );
        }
        return $letters;
    }
}
