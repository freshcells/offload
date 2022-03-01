<?php

namespace Aol\Offload\Cache;

class OffloadCacheRedisTest extends OffloadCacheTest
{
    protected function setUp(): void
    {
        $client = new \Predis\Client();
        $client->flushdb();
        $this->cache = new OffloadCacheRedis($client);
    }
}
