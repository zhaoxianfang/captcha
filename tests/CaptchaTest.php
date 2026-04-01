<?php

declare(strict_types=1);

namespace zxf\Captcha\Tests;

use PHPUnit\Framework\TestCase;
use zxf\Captcha\Captcha;
use zxf\Captcha\Storage\ArrayStorage;

/**
 * 验证码单元测试
 */
class CaptchaTest extends TestCase
{
    private Captcha $captcha;
    private ArrayStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new ArrayStorage();
        $this->captcha = new Captcha([
            'fault_tolerance' => 3,
            'max_error_count' => 5,
            'ttl' => 300,
        ], $this->storage);
    }

    /**
     * 测试生成验证码
     */
    public function testGenerate(): void
    {
        $result = $this->captcha->generate();
        
        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('format', $result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
        $this->assertArrayHasKey('key', $result);
        
        $this->assertNotEmpty($result['image']);
        $this->assertNotEmpty($result['key']);
        $this->assertGreaterThan(0, strlen($result['image']));
        
        // 验证尺寸
        $this->assertEquals(240, $result['width']);
        $this->assertEquals(150, $result['height']);
    }

    /**
     * 测试验证成功
     */
    public function testVerifySuccess(): void
    {
        $result = $this->captcha->generate();
        $key = $result['key'];
        
        // 设置到 storage 以便 verify 使用
        $this->captcha->setKeyToCookie($key);
        
        // 获取存储的正确偏移量
        $correctOffset = $this->storage->get("offset:{$key}");
        
        // 使用正确偏移量验证
        $verifyResult = $this->captcha->verify($correctOffset, $key);
        
        $this->assertTrue($verifyResult['success']);
        $this->assertEquals('success', $verifyResult['code']);
        $this->assertEquals('验证成功', $verifyResult['message']);
    }

    /**
     * 测试验证失败（偏移量错误）
     */
    public function testVerifyFail(): void
    {
        $result = $this->captcha->generate();
        $key = $result['key'];
        
        // 使用错误偏移量验证
        $verifyResult = $this->captcha->verify(999, $key);
        
        $this->assertFalse($verifyResult['success']);
        $this->assertEquals('failed', $verifyResult['code']);
        $this->assertArrayHasKey('remaining_attempts', $verifyResult);
    }

    /**
     * 测试容错机制
     */
    public function testFaultTolerance(): void
    {
        $captcha = new Captcha([
            'fault_tolerance' => 5,
        ], $this->storage);
        
        $result = $captcha->generate();
        $key = $result['key'];
        
        $correctOffset = $this->storage->get("offset:{$key}");
        
        // 在容错范围内应该通过
        $verifyResult = $captcha->verify($correctOffset + 3, $key);
        $this->assertTrue($verifyResult['success']);
        
        // 容错范围外应该失败
        $captcha2 = new Captcha([
            'fault_tolerance' => 2,
        ], new ArrayStorage());
        
        $result2 = $captcha2->generate();
        $key2 = $result2['key'];
        $correctOffset2 = $captcha2->getCurrentKey();
        
        $verifyResult2 = $captcha2->verify(999, $key2);
        $this->assertFalse($verifyResult2['success']);
    }

    /**
     * 测试错误次数限制
     */
    public function testMaxErrorCount(): void
    {
        $captcha = new Captcha([
            'fault_tolerance' => 0,
            'max_error_count' => 3,
        ], $this->storage);
        
        $result = $captcha->generate();
        $key = $result['key'];
        
        // 连续错误 3 次
        for ($i = 0; $i < 3; $i++) {
            $verifyResult = $captcha->verify(999, $key);
            
            if ($i < 2) {
                $this->assertEquals('failed', $verifyResult['code']);
                $this->assertArrayHasKey('remaining_attempts', $verifyResult);
            } else {
                // 第 3 次错误后应该提示错误次数过多
                $this->assertEquals('too_many_errors', $verifyResult['code']);
            }
        }
    }

    /**
     * 测试过期
     */
    public function testExpired(): void
    {
        $captcha = new Captcha([
            'ttl' => 1, // 1 秒过期
        ], $this->storage);
        
        $result = $captcha->generate();
        $key = $result['key'];
        
        // 等待过期
        sleep(2);
        
        $verifyResult = $captcha->verify(0, $key);
        
        $this->assertFalse($verifyResult['success']);
        $this->assertEquals('expired', $verifyResult['code']);
    }

    /**
     * 测试清除数据
     */
    public function testClear(): void
    {
        $result = $this->captcha->generate();
        $key = $result['key'];
        
        // 清除数据
        $this->captcha->clear($key);
        
        // 验证应该失败（已过期）
        $verifyResult = $this->captcha->verify(0, $key);
        $this->assertEquals('expired', $verifyResult['code']);
    }

    /**
     * 测试 isVerified 方法
     */
    public function testIsVerified(): void
    {
        $result = $this->captcha->generate();
        $key = $result['key'];
        
        // 未验证前
        $this->assertFalse($this->captcha->isVerified($key));
        
        // 获取正确偏移量并验证
        $correctOffset = $this->storage->get("offset:{$key}");
        $this->captcha->verify($correctOffset, $key);
        
        // 验证后
        $this->assertTrue($this->captcha->isVerified($key));
    }

    /**
     * 测试配置获取
     */
    public function testGetConfig(): void
    {
        $config = $this->captcha->getConfig();
        
        $this->assertArrayHasKey('bg_width', $config);
        $this->assertArrayHasKey('bg_height', $config);
        $this->assertArrayHasKey('fault_tolerance', $config);
        $this->assertArrayHasKey('max_error_count', $config);
        
        $this->assertEquals(240, $config['bg_width']);
        $this->assertEquals(150, $config['bg_height']);
    }

    /**
     * 测试图片格式
     */
    public function testImageFormats(): void
    {
        // WebP 格式
        if (function_exists('imagewebp')) {
            $captchaWebp = new Captcha([
                'output' => ['format' => 'webp'],
            ], new ArrayStorage());
            
            $result = $captchaWebp->generate();
            $this->assertEquals('webp', $result['format']);
        }
        
        // PNG 格式
        $captchaPng = new Captcha([
            'output' => ['format' => 'png'],
        ], new ArrayStorage());
        
        $result = $captchaPng->generate();
        $this->assertEquals('png', $result['format']);
    }

    /**
     * 测试无效偏移量
     */
    public function testInvalidOffset(): void
    {
        $result = $this->captcha->generate();
        $key = $result['key'];
        
        // 测试各种无效输入
        $invalidOffsets = ['', 'abc', null, -1, []];
        
        foreach ($invalidOffsets as $offset) {
            $verifyResult = $this->captcha->verify($offset ?? 0, $key);
            $this->assertFalse($verifyResult['success']);
        }
    }
}
