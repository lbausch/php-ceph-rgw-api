<?php

namespace Tests;

use Aws\Credentials\Credentials;
use DateTime;
use GuzzleHttp\Psr7\Request;
use LBausch\CephRadosgwAdmin\Config;
use LBausch\CephRadosgwAdmin\Signature\SignatureV2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use Psr\Http\Message\RequestInterface;

#[CoversClass(Config::class)]
#[CoversMethod(SignatureV2::class, 'canonicalizedAmzHeaders')]
#[CoversMethod(SignatureV2::class, 'canonicalizedResource')]
#[CoversMethod(SignatureV2::class, 'contentMd5')]
#[CoversMethod(SignatureV2::class, 'expires')]
#[CoversMethod(SignatureV2::class, 'signRequest')]
#[CoversMethod(SignatureV2::class, 'stringToSign')]
final class SignatureV2Test extends TestCase
{
    public function testRequestIsSigned(): void
    {
        $config = Config::make([
            'credentials' => new Credentials('access key', 'secret key'),
        ]);

        $request = new Request('GET', 'http://gateway/foo', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $signature = new SignatureV2();

        $signedRequest = $signature->signRequest($request, $config);

        $headers = $signedRequest->getHeaders();

        $this->assertArrayHasKey('Date', $headers);
        $this->assertCount(1, $headers['Date']);

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertCount(1, $headers['Authorization']);

        $this->assertMatchesRegularExpression('/^AWS access key:([a-zA-Z0-9+\/]){27}=$/', $headers['Authorization'][0]);
    }

    public function testChecksumForRequestBodyIsCalculatedCorrectly(): void
    {
        $requestWithoutBody = new Request('POST', 'http://gateway/foo', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $requestWithBody = new Request('POST', 'http://gateway/foo', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], '{"foo":"bar"}');

        $requestWithExistingHeader = new Request('POST', 'http://gateway/foo', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Content-MD5' => '32ba4e1f433e6cadc4e7599787fbcc5e',
        ]);

        $requestPOST = new Request('POST', 'http://gateway/foo', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], '{"foo":"bar"}');

        $requestGET = new Request('GET', 'http://gateway/foo', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], '{"foo":"bar"}');

        $requestPUT = new Request('PUT', 'http://gateway/foo', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], '{"foo":"bar"}');

        $signature = new class extends SignatureV2 {
            public function contentMd5(RequestInterface $request): string
            {
                return parent::contentMd5($request);
            }
        };

        $this->assertSame('', $signature->contentMd5($requestWithoutBody));
        $this->assertSame('9bb58f26192e4ba00f01e2e7b136bbd8', $signature->contentMd5($requestWithBody));
        $this->assertSame('32ba4e1f433e6cadc4e7599787fbcc5e', $signature->contentMd5($requestWithExistingHeader));
        $this->assertSame('9bb58f26192e4ba00f01e2e7b136bbd8', $signature->contentMd5($requestPOST));
        $this->assertSame('', $signature->contentMd5($requestGET));
        $this->assertSame('', $signature->contentMd5($requestPUT));
    }

    public function testRequestHasExpireDate(): void
    {
        $request = new Request('GET', 'http://gateway/foo', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $requestWithExistingHeader = new Request('GET', 'http://gateway/foo', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Date' => 'foo',
        ]);

        $signature = new class extends SignatureV2 {
            public function expires(RequestInterface $request): string
            {
                return parent::expires($request);
            }
        };

        $expires = $signature->expires($request);
        $date = DateTime::createFromFormat(DateTime::RFC2822, $expires);

        $this->assertNotFalse($date);
        $this->assertSame($date->format(DateTime::RFC2822), $expires);
        $this->assertSame('foo', $signature->expires($requestWithExistingHeader));
    }
}
