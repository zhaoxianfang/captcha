<?php

declare(strict_types=1);

namespace zxf\Captcha\Storage;

use zxf\Captcha\Contracts\StorageInterface;
use zxf\Captcha\Exceptions\CaptchaException;

/**
 * 存储工厂类
 *
 * 用于创建不同类型的存储实例
 */
class StorageFactory
{
    /**
     * 创建存储实例
     *
     * @param string $driver 驱动类型
     * @param array $config 配置
     * @return StorageInterface
     * @throws CaptchaException
     */
    public static function create(string $driver, array $config = []): StorageInterface
    {
        return match ($driver) {
            'session' => new SessionStorage($config),
            'array' => new ArrayStorage($config),
            'custom' => self::createCustomStorage($config),
            default => throw new CaptchaException("不支持的存储驱动: {$driver}"),
        };
    }

    /**
     * 创建自定义存储实例
     *
     * @param array $config 配置
     * @return StorageInterface
     * @throws CaptchaException
     */
    private static function createCustomStorage(array $config): StorageInterface
    {
        $class = $config['custom_class'] ?? null;

        if (empty($class)) {
            throw new CaptchaException('自定义存储驱动必须配置 custom_class 选项');
        }

        if (!class_exists($class)) {
            throw new CaptchaException("自定义存储类不存在: {$class}");
        }

        $instance = new $class($config);

        if (!$instance instanceof StorageInterface) {
            throw new CaptchaException("自定义存储类必须实现 StorageInterface 接口");
        }

        return $instance;
    }
}
