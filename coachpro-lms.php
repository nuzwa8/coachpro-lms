<?php
/**
 * CoachPro LMS — Non-AI core. Admin/Frontend scaffolding, DB schema, roles/caps, AJAX/REST hooks, templates, enqueue.
 * Version: 1.0.0
 * Author: CoachPro Team
 *
 * Plugin Name: CoachPro LMS
 * Description: Non-AI Coaching LMS: Programs, Sessions (notes/chat), Progress, Assessments, Reports. Ready for future AI switch without API keys.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * Text Domain: coachpro-lms
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('CoachPro_LMS')) {
final class CoachPro_LMS {

    const VERSION = '1.0.0';
    const TD = 'coachpro-lms';
    private static ?CoachPro_LMS $instance = null;

    /** Singleton */
    public static function instance(): CoachPro_LMS {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Hooks
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_post_types']);
        add_action('admin_menu', [$this, 'admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_front_assets']);
        add_action('rest_api_init', [$this, 'register_rest_endpoints']);

        // AJAX
        add_action('wp_ajax_coachpro_enroll_program', [$this, 'ajax_enroll_program']);
        add_action('wp_ajax_nopriv_coachpro_enroll_program', [$this, 'ajax_forbidden']);
        add_action('wp_ajax_coachpro_start_session', [$this, 'ajax_start_session']);
        add_action('wp_ajax_nopriv_coachpro_start_session', [$this, 'ajax_forbidden']);
        add_action('wp_ajax_coachpro_send_message', [$this, 'ajax_send_message']);
        add_action('wp_ajax_nopriv_coachpro_send_message', [$this, 'ajax_forbidden']);
        add_action('wp_ajax_coachpro_get_progress', [$this, 'ajax_get_progress']);
        add_action('wp_ajax_nopriv_coachpro_get_progress', [$this, 'ajax_forbidden']);

        // Shortcodes (frontend)
        add_shortcode('coachpro_programs', [$this, 'sc_programs']);
        add_shortcode('coachpro_chat', [$this, 'sc_chat']);
        add_shortcode('coachpro_dashboard', [$this, 'sc_dashboard']);
        add_shortcode('coachpro_progress', [$this, 'sc_progress']);
        add_shortcode('coachpro_coaches', [$this, 'sc_coaches']);
    }

    /** i18n */
    public function load_textdomain() {
        load_plugin_textdomain(self::TD, false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /** Post Types: Program/Module/Lesson/Coach as CPTs for SEO & templates */
    public function register_post_types() {
        register_post_type('cpl_program', [
            'label' => __('Programs', self::TD),
            'public' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'coaching-programs'],
            'show_in_rest' => true,
        ]);
        register_post_type('cpl_module', [
            'label' => __('Modules', self::TD),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor'],
            'show_in_rest' => true,
        ]);
        register_post_type('cpl_lesson', [
            'label' => __('Lessons', self::TD),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor'],
            'show_in_rest' => true,
        ]);
        register_post_type('cpl_coach', [
            'label' => __('Coaches', self::TD),
            'public' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'coaches'],
            'show_in_rest' => true,
        ]);
        // Taxonomy for Program categories
        register_taxonomy('cpl_program_cat', ['cpl_program'], [
            'label' => __('Program Categories', self::TD),
            'public' => true,
            'hierarchical' => true,
            'show_in_rest' => true
        ]);
    }

    /** Centralized table names */
    public static function table_names(): array {
        global $wpdb;
        $p = $wpdb->prefix;
        return [
            'profiles'      => "{$p}coachproai_profiles",
            'sessions'      => "{$p}coachproai_ai_sessions",
            'progress'      => "{$p}coachproai_learning_progress",
            'recs'          => "{$p}coachproai_recommendations",
            'analytics'     => "{$p}coachproai_analytics",
            'assessments'   => "{$p}coachproai_assessments",
            'responses'     => "{$p}coachproai_assessments_responses",
            'enrollments'   => "{$p}coachproai_enrollments",
        ];
    }

    /** Admin menus */
    public function admin_menus() {
        $cap_manage = 'manage_coachpro';
        add_menu_page(
            __('CoachPro LMS', self::TD),
            __('CoachPro LMS', self::TD),
            $cap_manage,
            'coachpro-lms',
            [$this, 'render_admin_app'],
            'dashicons-welcome-learn-more',
            26
        );
        add_submenu_page('coachpro-lms', __('Dashboard', self::TD), __('Dashboard', self::TD), $cap_manage, 'coachpro-lms', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Programs', self::TD), __('Programs', self::TD), 'edit_coachpro', 'coachpro-programs', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Students', self::TD), __('Students', self::TD), 'edit_coachpro', 'coachpro-students', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Sessions', self::TD), __('Sessions', self::TD), 'edit_coachpro', 'coachpro-sessions', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Assessments', self::TD), __('Assessments', self::TD), 'edit_coachpro', 'coachpro-assessments', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Reports', self::TD), __('Reports', self::TD), 'view_coachpro', 'coachpro-reports', [$this, 'render_admin_app']);
        add_submenu_page('coachpro-lms', __('Settings', self::TD), __('Settings', self::TD), $cap_manage, 'coachpro-settings', [$this, 'render_admin_app']);
    }

    /** Admin screen container + Template blocks */
    public function render_admin_app() {
        if (!current_user_can('view_coachpro') && !current_user_can('edit_coachpro') && !current_user_can('manage_coachpro')) {
            wp_die(__('You do not have permission to access CoachPro LMS.', self::TD));
        }
        $screen = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'coachpro-lms';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('CoachPro LMS', self::TD)); ?></h1>
            <div id="ssm-admin-screen" class="ssm-root" data-screen="<?php echo esc_attr($screen); ?>"></div>

            <?php $this->print_admin_templates(); ?>
        </div>
        <?php
    }

    /** Print all admin <template> blocks once */
    private function print_admin_templates() {
        ?>
        <!-- Dashboard Template -->
        <template id="ssm-tpl-dashboard">
            <section class="ssm ssm-dashboard">
                <header class="ssm-header">
                    <h2><?php echo esc_html(__('Dashboard', self::TD)); ?></h2>
                    <div class="ssm-actions">
                        <button class="button button-primary" data-action="new-program"><?php echo esc_html(__('Add Program', self::TD)); ?></button>
                        <button class="button" data-action="export-csv"><?php echo esc_html(__('Export CSV', self::TD)); ?></button>
                    </div>
                </header>
                <div class="ssm-kpis">
                    <div class="ssm-kpi"><strong data-kpi="total_programs">0</strong><span><?php _e('Programs', self::TD); ?></span></div>
                    <div class="ssm-kpi"><strong data-kpi="active_students">0</strong><span><?php _e('Active Students', self::TD); ?></span></div>
                    <div class="ssm-kpi"><strong data-kpi="open_sessions">0</strong><span><?php _e('Open Sessions', self::TD); ?></span></div>
                    <div class="ssm-kpi"><strong data-kpi="avg_score">0%</strong><span><?php _e('Avg Score', self::TD); ?></span></div>
                </div>
                <div class="ssm-table-wrap">
                    <table class="widefat">
                        <thead><tr>
                            <th><?php _e('Student', self::TD); ?></th>
                            <th><?php _e('Program', self::TD); ?></th>
                            <th><?php _e('Status', self::TD); ?></th>
                            <th><?php _e('Updated', self::TD); ?></th>
                        </tr></thead>
                        <tbody data-list="recent_enrollments"></tbody>
                    </table>
                </div>
            </section>
        </template>

        <!-- Programs Template -->
        <template id="ssm-tpl-programs">
            <section class="ssm ssm-programs">
                <header class="ssm-header">
                    <h2><?php _e('Programs', self::TD); ?></h2>
                    <div class="ssm-actions">
                        <input type="search" placeholder="<?php esc_attr_e('Search…', self::TD); ?>" data-ref="search">
                        <button class="button button-primary" data-action="add-program"><?php _e('Add Program', self::TD); ?></button>
                    </div>
                </header>
                <div class="ssm-table-wrap">
                    <table class="widefat">
                        <thead><tr>
                            <th><?php _e('Title', self::TD); ?></th>
                            <th><?php _e('Category', self::TD); ?></th>
                            <th><?php _e('Price', self::TD); ?></th>
                            <th><?php _e('Enrollments', self::TD); ?></th>
                            <th><?php _e('Actions', self::TD); ?></th>
                        </tr></thead>
                        <tbody data-list="programs"></tbody>
                    </table>
                    <div class="tablenav"><div class="tablenav-pages" data-ref="pagination"></div></div>
                </div>
            </section>
        </template>

        <!-- Students Template -->
        <template id="ssm-tpl-students">
            <section class="ssm ssm-students">
                <header class="ssm-header">
                    <h2><?php _e('Students', self::TD); ?></h2>
                    <div class="ssm-actions">
                        <input type="search" placeholder="<?php esc_attr_e('Search by name/email…', self::TD); ?>" data-ref="search">
                    </div>
                </header>
                <div class="ssm-table-wrap">
                    <table class="widefat">
                        <thead><tr>
                            <th><?php _e('Name', self::TD); ?></th>
                            <th><?php _e('Email', self::TD); ?></th>
                            <th><?php _e('Enrolled', self::TD); ?></th>
                            <th><?php _e('Avg Score', self::TD); ?></th>
                            <th><?php _e('Actions', self::TD); ?></th>
                        </tr></thead>
                        <tbody data-list="students"></tbody>
                    </table>
                    <div class="tablenav"><div class="tablenav-pages" data-ref="pagination"></div></div>
                </div>
            </section>
        </template>

        <!-- Sessions Template -->
        <template id="ssm-tpl-sessions">
            <section class="ssm ssm-sessions">
                <header class="ssm-header">
                    <h2><?php _e('Sessions (Notes/Chat)', self::TD); ?></h2>
                    <div class="ssm-actions">
                        <select data-ref="student"></select>
                        <select data-ref="program"></select>
                        <button class="button button-primary" data-action="start-session"><?php _e('Start Session', self::TD); ?></button>
                    </div>
                </header>
                <div class="ssm-session">
                    <div class="ssm-messages" data-list="messages"></div>
                    <form data-ref="composer">
                        <label>
                            <span class="screen-reader-text"><?php _e('Message', self::TD); ?></span>
                            <textarea rows="3" data-ref="message"></textarea>
                        </label>
                        <div class="ssm-row">
                            <input type="file" data-ref="file" />
                            <button class="button button-primary" data-action="send"><?php _e('Send', self::TD); ?></button>
                        </div>
                    </form>
                </div>
            </section>
        </template>

        <!-- Assessments Template -->
        <template id="ssm-tpl-assessments">
            <section class="ssm ssm-assessments">
                <header class="ssm-header">
                    <h2><?php _e('Assessments', self::TD); ?></h2>
                    <div class="ssm-actions">
                        <button class="button button-primary" data-action="new-assessment"><?php _e('New Assessment', self::TD); ?></button>
                    </div>
                </header>
                <div class="ssm-table-wrap">
                    <table class="widefat">
                        <thead><tr>
                            <th><?php _e('Title', self::TD); ?></th>
                            <th><?php _e('Questions', self::TD); ?></th>
                            <th><?php _e('Submissions', self::TD); ?></th>
                            <th><?php _e('Actions', self::TD); ?></th>
                        </tr></thead>
                        <tbody data-list="assessments"></tbody>
                    </table>
                </div>
            </section>
        </template>

        <!-- Reports Template -->
        <template id="ssm-tpl-reports">
            <section class="ssm ssm-reports">
                <header class="ssm-header">
                    <h2><?php _e('Reports', self::TD); ?></h2>
                    <div class="ssm-actions">
                        <input type="date" data-ref="from">
                        <input type="date" data-ref="to">
                        <button class="button" data-action="run"><?php _e('Run', self::TD); ?></button>
                        <button class="button" data-action="export"><?php _e('Export CSV', self::TD); ?></button>
                    </div>
                </header>
                <div class="ssm-table-wrap">
                    <table class="widefat">
                        <thead><tr>
                            <th><?php _e('Program', self::TD); ?></th>
                            <th><?php _e('Enrollments', self::TD); ?></th>
                            <th><?php _e('Completion %', self::TD); ?></th>
                            <th><?php _e('Avg Score', self::TD); ?></th>
                        </tr></thead>
                        <tbody data-list="reports"></tbody>
                    </table>
                </div>
            </section>
        </template>

        <!-- Settings Template -->
        <template id="ssm-tpl-settings">
            <section class="ssm ssm-settings">
                <header class="ssm-header">
                    <h2><?php _e('Settings', self::TD); ?></h2>
                </header>
                <form data-ref="settings-form">
                    <fieldset>
                        <legend><?php _e('General', self::TD); ?></legend>
                        <label><?php _e('Currency', self::TD); ?>
                            <input type="text" data-ref="currency" value="<?php echo esc_attr(get_option('cpl_currency', 'USD')); ?>">
                        </label>
                        <label><?php _e('Default Program Page', self::TD); ?>
                            <input type="text" data-ref="program_page" value="<?php echo esc_attr(get_option('cpl_program_page', '')); ?>">
                        </label>
                    </fieldset>
                    <fieldset>
                        <legend><?php _e('WooCommerce', self::TD); ?></legend>
                        <label><input type="checkbox" data-ref="woo_enable" <?php checked((bool)get_option('cpl_woo_enable', false)); ?>> <?php _e('Enable WooCommerce Integration', self::TD); ?></label>
                    </fieldset>
                    <fieldset>
                        <legend><?php _e('Rule-based Recommendations', self::TD); ?></legend>
                        <textarea rows="6" data-ref="rules_json" placeholder='[{"when":{"avg_score":{"lt":60}},"then":{"recommend":"Lesson A"}}]'><?php echo esc_textarea(get_option('cpl_rules_json', '[]')); ?></textarea>
                    </fieldset>
                    <div>
                        <button class="button button-primary" data-action="save-settings"><?php _e('Save Settings', self::TD); ?></button>
                    </div>
                </form>
            </section>
        </template>
        <?php
    }

    /** Enqueue Admin */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'coachpro') === false) return;
        $this->enqueue_shared_assets(true);
    }

    /** Enqueue Frontend */
    public function enqueue_front_assets() {
        // Load on our shortcodes or CPTs
        if (is_singular(['cpl_program']) || has_shortcode(get_post_field('post_content', get_the_ID() ?: 0), 'coachpro_')) {
            $this->enqueue_shared_assets(false);
        }
    }

    /** Shared enqueue + localize */
    private function enqueue_shared_assets(bool $is_admin) {
        $ver = self::VERSION;
        $base = plugin_dir_url(__FILE__);
        wp_enqueue_style('coachpro-lms', $base . 'assets/css/coachpro-admin.css', [], $ver);
        wp_enqueue_script('coachpro-lms', $base . 'assets/js/coachpro-admin.js', ['jquery'], $ver, true);

        $caps = [
            'manage' => current_user_can('manage_coachpro'),
            'edit'   => current_user_can('edit_coachpro'),
            'view'   => current_user_can('view_coachpro'),
        ];
        wp_localize_script('coachpro-lms', 'ssmData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('cpl_ajax'),
            'caps'     => $caps,
            'is_admin' => $is_admin,
            'strings'  => [
                'error' => __('An error occurred. Please try again.', self::TD),
                'saved' => __('Saved successfully.', self::TD),
            ],
        ]);
    }

    /** REST Endpoints registered later in Part 4 */
    public function register_rest_endpoints() {
        // Placeholder method to be filled in Part 4 (already loaded below). Intentionally left callable.
    }

    /** AJAX guard */
    public function ajax_forbidden() {
        wp_send_json_error(['message' => __('Authentication required.', self::TD)], 401);
    }

    /** Utility: get user id safely */
    private static function uid(): int {
        return get_current_user_id() ?: 0;
    }

    /** ACTIVATION: DB + Roles + Options */
    public static function activate() {
        // Roles/Caps
        self::install_caps();

        // DB
        self::install_db();

        // Options
        add_option('coachpro_lms_version', self::VERSION);
        add_option('cpl_currency', 'USD');
        add_option('cpl_woo_enable', false);
        add_option('cpl_rules_json', '[]');
    }

    /** ROLES/CAPS */
    private static function install_caps() {
        $roles = [
            'administrator' => ['manage_coachpro', 'edit_coachpro', 'view_coachpro'],
            'editor'        => ['edit_coachpro', 'view_coachpro'],
            'author'        => ['view_coachpro'],
            'coachpro_student' => [],
            'coachpro_coach'   => ['edit_coachpro', 'view_coachpro'],
            'coachpro_admin'   => ['manage_coachpro', 'edit_coachpro', 'view_coachpro'],
        ];

        // Ensure custom roles exist
        if (!get_role('coachpro_student')) add_role('coachpro_student', __('CoachPro Student', self::TD), []);
        if (!get_role('coachpro_coach')) add_role('coachpro_coach', __('CoachPro Coach', self::TD), []);
        if (!get_role('coachpro_admin')) add_role('coachpro_admin', __('CoachPro Admin', self::TD), []);

        foreach ($roles as $role_key => $caps) {
            $role = get_role($role_key);
            if (!$role) continue;
            foreach ($caps as $cap) {
                $role->add_cap($cap);
            }
        }
    }

    /** DB: create tables using dbDelta */
    private static function install_db() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $t = self::table_names();

        $sql = [];

        // Profiles
        $sql[] = "CREATE TABLE {$t['profiles']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            preferences TEXT NULL,
            goals TEXT NULL,
            tags TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Sessions (non-AI notes/chat)
        $sql[] = "CREATE TABLE {$t['sessions']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            coach_id BIGINT UNSIGNED NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            message LONGTEXT NULL,
            attachment_url TEXT NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY student_id (student_id),
            KEY program_id (program_id)
        ) $charset_collate;";

        // Progress
        $sql[] = "CREATE TABLE {$t['progress']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            lessons_total INT UNSIGNED DEFAULT 0,
            lessons_done INT UNSIGNED DEFAULT 0,
            avg_score DECIMAL(5,2) DEFAULT 0,
            last_active DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY student_program (student_id, program_id)
        ) $charset_collate;";

        // Recommendations (rule-based)
        $sql[] = "CREATE TABLE {$t['recs']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            rule_json LONGTEXT NOT NULL,
            output_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY student_program (student_id, program_id)
        ) $charset_collate;";

        // Analytics snapshots
        $sql[] = "CREATE TABLE {$t['analytics']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            snapshot_date DATE NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            enrollments INT UNSIGNED DEFAULT 0,
            completion_rate DECIMAL(5,2) DEFAULT 0,
            avg_score DECIMAL(5,2) DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_date (program_id, snapshot_date)
        ) $charset_collate;";

        // Assessments
        $sql[] = "CREATE TABLE {$t['assessments']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            program_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            config_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY program_id (program_id)
        ) $charset_collate;";

        // Assessment responses
        $sql[] = "CREATE TABLE {$t['responses']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessment_id BIGINT UNSIGNED NOT NULL,
            student_id BIGINT UNSIGNED NOT NULL,
            answers_json LONGTEXT NOT NULL,
            score DECIMAL(5,2) DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY assessment_id (assessment_id),
            KEY student_id (student_id)
        ) $charset_collate;";

        // Enrollments
        $sql[] = "CREATE TABLE {$t['enrollments']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'enrolled',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY student_program (student_id, program_id)
        ) $charset_collate;";

        foreach ($sql as $q) {
            dbDelta($q);
        }
    }
}
// End class

