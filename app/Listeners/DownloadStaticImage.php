<?php

namespace STS\Listeners;

use STS\Events\Trip\Create;
use STS\Contracts\Repository\Files as FilesRepo;

class DownloadStaticImage
{
    protected $filesRepo;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(FilesRepo $filesRepo)
    {
        $this->filesRepo = $filesRepo;
    }

    /**
     * Handle the event.
     *
     * @param Create $event
     *
     * @return void
     */
    public function handle($event)
    {
        $trip = $event->trip;
        $enc_path = $event->enc_path;
        //$target_dir = public_path("image/paths/" . $id . ".png");

        if ($event->enc_path) {
            $temp_url = 'https://maps.googleapis.com/maps/api/staticmap?size=640x320&path=color:0x0000ff|weight:5';
            $temp_url .= '|enc:'.$enc_path;

            $data = file_get_contents($temp_url);
            $this->filesRepo->createFromData($data, 'png', 'image/paths/', $trip->id);
        }
    }
}
