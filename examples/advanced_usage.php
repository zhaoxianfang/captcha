<?php

/**
 * 高级用法示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use zxf\Captcha\Captcha;
use zxf\Captcha\CaptchaHelper;

// ========================================
// 1. 自定义背景图
// ========================================

$captcha = new Captcha([
    'background_images' => [
        '/path/to/custom/bg1.png',
        '/path/to/custom/bg2.png',
        '/path/to/custom/bg3.png',
    ],
    'slide_images' => [
        'transparent' => '/path/to/custom/mark.png',
        'dark' => '/path/to/custom/mark2.png',
    ],
]);

// ========================================
// 2. 调整安全级别
// ========================================

// 高安全级别（容错值小，错误次数少）
$highSecurityCaptcha = new Captcha([
    'fault_tolerance' => 2,      // 容错 2 像素
    'max_error_count' => 3,      // 最多错 3 次
    'ttl' => 180,                // 3 分钟过期
]);

// 低安全级别（容错值大，用户体验好）
$lowSecurityCaptcha = new Captcha([
    'fault_tolerance' => 5,      // 容错 5 像素
    'max_error_count' => 20,     // 最多错 20 次
    'ttl' => 600,                // 10 分钟过期
]);

// ========================================
// 3. Cookie 配置（生产环境）
// ========================================

$secureCaptcha = new Captcha([
    'cookie' => [
        'name' => 'my_captcha_key',
        'secure' => true,           // 仅 HTTPS
        'httponly' => true,         // 禁止 JS 读取
        'samesite' => 'Strict',     // 防止 CSRF
    ],
]);

// ========================================
// 4. 验证码使用流程
// ========================================

function demoUsage()
{
    $captcha = new Captcha();
    
    // 步骤 1：生成验证码（通常在 /captcha/image 接口）
    $result = $captcha->generate();
    
    // 设置 Cookie（框架适配器中自动完成）
    $captcha->setKeyToCookie($result['key']);
    
    // 输出图片
    header('Content-Type: image/' . $result['format']);
    echo $result['image'];
}

function demoVerify()
{
    $captcha = new Captcha();
    
    // 步骤 2：验证（接收前端传来的偏移量）
    $offset = $_POST['tn_r'] ?? 0;
    
    $result = $captcha->verify($offset);
    
    if ($result['success']) {
        // 验证成功，清除 Cookie
        $captcha->clearCookie();
        
        // 执行业务逻辑...
        echo "验证通过！";
    } else {
        // 验证失败
        echo "验证失败：" . $result['message'];
        
        // 如果有剩余次数，提示用户
        if (isset($result['remaining_attempts'])) {
            echo "还剩 " . $result['remaining_attempts'] . " 次机会";
        }
    }
}

// ========================================
// 5. 批量验证（如批量注册场景）
// ========================================

class BatchValidator
{
    private Captcha $captcha;
    private array $verifiedIps = [];
    
    public function __construct()
    {
        $this->captcha = new Captcha([
            'security' => [
                'frequency_limit_enabled' => true,
                'min_request_interval' => 2,
            ],
        ]);
    }
    
    public function validate(string $ip, string $offset): bool
    {
        // IP 级别频率检查
        if (isset($this->verifiedIps[$ip])) {
            $lastTime = $this->verifiedIps[$ip];
            if (time() - $lastTime < 2) {
                return false;
            }
        }
        
        $result = $this->captcha->verify($offset);
        
        if ($result['success']) {
            $this->verifiedIps[$ip] = time();
        }
        
        return $result['success'];
    }
}

// ========================================
// 6. 验证码统计
// ========================================

class CaptchaStats
{
    private array $stats = [
        'generated' => 0,
        'verified_success' => 0,
        'verified_fail' => 0,
        'expired' => 0,
    ];
    
    public function recordGenerated(): void
    {
        $this->stats['generated']++;
    }
    
    public function recordVerifyResult(bool $success, string $code = ''): void
    {
        if ($success) {
            $this->stats['verified_success']++;
        } else {
            $this->stats['verified_fail']++;
            if ($code === 'expired') {
                $this->stats['expired']++;
            }
        }
    }
    
    public function getStats(): array
    {
        $total = $this->stats['verified_success'] + $this->stats['verified_fail'];
        $successRate = $total > 0 ? round($this->stats['verified_success'] / $total * 100, 2) : 0;
        
        return array_merge($this->stats, [
            'total_verify' => $total,
            'success_rate' => $successRate . '%',
        ]);
    }
}

// ========================================
// 7. 调试模式
// ========================================

function debugCaptcha()
{
    // 获取系统信息
    $info = CaptchaHelper::getSystemInfo();
    echo "=== 系统信息 ===\n";
    print_r($info);
    
    // 创建测试验证码
    $captcha = new Captcha([
        'fault_tolerance' => 10,  // 调试时增大容错值
    ]);
    
    $result = $captcha->generate();
    echo "\n=== 生成的验证码 ===\n";
    echo "Key: " . $result['key'] . "\n";
    echo "格式: " . $result['format'] . "\n";
    echo "尺寸: " . $result['width'] . "x" . $result['height'] . "\n";
    
    // 保存图片用于调试
    file_put_contents('/tmp/captcha_test.' . $result['format'], $result['image']);
    echo "图片已保存到: /tmp/captcha_test." . $result['format'] . "\n";
}
