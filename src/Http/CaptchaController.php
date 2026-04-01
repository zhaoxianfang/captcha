<?php

declare(strict_types=1);

namespace zxf\Captcha\Http;

use zxf\Captcha\Captcha;
use zxf\Captcha\Contracts\RequestInterface;
use zxf\Captcha\Contracts\ResponseInterface;
use zxf\Captcha\Exceptions\CaptchaException;

/**
 * 验证码控制器
 *
 * 处理验证码图片生成、验证和资源输出
 * 
 * @package zxf\Captcha\Http
 */
class CaptchaController
{
    /**
     * 验证码实例
     */
    private Captcha $captcha;

    /**
     * 配置
     */
    private array $config;

    /**
     * 存储适配器
     */
    private ?StorageAdapter $storageAdapter = null;

    /**
     * 构造函数
     *
     * @param array $config 配置
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->captcha = new Captcha($config);
        
        // 初始化存储适配器（用于频率限制）
        $this->storageAdapter = new StorageAdapter($config['storage'] ?? []);
    }

    /**
     * 获取验证码图片
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return mixed
     */
    public function image(RequestInterface $request, ResponseInterface $response): mixed
    {
        try {
            // 检查频率限制
            if ($this->isRateLimited($request)) {
                return $response->json([
                    'success' => false,
                    'message' => '请求过于频繁，请稍后再试',
                    'code' => 'rate_limited',
                ], 429);
            }

            $result = $this->captcha->generate();

            // 设置 Cookie（用于标识验证码）
            $this->setCookie($result['key']);

            // 记录请求时间
            $this->recordRequest($request);

            return $response->image($result['image'], $result['format']);
        } catch (CaptchaException $e) {
            return $response->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * 验证验证码
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return mixed
     */
    public function verify(RequestInterface $request, ResponseInterface $response): mixed
    {
        $offset = $request->input('tn_r');

        if ($offset === null) {
            return $response->json([
                'success' => false,
                'message' => '缺少验证参数',
                'code' => 'missing_parameter',
            ], 400);
        }

        // 验证（key 从 cookie 中获取）
        $result = $this->captcha->verify($offset);

        // 验证成功后清除 Cookie
        if ($result['success']) {
            $this->clearCookie();
        }

        $statusCode = $result['success'] ? 200 : 400;

        return $response->json($result, $statusCode);
    }

    /**
     * 输出 CSS 资源
     *
     * @param ResponseInterface $response
     * @return mixed
     */
    public function css(ResponseInterface $response): mixed
    {
        $content = $this->getAssetContent('css/captcha.css');

        if ($content === null) {
            return $response->json(['message' => 'Not Found'], 404);
        }

        return $response->asset($content, 'text/css');
    }

    /**
     * 输出 JS 资源
     *
     * @param ResponseInterface $response
     * @return mixed
     */
    public function js(ResponseInterface $response): mixed
    {
        $content = $this->getAssetContent('js/captcha.js');

        if ($content === null) {
            return $response->json(['message' => 'Not Found'], 404);
        }

        return $response->asset($content, 'application/javascript');
    }

    /**
     * 输出图片资源
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param string $filename 文件名
     * @return mixed
     */
    public function img(RequestInterface $request, ResponseInterface $response, string $filename): mixed
    {
        $allowedFiles = ['icon.png', 'mark.png', 'mark2.png'];

        if (!in_array($filename, $allowedFiles, true)) {
            return $response->json(['message' => 'Not Found'], 404);
        }

        $content = $this->getAssetContent('img/' . $filename);

        if ($content === null) {
            return $response->json(['message' => 'Not Found'], 404);
        }

        return $response->asset($content, 'image/png');
    }

    /**
     * 获取资源文件内容
     *
     * @param string $path 相对路径
     * @return string|null
     */
    private function getAssetContent(string $path): ?string
    {
        $fullPath = __DIR__ . '/../../resources/assets/' . $path;

        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath);
    }

    /**
     * 设置 Cookie
     *
     * @param string $key
     * @return void
     */
    private function setCookie(string $key): void
    {
        $cookieName = $this->config['cookie']['name'] ?? 'zxf_captcha_key';
        $path = $this->config['cookie']['path'] ?? '/';
        $expire = $this->config['cookie']['expire'] ?? 0;
        $secure = $this->config['cookie']['secure'] ?? false;
        $httponly = $this->config['cookie']['httponly'] ?? true;
        $samesite = $this->config['cookie']['samesite'] ?? 'Lax';

        // PHP 7.3+ 支持 SameSite
        if (PHP_VERSION_ID >= 70300) {
            setcookie($cookieName, $key, [
                'expires' => time() + 3600,
                'path' => $path,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ]);
        } else {
            setcookie($cookieName, $key, time() + 3600, $path . '; SameSite=' . $samesite, '', $secure, $httponly);
        }

        $_COOKIE[$cookieName] = $key;
    }

    /**
     * 清除 Cookie
     *
     * @return void
     */
    private function clearCookie(): void
    {
        $cookieName = $this->config['cookie']['name'] ?? 'zxf_captcha_key';
        $path = $this->config['cookie']['path'] ?? '/';

        setcookie($cookieName, '', time() - 3600, $path);
        unset($_COOKIE[$cookieName]);
    }

    /**
     * 检查是否被频率限制
     *
     * @param RequestInterface $request
     * @return bool
     */
    private function isRateLimited(RequestInterface $request): bool
    {
        $security = $this->config['security'] ?? [];

        if (!($security['frequency_limit_enabled'] ?? true)) {
            return false;
        }

        $ip = $request->getClientIp();
        $key = 'captcha_rate_limit:' . md5($ip);
        $lastRequest = $this->storageAdapter->get($key, 0);
        $minInterval = $security['min_request_interval'] ?? 1;

        return (time() - $lastRequest) < $minInterval;
    }

    /**
     * 记录请求时间
     *
     * @param RequestInterface $request
     * @return void
     */
    private function recordRequest(RequestInterface $request): void
    {
        $ip = $request->getClientIp();
        $key = 'captcha_rate_limit:' . md5($ip);
        $this->storageAdapter->set($key, time(), 60);
    }
}

/**
 * 存储适配器（用于频率限制）
 */
class StorageAdapter
{
    private array $data = [];
    private string $driver;
    private string $sessionKey;

    public function __construct(array $config)
    {
        $this->driver = $config['driver'] ?? 'session';
        $this->sessionKey = $config['session_key'] ?? 'zxf_captcha';
        
        if ($this->driver === 'session' && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function set(string $key, mixed $value, int $ttl = 300): void
    {
        if ($this->driver === 'session') {
            $_SESSION[$this->sessionKey . '_rate_' . $key] = [
                'value' => $value,
                'expires' => time() + $ttl,
            ];
        } else {
            $this->data[$key] = [
                'value' => $value,
                'expires' => time() + $ttl,
            ];
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->driver === 'session') {
            $data = $_SESSION[$this->sessionKey . '_rate_' . $key] ?? null;
        } else {
            $data = $this->data[$key] ?? null;
        }

        if ($data === null) {
            return $default;
        }

        if (isset($data['expires']) && $data['expires'] < time()) {
            return $default;
        }

        return $data['value'] ?? $default;
    }
}