// Bootstrap
add_action('plugins_loaded', function() {
    CoachPro_LMS::instance();
});

// Activation hook
register_activation_hook(__FILE__, ['CoachPro_LMS', 'activate']);
}
/** Part 2 — Shortcodes (programs, chat, dashboard, progress, coaches) */
if (!defined('ABSPATH')) exit;

if (!function_exists('cpl_render_container')) {
    function cpl_render_container(string $id, array $attrs = [], string $fallback = ''): string {
        $attrs_str = '';
        foreach ($attrs as $k => $v) {
            $attrs_str .= ' data-' . esc_attr($k) . '="' . esc_attr($v) . '"';
        }
        $html  = '<div id="' . esc_attr($id) . '" class="ssm ssm-root"'.$attrs_str.'>';
        $html .= '<noscript><div class="ssm-noscript">'. esc_html__('This feature requires JavaScript.', CoachPro_LMS::TD) .'</div></noscript>';
        if ($fallback) $html .= '<div class="ssm-fallback">'. wp_kses_post($fallback) .'</div>';
        $html .= '</div>';
        return $html;
    }
}

CoachPro_LMS::instance(); // ensure class loaded

// [coachpro_programs category="leadership" limit="6" columns="3"]
CoachPro_LMS::instance()->sc_programs = function($atts){
    $a = shortcode_atts([
        'category' => '',
        'limit'    => 6,
        'columns'  => 3,
    ], $atts, 'coachpro_programs');

    $fallback = '<p>'.__('Programs list will appear here. Use filters above if available.', CoachPro_LMS::TD).'</p>';
    return cpl_render_container('ssm-programs-root', [
        'screen' => 'shortcode-programs',
        'category' => $a['category'],
        'limit' => (int)$a['limit'],
        'columns' => (int)$a['columns'],
    ], $fallback);
};
add_shortcode('coachpro_programs', CoachPro_LMS::instance()->sc_programs);

