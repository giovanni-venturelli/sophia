<?php
namespace Sophia\Debug;

class Profiler
{
    private static array $timers = [];
    private static array $counters = [];
    private static bool $enabled = true;

    public static function start(string $name): void
    {
        if (!self::$enabled) return;

        self::$timers[$name] = [
            'start' => microtime(true),
            'memory' => memory_get_usage()
        ];
    }

    public static function end(string $name): void
    {
        if (!self::$enabled || !isset(self::$timers[$name])) return;

        $data = self::$timers[$name];
        $data['end'] = microtime(true);
        $data['duration'] = $data['end'] - $data['start'];
        $data['memory_used'] = memory_get_usage() - $data['memory'];

        self::$timers[$name] = $data;
    }

    public static function count(string $name): void
    {
        if (!self::$enabled) return;

        if (!isset(self::$counters[$name])) {
            self::$counters[$name] = 0;
        }
        self::$counters[$name]++;
    }

    public static function getReport(): string
    {
        if (!self::$enabled) return '';

        $report = "\n\n<!-- PERFORMANCE PROFILER REPORT\n";
        $report .= "=====================================\n\n";

        // Ordina per durata
        uasort(self::$timers, fn($a, $b) => ($b['duration'] ?? 0) <=> ($a['duration'] ?? 0));

        $report .= "TIMERS (sorted by duration):\n";
        $report .= str_repeat("-", 70) . "\n";
        $totalTime = 0;

        foreach (self::$timers as $name => $data) {
            if (!isset($data['duration'])) continue;

            $duration = $data['duration'] * 1000; // Convert to ms
            $memory = $data['memory_used'] / 1024; // Convert to KB
            $totalTime += $data['duration'];

            $report .= sprintf(
                "%-40s %8.2fms %8.2fKB\n",
                $name,
                $duration,
                $memory
            );
        }

        $report .= str_repeat("-", 70) . "\n";
        $report .= sprintf("TOTAL TIME: %.2fms\n\n", $totalTime * 1000);

        if (!empty(self::$counters)) {
            $report .= "COUNTERS:\n";
            $report .= str_repeat("-", 70) . "\n";

            foreach (self::$counters as $name => $count) {
                $report .= sprintf("%-40s %8d calls\n", $name, $count);
            }
            $report .= "\n";
        }

        $report .= "MEMORY:\n";
        $report .= str_repeat("-", 70) . "\n";
        $report .= sprintf("Current: %.2f MB\n", memory_get_usage() / 1024 / 1024);
        $report .= sprintf("Peak:    %.2f MB\n", memory_get_peak_usage() / 1024 / 1024);

        $report .= "\n=====================================\n-->\n";

        return $report;
    }

    public static function reset(): void
    {
        self::$timers = [];
        self::$counters = [];
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }
}