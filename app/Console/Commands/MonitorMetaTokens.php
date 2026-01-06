<?php

namespace App\Console\Commands;

use App\Models\SocialIntegration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorMetaTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chatbot:monitor-tokens {--days=5 : Days threshold for expiry warning}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor Meta access tokens for expiry and log warnings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $daysThreshold = (int) $this->option('days');

        $this->info("Checking for tokens expiring in {$daysThreshold} days...");

        // Get tokens expiring soon
        $expiring = SocialIntegration::expiringTokens($daysThreshold)->get();

        if ($expiring->isEmpty()) {
            $this->info('✅ No tokens expiring soon');
            return Command::SUCCESS;
        }

        $this->warn("⚠️  Found {$expiring->count()} token(s) expiring soon:");

        foreach ($expiring as $integration) {
            $daysRemaining = $integration->token_expires_at->diffInDays(now());

            $this->line("  - Salon: {$integration->salon->name} (ID: {$integration->salon_id})");
            $this->line("    Platform: {$integration->platform}");
            $this->line("    Expires: {$integration->token_expires_at->format('Y-m-d H:i')}");
            $this->line("    Days remaining: {$daysRemaining}");
            $this->line('');

            // Log warning
            Log::warning('Meta token expiring soon', [
                'salon_id' => $integration->salon_id,
                'salon_name' => $integration->salon->name,
                'platform' => $integration->platform,
                'expires_at' => $integration->token_expires_at,
                'days_remaining' => $daysRemaining,
            ]);

            // TODO: Send email notification
            // Mail::to($integration->salon->email)->send(new TokenExpiringMail($integration));
        }

        return Command::SUCCESS;
    }
}
