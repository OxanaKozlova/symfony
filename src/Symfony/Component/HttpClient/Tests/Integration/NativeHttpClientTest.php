<?php

namespace Symfony\Component\HttpClient;

use Symfony\Contracts\Http\HttpClientInterface;

class NativeHttpClientTest extends BaseIntegrationTest
{
    protected function createHttpClient(): HttpClientInterface
    {
        return new NativeHttpClient([
            'http' => ['follow_location' => false],
        ]);
    }
}
