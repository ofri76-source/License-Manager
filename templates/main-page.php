<?php
if (!defined('ABSPATH')) exit;

$portal_urls  = function_exists('kbbm_get_portal_urls') ? kbbm_get_portal_urls() : array();
$main_url     = $portal_urls['main'] ?? 'https://kb.macomp.co.il/?page_id=14296';
$recycle_url  = $portal_urls['recycle'] ?? 'https://kb.macomp.co.il/?page_id=14291';
$settings_url = $portal_urls['settings'] ?? 'https://kb.macomp.co.il/?page_id=14292';
$logs_url     = $portal_urls['logs'] ?? 'https://kb.macomp.co.il/?page_id=14285';
$alerts_url   = $portal_urls['alerts'] ?? 'https://kb.macomp.co.il/?page_id=14290';
$active       = isset($active) ? $active : '';

// Billing period input removed from header per user request; keep defaults for downstream use if present
$current_billing_period = isset($_GET['billing_period']) ? sanitize_text_field(wp_unslash($_GET['billing_period'])) : '';
$billing_period_label = $current_billing_period !== '' ? $current_billing_period : '';

$grouped_customers = array();
$types_by_sku      = array();

if (!empty($license_types)) {
    foreach ($license_types as $type) {
        if (!empty($type->sku)) {
            $types_by_sku[$type->sku] = $type;
        }
    }
}

