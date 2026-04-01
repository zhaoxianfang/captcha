<?php

declare(strict_types=1);

namespace zxf\Captcha\Tests;

use PHPUnit\Framework\TestCase;
use zxf\Captcha\Storage\ArrayStorage;
use zxf\Captcha\Storage\SessionStorage;

/**
 * 存储测试
 */
class StorageTest extends TestCase
{
    /**
     * 测试 ArrayStorage
     */
    public function testArrayStorage(): void
    {
        $storage = new ArrayStorage(['prefix' => 'test_']);
        
        // 测试 set/get
        $storage->set('key1', 'value1', 300);
        $this->assertEquals('value1', $storage->get('key1'));
        
        // 测试 has
        $this->assertTrue($storage->has('key1'));
        $this->assertFalse($storage->has('nonexistent'));
        
        // 测试 delete
        $storage->delete('key1');
        $this->assertFalse($storage->has('key1'));
        
        // 测试默认值
        $this->assertEquals('default', $storage->get('nonexistent', 'default'));
    }

    /**
     * 测试过期
     */
    public function testArrayStorageExpiration(): void
    {
        $storage = new ArrayStorage();
        
        // 设置 1 秒过期
        $storage->set('key1', 'value1', 1);
        $this->assertEquals('value1', $storage->get('key1'));
        
        // 等待过期
        sleep(2);
        
        $this->assertNull($storage->get('key1'));
    }

    /**
     * 测试 ArrayStorage clear
     */
    public function testArrayStorageClear(): void
    {
        $storage = new ArrayStorage();
        
        $storage->set('key1', 'value1');
        $storage->set('key2', 'value2');
        
        $storage->clear();
        
        $this->assertNull($storage->get('key1'));
        $this->assertNull($storage->get('key2'));
    }

    /**
     * 测试 SessionStorage（如果 session 可用）
     */
    public function testSessionStorage(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $storage = new SessionStorage(['session_key' => 'test_captcha']);
        
        // 测试 set/get
        $storage->set('key1', 'value1', 300);
        $this->assertEquals('value1', $storage->get('key1'));
        
        // 测试 has
        $this->assertTrue($storage->has('key1'));
        
        // 测试 delete
        $storage->delete('key1');
        $this->assertFalse($storage->has('key1'));
    }

    /**
     * 测试存储复杂数据
     */
    public function testComplexData(): void
    {
        $storage = new ArrayStorage();
        
        $complexData = [
            'array' => [1, 2, 3],
            'object' => new \stdClass(),
            'nested' => ['a' => ['b' => 'c']],
        ];
        
        $storage->set('complex', $complexData);
        $retrieved = $storage->get('complex');
        
        $this->assertEquals($complexData['array'], $retrieved['array']);
        $this->assertEquals($complexData['nested'], $retrieved['nested']);
    }
}
