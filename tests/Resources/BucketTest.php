<?php

namespace Tests\Resources;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use LBausch\CephRadosgwAdmin\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use Tests\TestCase;

#[CoversClass(\LBausch\CephRadosgwAdmin\ApiRequest::class)]
#[CoversClass(\LBausch\CephRadosgwAdmin\ApiResponse::class)]
#[CoversClass(Client::class)]
#[CoversClass(\LBausch\CephRadosgwAdmin\Config::class)]
#[CoversClass(\LBausch\CephRadosgwAdmin\Signature\SignatureV2::class)]
#[CoversClass(\LBausch\CephRadosgwAdmin\Signature\SignatureV4::class)]
#[CoversClass(\LBausch\CephRadosgwAdmin\Middlewares\SignatureMiddleware::class)]
#[CoversClass(\LBausch\CephRadosgwAdmin\Resources\AbstractResource::class)]
#[CoversMethod(\LBausch\CephRadosgwAdmin\Resources\Bucket::class, 'check')]
#[CoversMethod(\LBausch\CephRadosgwAdmin\Resources\Bucket::class, 'info')]
#[CoversMethod(\LBausch\CephRadosgwAdmin\Resources\Bucket::class, 'link')]
#[CoversMethod(\LBausch\CephRadosgwAdmin\Resources\Bucket::class, 'list')]
#[CoversMethod(\LBausch\CephRadosgwAdmin\Resources\Bucket::class, 'policy')]
#[CoversMethod(\LBausch\CephRadosgwAdmin\Resources\Bucket::class, 'remove')]
#[CoversMethod(\LBausch\CephRadosgwAdmin\Resources\Bucket::class, 'removeObject')]
#[CoversMethod(\LBausch\CephRadosgwAdmin\Resources\Bucket::class, 'setQuota')]
#[CoversMethod(\LBausch\CephRadosgwAdmin\Resources\Bucket::class, 'unlink')]
final class BucketTest extends TestCase
{
    public function testBucketsAreListed(): void
    {
        $transactions = [];

        $config = $this->getConfigWithMockedHandlers($transactions, [
            new Response(200, [], '["mybucket"]'),
        ]);

        $client = Client::make('http://gateway', 'acesskey', 'secretkey', $config);

        $response = $client->bucket()->list();

        $this->assertSame(['mybucket'], $response->get());

        $this->assertCount(1, $transactions);

        /** @var Request $request */
        $request = $transactions[0]['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('http://gateway/admin/metadata/bucket', (string) $request->getUri());
    }

    public function testBucketIsRemoved(): void
    {
        $transactions = [];

        $config = $this->getConfigWithMockedHandlers($transactions, [
            new Response(200, [], '["mybucket"]'),
        ]);

        $client = Client::make('http://gateway', 'acesskey', 'secretkey', $config);

        $response = $client->bucket()->remove('mybucket');

        $this->assertSame(['mybucket'], $response->get());

        $this->assertCount(1, $transactions);

        /** @var Request $request */
        $request = $transactions[0]['request'];

        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('http://gateway/admin/bucket?bucket=mybucket', (string) $request->getUri());
    }

    public function testBucketInfoIsRetrieved(): void
    {
        $transactions = [];

        $config = $this->getConfigWithMockedHandlers($transactions, [
            new Response(200, [], <<<'EOT'
{
    "bucket": "mybucket",
    "num_shards": 1,
    "tenant": "",
    "bucket_quota": {
        "enabled": false,
        "check_on_raw": false,
        "max_size": -1,
        "max_size_kb": 0,
        "max_objects": -1
    }
}
EOT),
        ]);

        $client = Client::make('http://gateway', 'acesskey', 'secretkey', $config);

        $response = $client->bucket()->info([
            'bucket' => 'mybucket',
        ]);

        $this->assertCount(1, $transactions);

        /** @var Request $request */
        $request = $transactions[0]['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('http://gateway/admin/bucket?bucket=mybucket', (string) $request->getUri());

        $this->assertSame([
            'bucket' => 'mybucket',
            'num_shards' => 1,
            'tenant' => '',
            'bucket_quota' => [
                'enabled' => false,
                'check_on_raw' => false,
                'max_size' => -1,
                'max_size_kb' => 0,
                'max_objects' => -1,
            ],
        ], $response->get());
    }

    public function testBucketIndexIsChecked(): void
    {
        $transactions = [];

        $config = $this->getConfigWithMockedHandlers($transactions, [
            new Response(200, [], '[]'),
        ]);

        $client = Client::make('http://gateway', 'acesskey', 'secretkey', $config);

        $response = $client->bucket()->check('mybucket');

        $this->assertSame([], $response->get());

        $this->assertCount(1, $transactions);

        /** @var Request $request */
        $request = $transactions[0]['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('http://gateway/admin/bucket?index=&bucket=mybucket', (string) $request->getUri());
    }

    public function testBucketIsLinked(): void
    {
        $transactions = [];

        $config = $this->getConfigWithMockedHandlers($transactions, [
            new Response(),
        ]);

        $client = Client::make('http://gateway', 'acesskey', 'secretkey', $config);

        $response = $client->bucket()->link('mybucket', 'foobar');

        $this->assertNull($response->get());

        $this->assertCount(1, $transactions);

        /** @var Request $request */
        $request = $transactions[0]['request'];

        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('http://gateway/admin/bucket?bucket=mybucket&uid=foobar', (string) $request->getUri());
    }

    public function testBucketIsUnlinked(): void
    {
        $transactions = [];

        $config = $this->getConfigWithMockedHandlers($transactions, [
            new Response(),
        ]);

        $client = Client::make('http://gateway', 'acesskey', 'secretkey', $config);

        $response = $client->bucket()->unlink('mybucket', 'foobar');

        $this->assertNull($response->get());

        $this->assertCount(1, $transactions);

        /** @var Request $request */
        $request = $transactions[0]['request'];

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('http://gateway/admin/bucket?bucket=mybucket&uid=foobar', (string) $request->getUri());
    }

    public function testBucketPolicyIsRetrieved(): void
    {
        $transactions = [];

        $config = $this->getConfigWithMockedHandlers($transactions, [
            new Response(200, [], <<<'EOT'
{
    "acl": {},
    "owner": {
        "id": "foo",
        "display_name": "bar"
    }
}
EOT),
        ]);

        $client = Client::make('http://gateway', 'acesskey', 'secretkey', $config);

        $response = $client->bucket()->policy('mybucket');

        $this->assertCount(1, $transactions);

        /** @var Request $request */
        $request = $transactions[0]['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('http://gateway/admin/bucket?policy=&bucket=mybucket', (string) $request->getUri());

        $this->assertSame([
            'acl' => [],
            'owner' => [
                'id' => 'foo',
                'display_name' => 'bar',
            ],
        ], $response->get());
    }

    public function testBucketObjectIsRemoved(): void
    {
        $transactions = [];

        $config = $this->getConfigWithMockedHandlers($transactions, [
            new Response(),
        ]);

        $client = Client::make('http://gateway', 'acesskey', 'secretkey', $config);

        $response = $client->bucket()->removeObject('mybucket', 'foo');

        $this->assertNull($response->get());

        $this->assertCount(1, $transactions);

        /** @var Request $request */
        $request = $transactions[0]['request'];

        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('http://gateway/admin/bucket?bucket=mybucket&object=foo', (string) $request->getUri());
    }

    public function testBucketQuotaIsSet(): void
    {
        $transactions = [];

        $config = $this->getConfigWithMockedHandlers($transactions, [
            new Response(),
        ]);

        $client = Client::make('http://gateway', 'acesskey', 'secretkey', $config);

        $response = $client->bucket()->setQuota('foo', 'mybucket', ['enabled' => true]);

        $this->assertNull($response->get());

        $this->assertCount(1, $transactions);

        /** @var Request $request */
        $request = $transactions[0]['request'];

        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('http://gateway/admin/bucket?quota=&uid=foo&bucket=mybucket', (string) $request->getUri());
    }
}
