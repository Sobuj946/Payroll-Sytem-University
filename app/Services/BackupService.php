<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Writes the whole database as a plain SQL file that can be restored with the mysql command.
 * Written in PHP so it works on any machine without needing the mysqldump program.
 */
class BackupService
{
    /** Temporary tables that are not worth saving. */
    private const SKIP = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    /** @param resource $out */
    public function write($out): void
    {
        $pdo = DB::connection()->getPdo();
        $database = DB::connection()->getDatabaseName();

        fwrite($out, "-- Payroll Management System backup\n-- Database: {$database}\n-- Created: " . now()->format('Y-m-d H:i:s') . "\n\n");
        fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->reject(fn ($table) => in_array($table, self::SKIP, true))
            ->values();

        foreach ($tables as $table) {
            $create = (array) DB::select("SHOW CREATE TABLE `{$table}`")[0];

            fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n");

            $columns = null;
            $batch = [];

            foreach (DB::table($table)->cursor() as $row) {
                $row = (array) $row;
                $columns ??= '`' . implode('`,`', array_keys($row)) . '`';

                $batch[] = '(' . implode(',', array_map(
                    fn ($value) => $value === null ? 'NULL' : (is_int($value) ? (string) $value : $pdo->quote((string) $value)),
                    $row
                )) . ')';

                if (count($batch) >= 200) {
                    fwrite($out, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }

            if ($batch) {
                fwrite($out, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
            }

            fwrite($out, "\n");
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    public function fileName(): string
    {
        return 'payroll-backup-' . now()->format('Ymd-His') . '.sql';
    }
}
