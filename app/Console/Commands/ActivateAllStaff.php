<?php

namespace App\Console\Commands;

use App\Models\Staff;
use Illuminate\Console\Command;

class ActivateAllStaff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:activate-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set all staff members to public and active';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Activating all staff members...');

        $inactiveOrPrivate = Staff::where(function($query) {
            $query->whereRaw('is_public = false')
                  ->orWhereRaw('is_active = false');
        })->get();

        if ($inactiveOrPrivate->isEmpty()) {
            $this->info('All staff members are already public and active!');
            return 0;
        }

        $count = 0;
        foreach ($inactiveOrPrivate as $member) {
            $changes = [];

            if (!$member->is_public) {
                $member->is_public = true;
                $changes[] = 'made public';
            }

            if (!$member->is_active) {
                $member->is_active = true;
                $changes[] = 'activated';
            }

            $member->save();

            $this->line("✓ {$member->name} → " . implode(', ', $changes));
            $count++;
        }

        $this->info("✅ Updated {$count} staff members successfully!");
        return 0;
    }
}
