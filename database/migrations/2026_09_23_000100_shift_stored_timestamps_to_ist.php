<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The app ran on UTC, so every TIMESTAMP/DATETIME stamped by the app (now(),
 * Eloquent timestamps) holds a UTC wall-clock value. It now runs on
 * Asia/Kolkata: move those values forward 5h30m so they still name the same
 * instants. DATE columns are calendar days, not instants, and are left alone.
 * See OPERATIONS.md for the deploy order.
 */
return new class extends Migration
{
    private const IST_OFFSET_MINUTES = 330;

    // migrations has no time columns; jobs stores its times as unix ints.
    private const SKIP_TABLES = ['migrations', 'jobs'];

    // Wall-clock times an admin typed in. The office entered them in IST and
    // they were stored exactly as typed, so they are already IST; shifting
    // them would push, e.g., a post scheduled for 10:00 to 15:30.
    // (Date-only inputs such as payments.paid_at are stored at 00:00 and keep
    // their day when shifted, while their now() rows need it, so they shift.)
    private const USER_ENTERED_COLUMNS = [
        'blogs' => ['published_at'],
    ];

    public function up(): void
    {
        $this->shift(self::IST_OFFSET_MINUTES);
    }

    public function down(): void
    {
        $this->shift(-self::IST_OFFSET_MINUTES);
    }

    private function shift(int $minutes): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Only right when the app is actually on IST. An install that pins
        // APP_TIMEZONE=UTC keeps UTC data, so there is nothing to convert.
        if (Carbon::now(config('app.timezone'))->utcOffset() !== self::IST_OFFSET_MINUTES) {
            return;
        }

        $columns = collect(DB::select(
            "SELECT c.TABLE_NAME AS table_name, c.COLUMN_NAME AS column_name
               FROM information_schema.COLUMNS c
               JOIN information_schema.TABLES t
                 ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
              WHERE c.TABLE_SCHEMA = DATABASE()
                AND t.TABLE_TYPE = 'BASE TABLE'
                AND c.DATA_TYPE IN ('timestamp', 'datetime')
              ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION"
        ))->reject(fn ($column) => in_array($column->table_name, self::SKIP_TABLES, true));

        // All or nothing: a half-shifted database cannot be told apart from a
        // clean one, and re-running would shift the finished tables twice.
        DB::transaction(function () use ($columns, $minutes) {
            foreach ($columns->groupBy('table_name') as $table => $tableColumns) {
                // Every column in one statement. With explicit_defaults_for_timestamp
                // off, an ON UPDATE CURRENT_TIMESTAMP column left out of the SET
                // would be overwritten with NOW(), so excluded columns are
                // assigned to themselves rather than omitted. NULL + INTERVAL
                // stays NULL.
                $assignments = $tableColumns
                    ->map(function ($column) use ($table, $minutes) {
                        $name = $this->wrap($column->column_name);

                        return in_array($column->column_name, self::USER_ENTERED_COLUMNS[$table] ?? [], true)
                            ? "{$name} = {$name}"
                            : sprintf('%1$s = %1$s + INTERVAL %2$d MINUTE', $name, $minutes);
                    })
                    ->implode(', ');

                DB::update('UPDATE '.$this->wrap($table).' SET '.$assignments);
            }
        });
    }

    private function wrap(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};
