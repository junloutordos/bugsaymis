<?php

namespace App\Services\Atlas\Dyna;

use Google\Client;

class DynaGoogleClientFactory
{
    public function make(): Client
    {
        return new Client(['client_id' => config('services.google.dyna_client_id')]);
    }
}
