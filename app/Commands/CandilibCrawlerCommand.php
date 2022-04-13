<?php

namespace App\Commands;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Notifiers\TelegramNotifier;
use Illuminate\Support\Facades\Http;
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
    protected $signature = 'candilib:crawl {token?}
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
     * Candilib client id
     *
     * @var string
     */
    protected $clientId;

    /**
     * Candilib user id
     *
     * @var string
     */
    protected $userId;

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
     *
     * @var TelegramNotifier
     */
    protected $telegramNotifier;

    public function __construct(TelegramNotifier $telegramNotifier)
    {
        parent::__construct();

        $this->telegramNotifier = $telegramNotifier;
    }

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->baseUrl     = config('candibot.base_url');
        $this->token       = $input->getArgument('token') ?? config('candibot.token');
        $this->clientId    = config('candibot.headers.client_id');
        $this->userId      = config('candibot.headers.user_id');
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

        $this->checkAuth();

        $count = 1;

        do {
            $availabilities = $this->getAvailabilities($count);

            if ($availabilities->count() > 0) {
                $this->notifyAvailabilities($availabilities);
                sleep(20);
            }

            $this->sleep();
        } while (now()->isBefore($this->endAt));

        $this->line('');
        $this->error('Looks like there\'s no availability at this moment :( Maybe later!');
    }

    /**
     * Check if it's possible to authenticate
     */
    protected function checkAuth(): void
    {
        $start = microtime(1);
        $this->line('');
        $authSuccessful = $this->task('Checking authentication', function () {
            $response = Http::get($this->baseUrl . '/auth/candidat/verify-token', ['token' => $this->token]);

            return $response->successful()
                && $response->object()->auth;
        });
        $this->warn('Took ' . round((microtime(1) - $start), 2) . 's');

        if (! $authSuccessful) {
            $this->error('Authentification failed, please verify your token.');
            exit;
        }
    }

    /**
     * Get availabilities
     *
     * @return array
     */
    protected function getAvailabilities(int &$count): Collection
    {
        $results = collect();

        $start = microtime(1);
        $this->line('');
        $this->task("Fetching $count", function () use (&$results) {
            $response = Http::withToken($this->token)->withHeaders([
                'X-CLIENT-ID' => $this->clientId,
                'X-USER-ID'   => $this->userId,
            ])->get($this->baseUrl . '/candidat/departements');

            if (! $response->successful()) {
                $this->notifyFailure();
                exit;
            }

            $results = collect($response->object()->geoDepartementsInfos);

            return $response->successful();
        });
        $this->warn('Took ' . round((microtime(1) - $start), 2) . 's');

        $count++;

        return $results->filter(function ($department) {
            return in_array($department->geoDepartement, $this->postalCodes)
                && $department->count > 0;
        });
    }

    /**
     * Notify availabilities
     *
     * @param  Collection $availabilities
     */
    protected function notifyAvailabilities(Collection $availabilities): void
    {
        $this->info('Availability found!');
        $this->table(
            ['Deparment', 'Places'],
            $availabilities->map(function ($department) {
                return [$department->geoDepartement, $department->count];
            })->toArray()
        );

        $response = $this->telegramNotifier->sendMessage(
            "<b>🚨New availabilities found!🚨</b>"
            . PHP_EOL
            . PHP_EOL
            . $availabilities->map(function ($department) {
                return "$department->geoDepartement ➡️ <a href='$this->baseUrl/candilib/candidat/$department->geoDepartement/selection/selection-centre'>$department->count</a>";
            })->join(PHP_EOL)
            . PHP_EOL
            . PHP_EOL
            . "\n\n<a href='$this->baseUrl/candilib/candidat/home'>Click here to SHOTGUN 💥</a>"
        );

        if (! $response->successful()) {
            $this->error('Couldn\'t send Telegram.');
        }
    }

    /**
     * Notify failure
     */
    protected function notifyFailure(): void
    {
        $response = $this->telegramNotifier->sendMessage(
            "<b>⚠️Command failure⚠️</b>"
            . PHP_EOL
            . PHP_EOL
            . "You should start it again..."
        );

        if (! $response->successful()) {
            $this->error('Couldn\'t send Telegram.');
        }
    }

    /**
     * Have a good night
     */
    protected function sleep(): void
    {
        sleep(
            $this->refreshRate
            + mt_rand(- (1 / $this->refreshRate * 100), (1 / $this->refreshRate * 100)) / ($this->refreshRate * 100)
        );
    }
}
