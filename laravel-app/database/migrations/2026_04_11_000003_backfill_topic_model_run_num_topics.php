<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $counts = DB::table('topic_model_topics')
            ->select('topic_model_run_id', DB::raw('COUNT(*) as total_topics'))
            ->groupBy('topic_model_run_id')
            ->get();

        foreach ($counts as $row) {
            DB::table('topic_model_runs')
                ->where('id', (int) $row->topic_model_run_id)
                ->update([
                    'num_topics' => (int) $row->total_topics,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data correction migration: no-op.
    }
};
