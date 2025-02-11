<?php

namespace App\Console;

use App\Console\Commands\AggregateArticlesViews;
use App\Console\Commands\AggregateConversionEvents;
use App\Console\Commands\AggregatePageviews;
use App\Console\Commands\CompressSnapshots;
use App\Console\Commands\ComputeAuthorsSegments;
use App\Console\Commands\ComputeSectionSegments;
use App\Console\Commands\DashboardRefresh;
use App\Console\Commands\DeleteOldAggregations;
use App\Console\Commands\ProcessPageviewSessions;
use App\Console\Commands\SendNewslettersCommand;
use App\Console\Commands\CompressAggregations;
use App\Console\Commands\SnapshotArticlesViews;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Related commands are put into private functions

        $this->concurrentsSnapshots($schedule);
        $this->aggregations($schedule);
        $this->authorSegments($schedule);
        $this->dashboard($schedule);

        // All other unrelated commands

        $schedule->command(SendNewslettersCommand::COMMAND)
            ->everyMinute()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/send_newsletters.log'));

        // Aggregate any conversion events that weren't aggregated before due to Segments API fail
        // or other unexpected event
        $schedule->command(AggregateConversionEvents::COMMAND)
            ->dailyAt('3:30')
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/aggregate_conversion_events.log'));
    }

    /**
     * Snapshot of concurrents for dashboard + regular compression
     * @param Schedule $schedule
     */
    private function concurrentsSnapshots(Schedule $schedule)
    {
        $schedule->command(SnapshotArticlesViews::COMMAND)
            ->everyMinute()
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/snapshot_articles_views.log'));

        $schedule->command(CompressSnapshots::COMMAND)
            ->dailyAt('02:30')
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/compress_snapshots.log'));
    }

    /**
     * Author segments related aggregation and processing
     * @param Schedule $schedule
     */
    private function authorSegments(Schedule $schedule)
    {
        $schedule->command(AggregateArticlesViews::COMMAND, [
            '--skip-temp-aggregation',
            '--step' => config('system.commands.aggregate_article_views.default_step'),
        ])
            ->dailyAt('01:00')
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/aggregate_article_views.log'));

        $schedule->command(ComputeAuthorsSegments::COMMAND)
            ->dailyAt('02:00')
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/compute_author_segments.log'));

        $schedule->command(ComputeSectionSegments::COMMAND)
            ->dailyAt('03:00')
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/compute_section_segments.log'));
    }

    /**
     * Pageviews, timespent and session pageviews aggregation and cleanups
     * @param Schedule $schedule
     */
    private function aggregations(Schedule $schedule)
    {
        // Aggregates current 20-minute interval (may not be completed yet)
        $schedule->command(AggregatePageviews::COMMAND, ["--now='+20 minutes'"])
            ->everyMinute()
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/aggregate_pageviews.log'));

        // Aggregates last 20-minute interval only once
        $schedule->command(AggregatePageviews::COMMAND)
            ->cron('1-59/20 * * * *')
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/aggregate_pageviews.log'));
        
        $schedule->command(ProcessPageviewSessions::COMMAND)
            ->everyFiveMinutes()
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/process_pageview_sessions.log'));

        $schedule->command(DeleteOldAggregations::COMMAND)
            ->dailyAt('00:10')
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/delete_old_aggregations.log'));

        $schedule->command(CompressAggregations::COMMAND)
            ->dailyAt('00:20')
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/compress_aggregations.log'));
    }

    private function dashboard(Schedule $schedule)
    {
        $schedule->command(DashboardRefresh::COMMAND)
            ->everyMinute()
            ->runInBackground()
            ->withoutOverlapping(config('system.commands_overlapping_expires_at'))
            ->appendOutputTo(storage_path('logs/dashboard_refresh.log'));
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
        $this->load(__DIR__.'/Commands');
    }
}
