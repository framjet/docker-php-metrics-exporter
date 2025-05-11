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
            $this->addMetric($prefix . 'start_time', $fpmStatus['start-time'] ?? -1, $baseLabels, 'Start time of the PHP-FPM pool');
            $this->addMetric($prefix . 'start_since', $fpmStatus['start-since'] ?? -1, $baseLabels, 'Seconds since start of the PHP-FPM pool');
            $this->addMetric($prefix . 'accepted_conn', $fpmStatus['accepted-conn'] ?? -1, $baseLabels, 'Total accepted connections');
            $this->addMetric($prefix . 'listen_queue', $fpmStatus['listen-queue'] ?? -1, $baseLabels, 'Current listen queue size');
            $this->addMetric($prefix . 'max_listen_queue', $fpmStatus['max-listen-queue'] ?? -1, $baseLabels, 'Maximum listen queue size');
            $this->addMetric($prefix . 'listen_queue_len', $fpmStatus['listen-queue-len'] ?? -1, $baseLabels, 'Listen queue length');

            // Process counts by state
            $this->addMetric($prefix . 'processes', $fpmStatus['idle-processes'] ?? -1,
                array_merge($baseLabels, ['state' => 'idle']), 'Process count by state');
            $this->addMetric($prefix . 'processes', $fpmStatus['active-processes'] ?? -1,
                array_merge($baseLabels, ['state' => 'active']), 'Process count by state');

            $this->addMetric($prefix . 'total_processes', $fpmStatus['total-processes'] ?? -1, $baseLabels, 'Total process count');
            $this->addMetric($prefix . 'max_active_processes', $fpmStatus['max-active-processes'] ?? -1, $baseLabels, 'Maximum active processes');
            $this->addMetric($prefix . 'max_children_reached', $fpmStatus['max-children-reached'] ?? -1, $baseLabels, 'Max children reached');
            $this->addMetric($prefix . 'slow_requests', $fpmStatus['slow-requests'] ?? -1, $baseLabels, 'Slow request count');
            $this->addMetric($prefix . 'memory_peak', $fpmStatus['memory-peak'] ?? -1, $baseLabels, 'Peak memory usage');
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

                $this->addMetric($prefix . 'process_start_time', $proc['start-time'] ?? -1, $procLabels, 'Process start time');
                $this->addMetric($prefix . 'process_start_since', $proc['start-since'] ?? -1, $procLabels, 'Seconds since process start');
                $this->addMetric($prefix . 'process_requests', $proc['requests'] ?? -1, $procLabels, 'Number of requests served');
                $this->addMetric($prefix . 'process_request_duration', $proc['request-duration'] ?? -1, $procLabels, 'Current request duration');
                $this->addMetric($prefix . 'process_request_length', $proc['request-length'] ?? -1, $procLabels, 'Request content length');
                $this->addMetric($prefix . 'process_last_request_cpu', $proc['last-request-cpu'] ?? -1, $procLabels, 'CPU usage of last request');
                $this->addMetric($prefix . 'process_last_request_memory', $proc['last-request-memory'] ?? -1, $procLabels, 'Memory usage of last request');
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

        // Memory usage metrics
        $memory = $opcacheStatus['memory_usage'] ?? [];
        $this->addMetric($prefix . 'memory_bytes', $memory['used_memory'] ?? -1,
            array_merge($baseLabels, ['state' => 'used']), 'OPcache memory usage');
        $this->addMetric($prefix . 'memory_bytes', $memory['free_memory'] ?? -1,
            array_merge($baseLabels, ['state' => 'free']), 'OPcache memory usage');
        $this->addMetric($prefix . 'memory_bytes', $memory['wasted_memory'] ?? -1,
            array_merge($baseLabels, ['state' => 'wasted']), 'OPcache memory usage');
        $this->addMetric($prefix . 'wasted_percentage', $memory['current_wasted_percentage'] ?? -1,
            $baseLabels, 'Current wasted memory percentage');

        // Interned strings usage
        $internedStrings = $opcacheStatus['interned_strings_usage'] ?? [];
        $this->addMetric($prefix . 'interned_strings_memory_bytes', $internedStrings['used_memory'] ?? -1,
            array_merge($baseLabels, ['state' => 'used']), 'Interned strings memory usage');
        $this->addMetric($prefix . 'interned_strings_memory_bytes', $internedStrings['free_memory'] ?? -1,
            array_merge($baseLabels, ['state' => 'free']), 'Interned strings memory usage');
        $this->addMetric($prefix . 'interned_strings_buffer_size', $internedStrings['buffer_size'] ?? -1,
            $baseLabels, 'Interned strings buffer size');
        $this->addMetric($prefix . 'interned_strings_count', $internedStrings['number_of_strings'] ?? -1,
            $baseLabels, 'Number of interned strings');

        // Statistics
        $stats = $opcacheStatus['opcache_statistics'] ?? [];
        $this->addMetric($prefix . 'cached_scripts', $stats['num_cached_scripts'] ?? -1, $baseLabels, 'Number of cached scripts');
        $this->addMetric($prefix . 'cached_keys', $stats['num_cached_keys'] ?? -1, $baseLabels, 'Number of cached keys');
        $this->addMetric($prefix . 'max_cached_keys', $stats['max_cached_keys'] ?? -1, $baseLabels, 'Maximum cached keys');
        $this->addMetric($prefix . 'hits', $stats['hits'] ?? -1, $baseLabels, 'Cache hits');
        $this->addMetric($prefix . 'start_time', $stats['start_time'] ?? -1, $baseLabels, 'OPcache start time');
        $this->addMetric($prefix . 'last_restart_time', $stats['last_restart_time'] ?? -1, $baseLabels, 'Last restart time');

        // Restart counts by type
        $this->addMetric($prefix . 'restarts', $stats['oom_restarts'] ?? -1,
            array_merge($baseLabels, ['type' => 'oom']), 'Restart count by type');
        $this->addMetric($prefix . 'restarts', $stats['hash_restarts'] ?? -1,
            array_merge($baseLabels, ['type' => 'hash']), 'Restart count by type');
        $this->addMetric($prefix . 'restarts', $stats['manual_restarts'] ?? -1,
            array_merge($baseLabels, ['type' => 'manual']), 'Restart count by type');

        $this->addMetric($prefix . 'misses', $stats['misses'] ?? -1, $baseLabels, 'Cache misses');
        $this->addMetric($prefix . 'blacklist_misses', $stats['blacklist_misses'] ?? -1, $baseLabels, 'Blacklist misses');
        $this->addMetric($prefix . 'blacklist_miss_ratio', $stats['blacklist_miss_ratio'] ?? -1, $baseLabels, 'Blacklist miss ratio');
        $this->addMetric($prefix . 'hit_rate', $stats['opcache_hit_rate'] ?? -1, $baseLabels, 'Cache hit rate');

        // JIT metrics
        $jitStatus = $jit;  // Using the same jit variable from above
        $this->addMetric($prefix . 'jit_buffer_size', $jitStatus['buffer_size'] ?? -1, $baseLabels, 'JIT buffer size');
        $this->addMetric($prefix . 'jit_buffer_free', $jitStatus['buffer_free'] ?? -1, $baseLabels, 'JIT buffer free space');

        // Script metrics
        if ($options['scripts'] ?? true) {
            $scripts = $opcacheStatus['scripts'] ?? [];
            foreach ($scripts as $script) {
                $scriptLabels = array_merge($baseLabels, [
                    'script' => $script['full_path'] ?? 'unknown',
                ]);

                $this->addMetric($prefix . 'script_hits', $script['hits'] ?? -1,
                    $scriptLabels, 'Script cache hits');
                $this->addMetric($prefix . 'script_memory_consumption', $script['memory_consumption'] ?? -1,
                    $scriptLabels, 'Script memory consumption');
                $this->addMetric($prefix . 'script_last_used_timestamp', $script['last_used_timestamp'] ?? -1,
                    $scriptLabels, 'Script last used timestamp');
                $this->addMetric($prefix . 'script_revalidate', $script['revalidate'] ?? -1,
                    $scriptLabels, 'Script revalidate timestamp');
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
