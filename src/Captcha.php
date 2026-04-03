<?php

/**
 * zxf/captcha - 高性能滑动验证码 PHP 扩展包
 *
 * @package     zxf\Captcha
 * @license     MIT
 * @version     1.0.0
 */

declare(strict_types=1);

namespace zxf\Captcha;

use GdImage;
use RuntimeException;
use InvalidArgumentException;

/**
 * 滑动验证码核心类
 *
 * 该类负责生成滑动验证码图片、验证用户滑动结果等核心功能
 * 支持自定义背景图片、滑块样式、容错精度等配置
 *
 * @since  1.0.0
 */
class Captcha
{
    /**
     * 验证模式：仅前端验证（不安全，仅用于测试）
     */
    public const VERIFY_FRONTEND_ONLY = 'frontend_only';

    /**
     * 验证模式：仅后端验证
     */
    public const VERIFY_BACKEND_ONLY = 'backend_only';

    /**
     * 验证模式：前端+后端双重验证（推荐）
     */
    public const VERIFY_DUAL = 'dual';

    /**
     * 完整背景图片资源
     */
    private ?GdImage $imFullBg = null;

    /**
     * 裁剪后的背景图片资源
     */
    private ?GdImage $imBg = null;

    /**
     * 滑块图片资源
     */
    private ?GdImage $imSlide = null;

    /**
     * 最终合成图片资源
     */
    private ?GdImage $im = null;

    /**
     * 背景图片宽度（像素）
     */
    private int $bgWidth = 240;

    /**
     * 背景图片高度（像素）
     */
    private int $bgHeight = 150;

    /**
     * 滑块标记宽度（像素）
     */
    private int $markWidth = 50;

    /**
     * 滑块标记高度（像素）
     */
    private int $markHeight = 50;

    /**
     * 滑块在背景上的横坐标位置
     */
    private int $posX = 0;

    /**
     * 滑块在背景上的纵坐标位置
     */
    private int $posY = 0;

    /**
     * 容错像素值
     */
    private int $faultTolerance = 3;

    /**
     * 最大错误次数
     */
    private int $maxErrorCount = 10;

    /**
     * Token过期时间（秒）
     */
    private int $tokenExpire = 300;

    /**
     * 配置数组
     */
    private array $config = [];

    /**
     * Session 键名 - 存储验证码正确位置
     */
    private string $sessionKeyR = 'captcha_r';

    /**
     * Session 键名 - 存储错误次数
     */
    private string $sessionKeyErr = 'captcha_err';

    /**
     * Session 键名 - 存储验证状态
     */
    private string $sessionKeyCheck = 'captcha_check';

    /**
     * Session 键名 - 存储验证令牌
     */
    private string $sessionKeyToken = 'captcha_token';

    /**
     * Session 键名 - 存储令牌过期时间
     */
    private string $sessionKeyTokenExpire = 'captcha_token_expire';

    /**
     * 是否使用模拟Session（CLI模式）
     */
    private bool $useMockSession = false;

    /**
     * 模拟Session存储
     */
    private array $mockSession = [];

    /**
     * 默认背景图片路径
     */
    private array $defaultBgImages = [];

