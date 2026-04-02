<?php

/**
 * zxf/captcha - ThinkPHP 服务类
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
 * ThinkPHP 验证码服务类
 *
 * 为 ThinkPHP 框架提供验证码服务集成
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
        $prefix = $config['route_prefix'] ?? 'captcha';

        /** @var Route $route */
        $route = $this->app->route;

        // 验证码图片
        $route->get($prefix . '/image', function () {
            $captcha = app('xfCaptcha');
            $captcha->make();
        });

        // 验证接口
        $route->post($prefix . '/check', function () {
            return $this->handleCheck();
        });
        $route->get($prefix . '/check', function () {
            return $this->handleCheck();
        });

        // 静态资源
        $route->get($prefix . '/js', function () {
            return $this->outputStaticFile('js/captcha.js', 'application/javascript');
        });

        $route->get($prefix . '/css', function () {
            return $this->outputStaticFile('css/captcha.css', 'text/css');
        });

        $route->get($prefix . '/icon', function () {
            return $this->outputStaticFile('images/icon.png', 'image/png');
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
            $captcha = app('xfCaptcha');
            $result = $captcha->check();

            if ($result) {
                return json([
                    'success' => true,
                    'message' => '验证成功',
                    'code' => 200,
                ]);
            }

            return json([
                'success' => false,
                'message' => '验证失败，请重试',
                'code' => 400,
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
     * 输出静态文件
     *
     * @param string $path     相对路径
     * @param string $mimeType MIME 类型
     *
     * @return \think\Response
     */
    protected function outputStaticFile(string $path, string $mimeType): \think\Response
    {
        $resourcePath = dirname(__DIR__, 2) . '/resources/assets';
        $file = $resourcePath . '/' . $path;

        if (!file_exists($file)) {
            return response('File not found', 404);
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return response('Failed to read file', 500);
        }

        return response($content, 200, [
            'Content-Type' => $mimeType,
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
                return $captcha->check($value);
            }, '滑动验证码验证失败');

            $validate->extend('xfcaptcha', function ($value) {
                $captcha = app('xfCaptcha');
                return $captcha->check($value);
            }, '滑动验证码验证失败');
        });
    }
}
