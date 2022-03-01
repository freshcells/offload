<?php

namespace Aol\Offload\Lock;
use PHPUnit\Framework\TestCase;

abstract class OffloadLockTest extends TestCase
{
    /** @var OffloadLockInterface */
    protected $lock;

    public function testLock()
    {
        $this->assertNotNull($token = $this->lock->lock(__METHOD__, 10));
        $this->assertNull($this->lock->lock(__METHOD__, 10));
        $this->assertFalse($this->lock->unlock(__METHOD__ . 'x'));
        $this->assertTrue($this->lock->unlock($token));
    }
}
