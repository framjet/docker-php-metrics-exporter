<?php

class PrometheusMetricsExporter
{
    private array $metrics = [];

    /**
     * Add a metric to the output buffer
     */
    private function addMetric($name, $value, $labels = [], $help = '', $type = 'gauge'): void
    {
        // Skip metrics with invalid values
        if ($value === -1 || $value === null) {
            return;
        }

        $key = $name;
        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = [
                'name' => $name,
                'help' => $help,
                'type' => $type,
                'values' => []
            ];
        }

        $labelStr = '';
        if (!empty($labels)) {
            $labelParts = [];
            foreach ($labels as $k => $v) {
                // Escape quotes and backslashes in label values
                $v = str_replace(['\\', '"'], ['\\\\', '\\"'], $v);
                $labelParts[] = sprintf('%s="%s"', $k, $v);
            }
            $labelStr = '{' . implode(',', $labelParts) . '}';
        }

        $this->metrics[$key]['values'][] = [
            'labels' => $labelStr,
            'value' => $value
        ];
    }

    /**
     * Export FPM metrics
     */
    public function exportFpmMetrics($options = []): void
    {
        if (!function_exists('fpm_get_status')) {
            return;
        }

        $fpmStatus = fpm_get_status();
        if ($fpmStatus === false) {
            return;
        }

        $prefix = $options['prefix'] ?? 'php_fpm_';

        // Base labels
        $baseLabels = [
            'pool' => $fpmStatus['pool'] ?? 'unknown',
            'pm' => $fpmStatus['process-manager'] ?? 'unknown',
        ];

        if ($options['pool'] ?? true) {
            // Use _timestamp suffix for Unix timestamps
            $this->addMetric($prefix . 'start_timestamp_seconds', $fpmStatus['start-time'] ?? -1,
                $baseLabels, 'Start time of the PHP-FPM pool (Unix timestamp)');

            // Use _seconds suffix for durations
            $this->addMetric($prefix . 'uptime_seconds', $fpmStatus['start-since'] ?? -1,
                $baseLabels, 'Seconds since start of the PHP-FPM pool');

            // Use _total suffix for counters
            $this->addMetric($prefix . 'accepted_connections_total', $fpmStatus['accepted-conn'] ?? -1,
                $baseLabels, 'Total accepted connections', 'counter');

            // Queue metrics don't need special suffixes as they're counts
            $this->addMetric($prefix . 'listen_queue', $fpmStatus['listen-queue'] ?? -1,
                $baseLabels, 'Current listen queue size');
            $this->addMetric($prefix . 'max_listen_queue', $fpmStatus['max-listen-queue'] ?? -1,
                $baseLabels, 'Maximum listen queue size reached');
            $this->addMetric($prefix . 'listen_queue_length', $fpmStatus['listen-queue-len'] ?? -1,
                $baseLabels, 'Listen queue length');

            // Process counts - no special suffix needed
            $this->addMetric($prefix . 'processes', $fpmStatus['idle-processes'] ?? -1,
                array_merge($baseLabels, ['state' => 'idle']), 'Number of processes by state');
            $this->addMetric($prefix . 'processes', $fpmStatus['active-processes'] ?? -1,
                array_merge($baseLabels, ['state' => 'active']), 'Number of processes by state');

            $this->addMetric($prefix . 'processes_total', $fpmStatus['total-processes'] ?? -1,
                $baseLabels, 'Total number of processes');
            $this->addMetric($prefix . 'max_active_processes', $fpmStatus['max-active-processes'] ?? -1,
                $baseLabels, 'Maximum number of active processes reached');
            $this->addMetric($prefix . 'max_children_reached_total', $fpmStatus['max-children-reached'] ?? -1,
                $baseLabels, 'Number of times max children limit was reached', 'counter');

            // Use _total suffix for counters
            $this->addMetric($prefix . 'slow_requests_total', $fpmStatus['slow-requests'] ?? -1,
                $baseLabels, 'Total number of slow requests', 'counter');

            // Use _bytes suffix for memory
            $this->addMetric($prefix . 'memory_peak_bytes', $fpmStatus['memory-peak'] ?? -1,
                $baseLabels, 'Peak memory usage in bytes');
        }

        if ($options['processes'] ?? true) {
            foreach ($fpmStatus['procs'] ?? [] as $proc) {
                $procLabels = [
                    'pool' => $fpmStatus['pool'] ?? 'unknown',
                    'pm' => $fpmStatus['process-manager'] ?? 'unknown',
                    'pid' => $proc['pid'] ?? 'unknown',
                    'state' => $proc['state'] ?? 'unknown',
                    'method' => $proc['request-method'] ?? 'unknown',
                    'uri' => $proc['request-uri'] ?? 'unknown',
                    'user' => $proc['user'] ?? 'unknown',
                    'script' => $proc['script'] ?? 'unknown',
                ];

                // Unix timestamp
                $this->addMetric($prefix . 'process_start_timestamp_seconds', $proc['start-time'] ?? -1,
                    $procLabels, 'Process start time (Unix timestamp)');

                // Duration in seconds
                $this->addMetric($prefix . 'process_uptime_seconds', $proc['start-since'] ?? -1,
                    $procLabels, 'Seconds since process start');

                // Counter
                $this->addMetric($prefix . 'process_requests_total', $proc['requests'] ?? -1,
                    $procLabels, 'Total number of requests served', 'counter');

                // Duration in microseconds (convert to seconds for Prometheus)
                if (isset($proc['request-duration']) && $proc['request-duration'] !== -1) {
                    $this->addMetric($prefix . 'process_request_duration_seconds',
                        $proc['request-duration'] / 1000000, // Convert microseconds to seconds
                        $procLabels, 'Current request duration in seconds');
                }

                // Bytes
                $this->addMetric($prefix . 'process_request_length_bytes', $proc['request-length'] ?? -1,
                    $procLabels, 'Request content length in bytes');

                // CPU percentage (0-100)
                $this->addMetric($prefix . 'process_last_request_cpu_percent', $proc['last-request-cpu'] ?? -1,
                    $procLabels, 'CPU percentage used by last request');

                // Memory in bytes
                $this->addMetric($prefix . 'process_last_request_memory_bytes', $proc['last-request-memory'] ?? -1,
                    $procLabels, 'Memory usage of last request in bytes');
            }
        }
    }

    /**
     * Export OPCache metrics
     */
    public function exportOPCacheMetrics($options = []): void
    {
        if (!function_exists('opcache_get_status')) {
            return;
        }

        $opcacheStatus = opcache_get_status();
        if ($opcacheStatus === false) {
            return;
        }

        $prefix = $options['prefix'] ?? 'php_opcache_';

        $jit = $opcacheStatus['jit'] ?? [];
        $baseLabels = [
            'jit_enabled' => $jit['enabled'] ?? 'unknown',
            'jit_on' => $jit['on'] ?? 'unknown',
            'jit_kind' => $jit['kind'] ?? 'unknown',
            'jit_opt_level' => $jit['opt_level'] ?? 'unknown',
            'jit_opt_flags' => $jit['opt_flags'] ?? 'unknown',
        ];

        // Memory usage metrics with _bytes suffix
        $memory = $opcacheStatus['memory_usage'] ?? [];
        $this->addMetric($prefix . 'memory_bytes', $memory['used_memory'] ?? -1,
            array_merge($baseLabels, ['state' => 'used']), 'OPcache memory usage in bytes');
        $this->addMetric($prefix . 'memory_bytes', $memory['free_memory'] ?? -1,
            array_merge($baseLabels, ['state' => 'free']), 'OPcache memory usage in bytes');
        $this->addMetric($prefix . 'memory_bytes', $memory['wasted_memory'] ?? -1,
            array_merge($baseLabels, ['state' => 'wasted']), 'OPcache memory usage in bytes');

        // Use _ratio suffix for percentages (convert percentage to 0-1 ratio)
        if (isset($memory['current_wasted_percentage']) && $memory['current_wasted_percentage'] !== -1) {
            $this->addMetric($prefix . 'wasted_memory_ratio',
                $memory['current_wasted_percentage'] / 100,
                $baseLabels, 'Current wasted memory ratio (0-1)');
        }

        // Interned strings usage with proper suffixes
        $internedStrings = $opcacheStatus['interned_strings_usage'] ?? [];
        $this->addMetric($prefix . 'interned_strings_memory_bytes', $internedStrings['used_memory'] ?? -1,
            array_merge($baseLabels, ['state' => 'used']), 'Interned strings memory usage in bytes');
        $this->addMetric($prefix . 'interned_strings_memory_bytes', $internedStrings['free_memory'] ?? -1,
            array_merge($baseLabels, ['state' => 'free']), 'Interned strings memory usage in bytes');
        $this->addMetric($prefix . 'interned_strings_buffer_size_bytes', $internedStrings['buffer_size'] ?? -1,
            $baseLabels, 'Interned strings buffer size in bytes');
        $this->addMetric($prefix . 'interned_strings_total', $internedStrings['number_of_strings'] ?? -1,
            $baseLabels, 'Total number of interned strings');

        // Statistics with proper suffixes
        $stats = $opcacheStatus['opcache_statistics'] ?? [];
        $this->addMetric($prefix . 'cached_scripts_total', $stats['num_cached_scripts'] ?? -1,
            $baseLabels, 'Total number of cached scripts');
        $this->addMetric($prefix . 'cached_keys_total', $stats['num_cached_keys'] ?? -1,
            $baseLabels, 'Total number of cached keys');
        $this->addMetric($prefix . 'max_cached_keys', $stats['max_cached_keys'] ?? -1,
            $baseLabels, 'Maximum number of cached keys allowed');

        // Counters with _total suffix
        $this->addMetric($prefix . 'hits_total', $stats['hits'] ?? -1,
            $baseLabels, 'Total cache hits', 'counter');
        $this->addMetric($prefix . 'misses_total', $stats['misses'] ?? -1,
            $baseLabels, 'Total cache misses', 'counter');

        // Timestamps with _timestamp_seconds suffix
        $this->addMetric($prefix . 'start_timestamp_seconds', $stats['start_time'] ?? -1,
            $baseLabels, 'OPcache start time (Unix timestamp)');
        $this->addMetric($prefix . 'last_restart_timestamp_seconds', $stats['last_restart_time'] ?? -1,
            $baseLabels, 'Last restart time (Unix timestamp)');

        // Restart counts with _total suffix
        $this->addMetric($prefix . 'restarts_total', $stats['oom_restarts'] ?? -1,
            array_merge($baseLabels, ['type' => 'oom']), 'Total restart count by type', 'counter');
        $this->addMetric($prefix . 'restarts_total', $stats['hash_restarts'] ?? -1,
            array_merge($baseLabels, ['type' => 'hash']), 'Total restart count by type', 'counter');
        $this->addMetric($prefix . 'restarts_total', $stats['manual_restarts'] ?? -1,
            array_merge($baseLabels, ['type' => 'manual']), 'Total restart count by type', 'counter');

        $this->addMetric($prefix . 'blacklist_misses_total', $stats['blacklist_misses'] ?? -1,
            $baseLabels, 'Total blacklist misses', 'counter');

        // Ratios (0-1 range)
        $this->addMetric($prefix . 'blacklist_miss_ratio', $stats['blacklist_miss_ratio'] ?? -1,
            $baseLabels, 'Blacklist miss ratio (0-1)');

        // Convert hit rate percentage to ratio
        if (isset($stats['opcache_hit_rate']) && $stats['opcache_hit_rate'] !== -1) {
            $this->addMetric($prefix . 'hit_ratio',
                $stats['opcache_hit_rate'] / 100,
                $baseLabels, 'Cache hit ratio (0-1)');
        }

        // JIT metrics with proper suffixes
        $jitStatus = $jit;
        $this->addMetric($prefix . 'jit_buffer_size_bytes', $jitStatus['buffer_size'] ?? -1,
            $baseLabels, 'JIT buffer size in bytes');
        $this->addMetric($prefix . 'jit_buffer_free_bytes', $jitStatus['buffer_free'] ?? -1,
            $baseLabels, 'JIT buffer free space in bytes');

        // Script metrics
        if ($options['scripts'] ?? true) {
            $scripts = $opcacheStatus['scripts'] ?? [];
            foreach ($scripts as $script) {
                $scriptLabels = array_merge($baseLabels, [
                    'script' => $script['full_path'] ?? 'unknown',
                ]);

                $this->addMetric($prefix . 'script_hits_total', $script['hits'] ?? -1,
                    $scriptLabels, 'Total script cache hits', 'counter');
                $this->addMetric($prefix . 'script_memory_bytes', $script['memory_consumption'] ?? -1,
                    $scriptLabels, 'Script memory consumption in bytes');
                $this->addMetric($prefix . 'script_last_used_timestamp_seconds', $script['last_used_timestamp'] ?? -1,
                    $scriptLabels, 'Script last used timestamp (Unix timestamp)');
                $this->addMetric($prefix . 'script_revalidate_timestamp_seconds', $script['revalidate'] ?? -1,
                    $scriptLabels, 'Script revalidate timestamp (Unix timestamp)');
            }
        }
    }

    /**
     * Output metrics in Prometheus format
     */
    public function outputMetrics(): void
    {
        header('Content-Type: text/plain; version=0.0.4; charset=utf-8');

        foreach ($this->metrics as $metric) {
            // Output HELP and TYPE lines
            if ($metric['help']) {
                echo "# HELP {$metric['name']} {$metric['help']}\n";
            }
            echo "# TYPE {$metric['name']} {$metric['type']}\n";

            // Output metric values
            foreach ($metric['values'] as $value) {
                echo "{$metric['name']}{$value['labels']} {$value['value']}\n";
            }
            echo "\n";
        }
    }
}

$exporter = new PrometheusMetricsExporter();

// Export FPM metrics
$exporter->exportFpmMetrics([
    'prefix' => 'php_fpm_',
    'pool' => true,
    'processes' => true
]);

// Export OPCache metrics
$exporter->exportOPCacheMetrics([
    'prefix' => 'php_opcache_',
    'scripts' => true
]);

// Output all metrics
$exporter->outputMetrics();
