<?php

/**
 * zxf/captcha - 通用框架适配器
 *
 * @package     zxf\Captcha\Generic
 * @author      zhaoxianfang <zhaoxianfang@163.com>
 * @license     MIT
 */

declare(strict_types=1);

namespace zxf\Captcha\Generic;

use zxf\Captcha\Captcha;

/**
 * 通用框架适配器
 *
 * 用于在不支持服务提供者的框架或原生 PHP 中使用验证码
 *
 * @author zhaoxianfang
 * @since  2.0.0
 */
class Adapter
{
    /**
     * 验证码配置
     */
    protected array $config;

    /**
     * 路由前缀
     */
    protected string $routePrefix;

    /**
     * 构造函数
     *
     * @param array $config 配置数组
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = require dirname(__DIR__, 2) . '/config/xf_captcha.php';
        $this->config = array_merge($defaultConfig, $config);
        $this->routePrefix = $this->config['route_prefix'] ?? 'captcha';
    }

    /**
     * 处理请求
     *
     * 根据当前请求路径处理验证码相关请求
     *
     * @return void
     */
    public function handle(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH) ?? '';
        $path = trim($path, '/');
        $prefix = trim($this->routePrefix, '/');

        // 检查是否是验证码相关请求
        if (strpos($path, $prefix) !== 0) {
            return;
        }

        $action = substr($path, strlen($prefix) + 1);

        switch ($action) {
            case 'image':
                $this->image();
                break;
            case 'check':
                $this->check();
                break;
            case 'js':
                $this->js();
                break;
            case 'css':
                $this->css();
                break;
            case 'icon':
                $this->icon();
                break;
        }
    }

    /**
     * 获取验证码图片
     *
     * @return void
     */
    public function image(): void
    {
        try {
            $captcha = new Captcha($this->config);
            $captcha->make();
        } catch (\Throwable $e) {
            $this->outputErrorImage($e->getMessage());
        }
        exit;
    }

    /**
     * 验证滑动结果
     *
     * @return void
     */
    public function check(): void
    {
        header('Content-Type: application/json');

        try {
            $captcha = new Captcha($this->config);
            $result = $captcha->check();

            echo json_encode([
                'success' => $result,
                'message' => $result ? '验证成功' : '验证失败，请重试',
                'code' => $result ? 200 : 400,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => '验证出错：' . $e->getMessage(),
                'code' => 500,
            ]);
        }
        exit;
    }

    /**
     * 输出 JS 文件
     *
     * @return void
     */
    public function js(): void
    {
        $this->outputStaticFile(
            dirname(__DIR__, 2) . '/resources/assets/js/captcha.js',
            'application/javascript'
        );
        exit;
    }

    /**
     * 输出 CSS 文件
     *
     * @return void
     */
    public function css(): void
    {
        $this->outputStaticFile(
            dirname(__DIR__, 2) . '/resources/assets/css/captcha.css',
            'text/css'
        );
        exit;
    }

    /**
     * 输出图标文件
     *
     * @return void
     */
    public function icon(): void
    {
        $this->outputStaticFile(
            dirname(__DIR__, 2) . '/resources/assets/images/icon.png',
            'image/png'
        );
        exit;
    }

    /**
     * 输出静态文件
     *
     * @param string $file     文件路径
     * @param string $mimeType MIME 类型
     *
     * @return void
     */
    protected function outputStaticFile(string $file, string $mimeType): void
    {
        if (!file_exists($file)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            http_response_code(500);
            echo 'Failed to read file';
            return;
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: public, max-age=86400');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

        echo $content;
    }

    /**
     * 输出错误图片
     *
     * @param string $message 错误信息
     *
     * @return void
     */
    protected function outputErrorImage(string $message): void
    {
        $width = 240;
        $height = 150;
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            return;
        }

        // 背景色
        $bgColor = imagecolorallocate($image, 255, 235, 238);
        imagefill($image, 0, 0, $bgColor);

        // 文字颜色
        $textColor = imagecolorallocate($image, 198, 40, 40);

        // 绘制错误信息
        imagestring($image, 5, 10, 70, 'Error:', $textColor);
        imagestring($image, 3, 10, 90, substr($message, 0, 30), $textColor);

        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
    }

    /**
     * 获取验证码 HTML
     *
     * @param string $selector CSS 选择器
     * @param array  $options  额外选项
     *
     * @return string
     */
    public function render(string $selector = '.xf-captcha', array $options = []): string
    {
        $frontend = array_merge(
            $this->config['frontend'] ?? [],
            $options,
            ['handleDom' => $selector]
        );

        $jsConfig = json_encode([
            'handleDom' => $selector,
            'getImgUrl' => '/' . $this->routePrefix . '/image',
            'checkUrl' => '/' . $this->routePrefix . '/check',
            'placeholder' => $frontend['placeholder'] ?? '点击按钮进行验证',
            'slideText' => $frontend['slide_text'] ?? '拖动左边滑块完成上方拼图',
            'successText' => $frontend['success_text'] ?? '✓ 验证成功',
            'failText' => $frontend['fail_text'] ?? '验证失败，请重试',
            'showClose' => $frontend['show_close'] ?? true,
            'showRefresh' => $frontend['show_refresh'] ?? true,
            'showRipple' => $frontend['show_ripple'] ?? true,
        ]);

        return <<<HTML
<div class="xf-captcha"></div>
<link rel="stylesheet" href="/{$this->routePrefix}/css">
<script src="/{$this->routePrefix}/js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        xfCaptcha.init({$jsConfig});
    });
</script>
HTML;
    }
}
