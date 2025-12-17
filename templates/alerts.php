<?php
if (!defined('ABSPATH')) exit;

$filters = isset($filters) && is_array($filters) ? $filters : array();

$portal_urls  = function_exists('kbbm_get_portal_urls') ? kbbm_get_portal_urls() : array();
$main_url     = $portal_urls['main'] ?? 'https://kb.macomp.co.il/?page_id=14296';
$recycle_url  = $portal_urls['recycle'] ?? 'https://kb.macomp.co.il/?page_id=14291';
$settings_url = $portal_urls['settings'] ?? 'https://kb.macomp.co.il/?page_id=14292';
$logs_url     = $portal_urls['logs'] ?? 'https://kb.macomp.co.il/?page_id=14285';
$alerts_url   = $portal_urls['alerts'] ?? 'https://kb.macomp.co.il/?page_id=14290';
$active       = isset($active) ? $active : 'alerts';

$customer_query = isset($filters['customer_query']) ? $filters['customer_query'] : '';
$license_query  = isset($filters['license_query']) ? $filters['license_query'] : '';
$date_from      = isset($filters['date_from']) ? $filters['date_from'] : '';
$date_to        = isset($filters['date_to']) ? $filters['date_to'] : '';
?>

<div class="m365-lm-container">
    <div class="m365-nav-links">
        <a href="<?php echo esc_url($main_url); ?>" class="<?php echo $active === 'main' ? 'active' : ''; ?>">ראשי</a>
        <a href="<?php echo esc_url($recycle_url); ?>" class="<?php echo $active === 'recycle' ? 'active' : ''; ?>">סל מחזור</a>
        <a href="<?php echo esc_url($settings_url); ?>" class="<?php echo $active === 'settings' ? 'active' : ''; ?>">הגדרות</a>
        <a href="<?php echo esc_url($logs_url); ?>" class="<?php echo $active === 'logs' ? 'active' : ''; ?>">לוגים</a>
        <a href="<?php echo esc_url($alerts_url); ?>" class="<?php echo $active === 'alerts' ? 'active' : ''; ?>">התראות</a>
    </div>

    <div class="m365-header">
        <div class="m365-header-left">
            <h2>התראות ושינויים ברישיונות</h2>
            <p>צפה בשינויים שבוצעו ברישיונות לפי פילטרים של לקוח, טווח תאריכים וסוג רישיון.</p>
        </div>
        <div class="m365-actions">
            <a class="m365-btn m365-btn-primary" href="<?php echo esc_url($alerts_url); ?>" target="_blank" rel="noreferrer">מעבר למסך ההתראות</a>
        </div>
    </div>

    <div class="kbbm-log-toolbar">
        <label>
            לקוח (שם / מספר):
            <input type="text" id="alerts-filter-customer" value="<?php echo esc_attr($customer_query); ?>" placeholder="לדוגמה: 100043 או שם לקוח">
        </label>
        <label>
            סוג רישיון:
            <input type="text" id="alerts-filter-license" value="<?php echo esc_attr($license_query); ?>" placeholder="חפש לפי שם או SKU">
        </label>
        <label>
            מתאריך:
            <input type="date" id="alerts-filter-from" value="<?php echo esc_attr($date_from); ?>">
        </label>
        <label>
            עד תאריך:
            <input type="date" id="alerts-filter-to" value="<?php echo esc_attr($date_to); ?>">
        </label>
        <button type="button" class="m365-btn m365-btn-secondary" id="alerts-reset-filters">נקה מסננים</button>
    </div>

    <div class="m365-table-wrapper">
        <table class="m365-table kbbm-log-table" id="kbbm-alerts-table">
            <thead>
                <tr>
                    <th>זמן אירוע</th>
                    <th>שם לקוח</th>
                    <th>מספר לקוח</th>
                    <th>סוג רישיון</th>
                    <th>SKU</th>
                    <th>סוג פעולה</th>
                    <th>הודעה</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)) : ?>
                <tr><td colspan="7" class="no-data">אין אירועים להצגה</td></tr>
            <?php else : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr
                        data-customer-name="<?php echo esc_attr($log->customer_name ?? ''); ?>"
                        data-customer-number="<?php echo esc_attr($log->customer_number ?? ''); ?>"
                        data-license-name="<?php echo esc_attr($log->license_name ?? ''); ?>"
                        data-license-sku="<?php echo esc_attr($log->license_sku ?? ''); ?>"
                        data-event-time="<?php echo esc_attr($log->event_time); ?>"
                        data-context="<?php echo esc_attr($log->context); ?>"
                    >
                        <td><?php echo esc_html($log->event_time); ?></td>
                        <td><?php echo esc_html($log->customer_name ?? ''); ?></td>
                        <td><?php echo esc_html($log->customer_number ?? ''); ?></td>
                        <td><?php echo esc_html($log->license_name); ?></td>
                        <td><?php echo esc_html($log->license_sku); ?></td>
                        <td><?php echo esc_html($log->context); ?></td>
                        <td>
                            <div class="kbbm-log-message"><?php echo esc_html($log->message); ?></div>
                            <?php if (!empty($log->data)) : ?>
                                <pre class="kbbm-log-data"><?php echo esc_html($log->data); ?></pre>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
