<?php

namespace App\core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Monitor
{
    private $client;
    private $websites;
    private $notifier;
    private $logPath;
    private $configPath;

    public function __construct()
    {
        date_default_timezone_set('Asia/Singapore');
        $this->client = new Client(['timeout' => 10]);
        $this->configPath = __DIR__ . '/../public/config/websites.json';
        $this->logPath = __DIR__ . '/../../storage/logs/cron.log';
        $this->loadWebsites();
        $this->notifier = new TelegramNotifier(
            $_ENV['TELEGRAM_BOT_TOKEN'],
            $_ENV['TELEGRAM_CHAT_ID']
        );
    }

    private function loadWebsites(): void
    {
        if (!file_exists($this->configPath)) {
            throw new \RuntimeException('Websites configuration file not found');
        }

        $config = json_decode(file_get_contents($this->configPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid websites configuration format');
        }

        $this->websites = [];
        foreach ($config as $group) {
            foreach ($group['sites'] as $site) {
                $this->websites[] = $site;
            }
        }
    }

    public function checkWebsites(): array
    {
        $results = [];
        
        foreach ($this->websites as $site) {
            try {
                $startTime = microtime(true);
                $response = $this->client->get($site['url']);
                $endTime = microtime(true);
                
                $status = [
                    'name' => $site['name'],
                    'environment' => $site['environment'],
                    'status' => $response->getStatusCode(),
                    'isUp' => true,
                    'response_time' => round(($endTime - $startTime) * 1000, 2),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                $this->logStatus($site['url'], $status);
                $results[$site['url']] = $status;

                // Uncomment the following lines if you want to send notifications when a site is up
                // This is optional and can be controlled by the 'ignore_notification' flag in the site config.
                // if (!($site['ignore_notification'] ?? false)) {
                //     $this->notifier->sendNotification(
                //         "✅ {$site['name']} ({$site['environment']}) is up"
                //     );
                // }
                
            } catch (\Exception $e) {
                $status = [
                    'name' => $site['name'],
                    'environment' => $site['environment'],
                    'status' => 0,
                    'isUp' => false,
                    'error' => $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                $this->logStatus($site['url'], $status);
                $results[$site['url']] = $status;

                if (!($site['ignore_notification'] ?? false)) {
                    $this->notifier->sendNotification(
                        "🚨 {$site['name']} ({$site['environment']}) is down! Error: {$e->getMessage()}"
                    );
                }
            }
        }
        
        return $results;
    }

    private function logStatus(string $url, array $status): void
    {
        // Set timezone to GMT+8
        date_default_timezone_set('Asia/Singapore');

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $url,
            'status' => $status['status'],
            'isUp' => $status['isUp'],
            'response_time' => $status['response_time'] ?? 0
        ];

        file_put_contents(
            $this->logPath,
            json_encode($logEntry) . "\n",
            FILE_APPEND
        );

        // Reset to server default timezone
        date_default_timezone_set(date_default_timezone_get());
    }

    public function getUptimeData(string $url, int $hours = 24): array
    {
        if (!file_exists($this->logPath)) {
            return [];
        }

        $uptimeData = [];
        $lines = file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoffTime = strtotime("-{$hours} hours");

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && 
                $entry['url'] === $url && 
                strtotime($entry['timestamp']) >= $cutoffTime
            ) {
                $uptimeData[] = $entry;
            }
        }

        return $uptimeData;
    }

    public function getUptimeStats(string $url, int $hours = 24): array
    {
        $data = $this->getUptimeData($url, $hours);
        
        if (empty($data)) {
            return [
                'uptime_percentage' => 0,
                'avg_response_time' => 0,
                'total_checks' => 0,
                'total_failures' => 0
            ];
        }

        $totalChecks = count($data);
        $upChecks = count(array_filter($data, fn($entry) => $entry['isUp']));
        $responseTimes = array_column($data, 'response_time');

        return [
            'uptime_percentage' => round(($upChecks / $totalChecks) * 100, 2),
            'avg_response_time' => round(array_sum($responseTimes) / $totalChecks, 2),
            'total_checks' => $totalChecks,
            'total_failures' => $totalChecks - $upChecks
        ];
    }

    public function cleanOldLogs(int $daysToKeep = 30): void
    {
        if (!file_exists($this->logPath)) {
            return;
        }

        $lines = file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoffTime = strtotime("-{$daysToKeep} days");
        $newLogs = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && strtotime($entry['timestamp']) >= $cutoffTime) {
                $newLogs[] = $line;
            }
        }

        file_put_contents($this->logPath, implode("\n", $newLogs) . "\n");
    }
}