    /**
     * 构造函数
     *
     * @param array $config 验证码配置数组
     *
     * @throws RuntimeException 当 GD 库未安装时抛出
     */
    public function __construct(array $config = [])
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('GD 扩展未安装，请先安装 GD 扩展');
        }

        // 初始化默认配置
        $defaultConfig = $this->getDefaultConfig();
        $this->config = array_merge($defaultConfig, $config);

        // 应用配置
        $this->applyConfig();

        // 确保 Session 已启动
        $this->ensureSessionStarted();
    }

    /**
     * 获取默认配置
     *
     * @return array 默认配置数组
     */
    private function getDefaultConfig(): array
    {
        $basePath = dirname(__DIR__);

        return [
            // 前端图标组图片路径
            'tool_icon_img' => $basePath . '/resources/assets/images/icon.png',

            // 黑色滑块图片路径
            'slide_dark_img' => $basePath . '/resources/assets/images/mark_02.png',

            // 透明滑块图片路径
            'slide_transparent_img' => $basePath . '/resources/assets/images/mark_01.png',

            // 背景图片目录
            'bg_images_dir' => $basePath . '/resources/assets/images/bg/',

            // 背景图片列表
            'bg_images' => [],

            // 容错像素值
            'fault_tolerance' => 3,

            // 最大错误次数
            'max_error_count' => 10,

            // 背景图片宽度
            'bg_width' => 240,

            // 背景图片高度
            'bg_height' => 150,

            // 滑块宽度
            'mark_width' => 50,

            // 滑块高度
            'mark_height' => 50,

            // 图片输出格式
            'output_format' => 'webp',

            // WebP 图片质量
            'webp_quality' => 40,

            // PNG 图片压缩级别
            'png_quality' => 7,

            // Session 前缀
            'session_prefix' => 'xf_captcha',

            // 验证模式
            'verify_mode' => self::VERIFY_DUAL,

            // Token过期时间（秒）
            'token_expire' => 300,
        ];
    }

    /**
     * 应用配置到类属性
     */
    private function applyConfig(): void
    {
        $this->bgWidth = (int) ($this->config['bg_width'] ?? 240);
        $this->bgHeight = (int) ($this->config['bg_height'] ?? 150);
        $this->markWidth = (int) ($this->config['mark_width'] ?? 50);
        $this->markHeight = (int) ($this->config['mark_height'] ?? 50);
        $this->faultTolerance = (int) ($this->config['fault_tolerance'] ?? 3);
        $this->maxErrorCount = (int) ($this->config['max_error_count'] ?? 10);
        $this->tokenExpire = (int) ($this->config['token_expire'] ?? 300);

        // 设置 Session 键名
        $prefix = $this->config['session_prefix'] ?? 'xf_captcha';
        $this->sessionKeyR = $prefix . '_r';
        $this->sessionKeyErr = $prefix . '_err';
        $this->sessionKeyCheck = $prefix . '_check';
        $this->sessionKeyToken = $prefix . '_token';
        $this->sessionKeyTokenExpire = $prefix . '_token_expire';

        // 修复图片路径
        $this->fixImagePaths();

        // 设置默认背景图片
        $this->defaultBgImages = $this->getBgImages();
    }

    /**
     * 修复图片路径
     */
    private function fixImagePaths(): void
    {
        $packageRoot = dirname(__DIR__);

        $pathMap = [
            'tool_icon_img' => 'icon.png',
            'slide_dark_img' => 'mark_02.png',
            'slide_transparent_img' => 'mark_01.png',
        ];

        foreach ($pathMap as $key => $defaultFilename) {
            $configuredPath = $this->config[$key] ?? '';
            if (empty($configuredPath) || !file_exists($configuredPath)) {
                $defaultPath = $packageRoot . '/resources/assets/images/' . $defaultFilename;
                if (file_exists($defaultPath)) {
                    $this->config[$key] = $defaultPath;
                }
            }
        }

        $bgDir = $this->config['bg_images_dir'] ?? '';
        if (empty($bgDir) || !is_dir($bgDir)) {
            $defaultBgDir = $packageRoot . '/resources/assets/images/bg/';
            if (is_dir($defaultBgDir)) {
                $this->config['bg_images_dir'] = $defaultBgDir;
            }
        }
    }

    /**
     * 获取背景图片列表
     *
     * @return array 背景图片路径数组
     */
    private function getBgImages(): array
    {
        if (!empty($this->config['bg_images'])) {
            return $this->config['bg_images'];
        }

        $bgDir = $this->config['bg_images_dir'];
        if (!is_dir($bgDir)) {
            return [];
        }

        $images = [];
        $extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

        foreach (scandir($bgDir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions, true)) {
                $images[] = $bgDir . $file;
            }
        }

        return $images;
    }

    /**
     * 确保 Session 已启动
     */
    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            if (php_sapi_name() === 'cli') {
                $this->useMockSession = true;
                return;
            }

            if (!headers_sent()) {
                session_start();
            } else {
                $this->useMockSession = true;
            }
        }
    }

    /**
     * 设置 Session 值
     */
    private function setSessionValue(string $key, mixed $value): void
    {
        if ($this->useMockSession) {
            $this->mockSession[$key] = $value;
        } else {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * 获取 Session 值
     */
    private function getSessionValue(string $key, mixed $default = null): mixed
    {
        if ($this->useMockSession) {
            return $this->mockSession[$key] ?? $default;
        }
        return $_SESSION[$key] ?? $default;
    }

    /**
     * 删除 Session 值
     */
    private function deleteSessionValue(string $key): void
    {
        if ($this->useMockSession) {
            unset($this->mockSession[$key]);
        } else {
            unset($_SESSION[$key]);
        }
    }

    /**
     * 检查 Session 值是否存在
     */
    private function hasSessionValue(string $key): bool
    {
        if ($this->useMockSession) {
            return isset($this->mockSession[$key]);
        }
        return isset($_SESSION[$key]);
    }

    /**
     * 生成验证码图片
     *
     * @param array $bgImages 自定义背景图片路径数组
     *
     * @return void
     * @throws RuntimeException 当图片生成失败时抛出
     */
    public function make(array $bgImages = []): void
    {
        try {
            $this->init($bgImages);
            $this->createSlide();
            $this->createBg();
            $this->merge();
            $this->output();
        } finally {
            $this->destroy();
        }
    }

    /**
     * 生成验证码图片并返回二进制数据
     *
     * @param array $bgImages 自定义背景图片路径数组
     *
     * @return string 图片二进制数据
     * @throws RuntimeException 当图片生成失败时抛出
     */
    public function makeRaw(array $bgImages = []): string
    {
        try {
            $this->init($bgImages);
            $this->createSlide();
            $this->createBg();
            $this->merge();

            $format = $this->getOutputFormat();
            $quality = $format === 'webp'
                ? (int) ($this->config['webp_quality'] ?? 40)
                : (int) ($this->config['png_quality'] ?? 7);

            ob_start();
            $func = 'image' . $format;
            $func($this->im, null, $quality);
            $data = ob_get_clean();

            if ($data === false) {
                throw new RuntimeException('生成图片数据失败');
            }

            return $data;
        } finally {
            $this->destroy();
        }
    }

    /**
     * 获取图片输出格式
     *
     * @return string 图片格式（webp 或 png）
     */
    private function getOutputFormat(): string
    {
        if (isset($_GET['nowebp']) || !function_exists('imagewebp')) {
            return 'png';
        }

        return $this->config['output_format'] === 'webp' ? 'webp' : 'png';
    }

    /**
     * 验证用户滑动结果
     *
     * @param string|int|null $offset 用户滑动的偏移量
     * @param string|null $token 验证令牌（双重验证模式使用）
     *
     * @return array 验证结果 ['success' => bool, 'token' => string|null, 'message' => string]
     */
    public function verify(string|int|null $offset = null, ?string $token = null): array
    {
        $verifyMode = $this->config['verify_mode'] ?? self::VERIFY_DUAL;

        return match ($verifyMode) {
            self::VERIFY_FRONTEND_ONLY => $this->verifyFrontendOnly(),
            self::VERIFY_BACKEND_ONLY => $this->verifyBackendOnly($offset),
            self::VERIFY_DUAL => $this->verifyDual($offset, $token),
            default => $this->verifyDual($offset, $token),
        };
    }

    /**
     * 仅前端验证模式（不安全，仅用于测试）
     */
    private function verifyFrontendOnly(): array
    {
        return [
            'success' => true,
            'token' => 'frontend_only',
            'message' => '前端验证通过',
        ];
    }

    /**
     * 仅后端验证模式
     */
    private function verifyBackendOnly(string|int|null $offset): array
    {
        if (!$this->hasSessionValue($this->sessionKeyR)) {
            return [
                'success' => false,
                'token' => null,
                'message' => '验证码已过期，请刷新重试',
            ];
        }

        if ($offset === '' || $offset === null) {
            $offset = $_REQUEST['captcha_r'] ?? $_REQUEST['xf_captcha'] ?? '';
        }

        if (!is_numeric($offset)) {
            $this->handleFailedCheck();
            return [
                'success' => false,
                'token' => null,
                'message' => '无效的偏移量',
            ];
        }

        $offset = (float) $offset;
        $correctPos = (float) $this->getSessionValue($this->sessionKeyR);

        $diff = abs($correctPos - $offset);
        $isValid = $diff <= $this->faultTolerance;

        if ($isValid) {
            $this->handleSuccessfulCheck();
            return [
                'success' => true,
                'token' => null,
                'message' => '验证成功',
            ];
        } else {
            $this->handleFailedCheck();
            return [
                'success' => false,
                'token' => null,
                'message' => '验证失败，请重试',
            ];
        }
    }

    /**
     * 双重验证模式
     */
    private function verifyDual(string|int|null $offset, ?string $token): array
    {
        // 如果有token，进行二次验证
        if ($token !== null && $token !== '') {
            return $this->verifySecondary($token);
        }

        // 首次验证
        return $this->verifyPrimary($offset);
    }

    /**
     * 首次验证（前端滑动验证）
     */
    private function verifyPrimary(string|int|null $offset): array
    {
        if (!$this->hasSessionValue($this->sessionKeyR)) {
            return [
                'success' => false,
                'token' => null,
                'message' => '验证码已过期，请刷新重试',
            ];
        }

        if ($offset === '' || $offset === null) {
            $offset = $_REQUEST['captcha_r'] ?? $_REQUEST['xf_captcha'] ?? '';
        }

        if (!is_numeric($offset)) {
            $this->handleFailedCheck();
            return [
                'success' => false,
                'token' => null,
                'message' => '无效的偏移量',
            ];
        }

        $offset = (float) $offset;
        $correctPos = (float) $this->getSessionValue($this->sessionKeyR);

        $diff = abs($correctPos - $offset);
        $isValid = $diff <= $this->faultTolerance;

        if ($isValid) {
            // 根据验证模式处理
            $verifyMode = $this->config['verify_mode'] ?? self::VERIFY_DUAL;
            
            if ($verifyMode === self::VERIFY_BACKEND_ONLY) {
                // 仅后端验证模式：验证成功后立即销毁数据
                $this->handleSuccessfulCheck();
                return [
                    'success' => true,
                    'token' => null,
                    'message' => '验证成功',
                ];
            }
            
            // 双重验证模式：生成一次性令牌
            $token = $this->generateToken();
            $this->setSessionValue($this->sessionKeyToken, $token);
            $this->setSessionValue($this->sessionKeyTokenExpire, time() + $this->tokenExpire);
            $this->setSessionValue($this->sessionKeyCheck, 'pending');

            return [
                'success' => true,
                'token' => $token,
                'message' => '验证成功，请完成后续操作',
            ];
        } else {
            $this->handleFailedCheck();
            return [
                'success' => false,
                'token' => null,
                'message' => '验证失败，请重试',
            ];
        }
    }

    /**
     * 二次验证（表单提交时验证）
     */
    private function verifySecondary(string $token): array
    {
        // 检查是否有待验证的token
        if (!$this->hasSessionValue($this->sessionKeyToken)) {
            return [
                'success' => false,
                'token' => null,
                'message' => '验证令牌不存在，请重新验证',
            ];
        }

        // 检查token是否匹配
        $storedToken = $this->getSessionValue($this->sessionKeyToken);
        if (!hash_equals($storedToken, $token)) {
            return [
                'success' => false,
                'token' => null,
                'message' => '验证令牌无效',
            ];
        }

        // 检查token是否过期
        $expireTime = $this->getSessionValue($this->sessionKeyTokenExpire, 0);
        if (time() > $expireTime) {
            $this->clearToken();
            return [
                'success' => false,
                'token' => null,
                'message' => '验证令牌已过期，请重新验证',
            ];
        }

        // 检查是否已使用过
        $checkStatus = $this->getSessionValue($this->sessionKeyCheck);
        if ($checkStatus === 'used') {
            return [
                'success' => false,
                'token' => null,
                'message' => '验证令牌已被使用，请重新验证',
            ];
        }

        // 标记为已使用
        $this->setSessionValue($this->sessionKeyCheck, 'used');
        $this->handleSuccessfulCheck();

        return [
            'success' => true,
            'token' => null,
            'message' => '二次验证成功',
        ];
    }

    /**
     * 生成验证令牌
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 清除Token
     */
    private function clearToken(): void
    {
        $this->deleteSessionValue($this->sessionKeyToken);
        $this->deleteSessionValue($this->sessionKeyTokenExpire);
        $this->deleteSessionValue($this->sessionKeyCheck);
    }

    /**
     * 处理验证成功
     */
    private function handleSuccessfulCheck(): void
    {
        $this->deleteSessionValue($this->sessionKeyR);
        $this->deleteSessionValue($this->sessionKeyErr);
    }

    /**
     * 处理验证失败
     */
    private function handleFailedCheck(): void
    {
        $errCount = ($this->getSessionValue($this->sessionKeyErr) ?? 0) + 1;
        $this->setSessionValue($this->sessionKeyErr, $errCount);

        if ($errCount > $this->maxErrorCount) {
            $this->deleteSessionValue($this->sessionKeyR);
        }

        $this->setSessionValue($this->sessionKeyCheck, 'error');
    }

    /**
     * 检查验证码是否已通过验证（向后兼容）
     *
     * @return bool 是否已通过验证
     */
    public function isChecked(): bool
    {
        $checkStatus = $this->getSessionValue($this->sessionKeyCheck);
        return $checkStatus === 'used';
    }

    /**
     * 检查验证状态
     *
     * @return string 验证状态：'none' | 'pending' | 'used' | 'error'
     */
    public function getCheckStatus(): string
    {
        return $this->getSessionValue($this->sessionKeyCheck, 'none');
    }

    /**
     * 刷新验证码
     */
    public function refresh(): void
    {
        $this->deleteSessionValue($this->sessionKeyR);
        $this->deleteSessionValue($this->sessionKeyErr);
        $this->deleteSessionValue($this->sessionKeyCheck);
        $this->clearToken();
    }

    /**
     * 初始化图片资源
     *
     * @param array $bgImages 自定义背景图片路径
     *
     * @throws RuntimeException 当背景图片加载失败时抛出
     */
    private function init(array $bgImages = []): void
    {
        $images = !empty($bgImages) ? $bgImages : $this->defaultBgImages;

        if (empty($images)) {
            throw new RuntimeException('没有可用的背景图片，请配置背景图片');
        }

        $bgFile = $images[array_rand($images)];

        if (!file_exists($bgFile) || !is_readable($bgFile)) {
            throw new RuntimeException('背景图片不存在或无法读取: ' . $bgFile);
        }

        $this->imFullBg = $this->loadImage($bgFile);
        if ($this->imFullBg === null) {
            throw new RuntimeException('加载背景图片失败: ' . $bgFile);
        }

        $this->imBg = imagecreatetruecolor($this->bgWidth, $this->bgHeight);
        if ($this->imBg === false) {
            throw new RuntimeException('创建背景画布失败');
        }

        imagecopy($this->imBg, $this->imFullBg, 0, 0, 0, 0, $this->bgWidth, $this->bgHeight);

        $this->imSlide = imagecreatetruecolor($this->markWidth, $this->bgHeight);
        if ($this->imSlide === false) {
            throw new RuntimeException('创建滑块画布失败');
        }

        $minX = $this->markWidth;
        $maxX = $this->bgWidth - $this->markWidth - 1;
        $maxY = $this->bgHeight - $this->markHeight - 1;

        $this->posX = mt_rand($minX, $maxX);
        $this->posY = mt_rand(0, max(0, $maxY));

        $this->setSessionValue($this->sessionKeyR, $this->posX);
        $this->setSessionValue($this->sessionKeyErr, 0);
    }

    /**
     * 加载图片
     *
     * @param string $file 图片文件路径
     *
     * @return GdImage|null 图片资源或 null
     */
    private function loadImage(string $file): ?GdImage
    {
        if (!file_exists($file)) {
            return null;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        try {
            return match ($ext) {
                'png' => imagecreatefrompng($file),
                'jpg', 'jpeg' => imagecreatefromjpeg($file),
                'gif' => imagecreatefromgif($file),
                'webp' => function_exists('imagecreatefromwebp')
                    ? imagecreatefromwebp($file)
                    : null,
                default => null,
            };
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 创建滑块图片
     *
     * @throws RuntimeException 当滑块图片加载失败时抛出
     */
    private function createSlide(): void
    {
        $markFile = $this->config['slide_dark_img'];

        if (!file_exists($markFile)) {
            throw new RuntimeException('滑块图片不存在: ' . $markFile);
        }

        $imgMark = imagecreatefrompng($markFile);
        if ($imgMark === false) {
            throw new RuntimeException('加载滑块图片失败: ' . $markFile);
        }

        imagecopy(
            $this->imSlide,
            $this->imFullBg,
            0,
            $this->posY,
            $this->posX,
            $this->posY,
            $this->markWidth,
            $this->markHeight
        );

        imagecopy($this->imSlide, $imgMark, 0, $this->posY, 0, 0, $this->markWidth, $this->markHeight);

        imagecolortransparent($this->imSlide, 0);

        imagedestroy($imgMark);
    }

    /**
     * 创建背景缺口
     *
     * @throws RuntimeException 当透明滑块图片加载失败时抛出
     */
    private function createBg(): void
    {
        $markFile = $this->config['slide_transparent_img'];

        if (!file_exists($markFile)) {
            throw new RuntimeException('透明滑块图片不存在: ' . $markFile);
        }

        $im = imagecreatefrompng($markFile);
        if ($im === false) {
            throw new RuntimeException('加载透明滑块图片失败: ' . $markFile);
        }

        imagecolortransparent($im, 0);

        imagecopy($this->imBg, $im, $this->posX, $this->posY, 0, 0, $this->markWidth, $this->markHeight);

        imagedestroy($im);
    }

    /**
     * 合并所有图层
     */
    private function merge(): void
    {
        $this->im = imagecreatetruecolor($this->bgWidth, $this->bgHeight * 3);
        if ($this->im === false) {
            throw new RuntimeException('创建合成画布失败');
        }

        imagecopy($this->im, $this->imBg, 0, 0, 0, 0, $this->bgWidth, $this->bgHeight);

        imagecopy(
            $this->im,
            $this->imSlide,
            0,
            $this->bgHeight,
            0,
            0,
            $this->markWidth,
            $this->bgHeight
        );

        imagecopy($this->im, $this->imFullBg, 0, $this->bgHeight * 2, 0, 0, $this->bgWidth, $this->bgHeight);

        imagecolortransparent($this->im, 0);
    }

    /**
     * 输出图片到浏览器
     */
    private function output(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $format = $this->getOutputFormat();
        $quality = $format === 'webp'
            ? (int) ($this->config['webp_quality'] ?? 40)
            : (int) ($this->config['png_quality'] ?? 7);

        if (!headers_sent()) {
            header('Content-Type: image/' . $format);
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $func = 'image' . $format;
        $func($this->im, null, $quality);
    }

    /**
     * 销毁图片资源
     */
    private function destroy(): void
    {
        if ($this->im !== null) {
            imagedestroy($this->im);
            $this->im = null;
        }
        if ($this->imFullBg !== null) {
            imagedestroy($this->imFullBg);
            $this->imFullBg = null;
        }
        if ($this->imBg !== null) {
            imagedestroy($this->imBg);
            $this->imBg = null;
        }
        if ($this->imSlide !== null) {
            imagedestroy($this->imSlide);
            $this->imSlide = null;
        }
    }

    /**
     * 获取配置项
     *
     * @param string $key     配置键名
     * @param mixed  $default 默认值
     *
     * @return mixed 配置值
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 设置配置项
     *
     * @param string $key   配置键名
     * @param mixed  $value 配置值
     *
     * @return self 支持链式调用
     */
    public function setConfig(string $key, mixed $value): self
    {
        $this->config[$key] = $value;
        $this->applyConfig();
        return $this;
    }

    /**
     * 批量设置配置
     *
     * @param array $config 配置数组
     *
     * @return self 支持链式调用
     */
    public function setConfigs(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        $this->applyConfig();
        return $this;
    }

    /**
     * 析构函数 - 确保资源被释放
     */
    public function __destruct()
    {
        $this->destroy();
    }
}
