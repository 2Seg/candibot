<?php

namespace App\Commands;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CandilibCrawlerCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'candilib:crawl
                                {--limit=30}
                                {--refreshRate=5}
                                {--postalCodes=95,94,93,92,91,78,77,69,38}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Crawl Candilib API and send a notification in case of an available slot';

    /**
     * Candilib API base URL
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Candilib personal JWT
     *
     * @var string
     */
    protected $token;

    /**
     * Launch time of the command
     *
     * @var Carbon
     */
    protected $startAt;

    /**
     * When the command should stop
     *
     * @var Carbon
     */
    protected $endAt;

    /**
     * Request refresh rate (seconds)
     *
     * @var integer
     */
    protected $refreshRate;

    /**
     * Departments postal codes
     *
     * @var string[]
     */
    protected $postalCodes;

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->baseUrl     = env('CANDILIB_BASE_URL');
        $this->token       = env('CANDILIB_TOKEN');
        $this->startAt     = now();
        $this->endAt       = $this->startAt->clone()->addMinutes($input->getOption('limit'));
        $this->refreshRate = $input->getOption('refreshRate');
        $this->postalCodes = explode(',', $input->getOption('postalCodes'));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Let\'s get you up and driving #CandilibIsWater...');
        $this->line('');
        $this->warn('Started search at:   ' . $this->startAt->clone()->toDateTimeString());
        $this->warn('Search will stop at: ' . $this->endAt->clone()->toDateTimeString());
        $this->line('');

        $this->checkAuth();

        $count = 1;

        do {
            if ($this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE){
                $this->line(".. Fetching ($count) ..");
            }

            if (($availabilities = $this->getAvailabilities())->count() > 0) {
                $this->info('Availability found!');
                $this->notify(
                    '⚠️ TODO: SHOTGUN ⚠️',
                    implode('', $availabilities->map(function ($department) {
                        return "$department->geoDepartement: $department->count place(s) \r\n";
                    })->toArray())
                );
                exit;
            }

            $count++;
            sleep($this->refreshRate);
        } while (now()->isBefore($this->endAt));

        $this->line('');
        $this->error('Looks like there\'s no availability at this moment :( Maybe later!');
    }

    /**
     * Check if it's possible to authenticate
     */
    protected function checkAuth(): void
    {
        $authSuccessful = $this->task('Checking authentication', function () {
            $request = Http::get($this->baseUrl . '/auth/candidat/verify-token', ['token' => $this->token]);

            return $request->successful()
                && $request->object()->auth;
        });
        $this->line('');

        if (! $authSuccessful) {
            $this->error("Authentification failed, please verify your token.");
            exit;
        }
    }

    /**
     * Get availabilities
     *
     * @return array
     */
    protected function getAvailabilities(): Collection
    {
        $request = Http::withToken($this->token)
            ->get($this->baseUrl . '/candidat/departements');

        if (! $request->successful()) {
            $this->error('Impossible to fetch availabilities.');
            exit;
        }

        $results = collect($request->object()->geoDepartementsInfos);

        return $results->filter(function ($department) {
            return in_array($department->geoDepartement, $this->postalCodes)
                && $department->count > 0;
        });
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
