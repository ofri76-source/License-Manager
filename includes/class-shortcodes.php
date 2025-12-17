<?php
if (!defined('ABSPATH')) exit;

class M365_LM_Shortcodes {
    
    public function __construct() {
        add_shortcode('m365_main_page', array($this, 'main_page'));
        add_shortcode('m365_recycle_bin', array($this, 'recycle_bin'));
        add_shortcode('m365_settings', array($this, 'settings_page'));
        add_shortcode('kb_billing_log', array($this, 'log_page'));
        add_shortcode('kbbm_alerts', array($this, 'alerts_page'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_m365_sync_licenses', array($this, 'ajax_sync_licenses'));
        add_action('wp_ajax_m365_sync_all_licenses', array($this, 'ajax_sync_all_licenses'));
        add_action('wp_ajax_m365_delete_license', array($this, 'ajax_delete_license'));
        add_action('wp_ajax_m365_restore_license', array($this, 'ajax_restore_license'));
        add_action('wp_ajax_m365_hard_delete', array($this, 'ajax_hard_delete'));
        add_action('wp_ajax_m365_save_license', array($this, 'ajax_save_license'));
        add_action('wp_ajax_m365_save_license_type', array($this, 'ajax_save_license_type'));
        add_action('wp_ajax_kbbm_test_connection', array($this, 'ajax_test_connection'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('m365-lm-style', M365_LM_PLUGIN_URL . 'assets/style.css', array(), M365_LM_VERSION);
        wp_enqueue_script('m365-lm-script', M365_LM_PLUGIN_URL . 'assets/script.js', array('jquery'), M365_LM_VERSION, true);
        wp_localize_script('m365-lm-script', 'm365Ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('m365_nonce'),
            'dcCustomers' => M365_LM_Database::get_dc_customers(),
        ));
    }
    
    // דף ראשי
    public function main_page($atts) {
        ob_start();
        $active = 'main';
        $licenses = M365_LM_Database::get_licenses();
        $customers = M365_LM_Database::get_customers();
        $license_types = M365_LM_Database::get_combined_license_types();
        include M365_LM_PLUGIN_DIR . 'templates/main-page.php';
        return ob_get_clean();
    }
    
    // סל מחזור
    public function recycle_bin($atts) {
        ob_start();
        $active = 'recycle';
        $deleted_licenses = M365_LM_Database::get_licenses(true);
        $deleted_licenses = array_filter($deleted_licenses, function($license) {
            return $license->is_deleted == 1;
        });
        include M365_LM_PLUGIN_DIR . 'templates/recycle-bin.php';
        return ob_get_clean();
    }
    
    // הגדרות
    public function settings_page($atts) {
        ob_start();
        $active = 'settings';
        $customers = M365_LM_Database::get_customers();
        $license_types = M365_LM_Database::get_combined_license_types();
        include M365_LM_PLUGIN_DIR . 'templates/settings.php';
        return ob_get_clean();
    }
    
    // AJAX - סנכרון רישיונות
    public function ajax_sync_licenses() {
        check_ajax_referer('m365_nonce', 'nonce');

        $customer_id = intval($_POST['customer_id']);
        $result = $this->sync_customer_licenses($customer_id);

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message'], 'count' => $result['count']));
        }

        wp_send_json_error(array('message' => $result['message']));
    }

    // AJAX - סנכרון לכל הלקוחות
    public function ajax_sync_all_licenses() {
        check_ajax_referer('m365_nonce', 'nonce');

        $customers = M365_LM_Database::get_customers();
        $summary   = array('synced' => 0, 'errors' => array());

        foreach ($customers as $customer) {
            $result = $this->sync_customer_licenses(intval($customer->id));
            if ($result['success']) {
                $summary['synced']++;
            } else {
                $summary['errors'][] = array(
                    'customer_id' => $customer->id,
                    'message'     => $result['message'],
                );
            }
        }

        if (!empty($summary['errors'])) {
            wp_send_json_error(array(
                'message' => 'סנכרון הושלם עם שגיאות עבור חלק מהלקוחות',
                'detail'  => $summary,
            ));
        }

        wp_send_json_success(array(
            'message' => 'סנכרון הושלם בהצלחה לכל הלקוחות',
            'count'   => $summary['synced'],
        ));
    }

    // AJAX - שמירת סוג רישיון
    public function ajax_save_license_type() {
        check_ajax_referer('m365_nonce', 'nonce');

        $sku              = sanitize_text_field($_POST['sku'] ?? '');
        $api_name         = sanitize_text_field($_POST['name'] ?? '');
        $display_name     = sanitize_text_field($_POST['display_name'] ?? $api_name);
        $cost_price       = floatval($_POST['cost_price'] ?? 0);
        $selling_price    = floatval($_POST['selling_price'] ?? 0);
        $billing_cycle    = sanitize_text_field($_POST['billing_cycle'] ?? 'monthly');
        $billing_frequency= intval($_POST['billing_frequency'] ?? 1);
        $show_in_main     = isset($_POST['show_in_main']) ? intval($_POST['show_in_main']) : 1;

        if (empty($sku) || empty($api_name)) {
            wp_send_json_error(array('message' => 'SKU ו-שם רישיון נדרשים')); 
        }

        M365_LM_Database::save_license_type(array(
            'sku'              => $sku,
            'name'             => $api_name,
            'display_name'     => $display_name,
            'cost_price'       => $cost_price,
            'selling_price'    => $selling_price,
            'billing_cycle'    => $billing_cycle,
            'billing_frequency'=> $billing_frequency,
            'show_in_main'     => $show_in_main,
        ));

        wp_send_json_success(array('message' => 'סוג הרישיון נשמר'));
    }

    private function sync_customer_licenses($customer_id) {
        $customer = M365_LM_Database::get_customer($customer_id);

        if (!$customer) {
            M365_LM_Database::log_event('error', 'sync_licenses', 'לקוח לא נמצא', $customer_id);
            return array('success' => false, 'message' => 'לקוח לא נמצא', 'count' => 0);
        }

        $tenants = M365_LM_Database::get_customer_tenants($customer_id);
        if (empty($tenants)) {
            $tenants = array((object) array(
                'tenant_id'     => $customer->tenant_id,
                'client_id'     => $customer->client_id,
                'client_secret' => $customer->client_secret,
                'tenant_domain' => $customer->tenant_domain,
            ));
        }

        $licenses_saved = 0;
        foreach ($tenants as $tenant) {
            if (empty($tenant->tenant_id) || empty($tenant->client_id) || empty($tenant->client_secret)) {
                M365_LM_Database::log_event('error', 'sync_licenses', 'חסרים פרטי Tenant/Client להגדרת חיבור', $customer_id, $tenant);
                continue;
            }

            $api = new M365_LM_API_Connector(
                $tenant->tenant_id,
                $tenant->client_id,
                $tenant->client_secret
            );

            $skus = $api->get_subscribed_skus();
            if (empty($skus['success'])) {
                $message = $skus['message'] ?? 'Graph error';
                M365_LM_Database::update_connection_status($customer_id, 'failed', $message);
                M365_LM_Database::log_event('error', 'sync_licenses', $message, $customer_id, $skus);
                continue;
            }

            foreach ($skus['skus'] as $sku) {
                $data = array(
                    'customer_id'      => $customer_id,
                    'sku_id'           => $sku['sku_id'],
                    'plan_name'        => $sku['plan_name'],
                    'quantity'         => $sku['enabled_units'],
                    'enabled_units'    => $sku['enabled_units'],
                    'consumed_units'   => $sku['consumed_units'],
                    'billing_cycle'    => 'monthly',
                    'billing_frequency'=> '1',
                    'cost_price'       => 0,
                    'selling_price'    => 0,
                    'status_text'      => $sku['status'] ?? '',
                    'tenant_domain'    => isset($tenant->tenant_domain) ? $tenant->tenant_domain : '',
                );

                M365_LM_Database::upsert_license_by_sku($customer_id, $sku['sku_id'], $data, $data['tenant_domain']);
                $licenses_saved++;
            }
        }

        M365_LM_Database::update_connection_status($customer_id, 'connected', 'Last sync successful');
        M365_LM_Database::log_event('info', 'sync_licenses', 'סנכרון רישוי הושלם בהצלחה', $customer_id, array('licenses_saved' => $licenses_saved));

        return array('success' => true, 'message' => 'סנכרון הושלם בהצלחה', 'count' => $licenses_saved);
    }

    /**
     * בדיקת חיבור לגרף עבור לקוח
     */
    
    public function ajax_test_connection() {
        check_ajax_referer('m365_nonce', 'nonce');

        $customer_id = intval($_POST['id']);
        $customer    = M365_LM_Database::get_customer($customer_id);

        if (!$customer) {
            M365_LM_Database::log_event('error', 'test_connection', 'לקוח לא נמצא', $customer_id);
            wp_send_json_error(array('message' => 'לקוח לא נמצא'));
        }

        if (empty($customer->tenant_id) || empty($customer->client_id) || empty($customer->client_secret)) {
            M365_LM_Database::log_event('error', 'test_connection', 'חסרים פרטי Tenant/Client להגדרת חיבור', $customer_id);
            wp_send_json_error(array('message' => 'חסרים פרטי Tenant/Client להגדרת חיבור'));
        }

        $api = new M365_LM_API_Connector(
            $customer->tenant_id,
            $customer->client_id,
            $customer->client_secret
        );

        $result = $api->test_connection();

        if (!empty($result['success'])) {
            $message = $result['message'] ?? 'Connected';
            M365_LM_Database::update_connection_status($customer_id, 'connected', $message);
            M365_LM_Database::log_event('info', 'test_connection', $message, $customer_id, $result);

            wp_send_json_success(array(
                'status'  => 'connected',
                'message' => $message,
                'time'    => current_time('mysql'),
            ));
        }

        $message = $result['message'] ?? 'Connection failed';
        M365_LM_Database::update_connection_status($customer_id, 'failed', $message);
        M365_LM_Database::log_event('error', 'test_connection', $message, $customer_id, $result);

        wp_send_json_error(array(
            'status'  => 'failed',
            'message' => $message,
            'time'    => current_time('mysql'),
        ));
    }


    // AJAX - מחיקה רכה
    public function ajax_delete_license() {
        check_ajax_referer('m365_nonce', 'nonce');
        $id = intval($_POST['id']);
        M365_LM_Database::soft_delete_license($id);
        wp_send_json_success();
    }
    
    // AJAX - שחזור
    public function ajax_restore_license() {
        check_ajax_referer('m365_nonce', 'nonce');
        $id = intval($_POST['id']);
        M365_LM_Database::restore_license($id);
        wp_send_json_success();
    }
    
    // AJAX - מחיקה קשה
    public function ajax_hard_delete() {
        check_ajax_referer('m365_nonce', 'nonce');
        $id = intval($_POST['id']);
        
        if ($id === 0) {
            M365_LM_Database::hard_delete_all_deleted();
        } else {
            M365_LM_Database::hard_delete_license($id);
        }
        
        wp_send_json_success();
    }
    
    // AJAX - שמירת רישיון
    public function ajax_save_license() {
        check_ajax_referer('m365_nonce', 'nonce');
        
        $data = array(
            'id' => intval($_POST['id']),
            'customer_id' => intval($_POST['customer_id']),
            'plan_name' => sanitize_text_field($_POST['plan_name']),
            'billing_account' => isset($_POST['billing_account']) ? sanitize_text_field($_POST['billing_account']) : '',
            'cost_price' => floatval($_POST['cost_price']),
            'selling_price' => floatval($_POST['selling_price']),
            'quantity' => intval($_POST['quantity']),
            'billing_cycle' => sanitize_text_field($_POST['billing_cycle']),
            'billing_frequency' => sanitize_text_field($_POST['billing_frequency']),
            'renewal_date' => !empty($_POST['renewal_date']) ? sanitize_text_field($_POST['renewal_date']) : null,
            'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
        );
        
        M365_LM_Database::save_license($data);
        wp_send_json_success();
    }
    public function log_page($atts) {
        $atts = shortcode_atts(array(
            'limit'       => 200,
            'customer_id' => 0,
            'level'       => '',
            'context'     => '',
        ), $atts, 'kb_billing_log');

        $args = array(
            'limit'       => intval($atts['limit']),
            'customer_id' => intval($atts['customer_id']) ?: null,
            'level'       => $atts['level'],
            'context'     => $atts['context'],
        );

        $logs      = M365_LM_Database::get_logs($args);
        $customers = M365_LM_Database::get_customers();
        $customers_by_id = array();
        $levels = array();
        $contexts = array();
        $tenant_domains = array();

        if (!empty($customers)) {
            foreach ($customers as $c) {
                $customers_by_id[$c->id] = $c;
            }
        }

        if (!empty($logs)) {
            foreach ($logs as $log_item) {
                if (!empty($log_item->level)) {
                    $levels[] = $log_item->level;
                }
                if (!empty($log_item->context)) {
                    $contexts[] = $log_item->context;
                }

                if (!empty($log_item->customer_id) && isset($customers_by_id[$log_item->customer_id])) {
                    $tenant_domains[] = $customers_by_id[$log_item->customer_id]->tenant_domain ?? '';
                }
            }

            $levels = array_unique(array_filter($levels));
            $contexts = array_unique(array_filter($contexts));
            $tenant_domains = array_unique(array_filter($tenant_domains));
        }

        ob_start();
        ?>
        <div class="m365-lm-container">
            <?php
                $portal_urls  = function_exists('kbbm_get_portal_urls') ? kbbm_get_portal_urls() : array();
                $main_url     = $portal_urls['main'] ?? 'https://kb.macomp.co.il/?page_id=14296';
                $recycle_url  = $portal_urls['recycle'] ?? 'https://kb.macomp.co.il/?page_id=14291';
                $settings_url = $portal_urls['settings'] ?? 'https://kb.macomp.co.il/?page_id=14292';
                $logs_url     = $portal_urls['logs'] ?? 'https://kb.macomp.co.il/?page_id=14285';
                $alerts_url   = $portal_urls['alerts'] ?? 'https://kb.macomp.co.il/?page_id=14290';
                $active       = 'logs';
            ?>
            <div class="m365-nav-links">
                <a href="<?php echo esc_url($main_url); ?>" class="<?php echo $active === 'main' ? 'active' : ''; ?>">ראשי</a>
                <a href="<?php echo esc_url($recycle_url); ?>" class="<?php echo $active === 'recycle' ? 'active' : ''; ?>">סל מחזור</a>
                <a href="<?php echo esc_url($settings_url); ?>" class="<?php echo $active === 'settings' ? 'active' : ''; ?>">הגדרות</a>
                <a href="<?php echo esc_url($logs_url); ?>" class="<?php echo $active === 'logs' ? 'active' : ''; ?>">לוגים</a>
                <a href="<?php echo esc_url($alerts_url); ?>" class="<?php echo $active === 'alerts' ? 'active' : ''; ?>">התראות</a>
            </div>
            <h2>KB Billing Manager – לוגים</h2>
            <?php if (empty($logs)): ?>
                <p>אין אירועים בלוג.</p>
            <?php else: ?>
                <div class="kbbm-log-toolbar">
                    <div class="kbbm-log-search">
                        <label for="kbbm-log-search-input">חיפוש חופשי:</label>
                        <input id="kbbm-log-search-input" type="text" placeholder="חיפוש בכל השדות">
                    </div>
                    <div class="kbbm-log-filters">
                        <label>
                            Level
                            <select class="kbbm-log-filter" data-field="level">
                                <option value="">הכל</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo esc_attr($level); ?>"><?php echo esc_html($level); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Context
                            <select class="kbbm-log-filter" data-field="context">
                                <option value="">הכל</option>
                                <?php foreach ($contexts as $context): ?>
                                    <option value="<?php echo esc_attr($context); ?>"><?php echo esc_html($context); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            לקוח
                            <select class="kbbm-log-filter" data-field="customer">
                                <option value="">הכל</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo esc_attr($customer->id); ?>"><?php echo esc_html($customer->customer_number . ' - ' . $customer->customer_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Tenant Domain
                            <select class="kbbm-log-filter" data-field="tenant_domain">
                                <option value="">הכל</option>
                                <?php foreach ($tenant_domains as $domain): ?>
                                    <option value="<?php echo esc_attr($domain); ?>"><?php echo esc_html($domain); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="m365-table-wrapper">
                    <table class="m365-table kbbm-log-table kb-logs-table">
                        <thead>
                            <tr>
                                <th class="sortable col-time has-filter" data-column="time">
                                    <div class="kbbm-log-header-title">
                                        <span class="title">זמן</span>
                                        <button type="button" class="kbbm-log-filter-toggle" aria-label="סינון זמן">▼</button>
                                    </div>
                                </th>
                                <th class="sortable col-level has-filter" data-column="level">
                                    <div class="kbbm-log-header-title">
                                        <span class="title">Level</span>
                                        <button type="button" class="kbbm-log-filter-toggle" aria-label="סינון Level">▼</button>
                                    </div>
                                </th>
                                <th class="sortable col-context has-filter" data-column="context">
                                    <div class="kbbm-log-header-title">
                                        <span class="title">Context</span>
                                        <button type="button" class="kbbm-log-filter-toggle" aria-label="סינון Context">▼</button>
                                    </div>
                                </th>
                                <th class="sortable col-customer-number has-filter" data-column="customer_number">
                                    <div class="kbbm-log-header-title">
                                        <span class="title">מספר לקוח</span>
                                        <button type="button" class="kbbm-log-filter-toggle" aria-label="סינון מספר לקוח">▼</button>
                                    </div>
                                </th>
                                <th class="sortable col-customer-name has-filter" data-column="customer_name">
                                    <div class="kbbm-log-header-title">
                                        <span class="title">שם לקוח</span>
                                        <button type="button" class="kbbm-log-filter-toggle" aria-label="סינון שם לקוח">▼</button>
                                    </div>
                                </th>
                                <th class="sortable col-tenant-domain has-filter" data-column="tenant_domain">
                                    <div class="kbbm-log-header-title">
                                        <span class="title">Tenant Domain</span>
                                        <button type="button" class="kbbm-log-filter-toggle" aria-label="סינון Tenant Domain">▼</button>
                                    </div>
                                </th>
                                <th class="sortable col-message has-filter" data-column="message">
                                    <div class="kbbm-log-header-title">
                                        <span class="title">הודעה</span>
                                        <button type="button" class="kbbm-log-filter-toggle" aria-label="סינון הודעה">▼</button>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs as $log):
                            $customer_label = '';
                            $customer_number = '';
                            $customer_name = '';
                            $tenant_domain = '';
                            if (!empty($log->customer_id) && isset($customers_by_id[$log->customer_id])) {
                                $c = $customers_by_id[$log->customer_id];
                                $customer_number = $c->customer_number;
                                $customer_name   = $c->customer_name;
                                $tenant_domain   = isset($c->tenant_domain) ? $c->tenant_domain : '';
                                $customer_label = esc_html($customer_number . ' - ' . $customer_name);
                            }
                            $row_class = '';
                            if ($log->level === 'error') {
                                $row_class = 'log-level-error';
                            } elseif ($log->level === 'warning') {
                                $row_class = 'log-level-warning';
                            } elseif ($log->level === 'info') {
                                $row_class = 'log-level-info';
                            }
                            ?>
                            <tr class="<?php echo esc_attr($row_class); ?>"
                                data-level="<?php echo esc_attr($log->level); ?>"
                                data-context="<?php echo esc_attr($log->context); ?>"
                                data-customer="<?php echo esc_attr($log->customer_id); ?>"
                                data-tenant_domain="<?php echo esc_attr($tenant_domain); ?>"
                            >
                                <td class="col-time" data-sort-value="<?php echo esc_attr($log->event_time); ?>"><?php echo esc_html($log->event_time); ?></td>
                                <td class="col-level" data-sort-value="<?php echo esc_attr($log->level); ?>"><?php echo esc_html($log->level); ?></td>
                                <td class="col-context" data-sort-value="<?php echo esc_attr($log->context); ?>"><?php echo esc_html($log->context); ?></td>
                                <td class="col-customer-number" data-sort-value="<?php echo esc_attr($customer_number); ?>"><?php echo esc_html($customer_number); ?></td>
                                <td class="col-customer-name" data-sort-value="<?php echo esc_attr($customer_name); ?>"><?php echo esc_html($customer_name); ?></td>
                                <td class="col-tenant-domain" data-sort-value="<?php echo esc_attr($tenant_domain); ?>"><?php echo esc_html($tenant_domain); ?></td>
                                <td class="col-message" data-sort-value="<?php echo esc_attr($log->message); ?>">
                                    <div class="kbbm-log-message"><?php echo esc_html($log->message); ?></div>
                                    <?php if (!empty($log->data)): ?>
                                        <pre class="kbbm-log-data"><?php echo esc_html($log->data); ?></pre>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function alerts_page($atts) {
        $filters = array(
            'customer_query' => isset($_GET['customer_query']) ? sanitize_text_field(wp_unslash($_GET['customer_query'])) : '',
            'license_query'  => isset($_GET['license_query']) ? sanitize_text_field(wp_unslash($_GET['license_query'])) : '',
            'date_from'      => isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '',
            'date_to'        => isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '',
            'limit'          => 500,
        );

        $logs   = M365_LM_Database::get_change_logs($filters);
        $active = 'alerts';

        ob_start();
        include M365_LM_PLUGIN_DIR . 'templates/alerts.php';
        return ob_get_clean();
    }
}
