<?php

namespace Symfony\Component\HttpClient;

use Symfony\Contracts\Http\HttpClientInterface;

class CurlHttpClientTest extends BaseIntegrationTest
{
    protected function createHttpClient(): HttpClientInterface
    {
        return new CurlHttpClient([
            'http' => ['follow_location' => false],
        ]);
    }
}
