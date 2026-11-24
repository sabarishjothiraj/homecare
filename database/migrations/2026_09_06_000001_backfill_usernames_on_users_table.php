<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Give every existing account a username.
 *
 * The username column was added after these rows were created, and the seeder
 * that assigns one only runs on a fresh install. That left every existing
 * account with username = null, which makes the super-admin API login
 * (POST /api/login with a username) impossible to satisfy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $taken = DB::table('users')
            ->whereNotNull('username')
            ->pluck('username')
            ->map(fn ($name) => strtolower($name))
            ->all();

        $rows = DB::table('users')
            ->where(function ($query) {
                $query->whereNull('username')->orWhere('username', '');
            })
            ->orderBy('id')
            ->get(['id', 'email', 'role']);

        foreach ($rows as $row) {
            $username = $this->uniqueUsername($row, $taken);

            DB::table('users')->where('id', $row->id)->update(['username' => $username]);

            $taken[] = strtolower($username);
        }
    }

    /**
     * Derive a username that does not collide with one already in use.
     *
     * The column is unique, so a duplicate would abort the whole migration.
     */
    private function uniqueUsername(object $row, array $taken): string
    {
        // "superadmin" is the name the seeder declares for the super admin, so
        // preferring it keeps seeded and existing installs consistent.
        $base = $row->role === 'super_admin'
            ? 'superadmin'
            : Str::slug(Str::before((string) $row->email, '@'), '');

        $base = $base !== '' ? $base : 'user'.$row->id;

        $candidate = $base;
        $suffix = 1;

        while (in_array(strtolower($candidate), $taken, true)) {
            $candidate = $base.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    public function down(): void
    {
        // Not reversible: the usernames this filled in are indistinguishable
        // from ones set deliberately, so clearing them could destroy real data.
    }
};
