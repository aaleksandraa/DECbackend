<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates all chatbot-related tables:
     * - social_integrations
     * - chatbot_conversations
     * - chatbot_messages
     * - chatbot_analytics
     *
     * SAFE FOR PRODUCTION:
     * - No modifications to existing tables
     * - All new tables with proper constraints
     * - Rollback support
     * - PostgreSQL optimized
     */
    public function up(): void
    {
        echo "\n========================================\n";
        echo "Creating Chatbot Tables\n";
        echo "========================================\n\n";

        // Table 1: Social Integrations
        if (!Schema::hasTable('social_integrations')) {
            Schema::create('social_integrations', function (Blueprint $table) {
                $table->id(); // BIGSERIAL PRIMARY KEY

                // Relationships
                $table->unsignedBigInteger('salon_id');
                $table->foreign('salon_id')
                    ->references('id')
                    ->on('salons')
                    ->onDelete('cascade');

                // Provider info
                $table->string('provider', 20)->default('meta')
                    ->comment('meta, whatsapp, telegram');
                $table->string('platform', 20)
                    ->comment('facebook, instagram, both');

                // Meta IDs
                $table->string('fb_page_id', 255)->nullable()->unique();
                $table->string('fb_page_name', 255)->nullable();
                $table->string('ig_business_account_id', 255)->nullable();
                $table->string('ig_username', 255)->nullable();

                // Tokens (encrypted at application level)
                $table->text('access_token');
                $table->string('token_type', 50)->default('page_access_token');
                $table->timestamp('token_expires_at')->nullable();
                $table->text('refresh_token')->nullable();

                // Permissions (JSON)
                $table->json('granted_scopes')->nullable();

                // Status
                $table->string('status', 20)->default('pending')
                    ->comment('pending, active, expired, revoked, error');
                $table->timestamp('last_verified_at')->nullable();
                $table->timestamp('last_message_at')->nullable();

                // Metadata
                $table->unsignedBigInteger('connected_by_user_id')->nullable();
                $table->foreign('connected_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
                $table->string('meta_user_id', 255)->nullable();
                $table->boolean('webhook_verified')->default(false);

                // Settings
                $table->boolean('auto_reply_enabled')->default(true);
                $table->boolean('business_hours_only')->default(false);
                $table->integer('max_response_time_seconds')->default(30);

                // Audit
                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->unique(['salon_id', 'provider', 'platform'], 'social_integrations_unique');
                $table->index(['salon_id', 'provider', 'status'], 'social_integrations_salon_status');
                $table->index(['status', 'last_verified_at'], 'social_integrations_status_verified');
            });

            echo "✅ Created social_integrations table\n";
        } else {
            echo "⚠️  social_integrations table already exists - skipping\n";
        }

        // Table 2: Chatbot Conversations
        if (!Schema::hasTable('chatbot_conversations')) {
            Schema::create('chatbot_conversations', function (Blueprint $table) {
                $table->id(); // BIGSERIAL PRIMARY KEY

                // Relationships
                $table->unsignedBigInteger('salon_id');
                $table->foreign('salon_id')
                    ->references('id')
                    ->on('salons')
                    ->onDelete('cascade');

                $table->unsignedBigInteger('social_integration_id');
                $table->foreign('social_integration_id')
                    ->references('id')
                    ->on('social_integrations')
                    ->onDelete('cascade');

                $table->unsignedBigInteger('appointment_id')->nullable();
                $table->foreign('appointment_id')
                    ->references('id')
                    ->on('appointments')
                    ->onDelete('set null');

                // Meta identifiers
                $table->string('platform', 20)
                    ->comment('facebook, instagram');
                $table->string('thread_id', 255);
                $table->string('sender_psid', 255)
                    ->comment('Page-scoped ID');
                $table->string('sender_name', 255)->nullable();
                $table->text('sender_profile_pic')->nullable();

                // Conversation state
                $table->string('state', 50)->default('new')
                    ->comment('new, greeting, collecting_service, collecting_datetime, collecting_contact, confirming, booked, completed, abandoned, error');

                $table->string('intent', 50)->nullable()
                    ->comment('booking, pricing, hours, location, cancellation, general');
                $table->decimal('confidence', 3, 2)->nullable()
                    ->comment('AI confidence score 0.00-1.00');

                // Extracted data (working memory)
                $table->json('context')->default('{}')
                    ->comment('Conversation context and extracted entities');

                // Metadata
                $table->integer('message_count')->default(0);
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('last_bot_response_at')->nullable();

                // Flags
                $table->boolean('requires_human')->default(false);
                $table->timestamp('human_takeover_at')->nullable();
                $table->unsignedBigInteger('human_takeover_by_user_id')->nullable();
                $table->foreign('human_takeover_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');

                // Lifecycle
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('abandoned_at')->nullable();

                $table->timestamps();

                // Indexes
                $table->unique(['thread_id', 'platform'], 'chatbot_conversations_thread_unique');
                $table->index(['salon_id', 'state', 'last_message_at'], 'chatbot_conversations_salon_state');
                $table->index('appointment_id', 'chatbot_conversations_appointment');
            });

            // Partial index for active conversations (PostgreSQL specific)
            DB::statement("
                CREATE INDEX IF NOT EXISTS chatbot_conversations_active
                ON chatbot_conversations (salon_id, state)
                WHERE state NOT IN ('completed', 'abandoned')
            ");

            echo "✅ Created chatbot_conversations table\n";
        } else {
            echo "⚠️  chatbot_conversations table already exists - skipping\n";
        }

        // Table 3: Chatbot Messages
        if (!Schema::hasTable('chatbot_messages')) {
            Schema::create('chatbot_messages', function (Blueprint $table) {
                $table->id(); // BIGSERIAL PRIMARY KEY

                // Relationships
                $table->unsignedBigInteger('conversation_id');
                $table->foreign('conversation_id')
                    ->references('id')
                    ->on('chatbot_conversations')
                    ->onDelete('cascade');

                // Message data
                $table->string('direction', 10)
                    ->comment('inbound, outbound');
                $table->string('message_type', 20)->default('text')
                    ->comment('text, image, quick_reply, button, template');

                // Content
                $table->text('message_text')->nullable();
                $table->json('message_payload')->nullable()
                    ->comment('Full Meta message object');

                // Meta IDs
                $table->string('meta_message_id', 255)->nullable()->unique();

                // AI processing (for inbound)
                $table->boolean('ai_processed')->default(false);
                $table->string('ai_intent', 50)->nullable();
                $table->json('ai_entities')->nullable()
                    ->comment('Extracted entities: service, date, time, etc');
                $table->decimal('ai_confidence', 3, 2)->nullable();
                $table->integer('ai_processing_time_ms')->nullable();

                // Response generation (for outbound)
                $table->string('template_used', 100)->nullable();
                $table->boolean('ai_generated')->default(false);

                // Delivery status
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('error_message')->nullable();

                $table->timestamp('created_at')->useCurrent();

                // Indexes
                $table->index(['conversation_id', 'created_at'], 'chatbot_messages_conversation');
                $table->index(['conversation_id', 'direction'], 'chatbot_messages_direction');
            });

            // Partial index for failed messages
            DB::statement("
                CREATE INDEX IF NOT EXISTS chatbot_messages_failed
                ON chatbot_messages (failed_at)
                WHERE failed_at IS NOT NULL
            ");

            echo "✅ Created chatbot_messages table\n";
        } else {
            echo "⚠️  chatbot_messages table already exists - skipping\n";
        }

        // Table 4: Chatbot Analytics
        if (!Schema::hasTable('chatbot_analytics')) {
            Schema::create('chatbot_analytics', function (Blueprint $table) {
                $table->id(); // BIGSERIAL PRIMARY KEY

                $table->unsignedBigInteger('salon_id');
                $table->foreign('salon_id')
                    ->references('id')
                    ->on('salons')
                    ->onDelete('cascade');

                $table->date('date');

                // Volume metrics
                $table->integer('total_conversations')->default(0);
                $table->integer('new_conversations')->default(0);
                $table->integer('completed_conversations')->default(0);
                $table->integer('abandoned_conversations')->default(0);

                // Message metrics
                $table->integer('total_messages_received')->default(0);
                $table->integer('total_messages_sent')->default(0);
                $table->decimal('avg_messages_per_conversation', 5, 2)->nullable();

                // Performance metrics
                $table->decimal('avg_response_time_seconds', 8, 2)->nullable();
                $table->decimal('avg_conversation_duration_minutes', 8, 2)->nullable();

                // Booking metrics
                $table->integer('booking_attempts')->default(0);
                $table->integer('successful_bookings')->default(0);
                $table->decimal('booking_conversion_rate', 5, 2)->nullable();

                // AI metrics
                $table->decimal('avg_ai_confidence', 3, 2)->nullable();
                $table->integer('low_confidence_count')->default(0)
                    ->comment('Count of messages with confidence < 0.7');

                // Human intervention
                $table->integer('human_takeover_count')->default(0);

                $table->timestamps();

                // Indexes
                $table->unique(['salon_id', 'date'], 'chatbot_analytics_salon_date');
                $table->index(['salon_id', 'date'], 'chatbot_analytics_lookup');
            });

            echo "✅ Created chatbot_analytics table\n";
        } else {
            echo "⚠️  chatbot_analytics table already exists - skipping\n";
        }

        echo "\n========================================\n";
        echo "Chatbot Tables Created Successfully\n";
        echo "========================================\n\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        echo "\n========================================\n";
        echo "Dropping Chatbot Tables\n";
        echo "========================================\n\n";

        Schema::dropIfExists('chatbot_analytics');
        echo "✅ Dropped chatbot_analytics\n";

        Schema::dropIfExists('chatbot_messages');
        echo "✅ Dropped chatbot_messages\n";

        Schema::dropIfExists('chatbot_conversations');
        echo "✅ Dropped chatbot_conversations\n";

        Schema::dropIfExists('social_integrations');
        echo "✅ Dropped social_integrations\n";

        echo "\n========================================\n";
        echo "Chatbot Tables Dropped Successfully\n";
        echo "========================================\n\n";
    }
};
