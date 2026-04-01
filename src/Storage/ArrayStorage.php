<?php

declare(strict_types=1);

namespace zxf\Captcha\Storage;

use zxf\Captcha\Contracts\StorageInterface;

/**
 * 数组存储实现（用于测试或无状态环境）
 */
class ArrayStorage implements StorageInterface
{
    /**
     * 存储数据
     */
    private array $storage = [];

    /**
     * 键名前缀
     */
    private string $prefix;

    /**
     * 构造函数
     *
     * @param array $config 配置项
     */
    public function __construct(array $config = [])
    {
        $this->prefix = $config['prefix'] ?? 'zxf_captcha';
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, int $ttl = 300): bool
    {
        $this->storage[$this->makeKey($key)] = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];
        return true;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $fullKey = $this->makeKey($key);

        if (!isset($this->storage[$fullKey])) {
            return $default;
        }

        $data = $this->storage[$fullKey];

        // 检查是否过期
        if (isset($data['expires']) && $data['expires'] < time()) {
            unset($this->storage[$fullKey]);
            return $default;
        }

        return $data['value'] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        unset($this->storage[$this->makeKey($key)]);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @inheritDoc
     */
    public function makeKey(string $key): string
    {
        return $this->prefix . '_' . $key;
    }

    /**
     * 清空所有存储数据
     *
     * @return void
     */
    public function clear(): void
    {
        $this->storage = [];
    }
}
