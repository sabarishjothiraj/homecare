<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give a real slug to posts whose title slugified to '' (e.g. Tamil
     * titles). Their public links resolved to /blogs, so they were unreachable.
     */
    public function up(): void
    {
        $ids = DB::table('blogs')->where('slug', '')->pluck('id');

        foreach ($ids as $id) {
            $slug = 'post-'.$id;
            $suffix = 2;

            // Soft-deleted rows are included: the unique index covers them too.
            while (DB::table('blogs')->where('slug', $slug)->exists()) {
                $slug = 'post-'.$id.'-'.$suffix++;
            }

            DB::table('blogs')->where('id', $id)->update(['slug' => $slug]);
        }
    }

    /**
     * Nothing to undo: an empty slug was never a working value.
     */
    public function down(): void
    {
        //
    }
};
