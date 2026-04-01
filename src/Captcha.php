<?php

declare(strict_types=1);

namespace zxf\Captcha;

use GdImage;
use zxf\Captcha\Contracts\StorageInterface;
use zxf\Captcha\Exceptions\CaptchaException;
use zxf\Captcha\Storage\StorageFactory;

/**
 * 滑动验证码核心类
 *
 * PHP 8.2+ 版本，完全类型化，高性能实现
 * 
 * @package zxf\Captcha
 * @author zhaoxianfang
 * @license MIT
 */
class Captcha
{
    /**
     * 完整背景图片资源
     */
    private ?GdImage $imFullBg = null;

    /**
     * 主要背景图片资源
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
     * 背景图片宽度
     */
    private int $bgWidth = 240;

    /**
     * 背景图片高度
     */
    private int $bgHeight = 150;

    /**
     * 滑块标记宽度
     */
    private int $markWidth = 50;

    /**
     * 滑块标记高度
     */
    private int $markHeight = 50;

    /**
     * 滑块 X 坐标
     */
    private int $offsetX = 0;

    /**
     * 滑块 Y 坐标
     */
    private int $offsetY = 0;

    /**
     * 容错像素值
     */
    private int $faultTolerance = 3;

    /**
     * 最大错误次数
     */
    private int $maxErrorCount = 10;

    /**
     * 验证码有效期（秒）
     */
    private int $ttl = 300;

    /**
     * 背景图片路径列表
     */
    private array $backgroundImages = [];

    /**
     * 滑块图片配置
     */
    private array $slideImages = [];

    /**
     * 输出配置
     */
    private array $outputConfig = [];

    /**
     * Cookie 配置
     */
    private array $cookieConfig = [];

    /**
     * 存储实例
     */
    private StorageInterface $storage;

    /**
     * 当前存储键名
     */
    private ?string $currentStorageKey = null;

    /**
     * 构造函数
     *
     * @param array $config 配置项
     * @param StorageInterface|null $storage 存储实例（可选）
     * @throws CaptchaException
     */
    public function __construct(array $config = [], ?StorageInterface $storage = null)
    {
        $this->initConfig($config);
        $this->initStorage($config, $storage);
    }

    /**
     * 初始化配置
     *
     * @param array $config
     * @return void
     */
    private function initConfig(array $config): void
    {
        $this->bgWidth = $config['bg_width'] ?? 240;
        $this->bgHeight = $config['bg_height'] ?? 150;
        $this->markWidth = $config['mark_width'] ?? 50;
        $this->markHeight = $config['mark_height'] ?? 50;
        $this->faultTolerance = $config['fault_tolerance'] ?? 3;
        $this->maxErrorCount = $config['max_error_count'] ?? 10;
        $this->ttl = $config['ttl'] ?? 300;
        $this->backgroundImages = $config['background_images'] ?? [];

        // 滑块图片配置
        $defaultSlideImages = [
            'transparent' => __DIR__ . '/../resources/assets/img/mark.png',
            'dark' => __DIR__ . '/../resources/assets/img/mark2.png',
            'icon' => __DIR__ . '/../resources/assets/img/icon.png',
        ];
        $this->slideImages = array_merge($defaultSlideImages, $config['slide_images'] ?? []);

        // 输出配置
        $this->outputConfig = array_merge([
            'format' => 'webp',
            'webp_quality' => 40,
            'png_quality' => 7,
        ], $config['output'] ?? []);

        // Cookie 配置
        $this->cookieConfig = array_merge([
            'name' => 'zxf_captcha_key',
            'expire' => 0, // 浏览器关闭时过期
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ], $config['cookie'] ?? []);
    }

    /**
     * 初始化存储
     *
     * @param array $config
     * @param StorageInterface|null $storage
     * @return void
     * @throws CaptchaException
     */
    private function initStorage(array $config, ?StorageInterface $storage): void
    {
        if ($storage !== null) {
            $this->storage = $storage;
            return;
        }

        $storageConfig = $config['storage'] ?? [];
        $driver = $storageConfig['driver'] ?? 'session';

        $this->storage = StorageFactory::create($driver, $storageConfig);
    }

