# PHP Metrics Exporter

A lightweight HTTP service that exposes Prometheus-compatible metrics from a PHP backend. This Docker image acts as a sidecar container to collect and expose metrics from PHP-FPM processes, including FPM status and OPCache statistics.

## Features

- Lightweight Alpine-based Docker image
- Exposes PHP-FPM metrics in Prometheus format
- Collects OPCache statistics and JIT metrics
- Provides per-process and pool-level metrics
- Simple HTTP server using lighttpd
- Automatic metrics script download and update
- Minimal configuration required

## Docker Images

The image is available from both Docker Hub and GitHub Container Registry:

```bash
# Docker Hub
docker pull framjet/php-metrics-exporter:latest

# GitHub Container Registry
docker pull ghcr.io/framjet/docker-php-metrics-exporter:latest
```

## Architecture

The PHP Metrics Exporter works as a sidecar container that:
1. Connects to a PHP-FPM container via FastCGI protocol
2. Executes a PHP script that collects metrics using `fpm_get_status()` and `opcache_get_status()`
3. Exposes these metrics via HTTP in Prometheus format

## Usage

### Docker Compose

Here's a basic example using docker-compose:

```yaml
services:
  php-fpm:
    image: framjet/php:8.4-prod
    container_name: fpm
    volumes:
      - "metrics:/shared/"

  php-metrics-exporter:
    image: framjet/php-metrics-exporter:latest
    container_name: php-metrics-exporter
    environment:
      - PHP_FPM_SERVER=php-fpm
      - PHP_FPM_PORT=9000
    volumes:
      - "metrics:/shared/"
    ports:
      - 1993:80

volumes:
  metrics:
```

### Kubernetes with emptyDir

Example of using PHP Metrics Exporter as a sidecar container in a Kubernetes pod:

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: php-app
  labels:
    app: php-app
spec:
  containers:
  - name: php-fpm
    image: framjet/php:8.4-prod
    ports:
    - containerPort: 9000
    volumeMounts:
    - name: metrics-shared
      mountPath: /shared
    
  - name: php-metrics-exporter
    image: framjet/php-metrics-exporter:latest
    ports:
    - containerPort: 80
      name: metrics
    env:
    - name: PHP_FPM_SERVER
      value: "localhost"
    - name: PHP_FPM_PORT
      value: "9000"
    volumeMounts:
    - name: metrics-shared
      mountPath: /shared
      
  volumes:
  - name: metrics-shared
    emptyDir: {}
```

### Kubernetes Service and ServiceMonitor

To expose metrics for Prometheus scraping:

```yaml
apiVersion: v1
kind: Service
metadata:
  name: php-app-metrics
  labels:
    app: php-app
spec:
  selector:
    app: php-app
  ports:
  - name: metrics
    port: 80
    targetPort: metrics
---
apiVersion: monitoring.coreos.com/v1
kind: ServiceMonitor
metadata:
  name: php-app-monitor
spec:
  selector:
    matchLabels:
      app: php-app
  endpoints:
  - port: metrics
    interval: 30s
    path: /metrics
