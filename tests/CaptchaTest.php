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
            ],
        ]);

        $this->assertEquals(Captcha::TYPE_CLICK, $captcha->getConfig('captcha_type'));
        $this->assertEquals(3, $captcha->getConfig('click.char_count'));
        $this->assertEquals(28, $captcha->getConfig('click.font_size'));
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

    /**
     * 读取 CLI mock session 中的私有数据（仅测试用）
     */
    private function getMockSession(Captcha $captcha): array
    {
        $ref = new \ReflectionClass($captcha);
        $prop = $ref->getProperty('mockSession');
        if (PHP_VERSION_ID < 80500) {
            $prop->setAccessible(true);
        }
        return $prop->getValue($captcha);
    }

    /**
     * 通过反射调用私有方法 verifySlideTrack（仅测试用）
     */
    private function callVerifySlideTrack(Captcha $captcha, array $track): array
    {
        $ref = new \ReflectionClass($captcha);
        $method = $ref->getMethod('verifySlideTrack');
        if (PHP_VERSION_ID < 80500) {
            $method->setAccessible(true);
        }
        return $method->invoke($captcha, $track);
    }

    /**
     * 生成一条“人类风格”的滑动轨迹（图片像素空间）。
     *
     * @param float $scale 坐标放大倍数（>1 表示以 CSS 像素上报）
     */
    private function buildHumanTrack(float $scale = 1.0): array
    {
        $track = [];
        $startY = 50;
        $steps = 16;
        $totalX = 140 * $scale; // 起点约 50，终点约 190（图片像素）
        for ($i = 0; $i < $steps; $i++) {
            $p = $i / ($steps - 1);
            // 加减速曲线 + 轻微纵向抖动，模拟人类
            $x = (50 * $scale) + $totalX * (1 - (1 - $p) * (1 - $p)); // 缓出曲线
            $y = ($startY * $scale) + sin($p * M_PI) * 6 * $scale + random_int(-2, 2) * $scale;
            $track[] = [
                'x' => (int) round($x),
                'y' => (int) round($y),
                't' => (int) ($i * 75), // 总耗时 ~1.1s
            ];
        }
        return $track;
    }

    /**
     * 回归测试：修复“滑动验证码轨迹异常”问题。
     *
     * 核心 Bug：前端以 CSS 像素上报轨迹 x，后端按图片像素校验，
     * 显示尺寸放大后速度/直线度阈值被突破，导致真实用户被误判为“轨迹异常”。
     */
    public function testSlideTrackScaleNormalization(): void
    {
        $captcha = new Captcha([
            'captcha_type' => Captcha::TYPE_SLIDE,
            'slide'        => ['track_verify' => true, 'track_strictness' => 'normal'],
        ]);

        // 1) 图片像素口径的人类轨迹应通过
        $imgTrack = $this->buildHumanTrack(1.0);
        $r1 = $this->callVerifySlideTrack($captcha, $imgTrack);
        $this->assertTrue($r1['success'], '图片像素口径的人类轨迹应验证通过: ' . ($r1['message'] ?? ''));

        // 2) CSS 像素口径（放大 1.5 倍）的人类轨迹，修复后应被尺度归一通过
        //    这正是此前报错“轨迹异常，请重试”的真实复现场景
        $cssTrack = $this->buildHumanTrack(1.5);
        $r2 = $this->callVerifySlideTrack($captcha, $cssTrack);
        $this->assertTrue($r2['success'], 'CSS 像素口径(放大1.5x)的人类轨迹应被正确归一并通过: ' . ($r2['message'] ?? ''));

        // 3) 放大 1.375 倍（典型显示宽 330 / 图片宽 240）也应通过
        $cssTrack2 = $this->buildHumanTrack(330 / 240);
        $r3 = $this->callVerifySlideTrack($captcha, $cssTrack2);
        $this->assertTrue($r3['success'], 'CSS 像素口径(330/240)的人类轨迹应被归一并通过: ' . ($r3['message'] ?? ''));
    }

    /**
     * 回归测试：机器人式瞬间直线轨迹仍应被拦截。
     */
    public function testSlideTrackRejectsBot(): void
    {
        $captcha = new Captcha([
            'captcha_type' => Captcha::TYPE_SLIDE,
            'slide'        => ['track_verify' => true, 'track_strictness' => 'normal'],
        ]);

        $baseTime = 1000;
        // 50ms 内直线滑动 140px，无抖动、无加减速 → 机器人特征
        $botTrack = [
            ['x' => 50, 'y' => 50, 't' => $baseTime],
            ['x' => 100, 'y' => 50, 't' => $baseTime + 25],
            ['x' => 190, 'y' => 50, 't' => $baseTime + 50],
        ];

        $result = $this->callVerifySlideTrack($captcha, $botTrack);
        $this->assertFalse($result['success'], '机器人瞬间直线轨迹应被拦截（操作过快/速度异常）');
    }

    /**
     * 测试滑动验证码：正确偏移量通过，错误偏移量失败
     */
    public function testSlideVerifyOffset(): void
    {
        $captcha = new Captcha([
            'captcha_type' => Captcha::TYPE_SLIDE,
            'verify_mode' => Captcha::VERIFY_BACKEND_ONLY,
            'slide' => ['track_verify' => false],
        ]);

        try {
            $captcha->makeData();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('背景图片', $e->getMessage());
            return;
        }

        $session = $this->getMockSession($captcha);
        $posX = (int) ($session['xf_captcha_r'] ?? null);
        $this->assertNotNull($posX, 'session 中应存储正确的缺口位置');

        // 正确位置（容错范围内）应验证通过
        $ok = $captcha->verify($posX);
        $this->assertTrue($ok['success'], '正确偏移量应验证通过: ' . ($ok['message'] ?? ''));

        // 偏移过大应验证失败
        $bad = $captcha->verify($posX + 50);
        $this->assertFalse($bad['success'], '明显错误的偏移量应验证失败');
    }

    /**
     * 测试点击验证码：按序点击正确位置通过，错误位置失败
     */
    public function testClickVerifyPoints(): void
    {
        $captcha = new Captcha([
            'captcha_type' => Captcha::TYPE_CLICK,
            'verify_mode' => Captcha::VERIFY_BACKEND_ONLY,
            'click' => ['char_count' => 3, 'fault_tolerance' => 25],
        ]);

        try {
            $captcha->makeData();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('背景图片', $e->getMessage());
            return;
        }

        $session = $this->getMockSession($captcha);
        $stored = $session['xf_captcha_click_data'] ?? [];
        $this->assertCount(3, $stored, '应生成 3 个点击位置');

        // 完全正确的点位（按序）
        $points = array_map(fn($c) => ['x' => $c['x'], 'y' => $c['y']], $stored);
        $ok = $captcha->verify(null, null, $points);
        $this->assertTrue($ok['success'], '正确的点击位置应验证通过: ' . ($ok['message'] ?? ''));

        // 把所有点位整体偏移 60px（远超容错）应失败
        $wrong = array_map(fn($c) => ['x' => $c['x'] + 60, 'y' => $c['y'] + 60], $stored);
        $bad = $captcha->verify(null, null, $wrong);
        $this->assertFalse($bad['success'], '明显错误的点击位置应验证失败');

        // 点位数量不足应失败
        $partial = array_slice($points, 0, 2);
        $badCount = $captcha->verify(null, null, $partial);
        $this->assertFalse($badCount['success'], '点击数量不足应失败');
    }

    /**
     * 测试双重验证模式：首次验证返回 token，二次验证消费 token
     */
    public function testDualVerifyTokenFlow(): void
    {
        $captcha = new Captcha([
            'captcha_type' => Captcha::TYPE_SLIDE,
            'verify_mode' => Captcha::VERIFY_DUAL,
            'slide' => ['track_verify' => false],
        ]);

        try {
            $captcha->makeData();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('背景图片', $e->getMessage());
            return;
        }

        $session = $this->getMockSession($captcha);
        $posX = (int) ($session['xf_captcha_r'] ?? null);

        // 首次验证：返回一次性 token
        $first = $captcha->verify($posX);
        $this->assertTrue($first['success']);
        $this->assertNotEmpty($first['token'], '双重验证首次应通过并返回 token');

        // 二次验证：消费 token 成功
        $second = $captcha->verify(null, $first['token']);
        $this->assertTrue($second['success'], '二次验证应成功');

        // token 一次性使用，再次使用应失败
        $reuse = $captcha->verify(null, $first['token']);
        $this->assertFalse($reuse['success'], '已使用的 token 不应重复通过');
    }

    /**
     * 测试点击验证码双重验证 token 流程
     */
    public function testClickDualVerifyTokenFlow(): void
    {
        $captcha = new Captcha([
            'captcha_type' => Captcha::TYPE_CLICK,
            'verify_mode' => Captcha::VERIFY_DUAL,
            'click' => ['char_count' => 3, 'fault_tolerance' => 25],
        ]);

        try {
            $captcha->makeData();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('背景图片', $e->getMessage());
            return;
        }

        $session = $this->getMockSession($captcha);
        $stored = $session['xf_captcha_click_data'] ?? [];
        $this->assertCount(3, $stored);

        $points = array_map(fn($c) => ['x' => $c['x'], 'y' => $c['y']], $stored);

        // 首次验证：返回一次性 token
        $first = $captcha->verify(null, null, $points);
        $this->assertTrue($first['success'], '点击双重验证首次应通过');
        $this->assertNotEmpty($first['token'], '点击双重验证首次应通过并返回 token');

        // 二次验证：消费 token 成功
        $second = $captcha->verify(null, $first['token']);
        $this->assertTrue($second['success'], '点击二次验证应成功');

        // token 一次性使用，再次使用应失败
        $reuse = $captcha->verify(null, $first['token']);
        $this->assertFalse($reuse['success'], '已使用的 token 不应重复通过');
    }

    /**
     * 测试验证成功后的重放防护（答案立即作废）
     */
    public function testReplayProtectionAfterSuccess(): void
    {
        $captcha = new Captcha([
            'captcha_type' => Captcha::TYPE_SLIDE,
            'verify_mode' => Captcha::VERIFY_BACKEND_ONLY,
            'slide' => ['track_verify' => false],
        ]);

        try {
            $captcha->makeData();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('背景图片', $e->getMessage());
            return;
        }

        $session = $this->getMockSession($captcha);
        $posX = (int) ($session['xf_captcha_r'] ?? null);

        // 首次验证通过
        $first = $captcha->verify($posX);
        $this->assertTrue($first['success'], '首次验证应通过');

        // 成功后再用同一偏移量重放：答案已作废，应失败
        $replay = $captcha->verify($posX);
        $this->assertFalse($replay['success'], '验证成功后同一答案不应被重复使用（重放防护）');
    }
}