if (!empty($licenses)) {
    foreach ($licenses as $license) {
        $cid = isset($license->customer_id) ? $license->customer_id : $license->customer_number;

        $sku_key = isset($license->sku_id) ? $license->sku_id : '';
        $type    = (!empty($sku_key) && isset($types_by_sku[$sku_key])) ? $types_by_sku[$sku_key] : null;

        if ($type && isset($type->show_in_main) && intval($type->show_in_main) === 0) {
            continue;
        }

        $display_plan_name = $license->plan_name;
        if ($type) {
            if (!empty($type->display_name)) {
                $display_plan_name = $type->display_name;
            } elseif (!empty($type->name)) {
                $display_plan_name = $type->name;
            }
        }

        $license->display_plan_name = $display_plan_name;

        if (!isset($grouped_customers[$cid])) {
            $grouped_customers[$cid] = array(
                'customer_number' => $license->customer_number ?? '',
                'customer_name'   => $license->customer_name ?? '',
                'tenant_domain'   => $license->tenant_domain ?? '',
                'tenant_domains'  => array(),
                'licenses'        => array(),
            );
        }

        $domain_key = isset($license->tenant_domain) && $license->tenant_domain !== '' ? $license->tenant_domain : __('לא צוין', 'm365-license-manager');
        if (!isset($grouped_customers[$cid]['tenant_domains'][$domain_key])) {
            $grouped_customers[$cid]['tenant_domains'][$domain_key] = array(
                'purchased' => 0,
                'charges'   => 0,
            );
        }

        $grouped_customers[$cid]['licenses'][] = $license;
    }
}
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
            <h2>ניהול רישיונות Microsoft 365</h2>
        </div>
        <div class="m365-actions">
            <form method="get" class="kbbm-period-form">
                <label for="kbbm-billing-period">מחזור חיוב</label>
                <input type="text" id="kbbm-billing-period" name="billing_period" value="<?php echo esc_attr($current_billing_period); ?>" placeholder="למשל: אפריל">
                <button type="submit" class="m365-btn m365-btn-secondary">עדכן</button>
            </form>
            <select id="customer-select">
                <option value="">בחר לקוח לסנכרון</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo esc_attr($customer->id); ?>">
                        <?php echo esc_html($customer->customer_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button id="sync-licenses" class="m365-btn m365-btn-primary">סנכרון רישיונות</button>
            <button id="sync-all-licenses" class="m365-btn m365-btn-secondary">סנכרון הכל</button>
        </div>
    </div>

    <div id="sync-message" class="m365-message" style="display:none;"></div>

    <div class="m365-table-wrapper">
        <table class="m365-table kbbm-report-table">
            <thead>
                <tr class="customer-header-row">
                    <th colspan="2">מספר לקוח</th>
                    <th colspan="2">שם לקוח</th>
                    <th colspan="2">Tenant Domain</th>
                    <th colspan="2">מחזור חיוב</th>
                    <th colspan="2">סה"כ חיובים</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($grouped_customers)): ?>
                <tr>
                    <td colspan="10" class="kbbm-no-data">אין נתונים להצגה. בצע סנכרון ראשוני.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($grouped_customers as $cid => $customer): ?>
                    <?php
                        $total_charges = 0;
                        $customer_notes = '';
                        foreach ($customer['licenses'] as $license) {
                            $total_purchased = ($license->quantity > 0) ? $license->quantity : $license->enabled_units;
                            $total_charges  += $total_purchased * $license->selling_price;
                            $domain_key = isset($license->tenant_domain) && $license->tenant_domain !== '' ? $license->tenant_domain : __('לא צוין', 'm365-license-manager');
                            if (!isset($customer['tenant_domains'][$domain_key])) {
                                $customer['tenant_domains'][$domain_key] = array('purchased' => 0, 'charges' => 0);
                            }
                            $customer['tenant_domains'][$domain_key]['purchased'] += $total_purchased;
                            $customer['tenant_domains'][$domain_key]['charges']   += $total_purchased * $license->selling_price;
                            if (empty($customer_notes) && !empty($license->notes)) {
                                $customer_notes = $license->notes;
                            }
                        }
                    ?>
                    <?php
                        $has_customer_number = !empty($customer['customer_number']);
                        $has_customer_name   = !empty($customer['customer_name']);
                        $has_tenant_domain   = !empty($customer['tenant_domains']);
                        $has_billing_period  = !empty($billing_period_label);
                        $has_total_charges   = $total_charges > 0;
                    ?>
                    <tr class="customer-summary" data-customer="<?php echo esc_attr($cid); ?>">
                        <td colspan="2" class="<?php echo $has_customer_number ? '' : 'kbbm-empty-summary'; ?>"><?php echo $has_customer_number ? esc_html($customer['customer_number']) : ''; ?></td>
                        <td colspan="2" class="<?php echo $has_customer_name ? '' : 'kbbm-empty-summary'; ?>"><?php echo $has_customer_name ? esc_html($customer['customer_name']) : ''; ?></td>
                        <td colspan="2" class="<?php echo $has_tenant_domain ? '' : 'kbbm-empty-summary'; ?>">
                            <?php if ($has_tenant_domain): ?>
                                <?php foreach ($customer['tenant_domains'] as $domain => $tenant_totals): ?>
                                    <div class="kbbm-tenant-summary">
                                        <strong><?php echo esc_html($domain); ?></strong>
                                        <span>(<?php echo esc_html($tenant_totals['purchased']); ?> רשיונות)</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td colspan="2" class="<?php echo $has_billing_period ? '' : 'kbbm-empty-summary'; ?>"><?php echo $has_billing_period ? esc_html($billing_period_label) : ''; ?></td>
                        <td colspan="2" class="<?php echo $has_total_charges ? '' : 'kbbm-empty-summary'; ?>"><?php echo $has_total_charges ? number_format($total_charges, 2) : ''; ?></td>
                    </tr>
                    <tr class="plans-header-row detail-row" data-customer="<?php echo esc_attr($cid); ?>" style="display:none;">
                        <th>תוכנית ללקוח</th>
                        <th>חשבון חיוב</th>
                        <th>מחיר ללקוח</th>
                        <th>מחיר רכישה</th>
                        <th>סה"כ נרכש</th>
                        <th>סה"כ בשימוש</th>
                        <th>סה"כ פנוי</th>
                        <th>ת. חיוב</th>
                        <th>חודשי/שנתי</th>
                        <th>פעולות</th>
                    </tr>
                    <?php foreach ($customer['licenses'] as $license): ?>
                        <?php
                            $total_purchased = ($license->quantity > 0) ? $license->quantity : $license->enabled_units;
                            $available = $total_purchased - $license->consumed_units;
                            $billing_display = $license->billing_cycle;
                            if (!empty($license->billing_frequency)) {
                                $billing_display .= ' / ' . $license->billing_frequency;
                            }
                            $plan_display = isset($license->display_plan_name) ? $license->display_plan_name : $license->plan_name;
                        ?>
                        <tr class="license-row detail-row" style="display:none;"
                            data-id="<?php echo esc_attr($license->id); ?>"
                            data-customer="<?php echo esc_attr($cid); ?>"
                            data-billing-cycle="<?php echo esc_attr($license->billing_cycle); ?>"
                            data-billing-frequency="<?php echo esc_attr($license->billing_frequency); ?>"
                            data-quantity="<?php echo esc_attr($license->quantity); ?>"
                            data-enabled="<?php echo esc_attr($license->enabled_units); ?>"
                            data-notes="<?php echo esc_attr($license->notes); ?>"
                        >
                            <td class="plan-name" data-field="plan_name"><?php echo esc_html($plan_display); ?></td>
                            <td data-field="billing_account"><?php echo esc_html($license->billing_account); ?></td>
                            <td class="editable-price" data-field="selling_price"><?php echo esc_html($license->selling_price); ?></td>
                            <td class="editable-price" data-field="cost_price"><?php echo esc_html($license->cost_price); ?></td>
                            <td data-field="total_purchased"><?php echo esc_html($total_purchased); ?></td>
                            <td data-field="consumed_units"><?php echo esc_html($license->consumed_units); ?></td>
                            <td data-field="available_units"><?php echo esc_html($available); ?></td>
                            <td data-field="renewal_date"><?php echo esc_html($license->renewal_date); ?></td>
                            <td data-field="billing_cycle"><?php echo esc_html($billing_display); ?></td>
                            <td class="actions">
                                <button type="button" class="m365-btn m365-btn-small m365-btn-secondary edit-license">ערוך</button>
                                <button type="button" class="m365-btn m365-btn-small m365-btn-danger delete-license" data-id="<?php echo esc_attr($license->id); ?>">מחק</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="kb-notes-row detail-row" data-customer="<?php echo esc_attr($cid); ?>" style="display:none;">
                        <td colspan="10" class="kb-notes-cell">
                            <strong>הערות:</strong>
                            <span class="kb-notes-value"><?php echo esc_html($customer_notes); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="edit-license-modal" class="m365-modal">
    <div class="m365-modal-content">
        <span class="m365-modal-close">&times;</span>
        <h3>עריכת רישיון</h3>
        <form id="edit-license-form">
            <input type="hidden" id="license-id" name="id">
            <input type="hidden" id="license-customer-id" name="customer_id">
            <div class="form-field">
                <label for="license-plan-name">תוכנית ללקוח</label>
                <input type="text" id="license-plan-name" name="plan_name" required>
            </div>
            <div class="form-field">
                <label for="license-billing-account">חשבון חיוב</label>
                <input type="text" id="license-billing-account" name="billing_account">
            </div>
            <div class="form-field">
                <label for="license-selling">מחיר ללקוח</label>
                <input type="number" step="0.01" id="license-selling" name="selling_price" required>
            </div>
            <div class="form-field">
                <label for="license-cost">מחיר לנו</label>
                <input type="number" step="0.01" id="license-cost" name="cost_price" required>
            </div>
            <div class="form-field">
                <label for="license-quantity">סה"כ נרכש</label>
                <input type="number" id="license-quantity" name="quantity" min="0">
            </div>
            <div class="form-field">
                <label for="license-billing-cycle">מחזור חיוב</label>
                <select id="license-billing-cycle" name="billing_cycle">
                    <option value="monthly">monthly</option>
                    <option value="yearly">yearly</option>
                </select>
            </div>
            <div class="form-field">
                <label for="license-billing-frequency">תדירות חיוב</label>
                <input type="text" id="license-billing-frequency" name="billing_frequency">
            </div>
            <div class="form-field">
                <label for="license-renewal-date">ת. חיוב</label>
                <input type="date" id="license-renewal-date" name="renewal_date">
            </div>
            <div class="form-field">
                <label for="license-notes">הערות</label>
                <textarea id="license-notes" name="notes" rows="3" style="width:100%;"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="m365-btn m365-btn-primary">שמור</button>
                <button type="button" class="m365-btn m365-btn-secondary m365-modal-cancel">ביטול</button>
            </div>
        </form>
    </div>
</div>
