<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chatbot_conversations')) {
            return;
        }

        Schema::table('chatbot_conversations', function (Blueprint $table) {
            if ($this->indexExists('chatbot_conversations', 'chatbot_conversations_thread_unique')) {
                $table->dropUnique('chatbot_conversations_thread_unique');
            }

            if (!$this->indexExists('chatbot_conversations', 'chatbot_conversations_salon_thread_platform_unique')) {
                $table->unique(
                    ['salon_id', 'thread_id', 'platform'],
                    'chatbot_conversations_salon_thread_platform_unique'
                );
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('chatbot_conversations')) {
            return;
        }

        Schema::table('chatbot_conversations', function (Blueprint $table) {
            if ($this->indexExists('chatbot_conversations', 'chatbot_conversations_salon_thread_platform_unique')) {
                $table->dropUnique('chatbot_conversations_salon_thread_platform_unique');
            }

            if (!$this->indexExists('chatbot_conversations', 'chatbot_conversations_thread_unique')) {
                $table->unique(['thread_id', 'platform'], 'chatbot_conversations_thread_unique');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                [$table, $indexName]
            );

            return $result !== null;
        }

        if ($driver === 'mysql') {
            $result = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
                [$table, $indexName]
            );

            return $result !== null;
        }

        return false;
    }
};

