<?php

declare(strict_types=1);

namespace zxf\Captcha;

/**
 * 验证码辅助类
 *
 * 提供便捷的帮助方法
 */
class CaptchaHelper
{
    /**
     * 生成验证码实例
     *
     * @param array $config
     * @return Captcha
     */
    public static function create(array $config = []): Captcha
    {
        return new Captcha($config);
    }

    /**
     * 快速生成验证码图片
     *
     * @param array $config
     * @return array
     */
    public static function generate(array $config = []): array
    {
        $captcha = self::create($config);
        return $captcha->generate();
    }

    /**
     * 快速验证
     *
     * @param int|float|string $offset
     * @param array $config
     * @return array
     */
    public static function verify(int|float|string $offset, array $config = []): array
    {
        $captcha = self::create($config);
        return $captcha->verify($offset);
    }

    /**
     * 获取资源文件路径
     *
     * @param string $file
     * @return string|null
     */
    public static function asset(string $file): ?string
    {
        $path = __DIR__ . '/../resources/assets/' . $file;
        return file_exists($path) ? $path : null;
    }

    /**
     * 获取资源文件内容
     *
     * @param string $file
     * @return string|null
     */
    public static function assetContent(string $file): ?string
    {
        $path = self::asset($file);
        return $path ? file_get_contents($path) : null;
    }

    /**
     * 获取资源文件 MIME 类型
     *
     * @param string $file
     * @return string
     */
    public static function assetMimeType(string $file): string
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        
        return match ($extension) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => 'application/octet-stream',
        };
    }

    /**
     * 获取默认背景图片列表
     *
     * @return array
     */
    public static function getDefaultBackgrounds(): array
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
     * 检查 GD 扩展
     *
     * @return bool
     */
    public static function checkGdExtension(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    /**
     * 检查 WebP 支持
     *
     * @return bool
     */
    public static function checkWebpSupport(): bool
    {
        return function_exists('imagewebp');
    }

    /**
     * 获取系统信息
     *
     * @return array
     */
    public static function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'gd_enabled' => self::checkGdExtension(),
            'webp_enabled' => self::checkWebpSupport(),
            'default_backgrounds' => self::getDefaultBackgrounds(),
        ];
    }
}
