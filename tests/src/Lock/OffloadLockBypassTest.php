<?php

namespace Aol\Offload\Lock;
use PHPUnit\Framework\TestCase;

class OffloadLockBypassTest extends TestCase
{
    /** @var OffloadLockBypass */
    protected $lock;

    protected function setUp(): void
    {
        $this->lock = new OffloadLockBypass();
    }

    public function testLock()
    {
        $this->assertNotNull($token = $this->lock->lock(__METHOD__, 10));
        $this->assertNotNull($this->lock->lock(__METHOD__, 10));
        $this->assertTrue($this->lock->unlock(__METHOD__ . 'x'));
        $this->assertTrue($this->lock->unlock($token));
    }
}
