<?php

namespace App\Services\StudentAttendance;

use Google\Client;

class StudentGoogleClientFactory
{
    public function make(): Client
    {
        return new Client(['client_id' => config('services.google.mobile_client_id')]);
    }
}
