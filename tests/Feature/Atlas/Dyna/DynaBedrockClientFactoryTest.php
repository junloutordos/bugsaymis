<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Services\Atlas\Dyna\DynaBedrockClientFactory;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Tests\TestCase;

class DynaBedrockClientFactoryTest extends TestCase
{
    public function test_make_returns_a_configured_bedrock_runtime_client(): void
    {
        config(['services.bedrock.region' => 'us-east-1']);

        $client = (new DynaBedrockClientFactory())->make();

        $this->assertInstanceOf(BedrockRuntimeClient::class, $client);
        $this->assertEquals('us-east-1', $client->getRegion());
    }
}
