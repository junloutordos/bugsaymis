<?php

namespace App\Services\Atlas\Dyna;

use Aws\BedrockRuntime\BedrockRuntimeClient;

class DynaBedrockClientFactory
{
    public function make(): BedrockRuntimeClient
    {
        $config = [
            'region' => config('services.bedrock.region', 'us-east-1'),
            'version' => 'latest',
        ];

        // Explicit credentials only when locally configured; otherwise fall back
        // to the ECS task role (mirrors PdfTextExtractorService::makeTextractClient()).
        $key = config('filesystems.disks.s3.key');
        if ($key) {
            $config['credentials'] = [
                'key' => $key,
                'secret' => config('filesystems.disks.s3.secret'),
            ];
        }

        return new BedrockRuntimeClient($config);
    }
}
