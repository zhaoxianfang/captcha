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

    /**
     * 测试验证状态方法
     */
    public function testCheckStatus(): void
    {
        $captcha = new Captcha();

        // 初始状态应该未验证
        $this->assertFalse($captcha->isChecked());

        // 刷新后仍然应该未验证
        $captcha->refresh();
        $this->assertFalse($captcha->isChecked());
    }

    /**
     * 测试验证方法 - 无 Session 数据时应失败
     */
    public function testCheckWithoutSession(): void
    {
        $captcha = new Captcha();

        // 没有生成验证码直接验证应该失败
        $result = $captcha->check(100);
        $this->assertFalse($result);
    }

    /**
     * 测试验证方法 - 无效输入
     */
    public function testCheckWithInvalidInput(): void
    {
        $captcha = new Captcha();

        // 非数字输入应该失败
        $this->assertFalse($captcha->check('invalid'));
        $this->assertFalse($captcha->check('abc123'));
        $this->assertFalse($captcha->check(''));

        // 超出范围的输入应该失败
        $this->assertFalse($captcha->check(-1));
        $this->assertFalse($captcha->check(9999));
    }

    /**
     * 测试图片生成
     */
    public function testImageGeneration(): void
    {
        $captcha = new Captcha();

        // 测试 makeRaw 方法返回数据
        try {
            $imageData = $captcha->makeRaw();
            $this->assertIsString($imageData);
            $this->assertGreaterThan(0, strlen($imageData));
        } catch (\RuntimeException $e) {
            // 如果没有背景图片可能会抛出异常，这是正常的
            $this->assertStringContainsString('背景图片', $e->getMessage());
        }
    }

    /**
     * 测试所有配置项
     */
    public function testAllConfigOptions(): void
    {
        $config = [
            'fault_tolerance' => 5,
            'max_error_count' => 5,
            'bg_width' => 300,
            'bg_height' => 200,
            'mark_width' => 60,
            'mark_height' => 60,
            'output_format' => 'png',
            'webp_quality' => 50,
            'png_quality' => 5,
            'session_prefix' => 'test_prefix',
        ];

        $captcha = new Captcha($config);

        foreach ($config as $key => $value) {
            $this->assertEquals($value, $captcha->getConfig($key));
        }
    }

    /**
     * 测试链式调用
     */
    public function testChaining(): void
    {
        $captcha = new Captcha();

        $result = $captcha
            ->setConfig('fault_tolerance', 10)
            ->setConfig('max_error_count', 20);

        $this->assertInstanceOf(Captcha::class, $result);
        $this->assertEquals(10, $captcha->getConfig('fault_tolerance'));
        $this->assertEquals(20, $captcha->getConfig('max_error_count'));
    }

    /**
     * 测试辅助函数存在
     */
    public function testHelperFunctionsExist(): void
    {
        $this->assertTrue(function_exists('xf_captcha'));
        $this->assertTrue(function_exists('xf_captcha_check'));
        $this->assertTrue(function_exists('xf_captcha_refresh'));
        $this->assertTrue(function_exists('xf_captcha_is_checked'));
        $this->assertTrue(function_exists('xf_captcha_html'));
        $this->assertTrue(function_exists('xf_captcha_script'));
    }

    /**
     * 测试 xf_captcha 辅助函数
     */
    public function testXfCaptchaHelper(): void
    {
        // 测试获取实例
        $captcha = xf_captcha();
        $this->assertInstanceOf(Captcha::class, $captcha);

        // 测试配置数组
        $captcha2 = xf_captcha(['fault_tolerance' => 15]);
        $this->assertInstanceOf(Captcha::class, $captcha2);
        $this->assertEquals(15, $captcha2->getConfig('fault_tolerance'));
    }
}
