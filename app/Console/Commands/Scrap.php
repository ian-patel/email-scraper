<?php

namespace App\Console\Commands;

use App\Models\Email;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        for ($i = $scrapingId; $i <= $scrapingId + 100; $i++) {
            $this->scrap($i);
        }

        DB::table('settings')->where('name', 'dribbble')->update(['id' => $i - 1]);

        return Command::SUCCESS;
    }

    public function scrap(int $scrapingId)
    {
        $response = Http::get($this->url . $scrapingId);
        $content = $response->body();

        if ($response->status() !== 200) {
            return;
        }

        $match = [];
        $pattern = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/';
        preg_match_all($pattern, $content, $match);

        $emails = array_values(array_unique($match[0]));

        if ($emails) {
            $crawler = new Crawler($content);
            $name = $crawler->filter('div.shot-user-details > a.shot-user-link')->first()->text();

            foreach ($emails as $email) {
                $emailobject = Email::firstOrCreate(
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