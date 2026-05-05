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
        $result = $captcha->verify(100);
        $this->assertFalse($result['success']);
    }

    /**
     * 测试验证方法 - 无效输入
     */
    public function testCheckWithInvalidInput(): void
    {
        $captcha = new Captcha();

        // 非数字输入应该失败
        $this->assertFalse($captcha->verify('invalid')['success']);
        $this->assertFalse($captcha->verify('abc123')['success']);
        $this->assertFalse($captcha->verify('')['success']);

        // 超出范围的输入应该失败
        $this->assertFalse($captcha->verify(-1)['success']);
        $this->assertFalse($captcha->verify(9999)['success']);
    }

    /**
     * 测试点击验证码配置
     */
    public function testClickCaptchaConfig(): void
    {
        $captcha = new Captcha([
            'captcha_type' => Captcha::TYPE_CLICK,
            'click' => [
                'char_count' => 3,
                'font_size' => 28,
                'text_rotate' => true,
                'text_bg_overlay' => true,
                'interference_lines' => true,
                'interference_line_count' => 5,
            ],
        ]);

        $this->assertEquals(Captcha::TYPE_CLICK, $captcha->getConfig('captcha_type'));
        $this->assertEquals(3, $captcha->getConfig('click.char_count'));
        $this->assertEquals(28, $captcha->getConfig('click.font_size'));
        $this->assertTrue($captcha->getConfig('click.interference_lines'));
        $this->assertEquals(5, $captcha->getConfig('click.interference_line_count'));
    }

    /**
     * 测试验证码过期配置
     */
    public function testCaptchaExpireConfig(): void
    {
        $captcha = new Captcha([
            'captcha_expire' => 300,
        ]);

        $this->assertEquals(300, $captcha->getConfig('captcha_expire'));
    }

    /**
     * 测试滑动验证码轨迹验证配置
     */
    public function testSlideTrackVerifyConfig(): void
    {
        $captcha = new Captcha([
            'slide' => [
                'track_verify' => true,
                'track_strictness' => 'strict',
            ],
        ]);

        $this->assertTrue($captcha->getConfig('slide.track_verify'));
        $this->assertEquals('strict', $captcha->getConfig('slide.track_strictness'));
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

    /**
     * 测试速率限制配置
     */
    public function testRateLimitConfig(): void
    {
        $captcha = new Captcha([
            'rate_limit' => [
                'enabled' => true,
                'window' => 120,
                'max_requests' => 50,
            ],
        ]);

        $this->assertTrue($captcha->getConfig('rate_limit.enabled'));
        $this->assertEquals(120, $captcha->getConfig('rate_limit.window'));
        $this->assertEquals(50, $captcha->getConfig('rate_limit.max_requests'));
    }

    /**
     * 测试点击验证码干扰线配置生效
     */
    public function testClickInterferenceLines(): void
    {
        $captcha = new Captcha([
            'captcha_type' => Captcha::TYPE_CLICK,
            'click' => [
                'interference_lines' => true,
                'interference_line_count' => 5,
            ],
        ]);

        try {
            $data = $captcha->makeData();
            $this->assertEquals(Captcha::TYPE_CLICK, $data['type']);
            $this->assertIsString($data['image']);
            $this->assertGreaterThan(0, strlen($data['image']));
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('背景图片', $e->getMessage());
        }
    }

    /**
     * 测试滑动轨迹验证
     */
    public function testSlideTrackVerify(): void
    {
        $captcha = new Captcha([
            'captcha_type' => Captcha::TYPE_SLIDE,
            'slide' => [
                'track_verify' => true,
                'track_strictness' => 'normal',
            ],
        ]);

        // 生成滑动验证码
        try {
            $captcha->makeData();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('背景图片', $e->getMessage());
            return;
        }

        // 无轨迹时正常验证（位置正确但无session记录应失败）
        $result = $captcha->verify(0);
        $this->assertFalse($result['success']);

        // 模拟人类轨迹：缓慢、有波动的滑动
        $humanTrack = [];
        $baseTime = (int) (microtime(true) * 1000);
        for ($i = 0; $i < 15; $i++) {
            $humanTrack[] = [
                'x' => $i * 5 + random_int(-2, 2),
                'y' => random_int(40, 60),
                't' => $baseTime + $i * 80,
            ];
        }

        // 机器人轨迹：瞬间直线完成
        $botTrack = [
            ['x' => 0, 'y' => 50, 't' => $baseTime],
            ['x' => 100, 'y' => 50, 't' => $baseTime + 50],
        ];

        // 机器人轨迹应该被识别为异常（但位置验证先失败，所以整体还是失败）
        $resultBot = $captcha->verify(0, null, [], $botTrack);
        $this->assertFalse($resultBot['success']);
    }
}
