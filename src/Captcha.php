<?php

/**
 * zxf/captcha - 高性能滑动验证码 & 点击验证码 PHP 扩展包
 *
 * @package     zxf\Captcha
 * @license     MIT
 * @version     1.0.6
 */

declare(strict_types=1);

namespace zxf\Captcha;

use GdImage;
use RuntimeException;

/**
 * 滑动验证码 & 点击验证码核心类
 *
 * 该类负责生成滑动验证码和点击验证码图片、验证用户操作结果等核心功能
 * 支持自定义背景图片、滑块样式、容错精度等配置
 *
 * @since  1.0.0
 * @since  1.0.6 新增点击验证码支持
 */
class Captcha
{
    /**
     * 验证码类型：滑动验证码
     */
    public const TYPE_SLIDE = 'slide';

    /**
     * 验证码类型：点击验证码
     */
    public const TYPE_CLICK = 'click';

    /**
     * 验证码类型：两者都使用（随机选择）
     */
    public const TYPE_BOTH = 'both';

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

    /** 默认图片输出格式 */
    private const DEFAULT_OUTPUT_FORMAT = 'webp';

    /** 默认 WebP 质量 */
    private const DEFAULT_WEBP_QUALITY = 40;

    /** 默认 PNG 压缩级别 */
    private const DEFAULT_PNG_QUALITY = 7;

    /** 验证码默认过期时间（秒） */
    private const DEFAULT_CAPTCHA_EXPIRE = 600;

    /** 最小滑块宽度 */
    /** 默认最小滑块宽度 */
    private const DEFAULT_MIN_MARK_WIDTH = 30;

    /** 默认最大滑块宽度 */
    private const DEFAULT_MAX_MARK_WIDTH = 80;

    /** 默认最小滑块高度 */
    private const DEFAULT_MIN_MARK_HEIGHT = 30;

    /** 默认最大滑块高度 */
    private const DEFAULT_MAX_MARK_HEIGHT = 80;

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
     * 滑动容错像素值
     */
    private int $faultTolerance = 3;

    /**
     * 点击容错像素值
     */
    private int $clickFaultTolerance = 25;

    /**
     * 最大错误次数
     */
    private int $maxErrorCount = 10;

    /**
     * 当前验证码类型
     */
    private string $captchaType = self::TYPE_SLIDE;

    /**
     * 点击验证码数据
     */
    private array $clickData = [];

    /**
     * Token过期时间（秒）
     */
    private int $tokenExpire = 300;

    /**
     * 配置数组
     */
    private array $config = [];

    /**
     * 请求指纹（用于安全追踪）
     */
    private string $requestFingerprint = '';

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
     * Session 键名 - 存储验证码类型
     */
    private string $sessionKeyType = 'captcha_type';

    /**
     * Session 键名 - 存储点击验证码数据
     */
    private string $sessionKeyClickData = 'captcha_click_data';

    /**
     * Session 键名 - 存储请求指纹
     */
    private string $sessionKeyFingerprint = 'captcha_fingerprint';

    /**
     * Session 键名 - 存储生成时间戳
     */
    private string $sessionKeyCreatedAt = 'captcha_created_at';

    /**
     * Session 键名 - 存储速率限制计数
     */
    private string $sessionKeyRateLimit = 'captcha_rate_limit';

    /**
     * Session 键名 - 存储速率限制时间窗口
     */
    private string $sessionKeyRateLimitTime = 'captcha_rate_limit_time';

    /**
     * Session 键名 - 存储滑动轨迹数据
     */
    private string $sessionKeySlideTrack = 'captcha_slide_track';

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
            // 验证码类型：slide/click/both
            'captcha_type' => self::TYPE_BOTH,

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
            'output_format' => self::DEFAULT_OUTPUT_FORMAT,

            // WebP 图片质量
            'webp_quality' => self::DEFAULT_WEBP_QUALITY,

            // PNG 图片压缩级别
            'png_quality' => self::DEFAULT_PNG_QUALITY,

            // Session 前缀
            'session_prefix' => 'xf_captcha',

            // 验证模式
            'verify_mode' => self::VERIFY_DUAL,

            // Token过期时间（秒）
            'token_expire' => 300,

            // 验证码过期时间（秒）
            'captcha_expire' => self::DEFAULT_CAPTCHA_EXPIRE,

            // 点击验证码配置
            'click' => [
                // 点击验证的文字数量
                'char_count' => 4,
                // 点击容错范围（像素）
                'fault_tolerance' => 25,
                // 字符库（留空则自动判断：优先中文+符号混合）
                'chars' => [],
                // 中文字体路径（支持中文点击验证推荐配置）
                'font_path' => '',
                // 文字大小（推荐 24-32，确保清晰可见）
                'font_size' => 26,
                // 文字颜色 [R, G, B]（留空则随机）
                'font_color' => [],
                // 是否添加文字阴影/描边增强可读性
                'text_stroke' => true,
                // 是否添加文字背景半透明遮罩增强可读性
                'text_bg_overlay' => true,
                // 提示文字模板
                'hint_text' => '请依次点击：%s',
                // 是否启用文字旋转（增强安全性）
                'text_rotate' => true,
                // 最大旋转角度（度数）
                'max_rotate' => 30,
            ],

