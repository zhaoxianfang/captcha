<?php

/**
 * zxf/captcha - ThinkPHP 8+ 服务类
 *
 * @package     zxf\Captcha\ThinkPHP
 * @author      zhaoxianfang <zhaoxianfang@163.com>
 * @license     MIT
 */

declare(strict_types=1);

namespace zxf\Captcha\ThinkPHP;

use think\Service;
use think\Route;
use think\Validate;
use zxf\Captcha\Captcha;

/**
 * ThinkPHP 8+ 验证码服务类
 *
 * @author zhaoxianfang
 * @since  2.0.0
 */
class CaptchaService extends Service
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        // 绑定 Captcha 实例
        $this->app->bind('xfCaptcha', function () {
            $config = $this->app->config->get('xf_captcha', []);
            return new Captcha($config);
        });

        // 绑定到容器
        $this->app->bind(Captcha::class, 'xfCaptcha');
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
        // 注册路由
        $this->registerRoutes();

        // 注册验证规则
        $this->registerValidator();
    }

    /**
     * 注册验证码路由
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $config = $this->app->config->get('xf_captcha', []);
        $prefix = $config['route_prefix'] ?? 'xf_captcha';

        /** @var Route $route */
        $route = $this->app->route;

        // 验证码图片
        $route->get($prefix . '/image', function () {
            try {
                /** @var Captcha $captcha */
                $captcha = app('xfCaptcha');
                $captcha->make();
            } catch (\Throwable $e) {
                return response('验证码生成失败: ' . $e->getMessage(), 500);
            }
        });

        // 验证接口
        $route->post($prefix . '/check', function () {
            return $this->handleCheck();
        });
        $route->get($prefix . '/check', function () {
            return $this->handleCheck();
        });

        // 静态资源 - 确保正确的 MIME 类型
        $route->get($prefix . '/js', function () {
            return $this->outputAsset('js/captcha.js', 'application/javascript');
        });

        $route->get($prefix . '/css', function () {
            return $this->outputAsset('css/captcha.css', 'text/css');
        });

        $route->get($prefix . '/icon', function () {
            return $this->outputAsset('images/icon.png', 'image/png');
        });
    }

    /**
     * 处理验证请求
     *
     * @return \think\Response
     */
    protected function handleCheck(): \think\Response
    {
        try {
            /** @var Captcha $captcha */
            $captcha = app('xfCaptcha');

            // 获取请求参数
            $offset = request()->get('captcha_r') ?? request()->post('captcha_r');
            $token = request()->get('xf_captcha_token') ?? request()->post('xf_captcha_token');

            // 执行验证
            $result = $captcha->verify($offset, $token);

            return json([
                'success' => $result['success'],
                'message' => $result['message'],
                'code' => $result['success'] ? 200 : 400,
                'token' => $result['token'],
            ]);
        } catch (\Throwable $e) {
            return json([
                'success' => false,
                'message' => '验证出错：' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取包根目录
     *
     * @return string
     */
    protected function getPackageRoot(): string
    {
        $possiblePaths = [
            dirname(__DIR__, 2),
            realpath(__DIR__ . '/../../..'),
            root_path() . 'vendor/zxf/captcha',
        ];

        foreach ($possiblePaths as $path) {
            $normalizedPath = realpath($path);
            if ($normalizedPath !== false && is_dir($normalizedPath . '/resources/assets')) {
                return $normalizedPath;
            }
        }

        return dirname(__DIR__, 2);
    }

    /**
     * 输出静态资源文件
     *
     * @param string $path     相对资源目录的路径
     * @param string $mimeType MIME 类型
     *
     * @return \think\Response
     */
    protected function outputAsset(string $path, string $mimeType): \think\Response
    {
        $packageRoot = $this->getPackageRoot();
        $file = $packageRoot . '/resources/assets/' . $path;
        $realFile = realpath($file);

        if ($realFile === false || !file_exists($realFile)) {
            return response('File not found: ' . $path . ' (looked in: ' . $file . ')', 404, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        $content = file_get_contents($realFile);
        if ($content === false) {
            return response('Failed to read file: ' . $path, 500, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        return response($content, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($content),
            'Cache-Control' => 'public, max-age=86400',
            'Expires' => gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT',
        ]);
    }

    /**
     * 注册验证器
     *
     * @return void
     */
    protected function registerValidator(): void
    {
        // 注册 xfCaptcha 验证规则
        Validate::maker(function ($validate) {
            $validate->extend('xfCaptcha', function ($value) {
                $captcha = app('xfCaptcha');
                $result = $captcha->verify(null, $value);
                return $result['success'];
            }, '滑动验证码验证失败');

            $validate->extend('xfcaptcha', function ($value) {
                $captcha = app('xfCaptcha');
                $result = $captcha->verify(null, $value);
                return $result['success'];
            }, '滑动验证码验证失败');
        });
    }
}
