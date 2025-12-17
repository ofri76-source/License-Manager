<?php
if (!defined('ABSPATH')) exit;

class M365_LM_Partner_Connector {

    /**
     * Get saved partner settings.
     */
    public static function get_settings() {
        return array(
            'enabled'       => (bool) get_option('kbbm_partner_enabled', false),
            'tenant_id'     => sanitize_text_field(get_option('kbbm_partner_tenant_id', '')),
            'client_id'     => sanitize_text_field(get_option('kbbm_partner_client_id', '')),
            'client_secret' => get_option('kbbm_partner_client_secret', ''),
        );
    }

    /**
     * Acquire an access token for Partner Center via client credentials.
     */
    public static function get_partner_access_token($context = 'partner_import_customers') {
        $settings = self::get_settings();

        if (empty($settings['enabled'])) {
            return new WP_Error('partner_disabled', 'Partner mode is disabled.');
        }

        if (empty($settings['tenant_id']) || empty($settings['client_id']) || empty($settings['client_secret'])) {
            return new WP_Error('partner_missing_credentials', 'Missing Partner credentials.');
        }

        $token_url = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $settings['tenant_id']);
        $body      = array(
            'grant_type'    => 'client_credentials',
            'client_id'     => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'scope'         => 'https://api.partnercenter.microsoft.com/.default',
        );

        $response = wp_remote_post($token_url, array(
            'body'    => $body,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            M365_LM_Database::log_event(
                'error',
                $context,
                'Failed to request partner token',
                null,
                array('error' => $response->get_error_message())
            );
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_str = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            M365_LM_Database::log_event(
                'error',
                $context,
                'Partner token request failed',
                null,
                array(
                    'status'  => $code,
                    'body'    => mb_substr($body_str, 0, 2048),
                )
            );
            return new WP_Error('partner_token_failed', 'Failed to obtain partner token');
        }

        $decoded = json_decode($body_str, true);
        if (empty($decoded['access_token'])) {
            M365_LM_Database::log_event(
                'error',
                $context,
                'Partner token missing access_token',
                null,
                array('body' => mb_substr($body_str, 0, 2048))
            );
            return new WP_Error('partner_token_missing', 'Partner token missing access token');
        }

        return $decoded['access_token'];
    }

    /**
     * List partner customers.
     */
    public static function list_partner_customers() {
        $token = self::get_partner_access_token('partner_import_customers');
        if (is_wp_error($token)) {
            return $token;
        }

        $response = wp_remote_get('https://api.partnercenter.microsoft.com/v1/customers', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            M365_LM_Database::log_event(
                'error',
                'partner_import_customers',
                'Failed to fetch partner customers',
                null,
                array('error' => $response->get_error_message())
            );
            return $response;
        }

        $code     = wp_remote_retrieve_response_code($response);
        $body_str = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            M365_LM_Database::log_event(
                'error',
                'partner_import_customers',
                'Partner customers request failed',
                null,
                array(
                    'status' => $code,
                    'body'   => mb_substr($body_str, 0, 2048),
                )
            );
            return new WP_Error('partner_customers_failed', 'Failed to fetch partner customers');
        }

        $decoded = json_decode($body_str, true);
        if (!$decoded) {
            M365_LM_Database::log_event(
                'error',
                'partner_import_customers',
                'Partner customers response was not valid JSON',
                null,
                array('body' => mb_substr($body_str, 0, 2048))
            );
            return new WP_Error('partner_customers_json', 'Invalid partner customers response');
        }

        $items = array();
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            $items = $decoded['items'];
        } elseif (is_array($decoded)) {
            $items = $decoded;
        }

        $customers = array();
        foreach ($items as $item) {
            $customer_id = isset($item['id']) ? $item['id'] : (isset($item['customer_id']) ? $item['customer_id'] : '');
            $profile     = isset($item['companyProfile']) ? $item['companyProfile'] : array();
            $name        = isset($item['companyProfile']['companyName']) ? $item['companyProfile']['companyName'] : (isset($item['name']) ? $item['name'] : '');
            $tenant_id   = isset($profile['tenantId']) ? $profile['tenantId'] : (isset($item['tenant_id']) ? $item['tenant_id'] : '');
            $domain      = '';
            if (isset($profile['domain'])) {
                $domain = $profile['domain'];
            } elseif (isset($profile['defaultDomain'])) {
                $domain = $profile['defaultDomain'];
            } elseif (isset($item['domain'])) {
                $domain = $item['domain'];
            }

            if (empty($customer_id) && empty($tenant_id)) {
                continue;
            }

            $customers[] = array(
                'partner_customer_id' => $customer_id,
                'name'                => $name,
                'tenant_id'           => $tenant_id,
                'tenant_domain'       => $domain,
            );
        }

        return $customers;
    }
}
