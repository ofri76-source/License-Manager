<?php
if (!defined('ABSPATH')) exit;

class M365_LM_API_Connector {
    
    private $tenant_id;
    private $client_id;
    private $client_secret;
    private $access_token;
    
    public function __construct($tenant_id, $client_id, $client_secret) {
        $this->tenant_id = $tenant_id;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
    }
    
    // קבלת Access Token
    private function request_access_token() {
        if ($this->access_token) {
            return array('success' => true, 'token' => $this->access_token);
        }

        $url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";

        $body = array(
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'scope'         => 'https://graph.microsoft.com/.default',
            'grant_type'    => 'client_credentials',
        );

        $response = wp_remote_post($url, array(
            'body'    => $body,
            'timeout' => 45,
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            return array('success' => true, 'token' => $this->access_token);
        }

        $error_msg = 'Graph authentication failed';
        if (isset($body['error_description'])) {
            $error_msg = $body['error_description'];
        } elseif (isset($body['error'])) {
            $error_msg = is_array($body['error']) && isset($body['error']['message']) ? $body['error']['message'] : $body['error'];
        }

        if (!empty($code)) {
            $error_msg = sprintf('%s (HTTP %s)', $error_msg, $code);
        }

        return array(
            'success' => false,
            'message' => $error_msg,
            'code'    => $code,
            'body'    => $body,
        );
    }

    // בדיקת תקינות החיבור לגרף
    public function test_connection() {
        $token_response = $this->request_access_token();

        if (!$token_response['success']) {
            return array('success' => false, 'message' => $token_response['message']);
        }

        $token   = $token_response['token'];
        $url     = 'https://graph.microsoft.com/v1.0/organization';
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        );

        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 45,
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && isset($body['value'][0]['id'])) {
            return array('success' => true, 'message' => 'Connected', 'tenant_id' => $body['value'][0]['id']);
        }

        $error_msg = 'Graph error';
        if (!empty($body['error']['message'])) {
            $error_msg = $body['error']['message'];
        }

        return array('success' => false, 'message' => $error_msg);
    }

    // משיכת רישיונות מ-Microsoft Graph API
    public function get_subscribed_skus() {
        $token_response = $this->request_access_token();
        if (!$token_response['success']) {
            return array('success' => false, 'message' => $token_response['message']);
        }

        $url = 'https://graph.microsoft.com/v1.0/subscribedSkus';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token_response['token'],
                'Content-Type' => 'application/json'
            ),
            'timeout' => 45
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && isset($body['value'])) {
            $parsed = $this->parse_skus($body['value']);
            if (empty($parsed)) {
                return array('success' => false, 'message' => 'Graph returned 0 SKUs');
            }

            return array('success' => true, 'skus' => $parsed);
        }

        $error_msg = 'No SKU data found';
        if (!empty($body['error']['message'])) {
            $error_msg = $body['error']['message'];
        } elseif (!empty($body['error_description'])) {
            $error_msg = $body['error_description'];
        }

        if (!empty($code)) {
            $error_msg = sprintf('HTTP %s: %s', $code, $error_msg);
        }

        return array('success' => false, 'message' => $error_msg, 'code' => $code, 'body' => $body);
    }
    
    // עיבוד נתוני SKU
    private function parse_skus($skus) {
        $result = array();
        
        foreach ($skus as $sku) {
            $result[] = array(
                'sku_id'          => $sku['skuId'] ?? '',
                'plan_name'       => $sku['skuPartNumber'] ?? 'Unknown',
                'consumed_units'  => $sku['consumedUnits'] ?? 0,
                'enabled_units'   => $sku['prepaidUnits']['enabled'] ?? 0,
                'suspended_units' => $sku['prepaidUnits']['suspended'] ?? 0,
                'status'          => $sku['capabilityStatus'] ?? '',
                'service_plans'   => $sku['servicePlans'] ?? array()
            );
        }
        
        return $result;
    }
    
    // יצירת סקריפט להגדרת API
    public static function generate_api_setup_script($tenant_domain) {
        $script = "# Microsoft 365 API Setup Script
# הפעל סקריפט זה ב-PowerShell כמנהל

# התחברות ל-Azure AD
Connect-AzureAD -TenantDomain \"$tenant_domain\"

# יצירת App Registration
\$appName = \"M365 License Manager - $tenant_domain\"
\$app = New-AzureADApplication -DisplayName \$appName

# יצירת Service Principal
\$sp = New-AzureADServicePrincipal -AppId \$app.AppId

# יצירת Client Secret
\$secret = New-AzureADApplicationPasswordCredential -ObjectId \$app.ObjectId -CustomKeyIdentifier \"M365LM\" -EndDate (Get-Date).AddYears(2)

# הענקת הרשאות API
# Microsoft Graph - Directory.Read.All
\$graphResourceId = \"00000003-0000-0000-c000-000000000000\"
\$directoryReadAll = \"7ab1d382-f21e-4acd-a863-ba3e13f7da61\"

Add-AzureADApplicationRequiredResourceAccess -ObjectId \$app.ObjectId -RequiredResourceAccess @{
    ResourceAppId = \$graphResourceId
    ResourceAccess = @(
        @{
            Id = \$directoryReadAll
            Type = \"Role\"
        }
    )
}

Write-Host \"==================================\"
Write-Host \"App Registration נוצר בהצלחה!\"
Write-Host \"==================================\"
Write-Host \"Tenant ID: \" \$app.PublisherDomain
Write-Host \"Application (Client) ID: \" \$app.AppId
Write-Host \"Client Secret: \" \$secret.Value
Write-Host \"==================================\"
Write-Host \"העתק את הפרטים האלה למסך ההגדרות בתוסף WordPress\"
Write-Host \"==================================\"

# אל תשכח לאשר את ההרשאות ב-Azure Portal:
Write-Host \"עבור ל-Azure Portal > Azure Active Directory > App Registrations > \$appName\"
Write-Host \"לחץ על API Permissions > Grant admin consent\"
";
        
        return $script;
    }
}