            // 滑动验证码配置
            'slide' => [
                // 滑块宽度
                'mark_width' => 50,
                // 滑块高度
                'mark_height' => 50,
                // 滑动容错像素值
                'fault_tolerance' => 3,
                // 是否启用轨迹验证（增强安全性，检测机器人）
                'track_verify' => true,
                // 轨迹验证严格程度：'strict' | 'normal' | 'loose'
                'track_strictness' => 'normal',
            ],
        ];
    }

    /**
     * 应用配置到类属性
     */
    private function applyConfig(): void
    {
        $this->bgWidth = $this->filterInt($this->config['bg_width'] ?? 240, 100, 800);
        $this->bgHeight = $this->filterInt($this->config['bg_height'] ?? 150, 50, 600);
        $this->maxErrorCount = $this->filterInt($this->config['max_error_count'] ?? 10, 1, 100);
        $this->tokenExpire = $this->filterInt($this->config['token_expire'] ?? 300, 30, 3600);

        // 滑动验证码配置 - 优先从 slide 数组读取，兼容顶层配置
        $slideConfig = $this->config['slide'] ?? [];
        $this->markWidth = $this->filterInt(
            $slideConfig['mark_width'] ?? $this->config['mark_width'] ?? 50,
            self::DEFAULT_MIN_MARK_WIDTH,
            self::DEFAULT_MAX_MARK_WIDTH
        );
        $this->markHeight = $this->filterInt(
            $slideConfig['mark_height'] ?? $this->config['mark_height'] ?? 50,
            self::DEFAULT_MIN_MARK_HEIGHT,
            self::DEFAULT_MAX_MARK_HEIGHT
        );
        $this->faultTolerance = $this->filterInt($slideConfig['fault_tolerance'] ?? $this->config['fault_tolerance'] ?? 3, 0, 20);

        // 点击验证码容错值
        $clickConfig = $this->config['click'] ?? [];
        $this->clickFaultTolerance = $this->filterInt($clickConfig['fault_tolerance'] ?? 25, 5, 100);

        // 设置 Session 键名
        $prefix = $this->config['session_prefix'] ?? 'xf_captcha';
        $this->sessionKeyR = $prefix . '_r';
        $this->sessionKeyErr = $prefix . '_err';
        $this->sessionKeyCheck = $prefix . '_check';
        $this->sessionKeyToken = $prefix . '_token';
        $this->sessionKeyTokenExpire = $prefix . '_token_expire';
        $this->sessionKeyType = $prefix . '_type';
        $this->sessionKeyClickData = $prefix . '_click_data';
        $this->sessionKeyFingerprint = $prefix . '_fingerprint';
        $this->sessionKeyCreatedAt = $prefix . '_created_at';
        $this->sessionKeyRateLimit = $prefix . '_rate_limit';
        $this->sessionKeyRateLimitTime = $prefix . '_rate_limit_time';
        $this->sessionKeySlideTrack = $prefix . '_slide_track';

        // 生成请求指纹用于安全校验
        $this->requestFingerprint = $this->generateFingerprint();

        // 修复图片路径
        $this->fixImagePaths();

        // 设置默认背景图片
        $this->defaultBgImages = $this->getBgImages();
    }

    /**
     * 生成请求指纹
     *
     * 使用稳定的请求特征生成唯一指纹，用于防止会话劫持和验证请求来源一致性
     */
    private function generateFingerprint(): string
    {
        $features = [
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '0.0.0.0'),
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
        ];

        return hash('sha256', implode('|', $features));
    }

    /**
     * 过滤整数值到指定范围
     *
     * @param mixed $value 输入值
     * @param int   $min   最小值
     * @param int   $max   最大值
     *
     * @return int 过滤后的值
     */
    private function filterInt(mixed $value, int $min, int $max): int
    {
        $int = (int) $value;
        return max($min, min($max, $int));
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
     * 获取当前验证码类型
     *
     * @param bool $forceSwitch 是否强制切换类型（用于刷新时）
     *
     * @return string 验证码类型 (slide/click)
     */
    public function getCaptchaType(bool $forceSwitch = false): string
    {
        $configType = $this->config['captcha_type'] ?? self::TYPE_BOTH;

        if ($configType === self::TYPE_BOTH) {
            // 获取当前 session 中的类型
            $currentType = $this->getSessionValue($this->sessionKeyType);

            // 如果强制切换且 session 中有类型，则切换为另一种
            if ($forceSwitch && $currentType !== null) {
                return $currentType === self::TYPE_SLIDE ? self::TYPE_CLICK : self::TYPE_SLIDE;
            }

            // 如果有当前类型，保持类型一致性
            if ($currentType !== null) {
                return in_array($currentType, [self::TYPE_SLIDE, self::TYPE_CLICK], true)
                    ? $currentType
                    : self::TYPE_SLIDE;
            }

            // 首次随机选择，使用 random_int 替代 mt_rand 增强安全性
            try {
                return random_int(0, 1) === 0 ? self::TYPE_SLIDE : self::TYPE_CLICK;
            } catch (\Exception) {
                return self::TYPE_SLIDE;
            }
        }

        return in_array($configType, [self::TYPE_SLIDE, self::TYPE_CLICK], true)
            ? $configType
            : self::TYPE_SLIDE;
    }

    /**
     * 获取背景图片列表
     *
     * @return array 背景图片路径数组
     */
    private function getBgImages(): array
    {
        if (!empty($this->config['bg_images'])) {
            $images = [];
            foreach ($this->config['bg_images'] as $img) {
                if (is_string($img) && file_exists($img)) {
                    $images[] = $img;
                }
            }
            return $images;
        }

        $bgDir = $this->config['bg_images_dir'] ?? '';
        if (empty($bgDir) || !is_dir($bgDir)) {
            return [];
        }

        $images = [];
        $extensions = ['jpg', 'png', 'jpeg', 'gif', 'webp'];

        $files = scandir($bgDir);
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions, true)) {
                $fullPath = rtrim($bgDir, '/\\') . DIRECTORY_SEPARATOR . $file;
                if (file_exists($fullPath)) {
                    $images[] = $fullPath;
                }
            }
        }

        return $images;
    }

    /**
     * 确保 Session 已启动
     */
    private function ensureSessionStarted(): void
    {
        $status = session_status();

        if ($status === PHP_SESSION_ACTIVE) {
            return;
        }

        if ($status === PHP_SESSION_NONE) {
            if (PHP_SAPI === 'cli') {
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
     * 生成验证码数据（统一接口）
     *
     * @param array $bgImages 自定义背景图片路径数组
     * @param bool $refresh 是否为刷新操作
     *
     * @return array 验证码数据
     */
    public function makeData(array $bgImages = [], bool $refresh = false): array
    {
        // 确定验证码类型（刷新时强制切换）
        $this->captchaType = $this->getCaptchaType($refresh);
        $this->setSessionValue($this->sessionKeyType, $this->captchaType);

        return match ($this->captchaType) {
            self::TYPE_CLICK => $this->makeClickData($bgImages),
            default => $this->makeSlideData($bgImages),
        };
    }

    /**
     * 生成滑动验证码数据
     *
     * @param array $bgImages 自定义背景图片路径数组
     *
     * @return array 滑动验证码数据
     */
    private function makeSlideData(array $bgImages = []): array
    {
        try {
            $this->init($bgImages);
            $this->createSlide();
            $this->createBg();
            $this->merge();

            $imageData = $this->outputImageToBuffer($this->im);
            $this->destroy();

            return [
                'type' => self::TYPE_SLIDE,
                'image' => $imageData,
                'image_base64' => 'data:image/' . $this->getOutputFormat() . ';base64,' . base64_encode($imageData),
                'bg_width' => $this->bgWidth,
                'bg_height' => $this->bgHeight,
                'mark_width' => $this->markWidth,
                'mark_height' => $this->markHeight,
                'char_count' => 0,
                'hint' => '拖动左边滑块完成上方拼图',
            ];
        } catch (\Throwable $e) {
            $this->destroy();
            throw $e;
        }
    }

    /**
     * 生成点击验证码数据
     *
     * @param array $bgImages 自定义背景图片路径数组
     *
     * @return array 点击验证码数据
     */
    private function makeClickData(array $bgImages = []): array
    {
        try {
            $this->initClick($bgImages);
            $imageData = $this->createClickImage();

            // 存储点击位置数据到 Session
            $this->setSessionValue($this->sessionKeyClickData, $this->clickData);
            $this->setSessionValue($this->sessionKeyErr, 0);
            $this->setSessionValue($this->sessionKeyFingerprint, $this->requestFingerprint);
            $this->setSessionValue($this->sessionKeyCreatedAt, time());

            // 生成提示文字
            $clickConfig = $this->config['click'] ?? [];
            $hintTemplate = $clickConfig['hint_text'] ?? '请依次点击：%s';
            $chars = array_column($this->clickData, 'char');
            $hint = sprintf($hintTemplate, implode(' → ', $chars));

            return [
                'type' => self::TYPE_CLICK,
                'image' => $imageData,
                'image_base64' => 'data:image/' . $this->getOutputFormat() . ';base64,' . base64_encode($imageData),
                'bg_width' => $this->bgWidth,
                'bg_height' => $this->bgHeight,
                'hint' => $hint,
                'char_count' => count($this->clickData),
            ];
        } finally {
            $this->destroy();
        }
    }

    /**
     * 初始化点击验证码
     *
     * @param array $bgImages 自定义背景图片路径
     */
    private function initClick(array $bgImages = []): void
    {
        $images = !empty($bgImages) ? $bgImages : $this->defaultBgImages;

        if (empty($images)) {
            throw new RuntimeException('没有可用的背景图片，请配置背景图片');
        }

        $bgFile = $images[$this->secureRandomIndex($images)];

        if (!file_exists($bgFile) || !is_readable($bgFile)) {
            throw new RuntimeException('背景图片不存在或无法读取: ' . $bgFile);
        }

        $this->imFullBg = $this->loadImage($bgFile);
        if ($this->imFullBg === null) {
            throw new RuntimeException('加载背景图片失败: ' . $bgFile);
        }

        // 调整背景图尺寸
        $this->imBg = imagecreatetruecolor($this->bgWidth, $this->bgHeight);
        if ($this->imBg === false) {
            throw new RuntimeException('创建背景画布失败');
        }

        imagecopyresampled(
            $this->imBg,
            $this->imFullBg,
            0, 0, 0, 0,
            $this->bgWidth,
            $this->bgHeight,
            imagesx($this->imFullBg),
            imagesy($this->imFullBg)
        );

        // 启用 alpha 混合，确保后续半透明文字背景遮罩能正确与背景图融合
        imagealphablending($this->imBg, true);

        // 生成点击位置数据
        $this->generateClickData();
    }

    /**
     * 安全的随机索引选择
     *
     * @param array $array 输入数组
     *
     * @return int 随机索引
     */
    private function secureRandomIndex(array $array): int
    {
        $count = count($array);
        if ($count <= 1) {
            return 0;
        }

        try {
            return random_int(0, $count - 1);
        } catch (\Exception) {
            return mt_rand(0, $count - 1);
        }
    }

    /**
     * 生成点击验证码的随机位置数据
     * 使用改进的分布算法，确保字符分布均匀且不重叠
     */
    private function generateClickData(): void
    {
        $clickConfig = $this->config['click'] ?? [];
        $charCount = $this->filterInt($clickConfig['char_count'] ?? 4, 1, 8);

        // 获取字符库（只取需要的数量）
        $chars = $this->getClickChars($charCount);

        $this->clickData = [];
        $padding = max(35, (int) ($this->bgWidth * 0.12)); // 动态边缘留白
        $minDistance = max(40, (int) ($this->bgWidth * 0.15)); // 动态最小距离
        $maxAttempts = 200; // 最大尝试次数

        // 自适应网格大小
        $gridCols = min($charCount, max(2, (int) ceil(sqrt($charCount * 1.5))));
        $gridRows = max(2, (int) ceil($charCount / $gridCols));
        $cellWidth = ($this->bgWidth - $padding * 2) / $gridCols;
        $cellHeight = ($this->bgHeight - $padding * 2) / $gridRows;

        $totalCells = $gridCols * $gridRows;
        $availableCells = range(0, $totalCells - 1);
        $usedCells = [];

        for ($i = 0; $i < $charCount; $i++) {
            $attempts = 0;
            $placed = false;

            while (!$placed && $attempts < $maxAttempts) {
                // 优先在未使用的网格中放置
                if ($i < $totalCells) {
                    $remainingCells = array_diff($availableCells, $usedCells);
                    if (!empty($remainingCells)) {
                        $remainingValues = array_values($remainingCells);
                        $cellIndex = $remainingValues[$this->secureRandomIndex($remainingValues)];
                        $usedCells[] = $cellIndex;
                    } else {
                        $cellIndex = $this->secureRandomInt(0, $totalCells - 1);
                    }
                } else {
                    $cellIndex = $this->secureRandomInt(0, $totalCells - 1);
                }

                $gridX = $cellIndex % $gridCols;
                $gridY = (int) ($cellIndex / $gridCols);

                // 在网格内随机位置，留有一定边距
                $margin = 20;
                $baseX = $padding + $gridX * $cellWidth;
                $baseY = $padding + $gridY * $cellHeight;

                $maxRandX = max(0, (int) ($baseX + $cellWidth - $margin) - (int) ($baseX + $margin));
                $maxRandY = max(0, (int) ($baseY + $cellHeight - $margin) - (int) ($baseY + $margin));

                $x = (int) ($baseX + $margin + $this->secureRandomInt(0, max(1, $maxRandX)));
                $y = (int) ($baseY + $margin + $this->secureRandomInt(0, max(1, $maxRandY)));

                // 边界检查
                $x = max($padding, min($this->bgWidth - $padding, $x));
                $y = max($padding, min($this->bgHeight - $padding, $y));

                // 检查与其他字符的距离
                $tooClose = false;
                foreach ($this->clickData as $existing) {
                    $distance = hypot($x - $existing['x'], $y - $existing['y']);
                    if ($distance < $minDistance) {
                        $tooClose = true;
                        break;
                    }
                }

                if (!$tooClose) {
                    $this->clickData[] = [
                        'char' => $chars[$i] ?? $chars[$this->secureRandomIndex($chars)],
                        'x' => $x,
                        'y' => $y,
                        'order' => $i + 1,
                    ];
                    $placed = true;
                }

                $attempts++;
            }

            // 如果无法放置，使用随机位置
            if (!$placed) {
                $this->clickData[] = [
                    'char' => $chars[$i] ?? $chars[$this->secureRandomIndex($chars)],
                    'x' => $this->secureRandomInt($padding, max($padding + 1, $this->bgWidth - $padding)),
                    'y' => $this->secureRandomInt($padding, max($padding + 1, $this->bgHeight - $padding)),
                    'order' => $i + 1,
                ];
            }
        }
    }

    /**
     * 安全的随机整数生成
     *
     * @param int $min 最小值
     * @param int $max 最大值
     *
     * @return int 随机整数
     */
    private function secureRandomInt(int $min, int $max): int
    {
        if ($min >= $max) {
            return $min;
        }

        try {
            return random_int($min, $max);
        } catch (\Exception) {
            // 极端降级：random_int 不可用时退化为 mt_rand（弱随机，仅兜底）
            return mt_rand($min, $max);
        }
    }

    /**
     * 获取点击验证码的字符库
     *
     * @return array 字符数组
     */
    /**
     * 获取点击验证码的字符库
     *
     * @param int $count 需要返回的字符数量，0 表示返回全部
     *
     * @return array 字符数组
     */
    private function getClickChars(int $count = 0): array
    {
        $clickConfig = $this->config['click'] ?? [];
        $customChars = $clickConfig['chars'] ?? [];

        if (!empty($customChars) && is_array($customChars)) {
            $chars = array_values(array_unique(array_filter($customChars, 'is_string')));
            if ($count > 0) {
                // 如果自定义字符不够，循环使用直到满足数量
                while (count($chars) < $count) {
                    $chars = array_merge($chars, $chars);
                }
                return array_slice($chars, 0, $count);
            }
            return $chars;
        }

        // 默认使用中文汉字库，去重
        $mixedChars = [
            '的', '是', '了', '我', '不', '人', '在', '他', '有', '这',
            '个', '上', '们', '来', '到', '说', '去', '你', '会', '也',
            '对', '生', '可', '以', '那', '大', '子', '得', '就', '下',
            '地', '天', '时', '要', '出', '小', '还', '自', '己', '好',
            '过', '家', '和', '她', '起', '把', '年', '样', '能', '没',
            '多', '么', '后', '只', '想', '看', '真', '太', '点', '女',
            '孩', '儿', '做', '都', '听', '笑', '回', '走', '里', '两',
            '道', '进', '很', '老', '月', '问', '让', '给', '手', '头',
            '面', '比', '关', '外', '高', '长', '见', '立', '中', '心',
            '公', '开', '水', '名', '叫', '当', '男', '用', '分', '合',
            '该', '话', '动', '新', '之', '如', '从', '等', '现', '制',
            '度', '表', '重', '应', '间', '事', '或', '别', '期', '活',
            '各', '少', '经', '体', '意', '主', '结', '果', '利', '实',
            '其', '相', '义', '第', '此', '明', '加', '定', '常', '量',
            '直', '总', '部', '种', '被', '任', '再', '便', '林', '气',
            '请', '教', '妈', '爸', '爷', '奶', '师', '学', '校', '书',
            '读', '写', '画', '玩', '跑', '跳', '吃', '喝', '睡', '衣',
            '服', '车', '路', '花', '草', '山', '石', '田', '云', '风',
            '雨', '雪', '电', '光', '火', '木', '米', '饭', '菜', '肉',
            '鱼', '爱', '哭', '喊', '推', '拉', '抱', '亲', '帮', '忙',
            '借', '还', '买', '卖', '坐', '站', '躺', '洗', '刷', '扫',
            '擦', '切', '炒', '煮', '蒸', '烤', '甜', '酸', '苦', '辣',
            '咸', '胖', '瘦', '矮', '短', '粗', '细', '快', '慢', '早',
            '晚', '春', '夏', '秋', '冬', '星', '温', '暖', '凉', '冷',
            '热', '干', '湿', '旧', '正', '反', '左', '右', '前', '后',
            '里', '东', '西', '南', '北', '旁', '边', '处', '方', '向',
            '颜', '色', '红', '黄', '蓝', '白', '紫', '黑', '青', '金',
        ];

        // 确保去重
        $mixedChars = array_values(array_unique($mixedChars));

        $total = count($mixedChars);
        $need = $count > 0 ? min($count, $total) : $total;

        // Fisher-Yates 部分洗牌：只洗牌需要的数量，减少计算开销
        for ($i = $total - 1, $n = $need; $i > 0 && $n > 0; $i--, $n--) {
            $j = $this->secureRandomInt(0, $i);
            [$mixedChars[$i], $mixedChars[$j]] = [$mixedChars[$j], $mixedChars[$i]];
        }

        return array_slice($mixedChars, -$need);
    }

    /**
     * 创建点击验证码图片
     *
     * @return string 图片二进制数据
     */
    private function createClickImage(): string
    {
        $clickConfig = $this->config['click'] ?? [];
        $fontSize = (int) ($clickConfig['font_size'] ?? 26);
        $textStroke = (bool) ($clickConfig['text_stroke'] ?? true);
        $textBgOverlay = (bool) ($clickConfig['text_bg_overlay'] ?? true);
        $fontPath = $clickConfig['font_path'] ?? '';
        $fontColor = $clickConfig['font_color'] ?? [];
        $textRotate = (bool) ($clickConfig['text_rotate'] ?? true);
        $maxRotate = (int) ($clickConfig['max_rotate'] ?? 30);

        // 优先使用系统默认中文字体路径（常见路径）
        if (empty($fontPath) || !file_exists($fontPath)) {
            $fontPath = $this->findSystemFont();
        }

        $hasTtf = !empty($fontPath) && file_exists($fontPath);

        // 绘制每个字符
        foreach ($this->clickData as $data) {
            $x = $data['x'];
            $y = $data['y'];
            $char = $data['char'];
            $rotateAngle = 0;

            // 随机旋转角度（仅 TTF 字体支持）
            if ($hasTtf && $textRotate) {
                $rotateAngle = $this->secureRandomInt(-$maxRotate, $maxRotate);
            }

            // 随机颜色（避免太浅或与背景相近的颜色）
            if (!empty($fontColor) && count($fontColor) >= 3) {
                $color = imagecolorallocate($this->imBg, $fontColor[0], $fontColor[1], $fontColor[2]);
            } else {
                $r = $this->secureRandomInt(30, 210);
                $g = $this->secureRandomInt(30, 210);
                $b = $this->secureRandomInt(30, 210);
                // 确保颜色有足够对比度（避免接近灰色）
                if (abs($r - $g) < 20 && abs($g - $b) < 20) {
                    $r = $this->secureRandomInt(0, 100);
                    $b = $this->secureRandomInt(150, 255);
                }
                $color = imagecolorallocate($this->imBg, $r, $g, $b);
            }

            if ($hasTtf) {
                // 统一的文字绘制起点
                $drawX = (int) ($x - $fontSize * 0.4);
                $drawY = (int) ($y + $fontSize * 0.35);

                // 计算文字边界框，绘制半透明背景遮罩（底层）增强可读性
                if ($textBgOverlay) {
                    $bbox = imagettfbbox($fontSize, $rotateAngle, $fontPath, $char);
                    if ($bbox !== false) {
                        $minX = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
                        $maxX = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
                        $minY = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
                        $maxY = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
                        $cx = (int) (($minX + $maxX) / 2 + $drawX);
                        $cy = (int) (($minY + $maxY) / 2 + $drawY);
                        $textW = $maxX - $minX;
                        $textH = $maxY - $minY;
                        // 半透明白色椭圆，覆盖文字并留出余量
                        $overlayColor = imagecolorallocatealpha($this->imBg, 255, 255, 255, 95);
                        imagefilledellipse(
                            $this->imBg,
                            $cx,
                            $cy,
                            (int) ($textW * 1.4 + 14),
                            (int) ($textH * 1.4 + 14),
                            $overlayColor
                        );
                    }
                }

                // 添加白色描边/阴影效果增强可读性
                if ($textStroke) {
                    $strokeColor = imagecolorallocate($this->imBg, 255, 255, 255);
                    for ($dx = -2; $dx <= 2; $dx++) {
                        for ($dy = -2; $dy <= 2; $dy++) {
                            if ($dx === 0 && $dy === 0) continue;
                            imagettftext(
                                $this->imBg,
                                $fontSize,
                                $rotateAngle,
                                $drawX + $dx,
                                $drawY + $dy,
                                $strokeColor,
                                $fontPath,
                                $char
                            );
                        }
                    }
                }

                imagettftext(
                    $this->imBg,
                    $fontSize,
                    $rotateAngle,
                    $drawX,
                    $drawY,
                    $color,
                    $fontPath,
                    $char
                );
            } else {
                // 使用内置字体时绘制背景遮罩
                if ($textBgOverlay) {
                    $overlayColor = imagecolorallocatealpha($this->imBg, 255, 255, 255, 95);
                    imagefilledellipse($this->imBg, $x + 4, $y - 2, 28, 28, $overlayColor);
                }

                // 使用内置字体（尽量选大的）
                $fontId = max(1, min(5, (int) ($fontSize / 3)));
                // 简单描边效果
                if ($textStroke) {
                    $strokeColor = imagecolorallocate($this->imBg, 255, 255, 255);
                    for ($dx = -1; $dx <= 1; $dx++) {
                        for ($dy = -1; $dy <= 1; $dy++) {
                            imagestring($this->imBg, $fontId, $x - 4 + $dx, $y - 8 + $dy, $char, $strokeColor);
                        }
                    }
                }
                imagestring($this->imBg, $fontId, $x - 4, $y - 8, $char, $color);
            }
        }

        // 输出图片
        return $this->outputImageToBuffer($this->imBg);
    }

    /**
     * 查找系统默认字体
     *
     * 按操作系统自动检测常见中文字体路径
     */
    private function findSystemFont(): string
    {
        $possiblePaths = [
            // 包内字体（优先）
            dirname(__FILE__, 2) . '/resources/assets/font/custom.ttf',
        ];

        // 根据操作系统添加常见字体路径
        if (PHP_OS_FAMILY === 'Windows') {
            $possiblePaths[] = 'C:\\Windows\\Fonts\\simhei.ttf';
            $possiblePaths[] = 'C:\\Windows\\Fonts\\simsun.ttc';
            $possiblePaths[] = 'C:\\Windows\\Fonts\\msyh.ttc';
            $possiblePaths[] = 'C:\\Windows\\Fonts\\msyhbd.ttc';
            $possiblePaths[] = 'C:\\Windows\\Fonts\\simkai.ttf';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $possiblePaths[] = '/System/Library/Fonts/PingFang.ttc';
            $possiblePaths[] = '/System/Library/Fonts/STHeiti Light.ttc';
            $possiblePaths[] = '/System/Library/Fonts/Hiragino Sans GB.ttc';
            $possiblePaths[] = '/Library/Fonts/Arial Unicode.ttf';
            $possiblePaths[] = '/System/Library/Fonts/Supplemental/Arial Unicode.ttf';
        } else {
            // Linux 及类 Unix 系统
            $possiblePaths[] = '/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc';
            $possiblePaths[] = '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc';
            $possiblePaths[] = '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc';
            $possiblePaths[] = '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc';
            $possiblePaths[] = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
            $possiblePaths[] = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';
            $possiblePaths[] = '/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf';
        }

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * 将图片资源输出为二进制数据
     *
     * @param GdImage $image  图片资源
     * @param bool    $toBase64 是否返回 base64 编码
     *
     * @return string 图片二进制数据或 base64 字符串
     */
    private function outputImageToBuffer(GdImage $image, bool $toBase64 = false): string
    {
        $format = $this->getOutputFormat();
        $quality = $format === 'webp'
            ? $this->filterInt($this->config['webp_quality'] ?? self::DEFAULT_WEBP_QUALITY, 0, 100)
            : $this->filterInt($this->config['png_quality'] ?? self::DEFAULT_PNG_QUALITY, 0, 9);

        ob_start();
        $func = 'image' . $format;

        if ($format === 'png') {
            // 保留可能存在的透明通道（如缺口/滑块区域）
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        $func($image, null, $quality);

        $data = ob_get_clean();

        if ($data === false) {
            throw new RuntimeException('生成图片数据失败：输出缓冲区获取数据失败');
        }

        if ($toBase64) {
            return 'data:image/' . $format . ';base64,' . base64_encode($data);
        }

        return $data;
    }

    /**
     * 获取图片数据（向后兼容）
     *
     * @return string 图片二进制数据
     */
    private function getImageData(): string
    {
        if ($this->im === null) {
            throw new RuntimeException('图片资源不存在');
        }

        return $this->outputImageToBuffer($this->im);
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

            return $this->outputImageToBuffer($this->im);
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
     * 验证用户操作结果
     *
     * @param string|int|null $offset 用户滑动的偏移量（滑动验证码）
     * @param string|null $token 验证令牌（双重验证模式使用）
     * @param array $clickPoints 用户点击的坐标点（点击验证码）
     *
     * @return array 验证结果 ['success' => bool, 'token' => string|null, 'message' => string]
     */
    /**
     * 验证用户操作结果
     *
     * @param string|int|null $offset      用户滑动的偏移量（滑动验证码）
     * @param string|null     $token       验证令牌（双重验证模式使用）
     * @param array           $clickPoints 用户点击的坐标点（点击验证码）
     * @param array           $slideTrack  滑动轨迹数据 [['x'=>int,'y'=>int,'t'=>int],...]
     *
     * @return array 验证结果 ['success' => bool, 'token' => string|null, 'message' => string]
     */
    public function verify(string|int|null $offset = null, ?string $token = null, array $clickPoints = [], array $slideTrack = []): array
    {
        // 速率限制检查
        if (!$this->checkRateLimit()) {
            return [
                'success' => false,
                'token' => null,
                'message' => '请求过于频繁，请稍后再试',
            ];
        }

        // 仅前端验证模式（不安全，仅测试）：跳过所有安全与位置校验，直接通过
        $verifyMode = $this->config['verify_mode'] ?? self::VERIFY_DUAL;
        if ($verifyMode === self::VERIFY_FRONTEND_ONLY) {
            return $this->verifyFrontendOnly();
        }

        // 安全校验：检查请求指纹是否匹配（防止会话劫持）
        // 仅首次验证（未携带 token）时校验；二次验证以一次性 token 为强凭证，
        // 不再受指纹约束，避免移动网络切换 / 代理导致指纹变化而误杀真实用户。
        $storedFingerprint = $this->getSessionValue($this->sessionKeyFingerprint);
        if ($token === null && $storedFingerprint !== null && $storedFingerprint !== $this->requestFingerprint) {
            $this->refresh();
            return [
                'success' => false,
                'token' => null,
                'message' => '安全校验失败，请刷新重试',
            ];
        }

        // 安全校验：检查验证码是否过期
        $captchaExpire = $this->filterInt($this->config['captcha_expire'] ?? self::DEFAULT_CAPTCHA_EXPIRE, 60, 3600);
        $createdAt = $this->getSessionValue($this->sessionKeyCreatedAt, 0);
        if ($createdAt > 0 && (time() - $createdAt) > $captchaExpire) {
            $this->refresh();
            return [
                'success' => false,
                'token' => null,
                'message' => '验证码已过期，请刷新重试',
            ];
        }

        // 获取当前验证码类型（优先从session，其次从配置）
        $sessionType = $this->getSessionValue($this->sessionKeyType);
        $configType = $this->config['captcha_type'] ?? self::TYPE_BOTH;

        if ($sessionType !== null) {
            $captchaType = $sessionType;
        } elseif ($configType !== self::TYPE_BOTH) {
            $captchaType = $configType;
        } else {
            $captchaType = self::TYPE_SLIDE;
        }

        // 点击验证码验证：如果传了点击坐标，或类型是点击验证码
        if (!empty($clickPoints) || $captchaType === self::TYPE_CLICK) {
            return $this->verifyClick($clickPoints, $token);
        }

        // 滑动验证码验证
        $verifyMode = $this->config['verify_mode'] ?? self::VERIFY_DUAL;

        return match ($verifyMode) {
            self::VERIFY_FRONTEND_ONLY => $this->verifyFrontendOnly(),
            self::VERIFY_BACKEND_ONLY => $this->verifyBackendOnly($offset, $slideTrack),
            self::VERIFY_DUAL => $this->verifyDual($offset, $token, $slideTrack),
            default => $this->verifyDual($offset, $token, $slideTrack),
        };
    }

    /**
     * 验证点击验证码
     *
     * @param array $clickPoints 用户点击的坐标点 [['x' => int, 'y' => int], ...]
     * @param string|null $token 验证令牌
     *
     * @return array 验证结果
     */
    private function verifyClick(array $clickPoints, ?string $token): array
    {
        // 如果有token，进行二次验证
        if ($token !== null && $token !== '') {
            return $this->verifySecondary($token);
        }

        // 获取存储的点击数据
        $storedData = $this->getSessionValue($this->sessionKeyClickData);
        if (empty($storedData)) {
            return [
                'success' => false,
                'token' => null,
                'message' => '验证码已过期，请刷新重试',
            ];
        }

        // 检查点击次数是否足够
        if (count($clickPoints) !== count($storedData)) {
            $this->handleFailedCheck();
            return [
                'success' => false,
                'token' => null,
                'message' => '请点击所有指定字符',
            ];
        }

        // 验证每个点击位置
        foreach ($storedData as $index => $expected) {
            if (!isset($clickPoints[$index])) {
                $this->handleFailedCheck();
                return [
                    'success' => false,
                    'token' => null,
                    'message' => '请点击第' . ($index + 1) . '个字符',
                ];
            }

            $actual = $clickPoints[$index];
            $distance = hypot(
                (float) ($actual['x'] - $expected['x']),
                (float) ($actual['y'] - $expected['y'])
            );

            if ($distance > $this->clickFaultTolerance) {
                $this->handleFailedCheck();
                return [
                    'success' => false,
                    'token' => null,
                    'message' => '点击位置不正确，请重试',
                ];
            }
        }

        // 验证通过，根据模式处理
        $verifyMode = $this->config['verify_mode'] ?? self::VERIFY_DUAL;

        if ($verifyMode === self::VERIFY_BACKEND_ONLY) {
            $this->handleSuccessfulCheck();
            return [
                'success' => true,
                'token' => null,
                'message' => '验证成功',
            ];
        }

        // 双重验证模式：生成一次性令牌
        $newToken = $this->generateToken();
        $this->setSessionValue($this->sessionKeyToken, $newToken);
        $this->setSessionValue($this->sessionKeyTokenExpire, time() + $this->tokenExpire);
        $this->setSessionValue($this->sessionKeyCheck, 'pending');
        // 首次成功后立即作废点击答案，防止重复利用同一张图刷 token
        $this->deleteSessionValue($this->sessionKeyClickData);

        return [
            'success' => true,
            'token' => $newToken,
            'message' => '验证成功，请完成后续操作',
        ];
    }

    /**
     * 校验滑动偏移量与轨迹（滑动验证公共逻辑）
     *
     * 抽离自 verifyBackendOnly / verifyPrimary，避免重复代码与行为不一致。
     * 返回 null 表示校验通过；返回数组表示校验失败结果。
     *
     * @param string|int|null $offset     用户滑动偏移量
     * @param array           $slideTrack 滑动轨迹
     *
     * @return array|null
     */
    private function validateSlideOffset(string|int|null $offset, array $slideTrack): ?array
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
        if ($diff > $this->faultTolerance) {
            $this->handleFailedCheck();
            return [
                'success' => false,
                'token' => null,
                'message' => '验证失败，请重试',
            ];
        }

        // 轨迹验证（如果启用且提供了轨迹数据）
        if (!empty($slideTrack)) {
            $trackResult = $this->verifySlideTrack($slideTrack);
            if (!$trackResult['success']) {
                $this->handleFailedCheck();
                return $trackResult;
            }
        }

        return null;
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
    private function verifyBackendOnly(string|int|null $offset, array $slideTrack = []): array
    {
        $check = $this->validateSlideOffset($offset, $slideTrack);
        if ($check !== null) {
            return $check;
        }

        $this->handleSuccessfulCheck();
        return [
            'success' => true,
            'token' => null,
            'message' => '验证成功',
        ];
    }

    /**
     * 双重验证模式
     */
    private function verifyDual(string|int|null $offset, ?string $token, array $slideTrack = []): array
    {
        // 如果有token，进行二次验证
        if ($token !== null && $token !== '') {
            return $this->verifySecondary($token);
        }

        // 首次验证
        return $this->verifyPrimary($offset, $slideTrack);
    }

    /**
     * 首次验证（前端滑动验证）
     */
    private function verifyPrimary(string|int|null $offset, array $slideTrack = []): array
    {
        $check = $this->validateSlideOffset($offset, $slideTrack);
        if ($check !== null) {
            return $check;
        }

        // 双重验证模式：生成一次性令牌
        $token = $this->generateToken();
        $this->setSessionValue($this->sessionKeyToken, $token);
        $this->setSessionValue($this->sessionKeyTokenExpire, time() + $this->tokenExpire);
        $this->setSessionValue($this->sessionKeyCheck, 'pending');
        // 首次成功后立即作废缺口位置，防止重复利用同一张图刷 token
        $this->deleteSessionValue($this->sessionKeyR);

        return [
            'success' => true,
            'token' => $token,
            'message' => '验证成功，请完成后续操作',
        ];
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
        // 验证成功后彻底消费本次验证码数据，防止重放
        $this->deleteSessionValue($this->sessionKeyR);
        $this->deleteSessionValue($this->sessionKeyErr);
        $this->deleteSessionValue($this->sessionKeyClickData);
        $this->deleteSessionValue($this->sessionKeyFingerprint);
        $this->deleteSessionValue($this->sessionKeyCreatedAt);
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
     * 检查速率限制
     *
     * 基于滑动时间窗口的速率限制，防止暴力破解和滥用
     *
     * @return bool 是否允许继续请求
     */
    private function checkRateLimit(): bool
    {
        $rateConfig = $this->config['rate_limit'] ?? [];
        $enabled = (bool) ($rateConfig['enabled'] ?? true);
        if (!$enabled) {
            return true;
        }

        $window = $this->filterInt($rateConfig['window'] ?? 60, 10, 3600);
        $maxRequests = $this->filterInt($rateConfig['max_requests'] ?? 30, 1, 1000);

        $lastTime = $this->getSessionValue($this->sessionKeyRateLimitTime, 0);
        $count = $this->getSessionValue($this->sessionKeyRateLimit, 0);

        if (time() - $lastTime > $window) {
            $this->setSessionValue($this->sessionKeyRateLimitTime, time());
            $this->setSessionValue($this->sessionKeyRateLimit, 1);
            return true;
        }

        if ($count >= $maxRequests) {
            return false;
        }

        $this->setSessionValue($this->sessionKeyRateLimit, $count + 1);
        return true;
    }

    /**
     * 验证滑动轨迹
     *
     * 通过分析滑动轨迹特征检测机器人行为
     *
     * @param array $track 轨迹数据 [['x'=>int,'y'=>int,'t'=>int],...]
     *
     * @return array 验证结果 ['success' => bool, 'message' => string]
     */
    private function verifySlideTrack(array $track): array
    {
        $slideConfig = $this->config['slide'] ?? [];
        $trackVerify = (bool) ($slideConfig['track_verify'] ?? true);
        if (!$trackVerify) {
            return ['success' => true, 'message' => '轨迹验证已关闭'];
        }

        $strictness = $slideConfig['track_strictness'] ?? 'normal';

        // 基本校验：轨迹数据格式
        // 注意：未提供轨迹或轨迹点过少时，直接跳过轨迹校验（通过），
        // 避免误杀操作过快/采样较少的真实用户；同时与“未发送轨迹即跳过”的行为保持一致。
        if (count($track) < 3) {
            return ['success' => true, 'message' => '轨迹数据不足，已跳过轨迹校验'];
        }

        // 提取有效数据
        $points = [];
        foreach ($track as $point) {
            if (isset($point['x'], $point['y'], $point['t'])) {
                $points[] = [
                    'x' => (int) $point['x'],
                    'y' => (int) $point['y'],
                    't' => (int) $point['t'],
                ];
            }
        }

        if (count($points) < 3) {
            return ['success' => true, 'message' => '轨迹数据格式不完整，已跳过轨迹校验'];
        }

        // 计算轨迹特征
        $totalTime = $points[count($points) - 1]['t'] - $points[0]['t'];
        $totalDistance = 0;
        $directionChanges = 0;
        $speeds = [];
        $prevDx = 0;

        for ($i = 1; $i < count($points); $i++) {
            $dx = $points[$i]['x'] - $points[$i - 1]['x'];
            $dy = $points[$i]['y'] - $points[$i - 1]['y'];
            $dt = max(1, $points[$i]['t'] - $points[$i - 1]['t']);

            $dist = hypot($dx, $dy);
            $totalDistance += $dist;
            $speeds[] = $dist / $dt;

            // 检测方向变化（人类滑动会有自然的方向微调）
            if ($i > 1) {
                if (($dx > 0 && $prevDx < 0) || ($dx < 0 && $prevDx > 0)) {
                    $directionChanges++;
                }
            }
            $prevDx = $dx;
        }

        // 根据严格程度设置阈值
        // 说明：阈值整体偏宽松，目的是在不误杀真实用户的前提下拦截明显的机器轨迹。
        $thresholds = match ($strictness) {
            'strict' => [
                'min_time' => 500,      // 最少耗时 500ms
                'max_speed' => 8,       // 最大速度 8px/ms
                'min_points' => 8,      // 最少轨迹点数
                'max_straight' => 0.96, // 直线度最大 96%
            ],
            'loose' => [
                'min_time' => 150,
                'max_speed' => 20,
                'min_points' => 3,
                'max_straight' => 0.995,
            ],
            default => [ // normal
                'min_time' => 200,
                'max_speed' => 12,
                'min_points' => 4,
                'max_straight' => 0.98,
            ],
        };

        // 检查最小耗时（防止瞬间完成）
        if ($totalTime < $thresholds['min_time']) {
            return ['success' => false, 'message' => '操作过快，请重试'];
        }

        // 轨迹点数不足时，样本太少不足以判断，直接跳过（通过）而非误判
        if (count($points) < $thresholds['min_points']) {
            return ['success' => true, 'message' => '轨迹样本不足，已跳过轨迹校验'];
        }

        // 检查最大速度（防止瞬间跳跃）
        if (!empty($speeds)) {
            $maxSpeed = max($speeds);
            if ($maxSpeed > $thresholds['max_speed']) {
                return ['success' => false, 'message' => '滑动速度异常，请重试'];
            }
        }

        // 检查直线度（人类滑动不会完全直线）
        if ($totalDistance > 0) {
            $startEndDist = hypot(
                $points[count($points) - 1]['x'] - $points[0]['x'],
                $points[count($points) - 1]['y'] - $points[0]['y']
            );
            $straightness = $startEndDist / $totalDistance;
            if ($straightness > $thresholds['max_straight']) {
                return ['success' => false, 'message' => '轨迹异常，请重试'];
            }
        }

        // 检查速度变化（人类有加减速过程）
        // 仅当轨迹点足够多时再做匀速检测，避免短轨迹误判
        if (count($speeds) >= 5) {
            $speedVariance = $this->calculateVariance($speeds);
            // 速度几乎完全不变，可能是机器人匀速滑动
            if ($speedVariance < 0.002) {
                return ['success' => false, 'message' => '滑动轨迹异常，请重试'];
            }
        }

        return ['success' => true, 'message' => '轨迹验证通过'];
    }

    /**
     * 计算数组方差
     *
     * @param array $values 数值数组
     *
     * @return float 方差值
     */
    private function calculateVariance(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $sumSquaredDiff = 0.0;
        foreach ($values as $value) {
            $diff = $value - $mean;
            $sumSquaredDiff += $diff * $diff;
        }

        return $sumSquaredDiff / $count;
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
        $this->deleteSessionValue($this->sessionKeyType);
        $this->deleteSessionValue($this->sessionKeyClickData);
        $this->deleteSessionValue($this->sessionKeyFingerprint);
        $this->deleteSessionValue($this->sessionKeyCreatedAt);
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

        $bgFile = $images[$this->secureRandomIndex($images)];

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

        $this->posX = $this->secureRandomInt($minX, max($minX, $maxX));
        $this->posY = $this->secureRandomInt(0, max(0, $maxY));

        $this->setSessionValue($this->sessionKeyR, $this->posX);
        $this->setSessionValue($this->sessionKeyErr, 0);
        $this->setSessionValue($this->sessionKeyFingerprint, $this->requestFingerprint);
        $this->setSessionValue($this->sessionKeyCreatedAt, time());
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
        } catch (\Throwable) {
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

        $this->destroyImage($imgMark);
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

        $this->destroyImage($im);
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
            ? $this->filterInt($this->config['webp_quality'] ?? self::DEFAULT_WEBP_QUALITY, 0, 100)
            : $this->filterInt($this->config['png_quality'] ?? self::DEFAULT_PNG_QUALITY, 0, 9);

        if (!headers_sent()) {
            header('Content-Type: image/' . $format);
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-Content-Type-Options: nosniff');
        }

        $func = 'image' . $format;
        $func($this->im, null, $quality);
    }

    /**
     * 安全销毁图片资源
     *
     * PHP 8.0+ 中 GdImage 会自动垃圾回收，但为确保内存立即释放，
     * 在支持的 PHP 版本中仍调用 imagedestroy
     */
    private function destroy(): void
    {
        $this->destroyImage($this->im);
        $this->destroyImage($this->imFullBg);
        $this->destroyImage($this->imBg);
        $this->destroyImage($this->imSlide);
    }

    /**
     * 销毁单个图片资源
     *
     * @param GdImage|null $image 图片资源
     */
    private function destroyImage(?GdImage &$image): void
    {
        if ($image !== null) {
            // PHP 8.0+ imagedestroy 已弃用，但为保证兼容性仍调用
            if (PHP_VERSION_ID < 80000) {
                imagedestroy($image);
            }
            $image = null;
        }
    }

    /**
     * 获取配置项
     *
     * 支持点号分隔访问嵌套配置，例如：
     * - 'click.char_count' 访问点击验证码的字符数量
     * - 'slide.track_verify' 访问滑动验证码的轨迹验证配置
     *
     * @param string $key     配置键名，支持点号分隔
     * @param mixed  $default 默认值
     *
     * @return mixed 配置值
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        // 支持点号分隔访问嵌套配置
        if (str_contains($key, '.')) {
            $parts = explode('.', $key);
            $value = $this->config;
            foreach ($parts as $part) {
                if (is_array($value) && array_key_exists($part, $value)) {
                    $value = $value[$part];
                } else {
                    return $default;
                }
            }
            return $value;
        }

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
