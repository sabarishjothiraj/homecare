<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return !empty($rows);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'first_name')) {
                $table->string('first_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('employees', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('employees', 'email')) {
                $table->string('email')->nullable()->after('last_name');
            }
            if (!Schema::hasColumn('employees', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('employees', 'alternate_phone')) {
                $table->string('alternate_phone')->nullable()->after('phone');
            }
        });

        DB::table('employees')->orderBy('id')->chunkById(100, function ($employees) {
            foreach ($employees as $employee) {
                $fieldData = json_decode($employee->field_data ?? '{}', true);
                if (!is_array($fieldData)) {
                    $fieldData = [];
                }

                $firstName = $fieldData['first_name'] ?? null;
                $lastName = $fieldData['last_name'] ?? null;
                $email = $fieldData['email'] ?? null;
                $phone = $fieldData['phone'] ?? null;
                $alternatePhone = $fieldData['alternate_phone'] ?? null;

                if (!$firstName && !empty($fieldData['name'])) {
                    $parts = preg_split('/\s+/', trim((string) $fieldData['name'])) ?: [];
                    $firstName = $parts[0] ?? null;
                    $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;
                }

                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'phone' => $phone,
                        'alternate_phone' => $alternatePhone,
                    ]);
            }
        });

        // Ensure legacy duplicate values do not block unique index creation.
        $this->deduplicateColumnValues('employees', 'email');
        $this->deduplicateColumnValues('employees', 'phone');

        Schema::table('employees', function (Blueprint $table) {
            if (!$this->indexExists('employees', 'employees_email_unique')) {
                $table->unique('email');
            }
            if (!$this->indexExists('employees', 'employees_phone_unique')) {
                $table->unique('phone');
            }
        });
    }

    private function deduplicateColumnValues(string $table, string $column): void
    {
        $duplicates = DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck($column);

        foreach ($duplicates as $value) {
            $ids = DB::table($table)
                ->where($column, $value)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $idsToNull = array_slice($ids, 1);
            if (!empty($idsToNull)) {
                DB::table($table)
                    ->whereIn('id', $idsToNull)
                    ->update([$column => null]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if ($this->indexExists('employees', 'employees_email_unique')) {
                $table->dropUnique('employees_email_unique');
            }
            if ($this->indexExists('employees', 'employees_phone_unique')) {
                $table->dropUnique('employees_phone_unique');
            }
            $dropColumns = [];
            foreach (['first_name', 'last_name', 'email', 'phone', 'alternate_phone'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
