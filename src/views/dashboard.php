<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rextifire Monitor</title>
    <?php require_once __DIR__ . '/partials/header.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #1a1a1a;
            color: #00ff00;
            font-family: monospace;
        }
        .container { max-width: 1200px; }
        .header {
            border-bottom: 1px solid #333;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .site-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        .site-card {
            background: #222;
            border: 1px solid #333;
            padding: 1rem;
            border-radius: 4px;
        }
        .site-name {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: #fff;
        }
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        .status-up { background: #00ff00; }
        .status-down { background: #ff0000; }
        .site-info {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 0;
        }
        .refresh-btn {
            background: transparent;
            border: 1px solid #333;
            color: #00ff00;
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }
        .refresh-btn:hover {
            background: #333;
            color: #00ff00;
        }
        .refresh-btn:disabled {
            opacity: 0.5;
        }
        .uptime-graph {
            height: 100px;
            margin-top: 1rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
        }
        .floating-stats {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        .stats-content {
            background: #222;
            border: 1px solid #333;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 0.8rem;
            color: #00ff00;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .next-refresh, .last-update {
            margin: 2px 0;
        }
    </style>
</head>
<body>
    <div class="floating-stats">
        <div class="stats-content">
            <div class="next-refresh">Next refresh in: <span id="countdown">5:00</span></div>
            <div class="last-update">Last update: <span id="last-update">-</span></div>
        </div>
    </div>
    <div class="container py-4">
        <div class="header d-flex justify-content-between align-items-center">
            <h1 class="h5 mb-0">Rextifire Monitor</h1>
            <button class="refresh-btn" onclick="checkAllSites()">Refresh</button>
        </div>
        <div class="site-grid" id="sites-container"></div>
    </div>

    <script>
        const statusHistory = new Map();
        let isLoading = false;
        let countdownInterval;
        const REFRESH_INTERVAL = 300; // 5 minutes in seconds

        async function getUptimeData(url) {
            try {
                const response = await fetch(`?action=get-uptime&url=${encodeURIComponent(url)}`);
                const data = await response.json();
                return data;
            } catch (error) {
                console.error('Error fetching uptime data:', error);
                return [];
            }
        }

        async function updateSiteStatus() {
            if (isLoading) return;
            isLoading = true;

            const container = document.getElementById('sites-container');
            try {
                const response = await fetch('?action=check-status');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                container.innerHTML = '';

                for (const [url, status] of Object.entries(data)) {
                    const card = document.createElement('div');
                    card.className = 'site-card';
                    
                    // Create uptime graph container
                    const graphId = `graph-${url.replace(/[^\w]/g, '-')}`;
                    
                    card.innerHTML = `
                        <div class="site-name">
                            <span class="status-indicator ${status.isUp ? 'status-up' : 'status-down'}"></span>
                            ${status.name || new URL(url).hostname}
                        </div>
                        <div class="site-info">
                            ${status.environment} | ${status.isUp ? 'Online' : 'Offline'} | ${status.status}
                            ${status.error ? `<br>${status.error}` : ''}
                            ${status.response_time ? `<br>Response: ${status.response_time}ms` : ''}
                        </div>
                        <div class="uptime-graph">
                            <canvas id="${graphId}"></canvas>
                        </div>
                    `;
                    
                    container.appendChild(card);
                    
                    // Get and render uptime data
                    const uptimeData = await getUptimeData(url);
                    const ctx = document.getElementById(graphId).getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: uptimeData.map(d => d.timestamp),
                            datasets: [{
                                data: uptimeData.map(d => d.isUp ? 100 : 0),
                                borderColor: '#00ff00',
                                backgroundColor: 'rgba(0, 255, 0, 0.1)',
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: {
                                    display: false,
                                    min: 0,
                                    max: 100
                                },
                                x: { display: false }
                            },
                            animation: false
                        }
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                container.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            } finally {
                isLoading = false;
            }
        }

        function updateLastRefreshTime() {
            const now = new Date();
            document.getElementById('last-update').textContent = now.toLocaleTimeString();
        }

        function startCountdown() {
            let timeLeft = REFRESH_INTERVAL;
            
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }

            countdownInterval = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                document.getElementById('countdown').textContent = 
                    `${minutes}:${seconds.toString().padStart(2, '0')}`;

                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    startCountdown();
                }
                timeLeft--;
            }, 1000);
        }

        async function checkAllSites() {
            if (isLoading) return;
            const button = document.querySelector('.refresh-btn');
            button.disabled = true;
            await updateSiteStatus();
            button.disabled = false;
            
            // Update stats
            updateLastRefreshTime();
            startCountdown();
        }

        // Initial check
        checkAllSites();

        // Auto refresh every 5 minutes
        setInterval(checkAllSites, REFRESH_INTERVAL * 1000);
    </script>
</body>
</html>