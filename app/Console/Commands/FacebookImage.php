<?php

namespace STS\Console\Commands;

use STS\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use STS\Repository\FileRepository; 

class FacebookImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:facebook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download profile images facebook from users';

    protected $files;

    protected $client;

    /**
     * Create a new command instance.
     *
     * @returnactiveRatings void
     */
    public function __construct(FileRepository $files)
    {
        parent::__construct();
        $this->files = $files;
        $this->client = new Client();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info("COMMAND FacebookImage");
        $users = User::whereHas('trips', function ($query) {
            $query->where('trip_date', '>=', Carbon::now()->toDateTimeString());
        })->has('accounts')->with('accounts')->get();

        $this->info($users->count());

        foreach ($users as $user) {
            if ($user->accounts && $user->accounts[0]->provider_user_id) {
                $url = $this->requestImages($user->accounts[0]->provider_user_id);
                $this->info($user->id.' '.$user->name.' '.$url);
                $this->downloadAndSave($user, $url);
            } else {
                $this->info('No account');
            }
        }
    }

    private function downloadAndSave($user, $url)
    {
        $img = file_get_contents($url);
        $user->image = $this->files->createFromData($img, 'jpg', 'image/profile/');
        $user->save();
    }

    private function requestImages($facebook_id)
    {
        $response = $this->request($facebook_id);
        if ($response->getStatusCode() == 200) {
            $body = json_decode($response->getBody());
            $url = $body->data->url;

            return $url;
        } else {
            return;
        }
    }

    private function request($id)
    {
        $res = $this->client->request('GET', 'https://graph.facebook.com/v3.3/'.$id.'/picture?redirect=0&height=200&width=200&type=normal');

        return $res;
    }
}