// [coachpro_chat program_id="123" allow_uploads="true"]
CoachPro_LMS::instance()->sc_chat = function($atts){
    $a = shortcode_atts([
        'program_id'    => 0,
        'allow_uploads' => 'true',
    ], $atts, 'coachpro_chat');

    $fallback = '<p>'.__('Session messages will load here. Start a session to begin.', CoachPro_LMS::TD).'</p>';
    return cpl_render_container('ssm-chat-root', [
        'screen' => 'shortcode-chat',
        'program_id' => absint($a['program_id']),
        'allow_uploads' => $a['allow_uploads'] === 'true' ? '1' : '0',
    ], $fallback);
};
add_shortcode('coachpro_chat', CoachPro_LMS::instance()->sc_chat);

// [coachpro_dashboard]
CoachPro_LMS::instance()->sc_dashboard = function($atts){
    $fallback = '<p>'.__('Your enrolled programs, progress, and recent activity will appear here.', CoachPro_LMS::TD).'</p>';
    return cpl_render_container('ssm-dashboard-root', [
        'screen' => 'shortcode-dashboard',
    ], $fallback);
};
add_shortcode('coachpro_dashboard', CoachPro_LMS::instance()->sc_dashboard);

// [coachpro_progress program_id="456" show_charts="true"]
CoachPro_LMS::instance()->sc_progress = function($atts){
    $a = shortcode_atts([
        'program_id' => 0,
        'show_charts'=> 'true',
    ], $atts, 'coachpro_progress');
    $fallback = '<p>'.__('Progress data will appear here.', CoachPro_LMS::TD).'</p>';
    return cpl_render_container('ssm-progress-root', [
        'screen' => 'shortcode-progress',
        'program_id' => absint($a['program_id']),
        'show_charts'=> $a['show_charts'] === 'true' ? '1' : '0',
    ], $fallback);
};
add_shortcode('coachpro_progress', CoachPro_LMS::instance()->sc_progress);

