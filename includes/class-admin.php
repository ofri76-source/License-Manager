<?php
if (!defined('ABSPATH')) exit;

class M365_LM_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_m365_save_customer', array($this, 'ajax_save_customer'));
        add_action('wp_ajax_m365_delete_customer', array($this, 'ajax_delete_customer'));
        add_action('wp_ajax_m365_get_customer', array($this, 'ajax_get_customer'));
        add_action('wp_ajax_kbbm_delete_customer', array($this, 'ajax_delete_customer'));
        add_action('wp_ajax_kbbm_get_customer', array($this, 'ajax_get_customer'));
        add_action('wp_ajax_kbbm_generate_script', array($this, 'ajax_generate_script'));
        add_action('wp_ajax_nopriv_kbbm_generate_script', array($this, 'ajax_generate_script'));
        add_action('wp_ajax_kbbm_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_kbbm_add_tenant', array($this, 'ajax_add_tenant'));
    }
    
    // הוספת תפריט ניהול
    public function add_admin_menu() {
        add_menu_page(
            'M365 License Manager',
            'M365 Licenses',
            'manage_options',
            'm365-license-manager',
            array($this, 'admin_page'),
            'dashicons-cloud',
            30
        );
        
        add_submenu_page(
            'm365-license-manager',
            'לקוחות',
            'לקוחות',
            'manage_options',
            'm365-customers',
            array($this, 'customers_page')
        );
        
        add_submenu_page(
            'm365-license-manager',
            'סל מחזור',
            'סל מחזור',
            'manage_options',
            'm365-recycle-bin',
            array($this, 'recycle_page')
        );
        
        add_submenu_page(
            'm365-license-manager',
            'הגדרות API',
            'הגדרות API',
            'manage_options',
            'm365-api-settings',
            array($this, 'api_settings_page')
        );
    }
    
    // טעינת סקריפטים לאדמין
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'm365') === false) {
            return;
        }
        
        wp_enqueue_style('m365-lm-admin-style', M365_LM_PLUGIN_URL . 'assets/style.css', array(), M365_LM_VERSION);
        wp_enqueue_script('m365-lm-admin-script', M365_LM_PLUGIN_URL . 'assets/script.js', array('jquery'), M365_LM_VERSION, true);
        wp_localize_script('m365-lm-admin-script', 'm365Ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('m365_nonce'),
            'dcCustomers' => M365_LM_Database::get_dc_customers(),
        ));
    }
    
    // עמוד ניהול ראשי
    public function admin_page() {
        $licenses = M365_LM_Database::get_licenses();
        $customers = M365_LM_Database::get_customers();
        $active = 'main';
        ?>
        <div class="wrap kbbm-wrap">
            <h1>ניהול רישיונות Microsoft 365</h1>
            <?php include M365_LM_PLUGIN_DIR . 'templates/main-page.php'; ?>
        </div>
        <?php
    }
    
    // עמוד לקוחות
    public function customers_page() {
        $customers = M365_LM_Database::get_customers();
        $license_types = M365_LM_Database::get_license_types();
        $active = 'settings';
        $log_retention_days = M365_LM_Database::get_log_retention_days();
        ?>
        <div class="wrap kbbm-wrap">
            <h1>ניהול לקוחות</h1>
            <?php include M365_LM_PLUGIN_DIR . 'templates/settings.php'; ?>
        </div>
        <?php
    }
    
    // עמוד סל מחזור
    public function recycle_page() {
        $deleted_licenses = M365_LM_Database::get_licenses(true);
        $deleted_licenses = array_filter($deleted_licenses, function($license) {
            return $license->is_deleted == 1;
        });
        $active = 'recycle';
        ?>
        <div class="wrap kbbm-wrap">
            <h1>סל מחזור</h1>
            <?php include M365_LM_PLUGIN_DIR . 'templates/recycle-bin.php'; ?>
        </div>
        <?php
    }
    
    // עמוד הגדרות API
    public function api_settings_page() {
        $customers = M365_LM_Database::get_customers();
        ?>
        <div class="wrap kbbm-wrap">
            <h1>הגדרות API</h1>
            <div class="m365-lm-container">
                <div class="m365-section">
                    <h3>יצירת סקריפט להגדרת API</h3>
                    <p>סקריפט זה יעזור לך להגדיר את ה-API בצד של Microsoft 365 עבור כל לקוח.</p>
                    
                    <div class="form-group">
                        <label>בחר לקוח:</label>
                        <select id="api-customer-select" data-download-base="<?php echo esc_url(admin_url('admin-post.php?action=kbbm_download_script&customer_id=')); ?>">
                            <option value="">בחר לקוח</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo esc_attr($customer->id); ?>">
                                    <?php echo esc_html($customer->customer_name); ?> (<?php echo esc_html($customer->customer_number); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button id="generate-api-script" class="button button-primary">צור סקריפט</button>

                    <div id="api-script-output" style="display:none; margin-top: 20px;">
                        <h4>סקריפט PowerShell:</h4>
                        <textarea id="api-script-text" readonly style="width: 100%; height: 400px; font-family: monospace; direction: ltr; text-align: left;"></textarea>
                        <div class="form-actions" style="margin-top:10px;">
                            <button id="copy-api-script" class="button button-secondary" type="button">העתק ללוח</button>
                            <a id="download-api-script" class="button" href="#" target="_blank" rel="noreferrer">הורד סקריפט</a>
                        </div>
                    </div>
                    
                    <div class="m365-info-box" style="margin-top: 20px;">
                        <h4>הוראות שימוש:</h4>
                        <ol>
                            <li>בחר לקוח מהרשימה</li>
                            <li>לחץ על "צור סקריפט"</li>
                            <li>העתק את הסקריפט והפעל אותו ב-PowerShell כמנהל</li>
                            <li>העתק את הפרטים שיוצגו (Tenant ID, Client ID, Client Secret)</li>
                            <li>עדכן את פרטי הלקוח בדף "לקוחות"</li>
                            <li>אשר את ההרשאות ב-Azure Portal</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    // AJAX - שמירת לקוח
    public static function ajax_save_customer() {
        check_ajax_referer('m365_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'אין הרשאה'));
        }
        
        $data = array(
            'customer_number' => sanitize_text_field($_POST['customer_number']),
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'tenant_id' => sanitize_text_field($_POST['tenant_id']),
            'client_id' => sanitize_text_field($_POST['client_id']),
            'client_secret' => sanitize_textarea_field($_POST['client_secret']),
            'tenant_domain' => sanitize_text_field($_POST['tenant_domain'])
        );

        $tenants_json = isset($_POST['tenants']) ? wp_unslash($_POST['tenants']) : '[]';
        $tenants = json_decode($tenants_json, true);
        if (!is_array($tenants)) {
            $tenants = [];
        }

        $clean_tenants = [];
        foreach ($tenants as $tenant) {
            if (!is_array($tenant)) {
                continue;
            }

            $tenant_id = sanitize_text_field($tenant['tenant_id'] ?? '');
            if ($tenant_id === '') {
                continue;
            }

            $clean_tenants[] = array(
                'tenant_id'     => $tenant_id,
                'client_id'     => sanitize_text_field($tenant['client_id'] ?? ''),
                'client_secret' => sanitize_textarea_field($tenant['client_secret'] ?? ''),
                'tenant_domain' => sanitize_text_field($tenant['tenant_domain'] ?? ''),
            );
        }

        if (!empty($_POST['id'])) {
            $data['id'] = intval($_POST['id']);
        }

        $result = M365_LM_Database::save_customer($data);

        if ($result) {
            M365_LM_Database::replace_customer_tenants($result, $clean_tenants);
        }
        
        if ($result) {
            wp_send_json_success(array('message' => 'לקוח נשמר בהצלחה'));
        } else {
            wp_send_json_error(array('message' => 'שגיאה בשמירת הלקוח'));
        }
    }

    public function ajax_add_tenant() {
        check_ajax_referer('m365_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'אין הרשאה'));
        }

        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'לקוח לא נמצא'));
        }

        $customer = M365_LM_Database::get_customer($customer_id);
        if (!$customer) {
            wp_send_json_error(array('message' => 'לקוח לא נמצא'));
        }

        $tenant_id     = sanitize_text_field($_POST['tenant_id'] ?? '');
        $client_id     = sanitize_text_field($_POST['client_id'] ?? '');
        $client_secret = sanitize_textarea_field($_POST['client_secret'] ?? '');
        $tenant_domain = sanitize_text_field($_POST['tenant_domain'] ?? '');

        if ($tenant_id === '') {
            wp_send_json_error(array('message' => 'Tenant ID נדרש'));
        }

        $existing = M365_LM_Database::get_customer_tenants($customer_id);
        $clean = array();
        foreach ($existing as $tenant) {
            $clean[] = array(
                'tenant_id'     => sanitize_text_field($tenant->tenant_id ?? ''),
                'client_id'     => sanitize_text_field($tenant->client_id ?? ''),
                'client_secret' => sanitize_textarea_field($tenant->client_secret ?? ''),
                'tenant_domain' => sanitize_text_field($tenant->tenant_domain ?? ''),
            );
        }

        $clean[] = array(
            'tenant_id'     => $tenant_id,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'tenant_domain' => $tenant_domain,
        );

        M365_LM_Database::replace_customer_tenants($customer_id, $clean);

        wp_send_json_success(array('message' => 'טננט נוסף ללקוח'));
    }
    
    // AJAX - קבלת נתוני לקוח
    public function ajax_get_customer() {
        check_ajax_referer('m365_nonce', 'nonce');
        
        $customer_id = intval($_POST['id']);
        $customer = M365_LM_Database::get_customer($customer_id);

        if ($customer) {
            $customer_array = (array) $customer;
            $customer_array['tenants'] = M365_LM_Database::get_customer_tenants($customer_id);
            wp_send_json_success($customer_array);
        } else {
            wp_send_json_error(array('message' => 'לקוח לא נמצא'));
        }
    }
    
    // AJAX - מחיקת לקוח
    public function ajax_delete_customer() {
        check_ajax_referer('m365_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'אין הרשאה'));
        }
        
        global $wpdb;
        $customer_id = intval($_POST['id']);
        
        // בדיקה אם יש רישיונות קשורים
        $table_licenses = $wpdb->prefix . 'm365_licenses';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_licenses WHERE customer_id = %d",
            $customer_id
        ));
        
        if ($count > 0) {
            wp_send_json_error(array('message' => 'לא ניתן למחוק לקוח עם רישיונות קיימים'));
        }
        
        $table_customers = M365_LM_Database::get_customers_table_name();
        $result = $wpdb->delete($table_customers, array('id' => $customer_id));
        
        if ($result) {
            wp_send_json_success(array('message' => 'לקוח נמחק בהצלחה'));
        } else {
            wp_send_json_error(array('message' => 'שגיאה במחיקת הלקוח'));
        }
    }

    // AJAX - יצירת סקריפט PowerShell מותאם ללקוח
    public function ajax_generate_script() {
        check_ajax_referer('m365_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'אין הרשאה'));
        }

        $customer_id = intval($_POST['customer_id']);
        $customer    = M365_LM_Database::get_customer($customer_id);
        if (!$customer) {
            wp_send_json_error(array('message' => 'לקוח לא נמצא'));
        }

        if (empty($customer->tenant_domain)) {
            wp_send_json_error(array('message' => 'חסר Tenant Domain ללקוח'));
        }

        $script = kbbm_generate_ps_script($customer_id);

        if (!$script) {
            wp_send_json_error(array('message' => 'לא ניתן ליצור סקריפט עבור הלקוח'));
        }

        $download_url = add_query_arg(
            array(
                'action'      => 'kbbm_download_script',
                'customer_id' => $customer_id,
            ),
            admin_url('admin-post.php')
        );

        wp_send_json_success(array(
            'script'         => $script,
            'download_url'   => esc_url_raw($download_url),
            'tenant_id'      => $customer->tenant_id ?? '',
            'client_id'      => $customer->client_id ?? '',
            'client_secret'  => $customer->client_secret ?? '',
            'tenant_domain'  => $customer->tenant_domain ?? '',
        ));
    }

    public function ajax_save_settings() {
        check_ajax_referer('m365_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'אין הרשאה'));
        }

        $retention_days = isset($_POST['log_retention_days']) ? intval($_POST['log_retention_days']) : 120;
        $retention_days = $retention_days > 0 ? $retention_days : 120;
        $use_test_server = isset($_POST['use_test_server']) ? (int) $_POST['use_test_server'] : 0;

        update_option('kbbm_log_retention_days', $retention_days);
        update_option('kbbm_use_test_server', $use_test_server);

        // בצע ניקוי מיידי בהתאם לערך המעודכן
        M365_LM_Database::prune_logs($retention_days);

        wp_send_json_success(array(
            'message' => 'ההגדרות נשמרו בהצלחה',
            'log_retention_days' => $retention_days,
            'use_test_server' => $use_test_server,
        ));
    }
}

