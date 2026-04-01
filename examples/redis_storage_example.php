<?php

/**
 * Redis 存储示例
 * 
 * 适用于分布式部署或高并发场景
 */

use zxf\Captcha\Contracts\StorageInterface;
use zxf\Captcha\Captcha;

/**
 * Redis 存储实现
 */
class RedisStorage implements StorageInterface
{
    private \Redis $redis;
    private string $prefix;
    private int $defaultTtl;

    /**
     * 构造函数
     *
     * @param \Redis $redis Redis 实例
     * @param string $prefix 键名前缀
     * @param int $defaultTtl 默认过期时间（秒）
     */
    public function __construct(\Redis $redis, string $prefix = 'captcha:', int $defaultTtl = 300)
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, int $ttl = 300): bool
    {
        $fullKey = $this->makeKey($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];
        
        return $this->redis->setex($fullKey, $ttl, serialize($data));
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $fullKey = $this->makeKey($key);
        $data = $this->redis->get($fullKey);
        
        if ($data === false) {
            return $default;
        }
        
        $unserialized = @unserialize($data);
        
        if ($unserialized === false) {
            return $default;
        }
        
        // 检查是否过期（Redis 已自动过期，这里是双重保险）
        if (isset($unserialized['expires']) && $unserialized['expires'] < time()) {
            $this->redis->del($fullKey);
            return $default;
        }
        
        return $unserialized['value'] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $fullKey = $this->makeKey($key);
        return $this->redis->del($fullKey) > 0;
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
        return $this->prefix . $key;
    }

    /**
     * 批量删除（按前缀）
     *
     * @param string $pattern
     * @return int
     */
    public function deleteByPattern(string $pattern): int
    {
        $iterator = null;
        $deleted = 0;
        
        while ($keys = $this->redis->scan($iterator, $pattern, 100)) {
            if (!empty($keys)) {
                $deleted += $this->redis->del(...$keys);
            }
        }
        
        return $deleted;
    }

    /**
     * 获取统计信息
     *
     * @return array
     */
    public function getStats(): array
    {
        $info = $this->redis->info('keyspace');
        $keys = $this->redis->keys($this->prefix . '*');
        
        return [
            'total_keys' => count($keys),
            'keyspace_info' => $info,
        ];
    }
}

// ========================================
// 使用示例
// ========================================

// 创建 Redis 连接
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);
$redis->select(0); // 选择数据库

// 创建 Redis 存储
$storage = new RedisStorage($redis, 'captcha:', 300);

// 创建验证码实例
$captcha = new Captcha([
    'storage' => [
        'driver' => 'custom',
        'custom_class' => RedisStorage::class,
    ],
    'fault_tolerance' => 3,
    'ttl' => 300,
], $storage);

// 生成验证码
$result = $captcha->generate();
echo "验证码 Key: " . $result['key'] . "\n";

// 验证
$verifyResult = $captcha->verify(100);
echo "验证结果: " . ($verifyResult['success'] ? '成功' : '失败') . "\n";

// 查看统计
print_r($storage->getStats());
