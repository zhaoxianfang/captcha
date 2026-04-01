<?php

declare(strict_types=1);

namespace zxf\Captcha\Adapters\Laravel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use zxf\Captcha\Captcha;
use zxf\Captcha\Exceptions\CaptchaException;

/**
 * Laravel 验证码控制器
 * 
 * @package zxf\Captcha\Adapters\Laravel
 */
class CaptchaController extends Controller
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
        $this->captcha = app(Captcha::class);
    }

    /**
     * 获取验证码图片
     *
     * @param Request $request
     * @return Response|JsonResponse
     */
    public function image(Request $request): Response|JsonResponse
    {
        try {
            // 检查频率限制
            if ($this->isRateLimited($request)) {
                return response()->json([
                    'success' => false,
                    'message' => '请求过于频繁，请稍后再试',
                    'code' => 'rate_limited',
                ], 429);
            }

            $result = $this->captcha->generate();

            // 设置 Cookie
            $this->captcha->setKeyToCookie($result['key']);

            // 记录请求时间
            $this->recordRequest($request);

            $mimeType = $result['format'] === 'webp' ? 'image/webp' : 'image/png';

            return response($result['image'], 200, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (CaptchaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * 验证验证码
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        $offset = $request->input('tn_r');

        if ($offset === null) {
            return response()->json([
                'success' => false,
                'message' => '缺少验证参数',
                'code' => 'missing_parameter',
            ], 400);
        }

        // 验证
        $result = $this->captcha->verify($offset);

        // 验证成功后清除 Cookie
        if ($result['success']) {
            $this->captcha->clearCookie();
        }

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    /**
     * 输出 CSS
     *
     * @return Response|JsonResponse
     */
    public function css(): Response|JsonResponse
    {
        $content = $this->getAssetContent('css/captcha.css');

        if ($content === null) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response($content, 200, [
            'Content-Type' => 'text/css',
            'Cache-Control' => 'public, max-age=86400',
            'Expires' => gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT',
        ]);
    }

    /**
     * 输出 JS
     *
     * @return Response|JsonResponse
     */
    public function js(): Response|JsonResponse
    {
        $content = $this->getAssetContent('js/captcha.js');

        if ($content === null) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age=86400',
            'Expires' => gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT',
        ]);
    }

    /**
     * 输出图片资源
     *
     * @param string $filename
     * @return Response|JsonResponse
     */
    public function img(string $filename): Response|JsonResponse
    {
        $allowedFiles = ['icon.png', 'mark.png', 'mark2.png'];

        if (!in_array($filename, $allowedFiles, true)) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $content = $this->getAssetContent('img/' . $filename);

        if ($content === null) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response($content, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'Expires' => gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT',
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
     * @param Request $request
     * @return bool
     */
    private function isRateLimited(Request $request): bool
    {
        $security = $this->config['security'] ?? [];

        if (!($security['frequency_limit_enabled'] ?? true)) {
            return false;
        }

        $ip = $request->ip();
        $key = 'captcha_rate_limit:' . md5($ip);
        $lastRequest = session($key, 0);
        $minInterval = $security['min_request_interval'] ?? 1;

        return (time() - $lastRequest) < $minInterval;
    }

    /**
     * 记录请求时间
     *
     * @param Request $request
     * @return void
     */
    private function recordRequest(Request $request): void
    {
        $ip = $request->ip();
        $key = 'captcha_rate_limit:' . md5($ip);
        session([$key => time()]);
    }
}
