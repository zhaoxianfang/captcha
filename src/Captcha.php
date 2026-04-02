<?php

/**
 * zxf/captcha - 高性能滑动验证码 PHP 扩展包
 *
 * @package     zxf\Captcha
 * @author      zhaoxianfang <zhaoxianfang@163.com>
 * @license     MIT
 * @version     2.0.0
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
 * @author zhaoxianfang
 * @since  2.0.0
 */
class Captcha
{
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
     * 值越大用户体验越好，但安全性降低
     * 值越小安全性越高，但可能增加用户操作难度
     */
    private int $faultTolerance = 3;

    /**
     * 最大错误次数
     * 超过此次数将强制刷新验证码
     */
    private int $maxErrorCount = 10;

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

            // 黑色滑块图片路径（用于滑块显示）
            'slide_dark_img' => $basePath . '/resources/assets/images/mark_02.png',

            // 透明滑块图片路径（用于背景缺口）
            'slide_transparent_img' => $basePath . '/resources/assets/images/mark_01.png',

            // 背景图片目录
            'bg_images_dir' => $basePath . '/resources/assets/images/bg/',

            // 背景图片列表（留空则自动扫描目录）
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

            // 图片输出格式：webp 或 png
            'output_format' => 'webp',

            // WebP 图片质量（0-100）
            'webp_quality' => 40,

            // PNG 图片压缩级别（0-9）
            'png_quality' => 7,

            // Session 前缀
            'session_prefix' => 'xf_captcha',
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

        // 设置 Session 键名
        $prefix = $this->config['session_prefix'] ?? 'xf_captcha';
        $this->sessionKeyR = $prefix . '_r';
        $this->sessionKeyErr = $prefix . '_err';
        $this->sessionKeyCheck = $prefix . '_check';

        // 修复图片路径 - 如果配置中的路径不存在，使用默认路径
        $this->fixImagePaths();

