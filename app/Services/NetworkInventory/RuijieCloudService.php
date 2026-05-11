<?php

namespace App\Services\NetworkInventory;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ruijie Cloud Open API integration.
 *
 * API base URL and exact endpoints must be confirmed from:
 * https://www.ruijienetworks.com/developer  (Ruijie Open Platform)
 *
 * Credentials are stored in SSM:
 *   /crcmis/prod/RUIJIE_CLOUD_API_KEY
 *   /crcmis/prod/RUIJIE_CLOUD_API_SECRET
 *   /crcmis/prod/RUIJIE_CLOUD_BASE_URL  (e.g. https://openapi.ruijienetworks.com)
 */
class RuijieCloudService
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('services.ruijie_cloud.base_url', ''), '/');
        $this->apiKey    = config('services.ruijie_cloud.api_key', '');
        $this->apiSecret = config('services.ruijie_cloud.api_secret', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl)
            && ! empty($this->apiKey)
            && ! empty($this->apiSecret);
    }

    /**
     * Fetch all wireless clients from cloud-managed APs.
     * Returns array of normalized device arrays ready for NetworkDeviceSyncService.
     */
    public function getWirelessClients(): array
    {
        if (! $this->isConfigured()) {
            Log::warning('RuijieCloudService: credentials not configured — skipping sync.');
            return [];
        }

        try {
            $token = $this->getAccessToken();
            if (! $token) return [];

            // Fetch wireless clients
            // NOTE: Verify exact endpoint from Ruijie Cloud API docs
            $response = Http::withToken($token)
                ->timeout(30)
                ->get("{$this->baseUrl}/v1/wireless/clients", [
                    'page'     => 1,
                    'pageSize' => 500,
                ]);

            if (! $response->successful()) {
                Log::error('RuijieCloudService: failed to fetch clients', [
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();

            // Normalize the response into our standard device format
            // NOTE: Adjust field names to match actual Ruijie Cloud API response structure
            return $this->normalizeClients($data['data'] ?? $data['list'] ?? []);

        } catch (\Throwable $e) {
            Log::error('RuijieCloudService: exception fetching clients', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Authenticate with Ruijie Cloud and get a Bearer access token.
     * NOTE: Verify the exact auth endpoint and request format from API docs.
     */
    private function getAccessToken(): ?string
    {
        if ($this->accessToken) return $this->accessToken;

        try {
            $response = Http::timeout(15)
                ->post("{$this->baseUrl}/v1/oauth/token", [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->apiKey,
                    'client_secret' => $this->apiSecret,
                ]);

            if (! $response->successful()) {
                Log::error('RuijieCloudService: authentication failed', [
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
                return null;
            }

            $this->accessToken = $response->json('access_token');
            return $this->accessToken;

        } catch (\Throwable $e) {
            Log::error('RuijieCloudService: auth exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Normalize Ruijie Cloud client records to our standard device array format.
     * NOTE: Adjust field mappings to match actual Ruijie Cloud API response.
     */
    private function normalizeClients(array $clients): array
    {
        $devices = [];

        foreach ($clients as $client) {
            $mac = $client['mac']        ?? $client['macAddress']   ?? $client['mac_address'] ?? null;
            $ip  = $client['ip']         ?? $client['ipAddress']    ?? $client['ip_address']  ?? null;
            $ap  = $client['apName']     ?? $client['ap_name']      ?? $client['apMac']       ?? null;
            $sig = $client['rssi']       ?? $client['signal']       ?? $client['signalStrength'] ?? null;
            $ssid = $client['ssid']      ?? $client['wlanName']     ?? null;
            $host = $client['hostname']  ?? $client['clientName']   ?? null;
            $vlan = $client['vlanId']    ?? $client['vlan_id']      ?? null;
            $id   = $client['clientId']  ?? $client['id']           ?? null;

            if (! $mac) continue;

            $devices[] = [
                'mac_address'     => $mac,
                'ip_address'      => $ip,
                'hostname'        => $host,
                'vendor'          => null, // enriched by sync service
                'ap_name'         => $ap,
                'ssid'            => $ssid,
                'signal_dbm'      => $sig ? (int) $sig : null,
                'vlan_id'         => $vlan ? (int) $vlan : null,
                'vlan_name'       => $client['vlanName'] ?? null,
                'cloud_id'        => $id,
            ];
        }

        return $devices;
    }
}
