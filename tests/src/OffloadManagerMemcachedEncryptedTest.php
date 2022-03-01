<?php

namespace Aol\Offload;

use Aol\Offload\Encoders\OffloadEncoderEncryptedAes256;

class OffloadManagerMemcachedEncryptedTest extends OffloadManagerMemcachedTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->manager->getCache()->setEncoder(
            new OffloadEncoderEncryptedAes256(
                $this->manager->getCache()->getEncoder(),
                'foo',
                ['foo' => 'bar']
            )
        );
    }
}
