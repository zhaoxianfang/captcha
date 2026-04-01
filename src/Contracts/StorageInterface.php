<?php

declare(strict_types=1);

namespace zxf\Captcha\Contracts;

/**
 * 验证码存储接口
 *
 * 定义验证码数据的存储和读取规范，支持多种存储实现
 */
interface StorageInterface
{
    /**
     * 存储验证码数据
     *
     * @param string $key 存储键名
     * @param mixed $value 存储值
     * @param int $ttl 有效期（秒）
     * @return bool
     */
    public function set(string $key, mixed $value, int $ttl = 300): bool;

    /**
     * 获取验证码数据
     *
     * @param string $key 存储键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 删除验证码数据
     *
     * @param string $key 存储键名
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * 检查验证码数据是否存在
     *
     * @param string $key 存储键名
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * 生成完整的存储键名
     *
     * @param string $key 原始键名
     * @return string
     */
    public function makeKey(string $key): string;
}
