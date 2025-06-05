<?php
require_once __DIR__ . '/../vendor/autoload.php';

try {
    // Load environment variables
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    // Initialize the monitor
    $monitor = new App\core\Monitor();
    
    // Handle AJAX requests
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        
        switch ($_GET['action']) {
            case 'check-status':
                try {
                    $results = $monitor->checkWebsites();
                    echo json_encode($results);
                } catch (\Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Internal server error']);
                }
                break;

            case 'get-uptime':
                try {
                    if (!isset($_GET['url'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'URL parameter is required']);
                        break;
                    }
                    
                    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
                    $uptimeData = $monitor->getUptimeData($_GET['url'], $hours);
                    echo json_encode($uptimeData);
                } catch (\Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Internal server error']);
                }
                break;
            
            case 'get-cron':
                try {
                    // Set timezone to GMT+8
                    date_default_timezone_set('Asia/Singapore');

                    // Initialize monitor with minimal components for cron
                    $cronMonitor = new App\Core\Monitor([
                        'enableDashboard' => false,
                        'logOnly' => true,
                        'notifyOnDown' => true
                    ]);

                    // Run the check
                    $results = $cronMonitor->checkWebsites();

                    // Log results
                    $downSites = [];
                    foreach ($results as $url => $status) {
                        if (!$status['isUp']) {
                            $downSites[] = [
                                'url' => $url,
                                'error' => $status['error'] ?? 'Unknown error',
                                'timestamp' => date('Y-m-d H:i:s')
                            ];
                            error_log(sprintf(
                                "[%s] Site %s is DOWN: %s",
                                date('Y-m-d H:i:s'),
                                $url,
                                $status['error'] ?? 'Unknown error'
                            ));
                        }
                    }

                    // Clean old logs once per day (if it's midnight)
                    if (date('H:i') === '00:00') {
                        $cronMonitor->cleanOldLogs(30); // Keep 30 days of logs
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Cron check completed',
                        'downSites' => $downSites,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                } catch (\Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Cron check failed',
                        'message' => $e->getMessage(),
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
        }
        exit;
    }

    // Display the dashboard
    include __DIR__ . '/views/dashboard.php';

} catch (\Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>