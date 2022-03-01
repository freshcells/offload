<?php

namespace Aol\Offload\Cache;

class OffloadCacheMemoryTest extends OffloadCacheTest
{
    protected function setUp(): void
    {
        $this->cache = new OffloadCacheMemory();
    }
}