    /**
     * 生成验证码图片
     *
     * @param array $bgImages 自定义背景图片路径列表（可选）
     * @return array 包含图片数据和位置信息的数组
     * @throws CaptchaException
     */
    public function generate(array $bgImages = []): array
    {
        try {
            $this->init($bgImages);
            $this->createSlide();
            $this->createBg();
            $this->merge();
            $imageData = $this->outputImage();
            $this->destroy();

            return [
                'image' => $imageData,
                'format' => $this->getOutputFormat(),
                'width' => $this->bgWidth,
                'height' => $this->bgHeight,
                'key' => $this->currentStorageKey,
            ];
        } catch (\Exception $e) {
            $this->cleanup();
            throw CaptchaException::imageGenerateFailed($e->getMessage());
        }
    }

    /**
     * 验证用户输入的偏移量
     *
     * @param int|float|string $offset 用户输入的偏移量
     * @param string|null $key 存储键名（可选，默认从 cookie 获取）
     * @return array 验证结果
     */
    public function verify(int|float|string $offset, ?string $key = null): array
    {
        $storageKey = $key ?? $this->getStorageKeyFromCookie();
        
        if (empty($storageKey)) {
            return [
                'success' => false,
                'message' => '验证码标识缺失，请刷新重试',
                'code' => 'missing_key',
            ];
        }

        $correctOffset = $this->storage->get("offset:{$storageKey}");

        if ($correctOffset === null) {
            return [
                'success' => false,
                'message' => '验证码已过期，请刷新重试',
                'code' => 'expired',
            ];
        }

        $offset = (int) $offset;
        $errorCount = (int) $this->storage->get("error_count:{$storageKey}", 0);

        // 检查是否在容错范围内
        $isValid = abs((int)$correctOffset - $offset) <= $this->faultTolerance;

        if ($isValid) {
            // 验证成功，清除数据
            $this->storage->delete("offset:{$storageKey}");
            $this->storage->delete("error_count:{$storageKey}");
            $this->storage->set("verified:{$storageKey}", true, $this->ttl);

            return [
                'success' => true,
                'message' => '验证成功',
                'code' => 'success',
            ];
        }

        // 验证失败，增加错误次数
        $errorCount++;
        $this->storage->set("error_count:{$storageKey}", $errorCount, $this->ttl);

        // 超过最大错误次数，清除验证码
        if ($errorCount >= $this->maxErrorCount) {
            $this->storage->delete("offset:{$storageKey}");
            $this->storage->delete("error_count:{$storageKey}");

            return [
                'success' => false,
                'message' => '错误次数过多，请刷新验证码',
                'code' => 'too_many_errors',
            ];
        }

        return [
            'success' => false,
            'message' => '验证失败，请重试',
            'code' => 'failed',
            'remaining_attempts' => $this->maxErrorCount - $errorCount,
        ];
    }

    /**
     * 检查验证码是否已通过验证
     *
     * @param string|null $key 存储键名
     * @return bool
     */
    public function isVerified(?string $key = null): bool
    {
        $storageKey = $key ?? $this->getStorageKeyFromCookie();
        
        if (empty($storageKey)) {
            return false;
        }
        
        return (bool) $this->storage->get("verified:{$storageKey}", false);
    }

    /**
     * 清除验证码数据
     *
     * @param string|null $key 存储键名
     * @return void
     */
    public function clear(?string $key = null): void
    {
        $storageKey = $key ?? $this->getStorageKeyFromCookie();
        
        if (!empty($storageKey)) {
            $this->storage->delete("offset:{$storageKey}");
            $this->storage->delete("error_count:{$storageKey}");
            $this->storage->delete("verified:{$storageKey}");
        }
    }

    /**
     * 设置存储键名到 Cookie
     *
     * @param string $key
     * @return void
     */
    public function setKeyToCookie(string $key): void
    {
        $cookieName = $this->cookieConfig['name'];
        $expire = $this->cookieConfig['expire'];
        $path = $this->cookieConfig['path'];
        $domain = $this->cookieConfig['domain'];
        $secure = $this->cookieConfig['secure'];
        $httponly = $this->cookieConfig['httponly'];
        $samesite = $this->cookieConfig['samesite'];

        // PHP 7.3+ 支持 SameSite
        if (PHP_VERSION_ID >= 70300) {
            setcookie($cookieName, $key, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ]);
        } else {
            setcookie($cookieName, $key, $expire, $path . '; SameSite=' . $samesite, $domain, $secure, $httponly);
        }

