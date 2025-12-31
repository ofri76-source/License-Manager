<?php
if (!defined('ABSPATH')) exit;

interface IExternalDataConnector {
    public function getAccessToken();
    public function fetchCustomers();
    public function fetchLicenses($partnerCustomerId);
}

class PartnerCenterConnector implements IExternalDataConnector {
    private $tenant_id;
    private $client_id;
    private $client_secret;
    private $environment;
    private $refresh_token;

    private function decodeJwtPayload($jwt) {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        $payload = strtr($parts[1], '-_', '+/');
        $pad = strlen($payload) % 4;
        if ($pad) {
            $payload .= str_repeat('=', 4 - $pad);
        }
        $json = base64_decode($payload);
        if ($json === false) {
            return null;
        }
        return json_decode($json, true);
    }

    public function __construct($tenant_id, $client_id, $client_secret, $environment = 'production') {
        $this->tenant_id = $tenant_id;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->environment = $environment === 'sandbox' ? 'sandbox' : 'production';
        $this->refresh_token = get_option('kbbm_partner_refresh_token', '');
    }

    public function getAccessToken() {
        $cache_key = 'kbbm_partner_access_token';
        $cached = get_transient($cache_key);
        if (!empty($cached)) {
            return array(
                'success' => true,
                'token' => $cached,
                'token_source' => 'cache',
            );
        }

        $url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        $use_refresh = !empty($this->refresh_token);
        $body = $use_refresh ? array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refresh_token,
            'scope' => 'https://api.partnercenter.microsoft.com/user_impersonation offline_access',
        ) : array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials',
            'scope' => 'https://api.partnercenter.microsoft.com/.default',
        );

        M365_LM_Database::log_event(
            'info',
            'partner_auth_debug',
            'Partner token request details',
            null,
            array(
                'token_url' => $url,
                'is_v2'     => strpos($url, '/oauth2/v2.0/') !== false,
                'scope'     => $body['scope'],
                'grant_type' => $body['grant_type'],
                'token_source' => $use_refresh ? 'delegated_refresh_token' : 'client_credentials',
            )
        );

        $response = wp_remote_post($url, array(
            'body' => $body,
            'timeout' => 45,
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message(), 'code' => 0);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $payload = json_decode($body_raw, true);

        if ($code === 200 && isset($payload['access_token'])) {
            set_transient($cache_key, $payload['access_token'], 50 * MINUTE_IN_SECONDS);
            $token_payload = $this->decodeJwtPayload($payload['access_token']);
            M365_LM_Database::log_event(
                'info',
                'partner_auth_debug',
                'Partner token diagnostics',
                null,
                array(
                    'aud'   => $token_payload['aud'] ?? null,
                    'tid'   => $token_payload['tid'] ?? null,
                    'appid' => $token_payload['appid'] ?? null,
                    'roles' => $token_payload['roles'] ?? null,
                    'scp'   => $token_payload['scp'] ?? null,
                    'upn'   => $token_payload['upn'] ?? null,
                )
            );
            if (!empty($payload['refresh_token'])) {
                update_option('kbbm_partner_refresh_token', $payload['refresh_token']);
                $this->refresh_token = $payload['refresh_token'];
            }
            return array(
                'success' => true,
                'token' => $payload['access_token'],
                'token_source' => $use_refresh ? 'delegated_refresh_token' : 'client_credentials',
                'grant_type' => $body['grant_type'],
            );
        }

        $message = $payload['error_description'] ?? ($payload['error'] ?? 'Partner auth failed');
        return array(
            'success' => false,
            'message' => $message,
            'code' => $code,
            'body' => $payload,
        );
    }

    public function fetchCustomers() {
        $token = $this->getAccessToken();
        if (empty($token['success'])) {
            return $token;
        }

        M365_LM_Database::log_event(
            'info',
            'partner_fetch_customers',
            'Partner customers token source',
            null,
            array(
                'token_source' => $token['token_source'] ?? null,
                'grant_type' => $token['grant_type'] ?? null,
            )
        );

        $url = 'https://api.partnercenter.microsoft.com/v1/customers';
        $headers = array(
            'Authorization' => 'Bearer ' . $token['token'],
            'Accept' => 'application/json',
            'MS-Contract-Version' => 'v1',
        );
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 45,
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message(), 'code' => 0);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $payload = json_decode($body_raw, true);
        $response_headers = wp_remote_retrieve_headers($response);

        $headers_for_log = $headers;
        if (!empty($headers_for_log['Authorization'])) {
            $headers_for_log['Authorization'] = 'Bearer ***';
        }
        M365_LM_Database::log_event(
            'info',
            'partner_fetch_customers',
            'Partner customers response',
            null,
            array(
                'status' => $code,
                'headers' => $headers_for_log,
                'response_headers' => $response_headers,
                'body' => $body_raw,
            )
        );

        if ($code === 200 && isset($payload['items'])) {
            return array(
                'success' => true,
                'customers' => $payload['items'],
                'code' => $code,
                'body_raw' => $body_raw,
                'headers' => $response_headers,
            );
        }

        return array(
            'success' => false,
            'message' => 'Partner customers fetch failed',
            'code' => $code,
            'body' => $payload,
            'body_raw' => $body_raw,
            'headers' => $response_headers,
        );
    }

    public function fetchLicenses($partnerCustomerId) {
        $token = $this->getAccessToken();
        if (empty($token['success'])) {
            return $token;
        }

        $url = sprintf('https://api.partnercenter.microsoft.com/v1/customers/%s/subscriptions', rawurlencode($partnerCustomerId));
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token['token'],
                'Accept' => 'application/json',
            ),
            'timeout' => 45,
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message(), 'code' => 0);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $payload = json_decode($body_raw, true);

        if ($code === 200 && isset($payload['items'])) {
            return array('success' => true, 'subscriptions' => $payload['items'], 'code' => $code);
        }

        return array(
            'success' => false,
            'message' => 'Partner subscriptions fetch failed',
            'code' => $code,
            'body' => $payload,
        );
    }
}

class GraphGdapConnector implements IExternalDataConnector {
    private $tenant_id;
    private $client_id;
    private $client_secret;

    public function __construct($tenant_id, $client_id, $client_secret) {
        $this->tenant_id = $tenant_id;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
    }

    public function getAccessToken() {
        return array('success' => false, 'message' => 'Graph enrichment disabled', 'code' => 0);
    }

    public function fetchCustomers() {
        return array('success' => false, 'message' => 'Graph enrichment disabled', 'code' => 0);
    }

    public function fetchLicenses($partnerCustomerId) {
        return array('success' => false, 'message' => 'Graph enrichment disabled', 'code' => 0);
    }
}
