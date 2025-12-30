<?php
if (!defined('ABSPATH')) exit;

class M365_LM_Sync_Service {
    private $partner_connector;
    private $graph_connector;
    private $partner_enabled;
    private $graph_enabled;

    public function __construct($partner_connector, $graph_connector, $partner_enabled, $graph_enabled) {
        $this->partner_connector = $partner_connector;
        $this->graph_connector = $graph_connector;
        $this->partner_enabled = $partner_enabled;
        $this->graph_enabled = $graph_enabled;
    }

    public function syncCustomers() {
        if (!$this->partner_enabled) {
            return array('success' => false, 'message' => 'Partner mode disabled');
        }

        $result = $this->partner_connector->fetchCustomers();
        if (empty($result['success'])) {
            M365_LM_Database::log_event(
                'error',
                'partner_sync_customers',
                $result['message'] ?? 'Partner customers fetch failed',
                null,
                array(
                    'http_code' => $result['code'] ?? null,
                    'body_snippet' => isset($result['body']) ? substr(wp_json_encode($result['body']), 0, 500) : null,
                )
            );
            return $result + array('http' => $result);
        }

        $count = 0;
        foreach ($result['customers'] as $customer) {
            $partner_id = $customer['id'] ?? '';
            if ($partner_id === '') {
                continue;
            }

            $company_profile = $customer['companyProfile'] ?? array();
            $tenant_domain = $company_profile['domain'] ?? '';
            $name = $company_profile['companyName'] ?? ($customer['companyProfile']['companyName'] ?? ($customer['companyName'] ?? ''));

            M365_LM_Database::upsert_partner_customer(array(
                'partner_customer_id' => $partner_id,
                'customer_name' => $name,
                'tenant_domain' => $tenant_domain,
            ));
            $count++;
        }

        M365_LM_Database::log_event(
            'info',
            'partner_sync_customers',
            'Partner customers synced',
            null,
            array(
                'count' => $count,
                'http_code' => $result['code'] ?? null,
                'body_snippet' => isset($result['customers']) ? substr(wp_json_encode($result['customers']), 0, 500) : null,
            )
        );

        return array('success' => true, 'count' => $count, 'http' => $result);
    }

    public function syncLicenses() {
        if (!$this->partner_enabled) {
            return array('success' => false, 'message' => 'Partner mode disabled');
        }

        $customers = M365_LM_Database::get_customers_by_source('partner');
        $synced = 0;
        $errors = array();

        foreach ($customers as $customer) {
            $partner_id = $customer->partner_customer_id ?? '';
            if ($partner_id === '') {
                continue;
            }

            $result = $this->partner_connector->fetchLicenses($partner_id);
            if (empty($result['success'])) {
                M365_LM_Database::log_event(
                    'error',
                    'partner_sync_licenses',
                    $result['message'] ?? 'Partner subscriptions error',
                    intval($customer->id),
                    array(
                        'partner_customer_id' => $partner_id,
                        'http_code' => $result['code'] ?? null,
                        'body_snippet' => isset($result['body']) ? substr(wp_json_encode($result['body']), 0, 500) : null,
                    )
                );
                $errors[] = array('partner_customer_id' => $partner_id, 'message' => $result['message'] ?? 'Partner subscriptions error');
                continue;
            }

            $items = $result['subscriptions'] ?? array();
            foreach ($items as $subscription) {
                $subscription_id = $subscription['id'] ?? '';
                if ($subscription_id === '') {
                    continue;
                }

                $data = array(
                    'customer_id' => intval($customer->id),
                    'partner_customer_id' => $partner_id,
                    'subscription_id' => $subscription_id,
                    'offer_id' => $subscription['offerId'] ?? '',
                    'plan_name' => $subscription['friendlyName'] ?? ($subscription['offerName'] ?? 'Subscription'),
                    'quantity' => intval($subscription['quantity'] ?? 0),
                    'status_text' => $subscription['status'] ?? '',
                    'enabled_units' => intval($subscription['quantity'] ?? 0),
                    'consumed_units' => intval($subscription['quantity'] ?? 0),
                    'tenant_domain' => $customer->tenant_domain ?? '',
                );

                M365_LM_Database::upsert_partner_subscription($data);
            }

            M365_LM_Database::log_event(
                'info',
                'partner_sync_licenses',
                'Partner subscriptions synced',
                intval($customer->id),
                array(
                    'partner_customer_id' => $partner_id,
                    'count' => count($items),
                    'http_code' => $result['code'] ?? null,
                    'body_snippet' => isset($result['subscriptions']) ? substr(wp_json_encode($result['subscriptions']), 0, 500) : null,
                )
            );

            $synced++;
        }

        return array(
            'success' => empty($errors),
            'count' => $synced,
            'errors' => $errors,
            'message' => empty($errors) ? 'Partner subscriptions synced' : 'Partner subscriptions synced with errors',
        );
    }
}