// [coachpro_coaches specialty="leadership" limit="4"]
CoachPro_LMS::instance()->sc_coaches = function($atts){
    $a = shortcode_atts([
        'specialty' => '',
        'limit'     => 4,
    ], $atts, 'coachpro_coaches');
    $fallback = '<p>'.__('Coaches will be listed here.', CoachPro_LMS::TD).'</p>';
    return cpl_render_container('ssm-coaches-root', [
        'screen' => 'shortcode-coaches',
        'specialty' => $a['specialty'],
        'limit' => (int)$a['limit'],
    ], $fallback);
};
add_shortcode('coachpro_coaches', CoachPro_LMS::instance()->sc_coaches);

/** Part 3 — AJAX endpoints (secure, non-AI logic) */
if (!defined('ABSPATH')) exit;

if (!method_exists('CoachPro_LMS', 'ajax_enroll_program')) {
class CoachPro_LMS_Ajax_Ext {
    /** Enroll program */
    public static function enroll() {
        check_ajax_referer('cpl_ajax', 'nonce');
        if (!current_user_can('edit_coachpro') && !current_user_can('view_coachpro') && !is_user_logged_in()) {
            wp_send_json_error(['message' => __('Permission denied.', CoachPro_LMS::TD)], 403);
        }
        $student_id = get_current_user_id();
        $program_id = isset($_POST['program_id']) ? absint($_POST['program_id']) : 0;
        if (!$student_id || !$program_id) wp_send_json_error(['message' => __('Missing data.', CoachPro_LMS::TD)], 400);

        global $wpdb;
        $t = CoachPro_LMS::table_names();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['enrollments']} WHERE student_id=%d AND program_id=%d", $student_id, $program_id));
        $now = current_time('mysql');
        if ($exists) {
            $wpdb->update($t['enrollments'], ['status' => 'enrolled', 'updated_at' => $now], ['id' => $exists], ['%s','%s'], ['%d']);
        } else {
            $wpdb->insert($t['enrollments'], [
                'student_id' => $student_id,
                'program_id' => $program_id,
                'status'     => 'enrolled',
                'created_at' => $now,
                'updated_at' => $now
            ], ['%d','%d','%s','%s','%s']);
            // Seed progress row
            $wpdb->insert($t['progress'], [
                'student_id' => $student_id,
                'program_id' => $program_id,
                'lessons_total' => 0,
                'lessons_done'  => 0,
                'avg_score'     => 0.00,
                'last_active'   => $now,
                'created_at'    => $now,
                'updated_at'    => $now
            ], ['%d','%d','%d','%d','%f','%s','%s','%s']);
        }
        wp_send_json_success(['message' => __('Enrolled successfully.', CoachPro_LMS::TD)]);
    }

    /** Start session (non-AI, opens a thread by inserting a meta record) */
    public static function start_session() {
        check_ajax_referer('cpl_ajax', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(['message' => __('Login required.', CoachPro_LMS::TD)], 401);

        $student_id = get_current_user_id();
        $coach_id   = isset($_POST['coach_id']) ? absint($_POST['coach_id']) : 0;
        $program_id = isset($_POST['program_id']) ? absint($_POST['program_id']) : 0;
        if (!$program_id) wp_send_json_error(['message' => __('Program required.', CoachPro_LMS::TD)], 400);

        global $wpdb;
        $t = CoachPro_LMS::table_names();
        $now = current_time('mysql');
        // Insert a system message that thread started
        $wpdb->insert($t['sessions'], [
            'student_id' => $student_id,
            'coach_id'   => $coach_id,
            'program_id' => $program_id,
            'message'    => wp_kses_post(__('Session started.', CoachPro_LMS::TD)),
            'attachment_url' => null,
            'meta_json'  => wp_json_encode(['type' => 'system', 'event' => 'start']),
            'created_at' => $now
        ], ['%d','%d','%d','%s','%s','%s','%s']);

        wp_send_json_success(['message' => __('Session opened.', CoachPro_LMS::TD)]);
    }

    /** Send message (text only; file handling can be added later safely) */
    public static function send_message() {
        check_ajax_referer('cpl_ajax', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(['message' => __('Login required.', CoachPro_LMS::TD)], 401);

        $student_id = get_current_user_id();
        $coach_id   = isset($_POST['coach_id']) ? absint($_POST['coach_id']) : 0;
        $program_id = isset($_POST['program_id']) ? absint($_POST['program_id']) : 0;
        $message    = isset($_POST['message']) ? wp_kses_post(wp_unslash($_POST['message'])) : '';

        if (!$program_id || $message === '') wp_send_json_error(['message' => __('Invalid message.', CoachPro_LMS::TD)], 400);

        global $wpdb;
        $t = CoachPro_LMS::table_names();
        $now = current_time('mysql');

        $wpdb->insert($t['sessions'], [
            'student_id'    => $student_id,
            'coach_id'      => $coach_id,
            'program_id'    => $program_id,
            'message'       => $message,
            'attachment_url'=> null,
            'meta_json'     => wp_json_encode(['type' => 'user']),
            'created_at'    => $now
        ], ['%d','%d','%d','%s','%s','%s','%s']);

        // touch progress last_active
        $wpdb->query($wpdb->prepare("UPDATE {$t['progress']} SET last_active=%s, updated_at=%s WHERE student_id=%d AND program_id=%d",
            $now, $now, $student_id, $program_id));

        wp_send_json_success(['message' => __('Message sent.', CoachPro_LMS::TD)]);
    }

    /** Get progress summary */
    public static function get_progress() {
        check_ajax_referer('cpl_ajax', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(['message' => __('Login required.', CoachPro_LMS::TD)], 401);
        $student_id = get_current_user_id();
        $program_id = isset($_POST['program_id']) ? absint($_POST['program_id']) : 0;
        if (!$program_id) wp_send_json_error(['message' => __('Program required.', CoachPro_LMS::TD)], 400);

        global $wpdb;
        $t = CoachPro_LMS::table_names();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['progress']} WHERE student_id=%d AND program_id=%d", $student_id, $program_id), ARRAY_A);
        if (!$row) $row = [
            'lessons_total' => 0, 'lessons_done' => 0, 'avg_score' => 0, 'last_active' => null
        ];
        wp_send_json_success(['progress' => $row]);
    }
}
// Bind methods to main class as wrappers
CoachPro_LMS::instance()->ajax_enroll_program = ['CoachPro_LMS_Ajax_Ext','enroll'];
CoachPro_LMS::instance()->ajax_start_session  = ['CoachPro_LMS_Ajax_Ext','start_session'];
CoachPro_LMS::instance()->ajax_send_message   = ['CoachPro_LMS_Ajax_Ext','send_message'];
CoachPro_LMS::instance()->ajax_get_progress   = ['CoachPro_LMS_Ajax_Ext','get_progress'];
}

/** Part 4 — REST API endpoints (read/write minimal set) */
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    $ns = 'coachpro/v1';

    register_rest_route($ns, '/programs', [
        [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => function(WP_REST_Request $req) {
                $cat = sanitize_text_field($req->get_param('category') ?: '');
                $args = [
                    'post_type' => 'cpl_program',
                    'post_status' => 'publish',
                    'posts_per_page' => 20
                ];
                if ($cat) $args['tax_query'] = [[
                    'taxonomy' => 'cpl_program_cat',
                    'field' => 'slug',
                    'terms' => $cat
                ]];
                $q = new WP_Query($args);
                $items = array_map(function($p){
                    return [
                        'id' => $p->ID,
                        'title' => get_the_title($p),
                        'excerpt' => wp_strip_all_tags(get_the_excerpt($p)),
                        'link' => get_permalink($p),
                        'thumb' => get_the_post_thumbnail_url($p, 'medium'),
                        'price' => get_post_meta($p->ID, '_cpl_price', true) ?: '0',
                    ];
                }, $q->posts);
                return new WP_REST_Response(['items' => $items], 200);
            },
            'permission_callback' => '__return_true'
        ]
    ]);

    register_rest_route($ns, '/coaches', [
        [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => function(WP_REST_Request $req) {
                $spec = sanitize_text_field($req->get_param('specialty') ?: '');
                $args = [
                    'post_type' => 'cpl_coach',
                    'post_status' => 'publish',
                    'posts_per_page' => 20
                ];
                if ($spec) $args['s'] = $spec;
                $q = new WP_Query($args);
                $items = array_map(function($p){
                    return [
                        'id' => $p->ID,
                        'title' => get_the_title($p),
                        'excerpt' => wp_strip_all_tags(get_the_excerpt($p)),
                        'link' => get_permalink($p),
                        'thumb' => get_the_post_thumbnail_url($p, 'medium'),
                    ];
                }, $q->posts);
                return new WP_REST_Response(['items' => $items], 200);
            },
            'permission_callback' => '__return_true'
        ]
    ]);

    register_rest_route($ns, '/sessions', [
        [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => function(WP_REST_Request $req) {
                if (!is_user_logged_in()) return new WP_Error('forbidden', __('Login required.', CoachPro_LMS::TD), ['status' => 401]);
                global $wpdb;
                $t = CoachPro_LMS::table_names();
                $student_id = get_current_user_id();
                $program_id = absint($req->get_param('program_id') ?: 0);
                if (!$program_id) return new WP_Error('bad_request', __('Program required.', CoachPro_LMS::TD), ['status' => 400]);

                $rows = $wpdb->get_results($wpdb->prepare("SELECT id, message, attachment_url, meta_json, created_at FROM {$t['sessions']} WHERE student_id=%d AND program_id=%d ORDER BY id ASC", $student_id, $program_id));
                $items = [];
                foreach ($rows as $r) {
                    $items[] = [
                        'id' => (int)$r->id,
                        'message' => wp_kses_post($r->message),
                        'attachment_url' => esc_url_raw($r->attachment_url),
                        'meta' => json_decode($r->meta_json ?: '{}', true),
                        'created_at' => $r->created_at,
                    ];
                }
                return new WP_REST_Response(['items' => $items], 200);
            },
            'permission_callback' => '__return_true'
        ]
    ]);

    register_rest_route($ns, '/analytics', [
        [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => function(WP_REST_Request $req) {
                $from = sanitize_text_field($req->get_param('from') ?: '');
                $to   = sanitize_text_field($req->get_param('to') ?: '');
                $program_id = absint($req->get_param('program_id') ?: 0);
                global $wpdb;
                $t = CoachPro_LMS::table_names();
                $where = '1=1';
                $args  = [];
                if ($program_id) { $where .= " AND program_id=%d"; $args[] = $program_id; }
                if ($from) { $where .= " AND snapshot_date >= %s"; $args[] = $from; }
                if ($to) { $where .= " AND snapshot_date <= %s"; $args[] = $to; }
                $sql = "SELECT snapshot_date, program_id, enrollments, completion_rate, avg_score FROM {$t['analytics']} WHERE {$where} ORDER BY snapshot_date ASC";
                $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
                return new WP_REST_Response(['items' => $rows], 200);
            },
            'permission_callback' => '__return_true'
        ]
    ]);
});

