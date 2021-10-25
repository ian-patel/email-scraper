<?php

namespace App\Console\Commands;

use App\Models\Email;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class Scrap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrap:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $url = 'https://dribbble.com/shots/';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $last = DB::table('settings')->where('name', 'dribbble')->first();

        $scrapingId = ++$last->id;

        for ($i = $scrapingId; $i <= $scrapingId + 30; $i++) {
            $this->scrap($i);
        }

        DB::table('settings')->where('name', 'dribbble')->update(['id' => $i - 1, 'updated_at' => now()]);

        return Command::SUCCESS;
    }

    public function scrap(int $scrapingId)
    {
        $response = Http::get($this->url . $scrapingId);
        $content = $response->body();

        if ($response->status() !== 200) {
            Log::info($this->url . $scrapingId . ' ==' . $response->status());
            return;
        }

        Log::info($this->url . $scrapingId . ' **' . $response->status());
        $match = [];
        $pattern = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/';
        preg_match_all($pattern, $content, $match);

        $emails = array_values(array_unique($match[0]));

        if ($emails) {
            $crawler = new Crawler($content);
            try {
                $name = $crawler->filter('div.shot-user-details > a.shot-user-link')->first()->text();
            } catch (Exception $e) {
                $name = $crawler->filter('div.shot-user-details')->first()->text();
            }

            foreach ($emails as $email) {
                Email::firstOrCreate(
                    [
                        'email' => $email
                    ],
                    [
                        'name' => $name,
                        'source_id' => $scrapingId,
                    ]
                );
            }
        }
    }
}