/**
 * יצירת סקריפט PowerShell מותאם ללקוח שנבחר
 */

    function kbbm_generate_ps_script($customer_id) {
        global $wpdb;

        $table = M365_LM_Database::get_customers_table_name();
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT customer_number, customer_name, tenant_domain FROM {$table} WHERE id = %d",
                $customer_id
            ),
            ARRAY_A
        );

        if (!$row) {
            return '';
        }

        $customer_number = isset($row['customer_number']) ? $row['customer_number'] : '';
        $customer_name   = isset($row['customer_name']) ? $row['customer_name'] : '';
        $tenant_domain   = !empty($row['tenant_domain']) ? $row['tenant_domain'] : 'contoso.onmicrosoft.com';

        $ps_template = <<<'PS'
<#
KBBM-Setup.ps1
KB Billing Manager – Full setup via Device Code + Graph REST

Customer: {{CUSTOMER_NUMBER}} {{CUSTOMER_NAME}}
Tenant Domain (from plugin): {{TENANT_DOMAIN}}

What this script does:
1. If run in Windows PowerShell:
   - Finds or installs PowerShell 7 (pwsh) silently.
   - Re-runs itself in PowerShell 7.
2. If run in PowerShell 7:
   - Performs Device Code auth against Microsoft identity platform using the Microsoft Graph CLI public client.
   - Fetches Tenant ID + initial verified domain.
   - Ensures an app registration "KB Billing Manager - <TenantDomain>" exists with Directory.Read.All + Organization.Read.All app roles.
   - Ensures a Service Principal exists.
   - Grants admin consent for those app roles (appRoleAssignments) on Microsoft Graph.
   - Creates a client secret (valid for 2 years).
   - Prints Tenant ID, Client ID and Client Secret for use in the WordPress plugin.