        // 设置默认背景图片
        $this->defaultBgImages = $this->getBgImages();
    }

    /**
     * 修复图片路径
     * 当配置文件中指定的路径不存在或为空时，使用包内的默认路径
     */
    private function fixImagePaths(): void
    {
        $packageRoot = dirname(__DIR__);

        // 路径映射：配置键 => 默认文件名
        $pathMap = [
            'tool_icon_img' => 'icon.png',
            'slide_dark_img' => 'mark_02.png',
            'slide_transparent_img' => 'mark_01.png',
        ];

        foreach ($pathMap as $key => $defaultFilename) {
            $configuredPath = $this->config[$key] ?? '';
            // 如果路径为空或文件不存在，使用默认路径
            if (empty($configuredPath) || !file_exists($configuredPath)) {
                $defaultPath = $packageRoot . '/resources/assets/images/' . $defaultFilename;
                if (file_exists($defaultPath)) {
                    $this->config[$key] = $defaultPath;
                }
            }
        }

        // 修复背景图片目录
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
        // 如果配置了具体的背景图片列表，直接使用
        if (!empty($this->config['bg_images'])) {
            return $this->config['bg_images'];
        }

        // 自动扫描背景图片目录
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * 生成验证码图片
     *
     * 该方法生成并输出验证码图片到浏览器
     *
     * @param array $bgImages 自定义背景图片路径数组，不传则使用配置中的背景图
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

            // 获取输出格式
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
        // 如果强制指定了格式
        if (isset($_GET['nowebp']) || !function_exists('imagewebp')) {
            return 'png';
        }

        return $this->config['output_format'] === 'webp' ? 'webp' : 'png';
    }

    /**
     * 验证用户滑动结果
     *
     * @param string|int $offset 用户滑动的偏移量
     *
     * @return bool 验证是否通过
     */
    public function check(string|int $offset = ''): bool
    {
        // 检查 Session 中是否存在正确的滑块位置
        if (!isset($_SESSION[$this->sessionKeyR])) {
            return false;
        }

        // 获取用户提交的偏移量
        if ($offset === '' || $offset === null) {
            $offset = $_REQUEST['captcha_r'] ?? $_REQUEST['xf_captcha'] ?? '';
        }

        // 验证偏移量是否为有效数字
        if (!is_numeric($offset)) {
            $this->handleFailedCheck();
            return false;
        }

        $offset = (float) $offset;
        $correctPos = (float) $_SESSION[$this->sessionKeyR];

        // 计算偏移差值是否在容错范围内
        $diff = abs($correctPos - $offset);
        $isValid = $diff <= $this->faultTolerance;

        if ($isValid) {
            $this->handleSuccessfulCheck();
        } else {
            $this->handleFailedCheck();
        }

        return $isValid;
    }

    /**
     * 处理验证成功
     */
    private function handleSuccessfulCheck(): void
    {
        // 清除正确位置信息
        unset($_SESSION[$this->sessionKeyR]);
        // 清除错误计数
        unset($_SESSION[$this->sessionKeyErr]);
        // 设置验证通过标记
        $_SESSION[$this->sessionKeyCheck] = 'ok';
    }

    /**
     * 处理验证失败
     */
    private function handleFailedCheck(): void
    {
        // 增加错误次数计数
        $errCount = ($_SESSION[$this->sessionKeyErr] ?? 0) + 1;
        $_SESSION[$this->sessionKeyErr] = $errCount;

        // 如果错误次数超过上限，强制刷新（清除正确位置）
        if ($errCount > $this->maxErrorCount) {
            unset($_SESSION[$this->sessionKeyR]);
        }

        // 设置验证失败标记
        $_SESSION[$this->sessionKeyCheck] = 'error';
    }

    /**
     * 检查验证码是否已通过验证
     *
     * @return bool 是否已通过验证
     */
    public function isChecked(): bool
    {
        return isset($_SESSION[$this->sessionKeyCheck]) &&
               $_SESSION[$this->sessionKeyCheck] === 'ok';
    }

    /**
     * 刷新验证码
     *
     * 清除当前验证码状态，强制用户重新验证
     *
     * @return void
     */
    public function refresh(): void
    {
        unset($_SESSION[$this->sessionKeyR]);
        unset($_SESSION[$this->sessionKeyErr]);
        unset($_SESSION[$this->sessionKeyCheck]);
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

        // 随机选择一张背景图
        $bgFile = $images[array_rand($images)];

        if (!file_exists($bgFile) || !is_readable($bgFile)) {
            throw new RuntimeException('背景图片不存在或无法读取: ' . $bgFile);
        }

        // 加载背景图片
        $this->imFullBg = $this->loadImage($bgFile);
        if ($this->imFullBg === null) {
            throw new RuntimeException('加载背景图片失败: ' . $bgFile);
        }

        // 创建主背景画布
        $this->imBg = imagecreatetruecolor($this->bgWidth, $this->bgHeight);
        if ($this->imBg === false) {
            throw new RuntimeException('创建背景画布失败');
        }

        // 复制背景到画布
        imagecopy($this->imBg, $this->imFullBg, 0, 0, 0, 0, $this->bgWidth, $this->bgHeight);

        // 创建滑块画布
        $this->imSlide = imagecreatetruecolor($this->markWidth, $this->bgHeight);
        if ($this->imSlide === false) {
            throw new RuntimeException('创建滑块画布失败');
        }

        // 随机生成滑块位置
        $minX = $this->markWidth;
        $maxX = $this->bgWidth - $this->markWidth - 1;
        $maxY = $this->bgHeight - $this->markHeight - 1;

        $this->posX = mt_rand($minX, $maxX);
        $this->posY = mt_rand(0, max(0, $maxY));

        // 存储正确位置到 Session
        $_SESSION[$this->sessionKeyR] = $this->posX;
        $_SESSION[$this->sessionKeyErr] = 0;
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

        // 将背景的一部分复制到滑块
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

        // 将滑块标记覆盖到滑块上
        imagecopy($this->imSlide, $imgMark, 0, $this->posY, 0, 0, $this->markWidth, $this->markHeight);

        // 设置透明色
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

        // 设置透明色
        imagecolortransparent($im, 0);

        // 将透明滑块复制到背景
        imagecopy($this->imBg, $im, $this->posX, $this->posY, 0, 0, $this->markWidth, $this->markHeight);

        imagedestroy($im);
    }

    /**
     * 合并所有图层
     */
    private function merge(): void
    {
        // 创建最终合成画布（高度为背景高度的3倍）
        $this->im = imagecreatetruecolor($this->bgWidth, $this->bgHeight * 3);
        if ($this->im === false) {
            throw new RuntimeException('创建合成画布失败');
        }

        // 第一层：带缺口的背景
        imagecopy($this->im, $this->imBg, 0, 0, 0, 0, $this->bgWidth, $this->bgHeight);

        // 第二层：滑块图片
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

        // 第三层：完整背景（用于前端显示）
        imagecopy($this->im, $this->imFullBg, 0, $this->bgHeight * 2, 0, 0, $this->bgWidth, $this->bgHeight);

        // 设置透明色
        imagecolortransparent($this->im, 0);
    }

    /**
     * 输出图片到浏览器
     */
    private function output(): void
    {
        // 清理输出缓冲区
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $format = $this->getOutputFormat();
        $quality = $format === 'webp'
            ? (int) ($this->config['webp_quality'] ?? 40)
            : (int) ($this->config['png_quality'] ?? 7);

        // 设置响应头
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
