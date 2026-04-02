<?php

/**
 * zxf/captcha - 单元测试
 *
 * @package     zxf\Captcha\Tests
 * @author      zhaoxianfang <zhaoxianfang@163.com>
 * @license     MIT
 */

declare(strict_types=1);

namespace zxf\Captcha\Tests;

use PHPUnit\Framework\TestCase;
use zxf\Captcha\Captcha;

/**
 * 验证码单元测试
 */
class CaptchaTest extends TestCase
{
    /**
     * 测试创建实例
     */
    public function testCanCreateInstance(): void
    {
        $captcha = new Captcha();
        $this->assertInstanceOf(Captcha::class, $captcha);
    }

    /**
     * 测试配置获取和设置
     */
    public function testConfigAccess(): void
    {
        $captcha = new Captcha();

        // 测试获取默认配置
        $this->assertIsInt($captcha->getConfig('fault_tolerance'));
        $this->assertEquals(3, $captcha->getConfig('fault_tolerance'));

        // 测试获取不存在的配置
        $this->assertNull($captcha->getConfig('non_existent_key'));
        $this->assertEquals('default', $captcha->getConfig('non_existent_key', 'default'));
    }

    /**
     * 测试配置设置
     */
    public function testConfigSet(): void
    {
        $captcha = new Captcha();

        // 测试单个配置设置
        $captcha->setConfig('fault_tolerance', 5);
        $this->assertEquals(5, $captcha->getConfig('fault_tolerance'));

        // 测试批量配置设置
        $captcha->setConfigs([
            'fault_tolerance' => 7,
            'max_error_count' => 20,
        ]);
        $this->assertEquals(7, $captcha->getConfig('fault_tolerance'));
        $this->assertEquals(20, $captcha->getConfig('max_error_count'));
    }

    /**
     * 测试自定义配置构造函数
     */
    public function testCustomConfigInConstructor(): void
    {
        $captcha = new Captcha([
            'fault_tolerance' => 10,
            'session_prefix' => 'test_captcha',
        ]);

        $this->assertEquals(10, $captcha->getConfig('fault_tolerance'));
        $this->assertEquals('test_captcha', $captcha->getConfig('session_prefix'));
    }
}