#>

param(
    [string]$TenantDomain = "{{TENANT_DOMAIN}}"
)

$ErrorActionPreference = 'Stop'

function Write-Section([string]$Text) {
    Write-Host ""
    Write-Host "==================================" -ForegroundColor Cyan
    Write-Host $Text -ForegroundColor Cyan
    Write-Host "==================================" -ForegroundColor Cyan
}

# -------- PART 1: Bootstrap to PowerShell 7 if needed --------

if ($PSVersionTable.PSEdition -ne 'Core') {
    Write-Section "KB Billing Manager - Bootstrap (Windows PowerShell detected)"

    $pwshCmd = Get-Command pwsh -ErrorAction SilentlyContinue

    if (-not $pwshCmd) {
        Write-Host "PowerShell 7 (pwsh) not found. Downloading and installing..." -ForegroundColor Yellow

        $psVersion   = "7.4.2"
        $msiFileName = "PowerShell-$psVersion-win-x64.msi"
        $downloadUrl = "https://github.com/PowerShell/PowerShell/releases/download/v$psVersion/$msiFileName"
        $msiPath     = Join-Path $env:TEMP $msiFileName

        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            Invoke-WebRequest -Uri $downloadUrl -OutFile $msiPath
        }
        catch {
            Write-Host "Failed to download PowerShell 7 MSI:" -ForegroundColor Red
            Write-Host $_.Exception.Message -ForegroundColor Red
            Write-Host "Install PowerShell 7 manually from https://github.com/PowerShell/PowerShell/releases/latest" -ForegroundColor Red
            exit 1
        }

        try {
            Write-Host "Installing PowerShell 7 silently..." -ForegroundColor Cyan
            $arguments = "/i `"$msiPath`" /qn /norestart"
            $process   = Start-Process -FilePath "msiexec.exe" -ArgumentList $arguments -Wait -PassThru
            if ($process.ExitCode -ne 0) {
                Write-Host "msiexec returned non-zero exit code: $($process.ExitCode)" -ForegroundColor Red
                exit 1
            }
            $machinePath = [System.Environment]::GetEnvironmentVariable("Path", "Machine")
            $userPath    = [System.Environment]::GetEnvironmentVariable("Path", "User")
            $env:PATH    = "$machinePath;$userPath"
            if (Test-Path $msiPath) { Remove-Item $msiPath -Force }
        }
        catch {
            Write-Host "Failed to install PowerShell 7 MSI:" -ForegroundColor Red
            Write-Host $_.Exception.Message -ForegroundColor Red
            exit 1
        }

        $pwshCmd = Get-Command pwsh -ErrorAction SilentlyContinue
        if (-not $pwshCmd) {
            Write-Host "PowerShell 7 still not found after installation." -ForegroundColor Red
            exit 1
        }
    }
    else {
        Write-Host "PowerShell 7 (pwsh) is already installed." -ForegroundColor Green
    }

    $scriptPath = $MyInvocation.MyCommand.Definition
    $pwshPath   = $pwshCmd.Source

    Write-Host "Opening a new PowerShell 7 window running this script..." -ForegroundColor Cyan

    $argList = @('-NoExit','-File',"`"$scriptPath`"")
    if ($TenantDomain) {
        $argList += @('-TenantDomain', $TenantDomain)
    }

    Start-Process -FilePath $pwshPath -ArgumentList $argList
    Write-Host "You can close this Windows PowerShell window." -ForegroundColor Yellow
    exit 0
}

# -------- PART 2: Device code (5 min) + Graph REST --------

Write-Section "KB Billing Manager - Microsoft 365 Setup (Graph REST / PowerShell 7)"

$ClientId       = "14d82eec-204b-4c2f-b7e8-296a70dab67e" # Microsoft Graph CLI public client
$deviceEndpoint = "https://login.microsoftonline.com/organizations/oauth2/v2.0/devicecode"
$tokenEndpoint  = "https://login.microsoftonline.com/organizations/oauth2/v2.0/token"

# Scopes (delegated) – must allow creating app registrations, SPs and appRoleAssignments
$scopes = @(
    "https://graph.microsoft.com/Application.ReadWrite.All",
    "https://graph.microsoft.com/Directory.Read.All",
    "https://graph.microsoft.com/AppRoleAssignment.ReadWrite.All",
    "offline_access",
    "openid",
    "profile"
)
$scopeString = ($scopes -join " ")

Write-Host "Requesting device code from Microsoft identity platform..." -ForegroundColor Cyan

$body = "client_id=$ClientId&scope=$([System.Uri]::EscapeDataString($scopeString))"
$deviceResponse = Invoke-RestMethod -Method POST -Uri $deviceEndpoint `
    -ContentType "application/x-www-form-urlencoded" -Body $body

$userCode        = $deviceResponse.user_code
$verificationUri = $deviceResponse.verification_uri
$expiresIn       = [int]$deviceResponse.expires_in
$interval        = [int]$deviceResponse.interval

Write-Host ""
Write-Host "To sign in, open:" -ForegroundColor Yellow
Write-Host "  $verificationUri" -ForegroundColor Yellow
Write-Host "and enter this code:" -ForegroundColor Yellow
Write-Host "  $userCode" -ForegroundColor Green
Write-Host ""

Start-Process $verificationUri

try {
    Add-Type -AssemblyName System.Windows.Forms
    Add-Type -AssemblyName System.Drawing
    [System.Windows.Forms.Application]::EnableVisualStyles()

    $form              = New-Object System.Windows.Forms.Form
    $form.Text         = "KB Billing Manager - Device Login"
    $form.StartPosition = "CenterScreen"
    $form.Size         = New-Object System.Drawing.Size(420,220)
    $form.TopMost      = $true

    $labelInfo = New-Object System.Windows.Forms.Label
    $labelInfo.Text = "1. בחלון הדפדפן, הדבק את הקוד." + [Environment]::NewLine +
                      "2. השלם התחברות כולל MFA." + [Environment]::NewLine +
                      "3. חזור לכאן ולחץ Continue."
    $labelInfo.AutoSize = $true
    $labelInfo.Location = New-Object System.Drawing.Point(15,15)
    $form.Controls.Add($labelInfo)

    $labelCode = New-Object System.Windows.Forms.Label
    $labelCode.Text = "Device code:"
    $labelCode.AutoSize = $true
    $labelCode.Location = New-Object System.Drawing.Point(15,80)
    $form.Controls.Add($labelCode)

    $textCode = New-Object System.Windows.Forms.TextBox
    $textCode.Text = $userCode
    $textCode.ReadOnly = $true
    $textCode.TextAlign = "Center"
    $textCode.Width = 250
    $textCode.Location = New-Object System.Drawing.Point(100,78)
    $form.Controls.Add($textCode)

    $btnCopy = New-Object System.Windows.Forms.Button
    $btnCopy.Text = "Copy"
    $btnCopy.Width = 80
    $btnCopy.Location = New-Object System.Drawing.Point(360,76)
    $btnCopy.Add_Click({
        [System.Windows.Forms.Clipboard]::SetText($textCode.Text)
        [System.Windows.Forms.MessageBox]::Show("Code copied to clipboard.","KB Billing Manager") | Out-Null
    })
    $form.Controls.Add($btnCopy)

    $btnContinue = New-Object System.Windows.Forms.Button
    $btnContinue.Text = "Continue"
    $btnContinue.Width = 100
    $btnContinue.Location = New-Object System.Drawing.Point(150,130)
    $btnContinue.Add_Click({ $form.Close() })
    $form.Controls.Add($btnContinue)

    [void]$form.ShowDialog()
}
catch {
    # ignore GUI issues (e.g. Server Core)
}

function Get-DeviceToken {
    param(
        [Parameter(Mandatory=$true)] $DeviceResponse,
        [Parameter(Mandatory=$true)] [string]$ClientId,
        [int]$MaxWaitSeconds = 300
    )

    $tokenEndpoint = "https://login.microsoftonline.com/organizations/oauth2/v2.0/token"
    $elapsed = 0
    $interval = [int]$DeviceResponse.interval
    $limit = [Math]::Min($MaxWaitSeconds, [int]$DeviceResponse.expires_in)

    while ($elapsed -lt $limit) {
        try {
            $body = "grant_type=urn:ietf:params:oauth:grant-type:device_code" +
                    "&client_id=$ClientId" +
                    "&device_code=$($DeviceResponse.device_code)"

            $tokenResponse = Invoke-RestMethod -Method POST -Uri $tokenEndpoint `
                -ContentType "application/x-www-form-urlencoded" -Body $body

            return $tokenResponse
        }
        catch {
            $errJson = $_.ErrorDetails.Message
            if ($errJson -match '"error"\s*:\s*"authorization_pending"') {
                Start-Sleep -Seconds $interval
                $elapsed += $interval
                continue
            }
            elseif ($errJson -match '"error"\s*:\s*"authorization_declined"') {
                throw "User declined authentication."
            }
            elseif ($errJson -match '"error"\s*:\s*"expired_token"') {
                throw "Device code expired."
            }
            else {
                throw $_
            }
        }
    }

    throw "Timed out waiting for authentication after $limit seconds."
}

