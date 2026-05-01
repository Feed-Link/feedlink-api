# Octane + FrankenPHP Deployment Guide

## Overview

This document describes how to deploy Laravel Octane with FrankenPHP on a Linux production server with Nginx and Supervisor.

## Prerequisites

- Linux production server
- Nginx installed and running
- Supervisor installed (`apt install supervisor` or `yum install supervisor`)
- PHP 8.2+ with required extensions
- FrankenPHP binary (downloaded automatically by Octane)

## 1. Nginx Configuration

Nginx will proxy requests to FrankenPHP which runs on port 8000 by default.

### `/etc/nginx/sites-available/feedlink-api`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.feedlink.com;  # Change to your domain

    # Redirect HTTP to HTTPS (if using SSL)
    # return 301 https://$server_name$request_uri;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
    }
}
```

For HTTPS (recommended):

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.feedlink.com;

    ssl_certificate /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Enable the site:
```bash
ln -s /etc/nginx/sites-available/feedlink-api /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

## 2. Environment Configuration

Update your production `.env` file:

```dotenv
OCTANE_SERVER=frankenphp
OCTANE_HTTPS=true  # Set to true if Nginx handles SSL
```

## 3. Supervisor Configuration

Create a Supervisor configuration file to manage the Octane FrankenPHP process.

### `/etc/supervisor/conf.d/octane-frankenphp.conf`:

```ini
[program:octane-frankenphp]
command=/path/to/feedlink-api/artisan octane:start --server=frankenphp --workers=4
directory=/path/to/feedlink-api
user=www-data  # or your web server user
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/path/to/feedlink-api/storage/logs/octane.log
stopwaitsecs=3600
```

Notes:
- `--workers=4`: Adjust based on your server's CPU cores (recommended: 2-4 per core)
- `user`: Should match your Nginx/PHP-FPM user (commonly `www-data` or `nginx`)
- `stopwaitsecs=3600`: Allows graceful shutdown for long-running requests

Reload Supervisor:
```bash
supervisorctl reread
supervisorctl update
supervisorctl start octane-frankenphp
```

Check status:
```bash
supervisorctl status octane-frankenphp
```

## 4. Starting and Stopping Octane

### Start:
```bash
php artisan octane:start --server=frankenphp
```

### Stop:
```bash
php artisan octane:stop
```

### Restart (after deployment):
```bash
php artisan octane:reload  # Graceful reload
# or
supervisorctl restart octane-frankenphp  # Via Supervisor
```

## 5. Verifying the Setup

1. Check if FrankenPHP is running:
   ```bash
   ps aux | grep frankenphp
   ```

2. Test the API endpoint:
   ```bash
   curl -I http://localhost:8000/api/health  # or your health endpoint
   ```

3. Check Nginx logs for proxy errors:
   ```bash
   tail -f /var/log/nginx/error.log
   ```

## 6. Important Notes for FeedLink

- **Database connections**: Octane keeps the app in memory. The `DisconnectFromDatabases` listener is enabled in `config/octane.php` to handle this.
- **Passport tokens**: JWT tokens work fine with Octane as they're validated per-request.
- **Scheduled commands**: Keep using `routes/console.php` with Laravel's scheduler (managed by Supervisor separately).
- **File uploads**: The `EnsureUploadedFilesAreValid` and `EnsureUploadedFilesCanBeMoved` listeners are enabled by default.

## 7. Troubleshooting

### FrankenPHP not starting:
- Check logs: `storage/logs/octane.log`
- Verify PHP extensions: `php -m | grep -E 'pcntl|posix|sockets'`

### Nginx 502 Bad Gateway:
- Check if FrankenPHP is running: `supervisorctl status octane-frankenphp`
- Verify port 8000 is not blocked by firewall

### Memory leaks:
- The `CollectGarbage` listener is enabled and will run when memory exceeds 50MB (configurable in `config/octane.php`)
