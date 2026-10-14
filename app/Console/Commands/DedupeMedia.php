<?php

namespace App\Console\Commands;

use App\Support\MediaDedupe;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * Merges identical pictures in the media library into one copy.
 *
 * For every group of identical files the oldest one is kept, every place that
 * used another copy (product, banner, brand, blog text, landing page, …) is
 * pointed at the kept one, and only then the extra copies are deleted. Demo
 * pictures (demo-*.png) are left alone.
 *
 * Try --dry-run first, and take a database backup before the real run.
 */
class DedupeMedia extends Command
{
    protected $signature = 'media:dedupe
        {--dry-run : Only show what would be merged}
        {--force : Do not ask for confirmation}';

    protected $description = 'Merge identical pictures in the media library into one copy, keeping every link working';

    public function handle(): int
    {
        $hashed = MediaDedupe::hashPending(100000);
        $plan = MediaDedupe::plan();

        if ($plan === []) {
            $this->info('একই ছবির কোনো ডুপ্লিকেট নেই। কিছু করার নেই।');

            return self::SUCCESS;
        }

        $copies = array_sum(array_map(fn ($g) => count($g['copies']), $plan));
        $bytes = array_sum(array_column($plan, 'bytes'));

        $this->info(count($plan)." দল ডুপ্লিকেট পাওয়া গেছে: {$copies} টি বাড়তি কপি, ".Number::fileSize($bytes).' জায়গা নিচ্ছে।');

        foreach ($plan as $group) {
            $this->line("  রাখা হবে: {$group['keep']}");

            foreach ($group['copies'] as $copy) {
                $this->line("     মিলিয়ে ফেলা হবে: {$copy}");
            }
        }

        if ($this->option('dry-run')) {
            $this->info('[dry run] কিছু বদলানো হয়নি।');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('এগুলো মিলিয়ে ফেলবেন? (যেখানে ব্যবহার হচ্ছে সেখানকার লিংক নিজে থেকেই ঠিক হবে)')) {
            $this->line('বাতিল করা হয়েছে।');

            return self::SUCCESS;
        }

        $stats = MediaDedupe::merge($plan);

        $this->info("{$stats['removed']} টি কপি মোছা হয়েছে ({$stats['groups']} দল), {$stats['updated']} জায়গার লিংক ঠিক করা হয়েছে, ".Number::fileSize($stats['bytes']).' জায়গা বাঁচল।');

        if ($stats['kept_in_use']) {
            $this->warn(count($stats['kept_in_use']).' টি কপি এখনও কোথাও ব্যবহার হচ্ছে বলে রাখা হয়েছে:');

            foreach ($stats['kept_in_use'] as $url) {
                $this->line("  {$url}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
