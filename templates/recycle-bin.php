<div class="m365-lm-container">
    <?php
        $portal_urls = function_exists('kbbm_get_portal_urls') ? kbbm_get_portal_urls() : array();
        $main_url    = $portal_urls['main'] ?? 'https://kb.macomp.co.il/?page_id=14296';
        $recycle_url = $portal_urls['recycle'] ?? 'https://kb.macomp.co.il/?page_id=14291';
        $settings_url = $portal_urls['settings'] ?? 'https://kb.macomp.co.il/?page_id=14292';
        $logs_url    = $portal_urls['logs'] ?? 'https://kb.macomp.co.il/?page_id=14285';
        $active       = isset($active) ? $active : '';
    ?>
    <div class="m365-nav-links">
        <a href="<?php echo esc_url($main_url); ?>" class="<?php echo $active === 'main' ? 'active' : ''; ?>">ראשי</a>
        <a href="<?php echo esc_url($recycle_url); ?>" class="<?php echo $active === 'recycle' ? 'active' : ''; ?>">סל מחזור</a>
        <a href="<?php echo esc_url($settings_url); ?>" class="<?php echo $active === 'settings' ? 'active' : ''; ?>">הגדרות</a>
            <a href="<?php echo esc_url($logs_url); ?>" class="<?php echo $active === 'logs' ? 'active' : ''; ?>">לוגים</a>
    </div>
    <div class="m365-header">
        <h2>סל מחזור</h2>
        <div class="m365-actions">
            <button id="delete-all-permanent" class="m365-btn m365-btn-danger">
                מחק הכל לצמיתות
            </button>
        </div>
    </div>
    
    <div class="m365-table-wrapper">
        <table class="m365-table m365-table-vertical">
            <thead>
                <tr>
                    <th><div class="vertical-header"><span>מספר לקוח</span></div></th>
                    <th><div class="vertical-header"><span>שם לקוח</span></div></th>
                    <th><div class="vertical-header"><span>שם תוכנית</span></div></th>
                    <th><div class="vertical-header"><span>עלות</span></div></th>
                    <th><div class="vertical-header"><span>מחיר</span></div></th>
                    <th><div class="vertical-header"><span>כמות</span></div></th>
                    <th><div class="vertical-header"><span>נמחק בתאריך</span></div></th>
                    <th><div class="vertical-header"><span>פעולות</span></div></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deleted_licenses)): ?>
                    <tr>
                        <td colspan="8" class="no-data">סל המחזור ריק</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($deleted_licenses as $license): ?>
                        <tr data-id="<?php echo $license->id; ?>">
                            <td><?php echo esc_html($license->customer_number); ?></td>
                            <td><?php echo esc_html($license->customer_name); ?></td>
                            <td class="plan-name"><?php echo esc_html($license->plan_name); ?></td>
                            <td><?php echo number_format($license->cost_price, 2); ?></td>
                            <td><?php echo number_format($license->selling_price, 2); ?></td>
                            <td><?php echo $license->quantity; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($license->deleted_at)); ?></td>
                            <td class="actions">
                                <button class="m365-btn m365-btn-small m365-btn-success restore-license" data-id="<?php echo $license->id; ?>">
                                    שחזר
                                </button>
                                <button class="m365-btn m365-btn-small m365-btn-danger hard-delete-license" data-id="<?php echo $license->id; ?>">
                                    מחק לצמיתות
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
