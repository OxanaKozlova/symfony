<?php


namespace Symfony\Component\HttpClient;

use Http\Client\Tests\HttpBaseTest;
use Symfony\Contracts\Http\Exception\ExceptionInterface;
use Symfony\Contracts\Http\HttpClientInterface;
use Symfony\Contracts\Http\ResponseInterface;

abstract class BaseIntegrationTest extends HttpBaseTest
{
    /**
     * @var HttpClientInterface
     */
    protected $httpClient;

    protected function setUp()
    {
        $this->httpClient = $this->createHttpClient();
    }

    protected function tearDown()
    {
        unset($this->httpClient);
    }

    abstract protected function createHttpClient(): HttpClientInterface;

    /**
     * @dataProvider requestProvider
     * @group        integration
     */
    public function testSendRequest($method, $uri, array $headers, $body)
    {
        if (null !== $body) {
            $headers['Content-Length'] = (string) strlen($body);
        }

        $response = $this->httpClient->request($method, $uri, [
            'headers'=> $headers,
            'body' => $body,
        ]);

        $this->assertResponse(
            $response,
            [
                'body' => 'HEAD' === $method ? null : 'Ok',
            ]
        );
        $this->assertRequest($method, $headers, $body, '1.1');
    }

    /**
     * @dataProvider requestWithOutcomeProvider
     * @group        integration
     */
    public function testSendRequestWithOutcome($uriAndOutcome, $protocolVersion, array $headers, $body)
    {
        if ('1.0' === $protocolVersion) {
            $body = null;
        }

        if (null != $body) {
            $headers['Content-Length'] = (string) strlen($body);
        }

        $response = $this->httpClient->request($method = 'GET', $uriAndOutcome[0], [
            'headers'=> $headers,
            'body' => $body,
            'http' => ['protocol_version'=>$protocolVersion],
        ]);

        $outcome = $uriAndOutcome[1];
        $outcome['protocolVersion'] = $protocolVersion;

        $this->assertResponse($response, $outcome);
        $this->assertRequest($method, $headers, $body, $protocolVersion);
    }

    /**
     * @expectedException \Symfony\Contracts\Http\Exception\ExceptionInterface
     * @group             integration
     */
    public function testSendWithInvalidUri()
    {
        $this->httpClient->request('GET', $this->getInvalidUri(), [
            'headers'=> $this->defaultHeaders,
        ])->getContent();
    }

    /**
     * @param ResponseInterface $response
     * @param array             $options
     */
    protected function assertResponse($response, array $options = [])
    {
        $this->assertInstanceOf(ResponseInterface::class, $response);

        $options = array_merge($this->defaultOptions, $options);

        try {
            // Make sure we do the request
            $response->getStatusCode();
        } catch (ExceptionInterface $e) {
            // Symfony HTTP exceptions are also Responses...
            $response = $e;
        }

        // The response version may be greater or equal to the request version. See https://tools.ietf.org/html/rfc2145#section-2.3
        //$this->assertTrue(substr($options['protocolVersion'], 0, 1) === substr($response->getProtocolVersion(), 0, 1) && 1 !== version_compare($options['protocolVersion'], $response->getProtocolVersion()));
        $this->assertSame($options['statusCode'], $response->getStatusCode());
        $this->assertNotEmpty($headers = $response->getHeaders());

        foreach ($options['headers'] as $name => $value) {
            $lowerName = strtolower($name);
            $this->assertTrue(array_key_exists($lowerName, $headers));
            $this->assertStringStartsWith($value, $headers[$lowerName][0]);
        }

        if (null === $options['body']) {
            $this->assertEmpty($response->getContent());
        } else {
            $this->assertContains($options['body'], $response->getContent());
        }
    }

}