<div class="m365-lm-container">
    <?php
        $portal_urls    = function_exists('kbbm_get_portal_urls') ? kbbm_get_portal_urls() : array();
        $main_url       = $portal_urls['main'] ?? 'https://kb.macomp.co.il/?page_id=14296';
        $recycle_url    = $portal_urls['recycle'] ?? 'https://kb.macomp.co.il/?page_id=14291';
        $settings_url   = $portal_urls['settings'] ?? 'https://kb.macomp.co.il/?page_id=14292';
        $logs_url       = $portal_urls['logs'] ?? 'https://kb.macomp.co.il/?page_id=14285';
        $alerts_url     = $portal_urls['alerts'] ?? 'https://kb.macomp.co.il/?page_id=14290';
        $active         = isset($active) ? $active : '';
        $license_types  = isset($license_types) ? $license_types : array();
        $log_retention_days = isset($log_retention_days) ? intval($log_retention_days) : 120;
        $use_test_server = (int) get_option('kbbm_use_test_server', 0);
    ?>
    <div class="m365-nav-links">
        <a href="<?php echo esc_url($main_url); ?>" class="<?php echo $active === 'main' ? 'active' : ''; ?>">ראשי</a>
        <a href="<?php echo esc_url($recycle_url); ?>" class="<?php echo $active === 'recycle' ? 'active' : ''; ?>">סל מחזור</a>
        <a href="<?php echo esc_url($settings_url); ?>" class="<?php echo $active === 'settings' ? 'active' : ''; ?>">הגדרות</a>
            <a href="<?php echo esc_url($logs_url); ?>" class="<?php echo $active === 'logs' ? 'active' : ''; ?>">לוגים</a>
            <a href="<?php echo esc_url($alerts_url); ?>" class="<?php echo $active === 'alerts' ? 'active' : ''; ?>">התראות</a>
    </div>
    <div class="m365-header">
        <h2>הגדרות</h2>
    </div>

    <div id="sync-message" class="m365-message" style="display:none;"></div>

    <div class="m365-settings-tabs">
        <button class="m365-tab-btn active" data-tab="customers">ניהול לקוחות</button>
        <button class="m365-tab-btn" data-tab="api-setup">הגדרת API</button>
        <button class="m365-tab-btn" data-tab="license-types">סוגי רישיונות</button>
        <button class="m365-tab-btn" data-tab="log-settings">הגדרות לוגים</button>
    </div>

    <!-- טאב לקוחות -->
    <div class="m365-tab-content active" id="customers-tab">
        <div class="m365-section">
            <h3>לקוחות רשומים</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button id="add-customer" class="m365-btn m365-btn-success">הוסף לקוח חדש</button>
                <button id="add-tenant-only" class="m365-btn m365-btn-secondary">הוסף טננט חדש</button>
            </div>

            <div id="customer-form-placeholder"></div>

            <div id="customer-form-wrapper" class="kbbm-customer-form" style="display:none;">
                <h3 id="customer-modal-title">הוסף לקוח חדש</h3>
                <form id="customer-form">
                        <input type="hidden" id="customer-id" name="id">

                        <div class="form-group customer-lookup">
                            <label>חיפוש לקוח קיים (מהתוסף המרכזי):</label>
                            <input type="text" id="customer-lookup" placeholder="התחל להקליד שם או מספר לקוח">
                            <div id="customer-lookup-results" class="customer-lookup-results"></div>
                            <small class="customer-lookup-hint">הקלד כל חלק מהמחרוזת ולחץ על התוצאה כדי למלא את הטופס.</small>
                        </div>

                        <div class="form-group">
                            <label>מספר לקוח:</label>
                            <input type="text" id="customer-number" name="customer_number">
                        </div>

                        <div class="form-group">
                            <label>שם לקוח:</label>
                            <input type="text" id="customer-name" name="customer_name">
                        </div>

                        <div class="form-group">
                            <label>Tenant ID:</label>
                            <input type="text" id="customer-tenant-id" name="tenant_id">
                        </div>

                        <div class="form-group">
                            <label>Client ID:</label>
                            <input type="text" id="customer-client-id" name="client_id">
                        </div>

                        <div class="form-group">
                            <label>Client Secret:</label>
                            <input type="password" id="customer-client-secret" name="client_secret">
                        </div>

                        <div class="form-group">
                            <label>Tenant Domain:</label>
                            <input type="text" id="customer-tenant-domain" name="tenant_domain" placeholder="example.onmicrosoft.com">
                        </div>

                        <div id="additional-tenants"></div>

                        <div class="form-group">
                            <button type="button" id="add-tenant-row" class="m365-btn m365-btn-small">
                                הוסף טננט נוסף
                            </button>
                        </div>

                        <input type="hidden" id="customer-tenants-json" name="tenants" value="[]">

                        <div class="form-group">
                            <label>הדבקת תוצאות סקריפט/חיבור:</label>
                            <textarea id="customer-paste-source" placeholder="הדבק כאן את ה-Tenant ID, Client ID, Client Secret ועוד..." rows="4"></textarea>
                            <button type="button" id="customer-paste-fill" class="m365-btn m365-btn-secondary" style="margin-top:8px;">מלא שדות מהטקסט</button>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="m365-btn m365-btn-primary">שמור</button>
                            <button type="button" class="m365-btn m365-modal-cancel">ביטול</button>
                        </div>
                    </form>
            </div>

            <div id="tenant-only-form" class="kbbm-customer-form" style="display:none; margin-top:20px;">
                <h3>הוסף טננט חדש ללקוח קיים</h3>
                <form id="tenant-only-form-inner">
                    <div class="form-group">
                        <label>בחר לקוח:</label>
                        <select id="tenant-only-customer-select" required>
                            <option value="">בחר לקוח</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo esc_attr($customer->id); ?>">
                                    <?php echo esc_html($customer->customer_number); ?> - <?php echo esc_html($customer->customer_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tenant ID:</label>
                        <input type="text" id="tenant-only-tenant-id" required>
                    </div>
                    <div class="form-group">
                        <label>Client ID:</label>
                        <input type="text" id="tenant-only-client-id">
                    </div>
                    <div class="form-group">
                        <label>Client Secret:</label>
                        <input type="password" id="tenant-only-client-secret">
                    </div>
                    <div class="form-group">
                        <label>Tenant Domain:</label>
                        <input type="text" id="tenant-only-tenant-domain" placeholder="example.onmicrosoft.com">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="m365-btn m365-btn-primary">הוסף טננט</button>
                        <button type="button" class="m365-btn m365-modal-cancel" onclick="jQuery('#tenant-only-form').hide();">ביטול</button>
                    </div>
                </form>
            </div>

            <table id="customers-table" class="m365-table" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>מספר לקוח</th>
                        <th>שם לקוח</th>
                        <th>Tenant ID</th>
                        <th>Client ID</th>
                        <th>סטטוס</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6" class="no-data">אין לקוחות רשומים</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <?php
                                    $tenant_id   = isset($customer->tenant_id) ? (string) $customer->tenant_id : '';
                                    $client_id   = isset($customer->client_id) ? (string) $customer->client_id : '';
                                    $status_raw  = isset($customer->last_connection_status) ? $customer->last_connection_status : 'unknown';
                                    $status_msg  = isset($customer->last_connection_message) ? $customer->last_connection_message : '';
                                    $status_time = isset($customer->last_connection_time) ? $customer->last_connection_time : '';

                                    $status_class = 'status-unknown';
                                    $status_label = 'לא נבדק';

                                    if ($status_raw === 'connected') {
                                        $status_class = 'status-connected';
                                        $status_label = 'מחובר';
                                    } elseif ($status_raw === 'failed') {
                                        $status_class = 'status-failed';
                                        $status_label = $status_msg ? 'נכשל: ' . $status_msg : 'נכשל';
                                    }
                                ?>
                                <td><?php echo esc_html($customer->customer_number); ?></td>
                                <td><?php echo esc_html($customer->customer_name); ?></td>
                                <td><?php echo esc_html(substr($tenant_id, 0, 20)) . (strlen($tenant_id) > 20 ? '...' : ''); ?></td>
                                <td><?php echo esc_html(substr($client_id, 0, 20)) . (strlen($client_id) > 20 ? '...' : ''); ?></td>
                                <td>
                                    <span id="connection-status-<?php echo esc_attr($customer->id); ?>" class="connection-status <?php echo esc_attr($status_class); ?>" title="<?php echo esc_attr($status_msg); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                    <?php if (!empty($status_time)): ?>
                                        <div class="status-time">עודכן: <?php echo esc_html($status_time); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="m365-btn m365-btn-small edit-customer kbbm-edit-customer" data-id="<?php echo $customer->id; ?>">
                                        ערוך
                                    </button>
                                    <button class="m365-btn m365-btn-small m365-btn-secondary kbbm-test-connection" data-id="<?php echo $customer->id; ?>">
                                        בדוק חיבור
                                    </button>
                                    <button class="m365-btn m365-btn-small m365-btn-danger delete-customer kbbm-delete-customer" data-id="<?php echo $customer->id; ?>">
                                        מחק
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- טאב הגדרת API -->
    <div class="m365-tab-content" id="api-setup-tab">
        <div class="m365-section">
            <h3>יצירת סקריפט להגדרת API</h3>
            <p>סקריפט זה יעזור לך להגדיר את ה-API בצד של Microsoft 365 עבור כל לקוח.</p>
            
            <div class="form-group">
                <label>בחר לקוח:</label>
                <select id="api-customer-select" data-download-base="<?php echo esc_url(admin_url('admin-post.php?action=kbbm_download_script&customer_id=')); ?>">
                    <option value="">בחר לקוח</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo esc_attr($customer->id); ?>">
                            <?php echo esc_html($customer->customer_number); ?> - <?php echo esc_html($customer->customer_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button id="generate-api-script" class="m365-btn m365-btn-primary">צור סקריפט</button>

            <div id="api-script-output" style="display:none; margin-top: 20px;">
                <h4>סקריפט PowerShell:</h4>
                <textarea id="api-script-text" readonly style="width: 100%; height: 400px; font-family: monospace; direction: ltr; text-align: left;"></textarea>
                <div class="form-actions" style="margin-top:10px;">
                    <button id="copy-api-script" class="m365-btn m365-btn-success" type="button">העתק ללוח</button>
                    <a id="download-api-script" class="m365-btn m365-btn-secondary" href="#" target="_blank" rel="noreferrer">הורד סקריפט</a>
                </div>
            </div>
            
            <div class="m365-info-box" style="margin-top: 20px;">
                <h4>הוראות שימוש:</h4>
                <ol>
                    <li>בחר לקוח מהרשימה</li>
                    <li>לחץ על "צור סקריפט"</li>
                    <li>העתק את הסקריפט והפעל אותו ב-PowerShell כמנהל</li>
                    <li>העתק את הפרטים שיוצגו (Tenant ID, Client ID, Client Secret)</li>
                    <li>עדכן את פרטי הלקוח בטאב "ניהול לקוחות"</li>
                    <li>אשר את ההרשאות ב-Azure Portal</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- טאב סוגי רישיונות -->
    <div class="m365-tab-content" id="license-types-tab">
        <div class="m365-section">
            <h3>סוגי רישיונות (מחירי ברירת מחדל)</h3>
            <p class="section-hint">הטבלה מציגה את שמות הרישיון מה-API, שם לתצוגה בטבלה הראשית, מחירי ברירת מחדל, ותיבה לבחירת הצגה בטבלה הראשית.</p>
            <div class="m365-table-wrapper">
                <table class="m365-table kbbm-license-types-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>שם רישיון (API)</th>
                            <th>שם לתצוגה</th>
                            <th class="col-cost">מחיר רכישה</th>
                            <th class="col-sell">מחיר ללקוח</th>
                            <th class="col-billing">חודשי/שנתי</th>
                            <th class="col-billing">תדירות</th>
                            <th class="col-show-main">בעמוד הראשי</th>
                            <th class="col-actions">פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($license_types)) : ?>
                            <?php foreach ($license_types as $type) : ?>
                                <tr
                                    data-sku="<?php echo esc_attr($type->sku); ?>"
                                    data-name="<?php echo esc_attr($type->name); ?>"
                                    data-display-name="<?php echo esc_attr($type->display_name ?? $type->name); ?>"
                                    data-cost-price="<?php echo esc_attr($type->cost_price); ?>"
                                    data-selling-price="<?php echo esc_attr($type->selling_price); ?>"
                                    data-billing-cycle="<?php echo esc_attr($type->billing_cycle ?? 'monthly'); ?>"
                                    data-billing-frequency="<?php echo esc_attr($type->billing_frequency ?? 1); ?>"
                                    data-show-in-main="<?php echo isset($type->show_in_main) ? esc_attr($type->show_in_main) : 1; ?>"
                                >
                                    <td><?php echo esc_html($type->sku); ?></td>
                                    <td><?php echo esc_html($type->name); ?></td>
                                    <td><?php echo esc_html($type->display_name ?? $type->name); ?></td>
                                    <td class="col-cost"><?php echo esc_html($type->cost_price); ?></td>
                                    <td class="col-sell"><?php echo esc_html($type->selling_price); ?></td>
                                    <td class="col-billing"><?php echo esc_html($type->billing_cycle ?? 'monthly'); ?></td>
                                    <td class="col-billing"><?php echo esc_html($type->billing_frequency ?? 1); ?></td>
                                    <td class="col-show-main"><input type="checkbox" disabled <?php echo (!isset($type->show_in_main) || intval($type->show_in_main) === 1) ? 'checked' : ''; ?>></td>
                                    <td class="col-actions"><button type="button" class="m365-btn m365-btn-small m365-btn-secondary license-type-edit">ערוך</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="9" class="no-data">אין סוגי רישיונות מוגדרים</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- טאב הגדרות לוגים -->
    <div class="m365-tab-content" id="log-settings-tab">
        <div class="m365-section">
            <h3>הגדרות לוגים</h3>
            <form id="kbbm-log-settings-form">
                <label style="display:block;margin-top:12px;">
                    <input type="checkbox" id="kbbm-use-test-server" name="use_test_server" <?php checked($use_test_server, 1); ?> />
                    שרת טסט
                </label>
                <small>כאשר האפשרות מסומנת, קישורי הניווט יעברו לשרת הבדיקות (kbtest.macomp.co.il).</small>
                <div class="form-group">
                    <label>מספר ימים לשמירת לוגים לפני מחיקה:</label>
                    <input type="number" id="kbbm-log-retention-days" name="log_retention_days" min="1" value="<?php echo esc_attr($log_retention_days); ?>" placeholder="120">
                    <small>ברירת המחדל: 120 ימים.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="m365-btn m365-btn-primary">שמור הגדרות</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="license-type-modal" class="m365-modal">
    <div class="m365-modal-content">
        <span class="m365-modal-close">&times;</span>
        <h3>עריכת סוג רישיון</h3>
        <form id="kbbm-license-type-form">
            <div class="form-group">
                <label>SKU</label>
                <input type="text" id="license-type-sku" name="sku" readonly>
            </div>
            <div class="form-group">
                <label>שם רישיון (API)</label>
                <input type="text" id="license-type-name" name="name" required>
            </div>
            <div class="form-group">
                <label>שם לתצוגה</label>
                <input type="text" id="license-type-display-name" name="display_name">
            </div>
            <div class="form-group">
                <label>מחיר רכישה</label>
                <input type="number" step="0.01" id="license-type-cost" name="cost_price">
            </div>
            <div class="form-group">
                <label>מחיר ללקוח</label>
                <input type="number" step="0.01" id="license-type-selling" name="selling_price">
            </div>
            <div class="form-group">
                <label>חודשי/שנתי</label>
                <select id="license-type-cycle" name="billing_cycle">
                    <option value="monthly">monthly</option>
                    <option value="yearly">yearly</option>
                </select>
            </div>
            <div class="form-group">
                <label>תדירות</label>
                <input type="number" id="license-type-frequency" name="billing_frequency" min="1" value="1">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="license-type-show" name="show_in_main" checked>
                    להציג בטבלה הראשית
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="m365-btn m365-btn-primary">שמור</button>
                <button type="button" class="m365-btn m365-btn-secondary m365-modal-cancel">ביטול</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal לתצוגת סקריפט -->
<div id="kbbm-script-modal" class="kbbm-modal-overlay" style="display:none;">
    <div class="m365-modal-content kbbm-modal">
        <span class="m365-modal-close">&times;</span>
        <h3>סקריפט PowerShell מותאם</h3>

        <div class="kbbm-script-meta">
            <div class="meta-box">
                <span class="meta-label">Tenant ID</span>
                <span id="kbbm-tenant-id"></span>
            </div>
            <div class="meta-box">
                <span class="meta-label">Client ID</span>
                <span id="kbbm-client-id"></span>
            </div>
            <div class="meta-box">
                <span class="meta-label">Client Secret</span>
                <span id="kbbm-client-secret"></span>
            </div>
            <div class="meta-box">
                <span class="meta-label">Tenant Domain</span>
                <span id="kbbm-tenant-domain"></span>
            </div>
        </div>

        <textarea id="kbbm-script-preview" readonly style="width:100%; height:300px; font-family: monospace; direction:ltr; text-align:left;"></textarea>
        <div class="form-actions" style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
            <button id="kbbm-copy-script" class="m365-btn m365-btn-secondary" type="button">Copy Script</button>
            <a id="kbbm-download-script" class="m365-btn m365-btn-primary" href="#" target="_blank" rel="noreferrer">Download Script</a>
        </div>
    </div>
</div>
