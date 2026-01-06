<?php

namespace App\Console\Commands;

use App\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateStaffSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:generate-slugs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate slugs for all staff members that don\'t have one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating slugs for staff members...');

        $staff = Staff::whereNull('slug')->orWhere('slug', '')->get();

        if ($staff->isEmpty()) {
            $this->info('All staff members already have slugs!');
            return 0;
        }

        $count = 0;
        foreach ($staff as $member) {
            $slug = Str::slug($member->name);

            // Ensure uniqueness
            $originalSlug = $slug;
            $counter = 1;
            while (Staff::where('slug', $slug)->where('id', '!=', $member->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $member->slug = $slug;
            $member->save();

            $this->line("✓ {$member->name} → {$slug}");
            $count++;
        }

        $this->info("✅ Generated {$count} slugs successfully!");
        return 0;
    }
}