        $_COOKIE[$cookieName] = $key;
    }

    /**
     * 清除 Cookie 中的键名
     *
     * @return void
     */
    public function clearCookie(): void
    {
        $cookieName = $this->cookieConfig['name'];
        $path = $this->cookieConfig['path'];
        $domain = $this->cookieConfig['domain'];

        setcookie($cookieName, '', time() - 3600, $path, $domain);
        unset($_COOKIE[$cookieName]);
    }

    /**
     * 从 Cookie 获取存储键名
     *
     * @return string
     */
    public function getStorageKeyFromCookie(): string
    {
        $cookieName = $this->cookieConfig['name'];
        $key = $_COOKIE[$cookieName] ?? '';
        
        // 清理非法字符
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
    }

    /**
     * 初始化图片资源和位置
     *
     * @param array $bgImages 自定义背景图片
     * @return void
     * @throws CaptchaException
     */
    private function init(array $bgImages = []): void
    {
        // 选择背景图片
        $bgImages = !empty($bgImages) ? $bgImages : $this->backgroundImages;

        if (empty($bgImages)) {
            // 使用默认背景图
            $bgImages = $this->getDefaultBackgrounds();
        }

        // 验证背景图片
        $validImages = array_filter($bgImages, fn($path) => file_exists($path) && is_readable($path));

        if (empty($validImages)) {
            throw CaptchaException::invalidConfig('未找到有效的背景图片');
        }

        $fileBg = $validImages[array_rand($validImages)];

        // 创建图片资源
        $this->imFullBg = imagecreatefrompng($fileBg);
        if ($this->imFullBg === false) {
            throw CaptchaException::imageGenerateFailed('无法加载背景图片');
        }
        
        $this->imBg = imagecreatetruecolor($this->bgWidth, $this->bgHeight);
        if ($this->imBg === false) {
            throw CaptchaException::imageGenerateFailed('无法创建背景画布');
        }
        
        imagecopy($this->imBg, $this->imFullBg, 0, 0, 0, 0, $this->bgWidth, $this->bgHeight);

        $this->imSlide = imagecreatetruecolor($this->markWidth, $this->bgHeight);
        if ($this->imSlide === false) {
            throw CaptchaException::imageGenerateFailed('无法创建滑块画布');
        }

        // 生成随机位置
        $this->offsetX = mt_rand(50, $this->bgWidth - $this->markWidth - 1);
        $this->offsetY = mt_rand(0, $this->bgHeight - $this->markHeight - 1);

        // 存储正确位置
        $this->currentStorageKey = $this->generateStorageKey();
        $this->storage->set("offset:{$this->currentStorageKey}", $this->offsetX, $this->ttl);
        $this->storage->set("error_count:{$this->currentStorageKey}", 0, $this->ttl);
    }

    /**
     * 创建滑块图片
     *
     * @return void
     * @throws CaptchaException
     */
    private function createSlide(): void
    {
        $markFile = $this->slideImages['dark'] ?? '';

        if (!file_exists($markFile)) {
            throw CaptchaException::invalidConfig("滑块图片不存在: {$markFile}");
        }

        $imgMark = imagecreatefrompng($markFile);
        if ($imgMark === false) {
            throw CaptchaException::imageGenerateFailed('无法加载滑块图片');
        }

        // 复制背景的一部分到滑块
        imagecopy(
            $this->imSlide,
            $this->imFullBg,
            0,
            $this->offsetY,
            $this->offsetX,
            $this->offsetY,
            $this->markWidth,
            $this->markHeight
        );

        // 叠加滑块标记
        imagecopy($this->imSlide, $imgMark, 0, $this->offsetY, 0, 0, $this->markWidth, $this->markHeight);
        imagecolortransparent($this->imSlide, 0);

        imagedestroy($imgMark);
    }

    /**
     * 创建背景（添加缺口）
     *
     * @return void
     * @throws CaptchaException
     */
    private function createBg(): void
    {
        $markFile = $this->slideImages['transparent'] ?? '';

        if (!file_exists($markFile)) {
            throw CaptchaException::invalidConfig("透明滑块图片不存在: {$markFile}");
        }

        $im = imagecreatefrompng($markFile);
        if ($im === false) {
            throw CaptchaException::imageGenerateFailed('无法加载透明滑块图片');
        }
        
        imagecolortransparent($im, 0);
        imagecopy($this->imBg, $im, $this->offsetX, $this->offsetY, 0, 0, $this->markWidth, $this->markHeight);
        imagedestroy($im);
    }

    /**
     * 合并图片
     *
     * @return void
     */
    private function merge(): void
    {
        $this->im = imagecreatetruecolor($this->bgWidth, $this->bgHeight * 3);

        if ($this->im === false) {
            throw CaptchaException::imageGenerateFailed('无法创建合成画布');
        }

        // 顶部：带缺口的背景
        imagecopy($this->im, $this->imBg, 0, 0, 0, 0, $this->bgWidth, $this->bgHeight);

        // 中部：滑块
        imagecopy($this->im, $this->imSlide, 0, $this->bgHeight, 0, 0, $this->markWidth, $this->bgHeight);

        // 底部：完整背景
        imagecopy($this->im, $this->imFullBg, 0, $this->bgHeight * 2, 0, 0, $this->bgWidth, $this->bgHeight);

        imagecolortransparent($this->im, 0);
    }

    /**
     * 输出图片并返回二进制数据
     *
     * @return string
     */
    private function outputImage(): string
    {
        ob_start();

        $format = $this->getOutputFormat();
        $quality = $format === 'webp'
            ? ($this->outputConfig['webp_quality'] ?? 40)
            : ($this->outputConfig['png_quality'] ?? 7);

        $func = 'image' . $format;
        $result = $func($this->im, null, $quality);
        
        if ($result === false) {
            ob_end_clean();
            throw CaptchaException::imageGenerateFailed('图片输出失败');
        }

        return ob_get_clean();
    }

    /**
     * 获取输出格式
     *
     * @return string
     */
    private function getOutputFormat(): string
    {
        $format = $this->outputConfig['format'] ?? 'webp';

        // 检查客户端是否支持 WebP
        if ($format === 'webp') {
            if (!function_exists('imagewebp')) {
                $format = 'png';
            } elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') === false) {
                // 客户端不支持 WebP
                if (!isset($_GET['force_webp'])) {
                    $format = 'png';
                }
            }
        }

        return $format;
    }

    /**
     * 销毁图片资源
     *
     * @return void
     */
    private function destroy(): void
    {
        if ($this->im) {
            imagedestroy($this->im);
            $this->im = null;
        }
        if ($this->imFullBg) {
            imagedestroy($this->imFullBg);
            $this->imFullBg = null;
        }
        if ($this->imBg) {
            imagedestroy($this->imBg);
            $this->imBg = null;
        }
        if ($this->imSlide) {
            imagedestroy($this->imSlide);
            $this->imSlide = null;
        }
    }

    /**
     * 清理资源
     *
     * @return void
     */
    private function cleanup(): void
    {
        $this->destroy();
    }

    /**
     * 生成存储键名
     *
     * @return string
     */
    private function generateStorageKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 获取默认背景图片列表
     *
     * @return array
     */
    private function getDefaultBackgrounds(): array
    {
        $bgDir = __DIR__ . '/../resources/assets/bg/';
        $images = [];

        if (is_dir($bgDir)) {
            foreach (glob($bgDir . '*.png') as $file) {
                $images[] = $file;
            }
        }

        return $images;
    }

    /**
     * 获取配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'bg_width' => $this->bgWidth,
            'bg_height' => $this->bgHeight,
            'mark_width' => $this->markWidth,
            'mark_height' => $this->markHeight,
            'fault_tolerance' => $this->faultTolerance,
            'max_error_count' => $this->maxErrorCount,
            'ttl' => $this->ttl,
        ];
    }

    /**
     * 获取当前存储键名
     *
     * @return string|null
     */
    public function getCurrentKey(): ?string
    {
        return $this->currentStorageKey;
    }
}