/** Part 5 — WooCommerce integration (optional auto-enroll) */
if (!defined('ABSPATH')) exit;

add_action('woocommerce_order_status_completed', function($order_id){
    if (!get_option('cpl_woo_enable', false)) return;
    if (!class_exists('WC_Order')) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_user_id();
    if (!$user_id) return;

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        // Map product -> program via meta _cpl_program_id
        $program_id = absint(get_post_meta($product_id, '_cpl_program_id', true));
        if ($program_id) {
            // Mimic AJAX enroll logic but server-side
            global $wpdb;
            $t = CoachPro_LMS::table_names();
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['enrollments']} WHERE student_id=%d AND program_id=%d", $user_id, $program_id));
            $now = current_time('mysql');
            if ($exists) {
                $wpdb->update($t['enrollments'], ['status'=>'enrolled','updated_at'=>$now], ['id'=>$exists], ['%s','%s'], ['%d']);
            } else {
                $wpdb->insert($t['enrollments'], [
                    'student_id'=>$user_id,'program_id'=>$program_id,'status'=>'enrolled','created_at'=>$now,'updated_at'=>$now
                ], ['%d','%d','%s','%s','%s']);
                $wpdb->insert($t['progress'], [
                    'student_id'=>$user_id,'program_id'=>$program_id,'lessons_total'=>0,'lessons_done'=>0,'avg_score'=>0,'last_active'=>$now,'created_at'=>$now,'updated_at'=>$now
                ], ['%d','%d','%d','%d','%f','%s','%s','%s']);
            }
        }
    }
}, 10, 1);

