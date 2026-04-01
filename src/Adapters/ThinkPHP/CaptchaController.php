<?php

declare(strict_types=1);

namespace zxf\Captcha\Adapters\ThinkPHP;

use think\facade\Request;
use think\Response;
use zxf\Captcha\Captcha;
use zxf\Captcha\Exceptions\CaptchaException;

/**
 * ThinkPHP 验证码控制器
 * 
 * @package zxf\Captcha\Adapters\ThinkPHP
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
     * 构造函数
     */
    public function __construct()
    {
        $this->config = config('captcha', []);
        $this->captcha = app('captcha');
    }

    /**
     * 获取验证码图片
     *
     * @return Response
     */
    public function image(): Response
    {
        try {
            if ($this->isRateLimited()) {
                return json([
                    'success' => false,
                    'message' => '请求过于频繁，请稍后再试',
                    'code' => 'rate_limited',
                ], 429);
            }

            $result = $this->captcha->generate();

            // 设置 Cookie
            $this->captcha->setKeyToCookie($result['key']);

            $this->recordRequest();

            $mimeType = $result['format'] === 'webp' ? 'image/webp' : 'image/png';

            return response($result['image'], 200, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'Pragma' => 'no-cache',
            ]);
        } catch (CaptchaException $e) {
            return json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * 验证验证码
     *
     * @return Response
     */
    public function verify(): Response
    {
        $offset = Request::param('tn_r');

        if ($offset === null) {
            return json([
                'success' => false,
                'message' => '缺少验证参数',
                'code' => 'missing_parameter',
            ], 400);
        }

        $result = $this->captcha->verify($offset);

        // 验证成功后清除 Cookie
        if ($result['success']) {
            $this->captcha->clearCookie();
        }

        $statusCode = $result['success'] ? 200 : 400;

        return json($result, $statusCode);
    }

    /**
     * 输出 CSS
     *
     * @return Response
     */
    public function css(): Response
    {
        $content = $this->getAssetContent('css/captcha.css');

        if ($content === null) {
            return json(['message' => 'Not Found'], 404);
        }

        return response($content, 200, [
            'Content-Type' => 'text/css',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * 输出 JS
     *
     * @return Response
     */
    public function js(): Response
    {
        $content = $this->getAssetContent('js/captcha.js');

        if ($content === null) {
            return json(['message' => 'Not Found'], 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * 输出图片资源
     *
     * @param string $filename
     * @return Response
     */
    public function img(string $filename): Response
    {
        $allowedFiles = ['icon.png', 'mark.png', 'mark2.png'];

        if (!in_array($filename, $allowedFiles, true)) {
            return json(['message' => 'Not Found'], 404);
        }

        $content = $this->getAssetContent('img/' . $filename);

        if ($content === null) {
            return json(['message' => 'Not Found'], 404);
        }

        return response($content, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * 获取资源文件内容
     *
     * @param string $path
     * @return string|null
     */
    private function getAssetContent(string $path): ?string
    {
        $fullPath = __DIR__ . '/../../../resources/assets/' . $path;

        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath);
    }

    /**
     * 检查是否被频率限制
     *
     * @return bool
     */
    private function isRateLimited(): bool
    {
        $security = $this->config['security'] ?? [];

        if (!($security['frequency_limit_enabled'] ?? true)) {
            return false;
        }

        $ip = Request::ip();
        $key = 'captcha_rate_limit:' . md5($ip);
        $lastRequest = session($key, 0);
        $minInterval = $security['min_request_interval'] ?? 1;

        return (time() - $lastRequest) < $minInterval;
    }

    /**
     * 记录请求时间
     *
     * @return void
     */
    private function recordRequest(): void
    {
        $ip = Request::ip();
        $key = 'captcha_rate_limit:' . md5($ip);
        session([$key => time()]);
    }
}
