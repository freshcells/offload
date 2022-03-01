<?php

namespace Aol\Offload\Lock;

class OffloadLockMemcachedTest extends OffloadLockTest
{
    protected function setUp(): void
    {
        $client = new \Memcached();
        $client->addServer('localhost', 11211);
        $client->flush();
        $this->lock = new OffloadLockMemcached($client);
    }
}