/** Part 6 — Helpers (SEO microdata, settings save via REST, sanitizers) */
if (!defined('ABSPATH')) exit;

/** Basic program microdata on single program pages */
add_action('wp_head', function(){
    if (!is_singular('cpl_program')) return;
    global $post;
    $price = get_post_meta($post->ID, '_cpl_price', true) ?: '0';
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => get_the_title($post),
        'description' => wp_strip_all_tags(get_the_excerpt($post)),
        'provider' => [
            '@type' => 'Organization',
            'name' => get_bloginfo('name')
        ],
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => get_option('cpl_currency', 'USD'),
            'price' => $price,
            'url' => get_permalink($post),
            'availability' => 'https://schema.org/InStock'
        ]
    ];
    echo '<script type="application/ld+json">'.wp_json_encode($data).'</script>';
});

/** REST endpoint to save settings securely (admin only) */
add_action('rest_api_init', function(){
    register_rest_route('coachpro/v1', '/settings', [
        'methods'  => WP_REST_Server::EDITABLE,
        'callback' => function(WP_REST_Request $req) {
            if (!current_user_can('manage_coachpro')) return new WP_Error('forbidden', __('Permission denied.', CoachPro_LMS::TD), ['status'=>403]);

            $currency = sanitize_text_field($req->get_param('currency'));
            $program_page = sanitize_text_field($req->get_param('program_page'));
            $woo_enable = (bool)$req->get_param('woo_enable');
            $rules_json = wp_unslash($req->get_param('rules_json'));
            // Validate JSON
            json_decode($rules_json);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('bad_request', __('Invalid rules JSON.', CoachPro_LMS::TD), ['status'=>400]);
            }

            update_option('cpl_currency', $currency ?: 'USD');
            update_option('cpl_program_page', $program_page);
            update_option('cpl_woo_enable', $woo_enable);
            update_option('cpl_rules_json', $rules_json);

            return new WP_REST_Response(['message' => __('Settings saved.', CoachPro_LMS::TD)], 200);
        },
        'permission_callback' => '__return_true'
    ]);
});

/** Simple sanitizers for arrays of ints/strings */
if (!function_exists('cpl_absint_array')) {
    function cpl_absint_array($arr): array {
        return array_map('absint', is_array($arr) ? $arr : []);
    }
}
if (!function_exists('cpl_sanitize_text_array')) {
    function cpl_sanitize_text_array($arr): array {
        return array_map('sanitize_text_field', is_array($arr) ? $arr : []);
    }
}