```

## Configuration

The exporter can be configured using environment variables:

| Variable                          | Default               | Description                                 |
|-----------------------------------|-----------------------|---------------------------------------------|
| `PHP_FPM_SERVER`                  | `php-fpm`             | PHP-FPM container hostname                  |
| `PHP_FPM_PORT`                    | `9000`                | PHP-FPM port                                |
| `PHP_FPM_DOCUMENT_ROOT`           | `/shared`             | Document root for FastCGI                   |
| `PHP_FPM_SCRIPT_FILENAME`         | `/shared/metrics.php` | Path to metrics script inside FPM container |
| `PHP_FPM_SCRIPT_NAME`             | `/metrics.php`        | Script name for FastCGI                     |
| `PHP_METRICS_SCRIPT_DOWNLOAD_URL` | (GitHub URL)          | URL to download the metrics script          |

## Endpoints

The service exposes the following endpoints:

- `/` - Welcome page with service information
- `/metrics` - Prometheus metrics endpoint
- `/status` - Basic lighttpd server status

## Metrics

The exporter follows Prometheus naming conventions with appropriate suffixes for different metric types.

### FPM Pool Metrics

- `php_fpm_start_timestamp_seconds` - Start time of the PHP-FPM pool (Unix timestamp)
- `php_fpm_uptime_seconds` - Seconds since start of the PHP-FPM pool
- `php_fpm_accepted_connections_total` - Total accepted connections (counter)
- `php_fpm_listen_queue` - Current listen queue size
- `php_fpm_max_listen_queue` - Maximum listen queue size reached
- `php_fpm_listen_queue_length` - Listen queue length
- `php_fpm_processes{state="idle|active"}` - Number of processes by state
- `php_fpm_processes_total` - Total number of processes
- `php_fpm_max_active_processes` - Maximum number of active processes reached
- `php_fpm_max_children_reached_total` - Number of times max children limit was reached (counter)
- `php_fpm_slow_requests_total` - Total number of slow requests (counter)
- `php_fpm_memory_peak_bytes` - Peak memory usage in bytes

### FPM Process Metrics

- `php_fpm_process_start_timestamp_seconds` - Process start time (Unix timestamp)
- `php_fpm_process_uptime_seconds` - Seconds since process start
- `php_fpm_process_requests_total` - Total number of requests served (counter)
- `php_fpm_process_request_duration_seconds` - Current request duration in seconds
- `php_fpm_process_request_length_bytes` - Request content length in bytes
- `php_fpm_process_last_request_cpu_percent` - CPU percentage used by last request (0-100)
- `php_fpm_process_last_request_memory_bytes` - Memory usage of last request in bytes

### OPCache Metrics

- `php_opcache_memory_bytes{state="used|free|wasted"}` - OPcache memory usage in bytes
- `php_opcache_wasted_memory_ratio` - Current wasted memory ratio (0-1)
- `php_opcache_interned_strings_memory_bytes{state="used|free"}` - Interned strings memory usage in bytes
- `php_opcache_interned_strings_buffer_size_bytes` - Interned strings buffer size in bytes
- `php_opcache_interned_strings_total` - Total number of interned strings
- `php_opcache_cached_scripts_total` - Total number of cached scripts
- `php_opcache_cached_keys_total` - Total number of cached keys
- `php_opcache_max_cached_keys` - Maximum number of cached keys allowed
- `php_opcache_hits_total` - Total cache hits (counter)
- `php_opcache_misses_total` - Total cache misses (counter)
- `php_opcache_start_timestamp_seconds` - OPcache start time (Unix timestamp)
- `php_opcache_last_restart_timestamp_seconds` - Last restart time (Unix timestamp)
- `php_opcache_restarts_total{type="oom|hash|manual"}` - Total restart count by type (counter)
- `php_opcache_blacklist_misses_total` - Total blacklist misses (counter)
- `php_opcache_blacklist_miss_ratio` - Blacklist miss ratio (0-1)
- `php_opcache_hit_ratio` - Cache hit ratio (0-1)
- `php_opcache_jit_buffer_size_bytes` - JIT buffer size in bytes
- `php_opcache_jit_buffer_free_bytes` - JIT buffer free space in bytes

### OPCache Script Metrics

- `php_opcache_script_hits_total` - Total script cache hits (counter)
- `php_opcache_script_memory_bytes` - Script memory consumption in bytes
- `php_opcache_script_last_used_timestamp_seconds` - Script last used timestamp (Unix timestamp)
- `php_opcache_script_revalidate_timestamp_seconds` - Script revalidate timestamp (Unix timestamp)

## How It Works

1. The exporter container shares a volume with the PHP-FPM container
2. On startup, it downloads the metrics collection script to the shared volume
3. lighttpd receives requests to `/metrics` and forwards them to PHP-FPM via FastCGI
4. PHP-FPM executes the metrics script which collects internal statistics
5. The metrics are returned in Prometheus format

## Requirements

- PHP-FPM must be configured to allow status requests
- The shared volume must be writable by both containers
- Network connectivity between the exporter and PHP-FPM containers

## Development

To build the image locally:

```bash
docker build -t php-metrics-exporter .
```

To run with custom metrics script:

```bash
docker run -e PHP_METRICS_SCRIPT_DOWNLOAD_URL=https://your-url/metrics.php php-metrics-exporter
```

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/framjet/docker-php-metrics-exporter/issues).
