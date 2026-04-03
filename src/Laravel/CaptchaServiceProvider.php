<?php

/**
 * zxf/captcha - Laravel 服务提供者
 *
 * @package     zxf\Captcha\Laravel
 * @license     MIT
 */

declare(strict_types=1);

namespace zxf\Captcha\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use zxf\Captcha\Captcha;

/**
 * Laravel 验证码服务提供者
 *
 * 该类为 Laravel 11+ 框架提供验证码服务的注册、配置加载和验证器扩展
 *
 * @since  1.0.0
 */
class CaptchaServiceProvider extends ServiceProvider
{
    /**
     * 启动服务
     *
     * 注册路由、验证器扩展等
     *
     * @return void
     */
    public function boot(): void
    {
        $packageRoot = dirname(__DIR__, 2);

        // 发布配置文件
        $configSource = $packageRoot . '/config/xf_captcha.php';
        if (file_exists($configSource)) {
            $this->publishes([$configSource => config_path('xf_captcha.php')], 'xf-captcha-config');
            $this->mergeConfigFrom($configSource, 'xf_captcha');
        }

        // 发布资源文件
        $assetsSource = $packageRoot . '/resources/assets';
        if (is_dir($assetsSource)) {
            $this->publishes([$assetsSource => public_path('vendor/xf-captcha')], 'xf-captcha-assets');
        }

        // 发布视图文件
        $viewsSource = $packageRoot . '/resources/views';
        if (is_dir($viewsSource)) {
            $this->publishes([$viewsSource => resource_path('views/vendor/xf-captcha')], 'xf-captcha-views');
            $this->loadViewsFrom($viewsSource, 'xf-captcha');
        }

        // 注册路由
        $this->registerRoutes();

        // 注册验证器扩展
        $this->registerValidator();
    }

    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('xfCaptcha', function ($app) {
            $config = $app['config']->get('xf_captcha', []);
            return new Captcha($config);
        });

        $this->app->alias('xfCaptcha', Captcha::class);
    }

    /**
     * 注册验证码路由
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $config = $this->app['config']->get('xf_captcha', []);
        $prefix = $config['route_prefix'] ?? 'xf_captcha';

        Route::prefix($prefix)->group(function () {
            // 验证码图片
            Route::get('/image', function (): Response {
                return $this->outputCaptchaImage();
            })->name('xf-captcha.image');

            // 验证接口
            Route::match(['get', 'post'], '/check', function (): JsonResponse {
                return $this->checkCaptcha();
            })->name('xf-captcha.check');

            // JS 文件 - 使用正确的 MIME 类型
            Route::get('/js', function (): Response {
                return $this->outputAsset('js/captcha.js', 'application/javascript');
            })->name('xf-captcha.js');

            // CSS 文件
            Route::get('/css', function (): Response {
                return $this->outputAsset('css/captcha.css', 'text/css');
            })->name('xf-captcha.css');

            // 图标文件
            Route::get('/icon', function (): Response {
                return $this->outputAsset('images/icon.png', 'image/png');
            })->name('xf-captcha.icon');
        });
    }

    /**
     * 输出验证码图片
     */
    protected function outputCaptchaImage(): Response
    {
        try {
            $captcha = app('xfCaptcha');
            $imageData = $captcha->makeRaw();

            $config = config('xf_captcha', []);
            $format = ($config['output_format'] ?? 'webp') === 'webp' && function_exists('imagewebp')
                ? 'webp'
                : 'png';

            return response($imageData, 200, [
                'Content-Type' => 'image/' . $format,
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (\Throwable $e) {
            return $this->outputErrorImage($e->getMessage());
        }
    }

    /**
     * 验证验证码
     */
    protected function checkCaptcha(): JsonResponse
    {
        try {
            $captcha = app('xfCaptcha');

            // 获取请求参数
            $offset = request('captcha_r');
            $token = request('xf_captcha_token');

            // 执行验证
            $result = $captcha->verify($offset, $token);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'code' => $result['success'] ? 200 : 400,
                'token' => $result['token'],
            ], $result['success'] ? 200 : 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '验证出错：' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取包根目录
     */
    protected function getPackageRoot(): string
    {
        $possiblePaths = [
            dirname(__DIR__, 2),
            dirname(__DIR__, 1) . '/../..',
            base_path('vendor/zxf/captcha'),
            realpath(__DIR__ . '/../../..'),
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
     */
    protected function outputAsset(string $path, string $mimeType): Response
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
     * 输出错误图片
     *
     * @param string $message 错误信息
     */
    protected function outputErrorImage(string $message): Response
    {
        $width = 240;
        $height = 150;
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            return response('Failed to create image', 500);
        }

        $bgColor = imagecolorallocate($image, 255, 235, 238);
        imagefill($image, 0, 0, $bgColor);

        $textColor = imagecolorallocate($image, 198, 40, 40);
        imagestring($image, 5, 10, 70, 'Error:', $textColor);
        imagestring($image, 3, 10, 90, substr($message, 0, 30), $textColor);

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return response($imageData, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * 注册验证器扩展
     *
     * @return void
     */
    protected function registerValidator(): void
    {
        Validator::extend('xfCaptcha', function ($attribute, $value, $parameters, $validator) {
            $captcha = app('xfCaptcha');
            $result = $captcha->verify(null, $value);
            return $result['success'];
        }, '滑动验证码验证失败');

        Validator::extend('xfcaptcha', function ($attribute, $value, $parameters, $validator) {
            $captcha = app('xfCaptcha');
            $result = $captcha->verify(null, $value);
            return $result['success'];
        }, '滑动验证码验证失败');
    }

    /**
     * 获取提供者所提供的服务
     *
     * @return array
     */
    public function provides(): array
    {
        return ['xfCaptcha', Captcha::class];
    }
}
