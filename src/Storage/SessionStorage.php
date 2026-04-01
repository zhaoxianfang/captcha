<?php

declare(strict_types=1);

namespace zxf\Captcha\Storage;

use zxf\Captcha\Contracts\StorageInterface;

/**
 * Session 存储实现
 *
 * 使用 PHP 原生 Session 存储验证码数据
 */
class SessionStorage implements StorageInterface
{
    /**
     * Session 键名前缀
     */
    private string $prefix;

    /**
     * 是否已启动 Session
     */
    private bool $sessionStarted = false;

    /**
     * 构造函数
     *
     * @param array $config 配置项
     */
    public function __construct(array $config = [])
    {
        $this->prefix = $config['session_key'] ?? 'zxf_captcha';
        $this->ensureSessionStarted();
    }

    /**
     * 确保 Session 已启动
     */
    private function ensureSessionStarted(): void
    {
        if (!$this->sessionStarted) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $this->sessionStarted = true;
        }
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, int $ttl = 300): bool
    {
        $this->ensureSessionStarted();
        $_SESSION[$this->makeKey($key)] = [
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
        $this->ensureSessionStarted();
        $fullKey = $this->makeKey($key);

        if (!isset($_SESSION[$fullKey])) {
            return $default;
        }

        $data = $_SESSION[$fullKey];

        // 检查是否过期
        if (isset($data['expires']) && $data['expires'] < time()) {
            unset($_SESSION[$fullKey]);
            return $default;
        }

        return $data['value'] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $this->ensureSessionStarted();
        unset($_SESSION[$this->makeKey($key)]);
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
}
