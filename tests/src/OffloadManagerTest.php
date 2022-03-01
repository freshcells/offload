<?php

namespace Aol\Offload;
use PHPUnit\Framework\TestCase;

use Aol\Offload\Cache\OffloadCacheInterface;
use Aol\Offload\Deferred\OffloadDeferred;
use Aol\Offload\Encoders\OffloadEncoderStandard;
use Aol\Offload\Exceptions\OffloadDrainException;

abstract class OffloadManagerTest extends TestCase
{
    /** @var OffloadManager */
    protected $manager;
    /** @var OffloadCacheInterface */
    protected $base_cache;

    public function testFetch()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $result = $this->manager->fetch(__METHOD__, function () use ($data) { return $data; });
        $this->assertEquals($data, $result->getData());
        $this->assertFalse($result->isFromCache());
    }

    public function testFetchCachedStale()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $result = $this->manager->fetch(__METHOD__, function () use ($data) { return $data; });
        $this->assertEquals($data, $result->getData());
        $this->assertFalse($result->isFromCache());
        $result = $this->manager->fetch(__METHOD__, function () use ($data) { return $data; });
        $this->assertEquals($data, $result->getData());
        $this->assertTrue($result->isFromCache());
        $this->assertGreaterThanOrEqual($result->getExpireTime(), time());
        $this->assertTrue($result->isStale());
    }

    public function testFetchCachedFresh()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $result = $this->manager->fetchCached(__METHOD__, 5, function () use ($data) { return $data; });
        $this->assertEquals($data, $result->getData());
        $this->assertFalse($result->isFromCache());
        $result = $this->manager->fetch(__METHOD__, function () use ($data) { return $data; });
        $this->assertEquals($data, $result->getData());
        $this->assertEquals($data, $result->getDeferred()->wait());
        $this->assertTrue($result->isFromCache());
        $this->assertTrue($result->getStaleTime() < 0);
        $this->assertFalse($result->isStale());
        $this->assertGreaterThan(time(), $result->getExpireTime());
    }

    public function testFetchForced()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $result = $this->manager->fetchCached(__METHOD__, 5, function () use ($data) { return $data; });
        $this->assertEquals($data, $result->getData());
        $this->assertFalse($result->isFromCache());
        $this->assertFalse($result->isStale());
        $result = $this->manager->fetchCached(__METHOD__, 5, function () use ($data) { return $data; }, [OffloadManagerInterface::OPTION_FORCE => true]);
        $this->assertEquals($data, $result->getData());
        $this->assertFalse($result->isFromCache());
        $this->assertFalse($result->isStale());
    }

    public function testFetchBad()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $result = $this->manager->fetchCached(__METHOD__, 5, function (OffloadRun $run) use ($data) {
            $run->setBad();
            return $data;
        });
        $this->assertEquals($data, $result->getData());
        $this->assertFalse($result->isFromCache());
        $result = $this->manager->fetch(__METHOD__, function () use ($data) { return $data; });
        $this->assertEquals($data, $result->getData());
        $this->assertFalse($result->isFromCache());
    }

    public function testDrain()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $result = $this->manager->fetch(__METHOD__, function () use ($data) { return $data; });
        $this->assertEquals($data, $result->getData());
        $this->assertFalse($result->isFromCache());
        $result = $this->manager->fetch(__METHOD__, function () use ($data) { return $data; });
        $this->assertEquals($data, $result->getData());
        $this->assertTrue($result->isFromCache());
        $this->assertTrue($result->isStale());
        $this->assertTrue($this->manager->hasWork());
        $drained = $this->manager->drain();
        $this->assertTrue(is_array($drained));
        $this->assertNotEmpty($drained);
        $this->assertEquals([__METHOD__ => $data], $drained);
    }

    public function testQueueNonExclusive()
    {
        $invoked = 0;
        $increment_invoked = function () use (&$invoked) { return $invoked++; };
        $this->manager->queue(__METHOD__, $increment_invoked, [OffloadManagerInterface::OPTION_EXCLUSIVE => false]);
        $this->manager->queue(__METHOD__, $increment_invoked, [OffloadManagerInterface::OPTION_EXCLUSIVE => false]);
        $this->manager->drain();
        $this->assertEquals(2, $invoked);
    }

    public function testQueueCached()
    {
        $invoked = 0;
        $increment_invoked = function () use (&$invoked) { return $invoked++; };
        $this->manager->queueCached(__METHOD__, 1, $increment_invoked);
        $this->manager->queueCached(__METHOD__, 1, $increment_invoked);
        $this->manager->drain();
        $this->assertEquals(1, $invoked);
    }

    public function testQueueCachedNonExclusive()
    {
        $invoked = 0;
        $increment_invoked = function () use (&$invoked) { return $invoked++; };
        $this->manager->queueCached(__METHOD__, 1, $increment_invoked, [OffloadManagerInterface::OPTION_EXCLUSIVE => false]);
        $this->manager->queueCached(__METHOD__, 1, $increment_invoked, [OffloadManagerInterface::OPTION_EXCLUSIVE => false]);
        $this->manager->drain();
        $this->assertEquals(2, $invoked);
    }

    public function testFetchMissZeroCacheTime()
    {
        $value = 'hey';
        $result = $this->manager->fetch(__METHOD__, function () use ($value) { return $value; }, [
            OffloadManager::OPTION_TTL_FRESH => 0,
            OffloadManager::OPTION_TTL_STALE => 0
        ]);
        $this->assertTrue($result instanceof OffloadResult);
        $this->assertEquals($value, $result->getData());
    }

    public function testBaseCache()
    {
        $this->assertEquals($this->base_cache, $this->manager->getCache()->getBaseCache());
    }

    public function testGetCacheHit()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $task = function () use ($data) { return $data; };
        $this->manager->fetchCached(__METHOD__, 5, $task);
        $result = $this->manager->getCache()->get(__METHOD__);
        $this->assertEquals($data, $result->getData());
    }

    public function testGetManyCacheHit()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $task = function () use ($data) { return $data; };
        $this->manager->fetchCached(__METHOD__ . '1', 5, $task);
        $this->manager->fetchCached(__METHOD__ . '2', 5, $task);
        $result = $this->manager->getCache()->getMany([__METHOD__ . '1', __METHOD__ . 'X', __METHOD__ . '2']);
        $this->assertTrue(is_array($result));
        $this->assertEquals(3, count($result));
        $this->assertEquals($data, $result[0]->getData());
        $this->assertNull($result[1]->getData());
        $this->assertFalse($result[1]->isFromCache());
        $this->assertEquals($data, $result[2]->getData());
    }

    public function testDeleteCache()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $task = function () use ($data) { return $data; };
        $this->manager->fetchCached(__METHOD__ . '1', 5, $task);
        $this->manager->fetchCached(__METHOD__ . '2', 5, $task);
        $this->assertTrue($this->manager->getCache()->get(__METHOD__ . '1')->isFromCache());
        $this->assertTrue($this->manager->getCache()->get(__METHOD__ . '2')->isFromCache());
        $this->assertEquals(2, $this->manager->getCache()->delete([__METHOD__ . '1', __METHOD__ . '2']));
        $this->assertFalse($this->manager->getCache()->get(__METHOD__ . '1')->isFromCache());
        $this->assertFalse($this->manager->getCache()->get(__METHOD__ . '2')->isFromCache());
    }

    public function testGetCacheMiss()
    {
        $result = $this->manager->getCache()->get(__METHOD__);
        $this->assertNull($result->getData());
        $this->assertFalse($result->isFromCache());
    }

    public function testCacheExpires()
    {
        $this->manager->getCache()->set(__METHOD__, '1', 0.5, 0.5);
        $this->assertEquals('1', $this->manager->getCache()->get(__METHOD__)->getData());
        sleep(1);
        $this->assertFalse($this->manager->getCache()->get(__METHOD__)->isFromCache());
    }

    public function testRealDeferred()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $result = $this->manager->fetchCached(__METHOD__, 5, function () use ($data) {
            return new OffloadDeferred(function () use ($data) {
                usleep(1000 * 100);
                return $data;
            });
        });

        $this->assertNull($this->base_cache->get(__METHOD__));
        $this->assertEquals($data, $result->getData());
        $this->assertNotNull($this->base_cache->get(__METHOD__));
    }

    public function testRealDeferredAlreadyWaited()
    {
        $data = __METHOD__ . time() . rand(0, 100);
        $result = $this->manager->fetchCached(__METHOD__, 5, function () use ($data) {
            $defer = new OffloadDeferred(function () use ($data) {
                usleep(1000 * 100);
                return $data;
            });
            $defer->wait();
            return $defer;
        });

        $this->assertNotNull($this->base_cache->get(__METHOD__));
        $this->assertEquals($data, $result->getData());
    }

    public function testDrainExceptions()
    {
        $ex1 = new \Exception();
        $ex2 = new \RuntimeException();
        $ex3 = new \InvalidArgumentException();
        $data1 = 1;
        $data2 = ['hi'];
        $this->manager->queue('k1', function () use ($ex1) { throw $ex1; });
        $this->manager->queue('k2', function () use ($data1) { return $data1; });
        $this->manager->queue('k3', function () use ($ex2) { throw $ex2; });
        $this->manager->queue('k4', function () use ($data2) { return $data2; });
        $this->manager->queue('k5', function () use ($ex3) {
            return new OffloadDeferred(function () use ($ex3) { throw $ex3; });
        });
        try {
            $this->manager->drain();
        } catch (OffloadDrainException $ex) {
            $this->assertEquals(['k1'=>$ex1, 'k3'=>$ex2, 'k5'=>$ex3], $ex->getDrainedExceptions());
            $this->assertEquals(['k2'=>$data1, 'k4'=>$data2], $ex->getDrainedResults());
            return;
        }
        $this->fail('Expected OffloadDrainException');
    }

    public function testCacheUsesSameEncoder()
    {
        $encoder = $this->manager->getCache()->getEncoder();
        self::assertSame($encoder, $this->manager->getCache()->getEncoder());
    }

    public function testCacheEncoderCanBeSet()
    {
        $encoder1 = $this->manager->getCache()->getEncoder();
        $encoder2 = new OffloadEncoderStandard();
        $this->manager->getCache()->setEncoder($encoder2);
        self::assertSame($encoder2, $this->manager->getCache()->getEncoder());
        self::assertNotSame($encoder1, $this->manager->getCache()->getEncoder());
    }

    public function testCacheDecoderCanBeSet()
    {
        $decoder1 = $this->manager->getCache()->getDecoder();
        $decoder2 = new OffloadEncoderStandard();
        $this->manager->getCache()->setDecoder($decoder2);
        self::assertSame($decoder2, $this->manager->getCache()->getDecoder());
        self::assertNotSame($decoder1, $this->manager->getCache()->getDecoder());
    }

    public function testCacheDecoderDefaultsToEncoder()
    {
        $encoder = new OffloadEncoderStandard();
        $this->manager->getCache()->setEncoder($encoder);
        self::assertSame($encoder, $this->manager->getCache()->getEncoder());
        self::assertSame($encoder, $this->manager->getCache()->getDecoder());
    }
}
