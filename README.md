# Rextifire Site Monitoring

## Overview
This project is a simple PHP site monitoring dashboard that checks the availability of multiple websites and sends notifications via a Telegram bot if any of the sites are down.

## Features
- Monitors a list of websites for availability
- Sends notifications to a Telegram chat when a website is down
- Configurable through environment variables
- Simple web dashboard interface

## Project Structure
```
Rextifire-Site-Monitoring/
├── src/
│   ├── Core/
│   │   ├── Monitor.php
│   │   └── TelegramNotifier.php
│   ├── public/
│   │   └── config/
│   │       └── websites.json
│   ├── views/
│   │   └── dashboard.php
│   └── index.php
├── cron/
│   └── check-sites.php
├── storage/
│   └── logs/
│       └── cron.log
├── vendor/
├── composer.json
├── .env.example
├── .env
└── README.md
```

Each directory serves a specific purpose:
- `src/`: Contains the main application code
  - `Core/`: Core classes for monitoring and notifications
  - `public/`: Public assets and configurations
  - `views/`: Dashboard and other view files
- `cron/`: Contains the cron job script
- `storage/`: Contains logs and other generated files
- `vendor/`: Composer dependencies
- `.env`: Environment configuration

## Prerequisites
### Installing Composer

#### On macOS:
1. Using Homebrew:
   ```bash
   brew install composer
   ```
2. Manual installation:
   ```bash
   php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
   php composer-setup.php
   php -r "unlink('composer-setup.php');"
   sudo mv composer.phar /usr/local/bin/composer
   ```

#### On Windows:
1. Download and run the Composer-Setup.exe from https://getcomposer.org/download/
2. Follow the installation wizard
3. Restart your computer after installation

## Installation
1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/Rextifire-Site-Monitoring.git
   cd Rextifire-Site-Monitoring
   ```

2. Install dependencies using Composer:
   ```bash
   composer install
   ```

3. Create necessary directories:
   ```bash
   mkdir -p storage/logs
   chmod -R 755 storage/logs
   ```

4. Configure your environment:
   ```bash
   cp .env.example .env
   ```
   Edit `.env` file and add your:
   - Telegram Bot Token
   - Telegram Chat ID

5. Create website configuration:
   ```bash
   mkdir -p src/public/config
   ```
   Create `websites.json` in the config directory:
   ```json
   {
       "group1": {
           "name": "My Websites",
           "sites": [
               {
                   "url": "https://example.com",
                   "name": "Example Site",
                   "environment": "production",
                   "ignore_notification": false
               }
           ]
       }
   }
   ```

6. Set up the cron job:
   ```bash
   # Open crontab editor
   crontab -e
   
   # Add this line (replace path with your actual project path)
   * * * * * /usr/bin/php /Users/yourusername/Documents/GitHub/Rextifire-Site-Monitoring/cron/check-sites.php >> /Users/yourusername/Documents/GitHub/Rextifire-Site-Monitoring/storage/logs/cron.log 2>&1
   ```

   To verify cron job is set:
   ```bash
   crontab -l
   ```

## Usage
1. Start your local PHP server:
   ```bash
   composer start
   ```

2. Access the dashboard:
   Open your browser and visit `http://localhost:8000`

3. Monitor your logs:
   ```bash
   tail -f storage/logs/cron.log
   ```

4. The monitoring service will:
   - Check websites every minute via cron job
   - Update the dashboard in real-time
   - Send Telegram notifications for down sites
   - Keep 30 days of monitoring history

## Configuration Options

### Website Configuration
In `src/public/config/websites.json`:
- `name`: Group name for websites
- `sites`: Array of sites to monitor
  - `url`: Website URL to monitor
  - `name`: Display name
  - `environment`: Environment label (production/staging/development)
  - `ignore_notification`: Set true to disable Telegram notifications

### Monitoring Settings
- Checks run every minute via cron
- Logs are automatically cleaned after 30 days
- Response timeout is set to 10 seconds
- Dashboard auto-refreshes every minute

## Troubleshooting

### Cron Issues
1. Check cron logs:
   ```bash
   tail -f storage/logs/cron.log
   ```

2. Verify cron permissions:
   ```bash
   chmod +x cron/check-sites.php
   ```

3. Test cron script manually:
   ```bash
   php cron/check-sites.php
   ```

### Permission Issues
```bash
chmod -R 755 storage/logs
chown -R $(whoami) storage/logs
```

## API Endpoints

### GET Parameters
The monitoring system provides the following API endpoints:

#### Check Status
```bash
# Get current status of all monitored websites
curl "https://monitor.rextifire.com/?action=check-status"
```
Response:
```json
{
  "https://example.com": {
    "status": 200,
    "isUp": true,
    "response_time": 0.432,
    "timestamp": "2025-06-05 14:30:00"
  }
}
```

#### Get Uptime
```bash
# Get uptime data for a specific URL
curl "https://monitor.rextifire.com/?action=get-uptime&url=https://example.com&hours=24"
```
Parameters:
- `url`: Required - The URL to check uptime for
- `hours`: Optional - Number of hours of history (default: 24)

Response:
```json
{
  "uptime_percentage": 99.8,
  "history": [
    {
      "timestamp": "2025-06-05 14:00:00",
      "status": 200,
      "isUp": true
    }
  ]
}
```

#### Manual Cron Check
```bash
# Trigger manual monitoring check
curl "https://monitor.rextifire.com/?action=get-cron"
```
Response:
```json
{
  "success": true,
  "message": "Cron check completed",
  "downSites": [
    {
      "url": "https://example.com",
      "error": "Connection timeout",
      "timestamp": "2025-06-05 14:30:00"
    }
  ],
  "timestamp": "2025-06-05 14:30:00"
}
```

### Error Responses
All endpoints may return the following error structure:
```json
{
  "error": "Error type",
  "message": "Detailed error message",
  "timestamp": "2025-06-05 14:30:00"
}
```

Common HTTP Status Codes:
- 200: Success
- 400: Bad Request (missing parameters)
- 500: Internal Server Error