Write-Host "Waiting for authentication to complete (up to 5 minutes)..." -ForegroundColor Cyan
$token = Get-DeviceToken -DeviceResponse $deviceResponse -ClientId $ClientId -MaxWaitSeconds 300
Write-Host "Authentication successful." -ForegroundColor Green

$accessToken = $token.access_token
$graphBase   = "https://graph.microsoft.com/v1.0"
$headers     = @{ Authorization = "Bearer $accessToken" }

# --- Organization (tenant id + domain) ---

try {
    $org = Invoke-RestMethod -Uri "$graphBase/organization" -Headers $headers -Method GET
    $orgObj = $org.value[0]
    $tenantId = $orgObj.id

    if (-not $TenantDomain -or $TenantDomain.Trim() -eq "") {
        $TenantDomain = ($orgObj.verifiedDomains | Where-Object { $_.isInitial -eq $true }).name
    }

    Write-Host "Tenant ID:   $tenantId"    -ForegroundColor Green
    Write-Host "TenantDomain: $TenantDomain" -ForegroundColor Green
}
catch {
    Write-Host "Error getting organization details:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

# --- Application registration ---

$appDisplayName   = "KB Billing Manager - $TenantDomain"
$graphResourceId  = "00000003-0000-0000-c000-000000000000"
$directoryReadAll = "7ab1d382-f21e-4acd-a863-ba3e13f7da61"
$orgReadAll       = "498476ce-e0fe-48b0-b801-37ba7e2685c6"

Write-Host "Ensuring application '$appDisplayName' exists..." -ForegroundColor Cyan

$filter = "displayName eq '$appDisplayName'"
$filterEncoded = [System.Uri]::EscapeDataString($filter)
$appSearchUri = "$graphBase/applications?`$filter=$filterEncoded"

$appSearch = Invoke-RestMethod -Uri $appSearchUri -Headers $headers -Method GET
if ($appSearch.value.Count -gt 0) {
    $app = $appSearch.value[0]
    Write-Host "Existing application found." -ForegroundColor Yellow
}
else {
    $appBody = @{
        displayName = $appDisplayName
        requiredResourceAccess = @(
            @{
                resourceAppId  = $graphResourceId
                resourceAccess = @(
                    @{ id = $directoryReadAll; type = "Role" }
                    @{ id = $orgReadAll;      type = "Role" }
                )
            }
        )
    } | ConvertTo-Json -Depth 5

    $app = Invoke-RestMethod -Uri "$graphBase/applications" -Headers $headers `
        -Method POST -ContentType "application/json" -Body $appBody
    Write-Host "App Registration created." -ForegroundColor Green
}

$appId       = $app.appId
$appObjectId = $app.id

# --- Service Principal ---

Write-Host "Ensuring Service Principal exists..." -ForegroundColor Cyan
$spSearchUri = "$graphBase/servicePrincipals?`$filter=appId eq '$appId'"
$spSearch    = Invoke-RestMethod -Uri $spSearchUri -Headers $headers -Method GET

if ($spSearch.value.Count -gt 0) {
    $sp = $spSearch.value[0]
    Write-Host "Service Principal already exists." -ForegroundColor Green
}
else {
    $spBody = @{ appId = $appId } | ConvertTo-Json
    $sp     = Invoke-RestMethod -Uri "$graphBase/servicePrincipals" -Headers $headers `
        -Method POST -ContentType "application/json" -Body $spBody
    Write-Host "Service Principal created." -ForegroundColor Green
}

function Grant-GraphAppRoles {
    param(
        [Parameter(Mandatory=$true)] [string]$GraphBase,
        [Parameter(Mandatory=$true)] $Headers,
        [Parameter(Mandatory=$true)] [string]$ClientSpId,
        [Parameter(Mandatory=$true)] [string]$GraphAppId,
        [string[]]$RoleIds
    )

    Write-Section "Granting admin consent for Microsoft Graph application permissions"

    try {
        $spGraph = Invoke-RestMethod -Uri "$GraphBase/servicePrincipals?`$filter=appId eq '$GraphAppId'" `
                                     -Headers $Headers -Method GET

        if (-not $spGraph.value -or $spGraph.value.Count -eq 0) {
            Write-Host "Could not find Microsoft Graph service principal in tenant." -ForegroundColor Yellow
            return
        }

        $graphSpId = $spGraph.value[0].id

        foreach ($roleId in $RoleIds) {
            $body = @{
                principalId = $ClientSpId
                resourceId  = $graphSpId
                appRoleId   = $roleId
            } | ConvertTo-Json

            try {
                Invoke-RestMethod -Uri "$GraphBase/servicePrincipals/$ClientSpId/appRoleAssignments" `
                    -Headers $Headers -Method POST -ContentType "application/json" -Body $body | Out-Null

                Write-Host ("Assigned Graph app role {0}" -f $roleId) -ForegroundColor Green
            }
            catch {
                $msg = $_.Exception.Message
                if ($msg -match "409" -or $msg -match "already exists") {
                    Write-Host ("Graph app role {0} already assigned (already existed)." -f $roleId) -ForegroundColor Yellow
                }
                else {
                    Write-Host ("Failed to assign Graph app role {0}: {1}" -f $roleId, $msg) -ForegroundColor Yellow
                }
            }
        }

        Write-Host "Admin consent for Graph roles finished." -ForegroundColor Green
    }
    catch {
        Write-Host "Failed to grant admin consent programmatically. You can still press 'Grant admin consent' in the portal if needed." -ForegroundColor Yellow
        Write-Host $_.Exception.Message -ForegroundColor DarkYellow
    }
}

Grant-GraphAppRoles -GraphBase $graphBase `
                    -Headers   $headers `
                    -ClientSpId $sp.id `
                    -GraphAppId $graphResourceId `
                    -RoleIds @($directoryReadAll, $orgReadAll)

# --- Client Secret (2 years) ---

Write-Host "Creating new client secret (2 years)..." -ForegroundColor Cyan

$pwdBody = @{
    passwordCredential = @{
        displayName = "KBBM"
        endDateTime = (Get-Date).AddYears(2).ToString("o")
    }
} | ConvertTo-Json -Depth 5

$pwdResp = Invoke-RestMethod -Uri "$graphBase/applications/$appObjectId/addPassword" `
    -Headers $headers -Method POST -ContentType "application/json" -Body $pwdBody

$clientSecret = $pwdResp.secretText

Write-Section "Values to use in KB Billing Manager plugin"

Write-Host ("Tenant ID:              {0}" -f $tenantId)       -ForegroundColor Yellow
Write-Host ("Application (Client) ID: {0}" -f $appId)         -ForegroundColor Yellow
Write-Host ("Client Secret:           {0}" -f $clientSecret)  -ForegroundColor Yellow

Write-Section "Next steps"

Write-Host "1. העתק שלושה ערכים אלו למסך ההגדרות של הלקוח בתוסף KB Billing Manager:" -ForegroundColor Cyan
Write-Host "   - Tenant ID" -ForegroundColor Cyan
Write-Host "   - Client ID (Application ID)" -ForegroundColor Cyan
Write-Host "   - Client Secret" -ForegroundColor Cyan
Write-Host "2. אין צורך ללחוץ ידנית על Grant admin consent – ההרשאות כבר הוקצו דרך הסקריפט (אם אושרה ההתחברות כ-Admin)." -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
PS;

        $script = str_replace(
            array('{{TENANT_DOMAIN}}', '{{CUSTOMER_NUMBER}}', '{{CUSTOMER_NAME}}'),
            array($tenant_domain, $customer_number, $customer_name),
            $ps_template
        );

        return $script;
    }


    function kbbm_download_script_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('No permission');
    }

    $customer_id = intval($_GET['customer_id'] ?? 0);
    if (!$customer_id) {
        wp_die('No customer selected');
    }

    $script = kbbm_generate_ps_script($customer_id);

    if (empty($script)) {
        wp_die('Customer not found');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="KBBM-Setup-' . $customer_id . '.ps1"');
    echo $script;
    exit;
}
