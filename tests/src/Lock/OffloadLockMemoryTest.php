<?php

namespace Aol\Offload\Lock;

class OffloadLockMemoryTest extends OffloadLockTest
{
    protected function setUp(): void
    {
        $this->lock = new OffloadLockMemory();
    }
}
