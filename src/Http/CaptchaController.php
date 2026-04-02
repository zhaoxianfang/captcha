<?php

/**
 * zxf/captcha - HTTP 控制器
 *
 * @package     zxf\Captcha\Http
 * @author      zhaoxianfang <zhaoxianfang@163.com>
 * @license     MIT
 */

declare(strict_types=1);

namespace zxf\Captcha\Http;

use zxf\Captcha\Captcha;

/**
 * 验证码 HTTP 控制器
 *
 * 处理验证码相关的 HTTP 请求，包括：
 * - 获取验证码图片
 * - 验证滑动结果
 * - 提供静态资源（JS、CSS、图标）
 *
 * @author zhaoxianfang
 * @since  2.0.0
 */
class CaptchaController
{
    /**
     * 验证码实例
     */
    protected Captcha $captcha;

    /**
     * 资源根目录
     */
    protected string $resourcePath;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->resourcePath = dirname(__DIR__, 2) . '/resources/assets';
        $this->captcha = $this->createCaptcha();
    }

    /**
     * 创建验证码实例
     *
     * @return Captcha
     */
    protected function createCaptcha(): Captcha
    {
        // 尝试从配置中获取配置信息
        $config = $this->getConfig();

        return new Captcha($config);
    }

    /**
     * 获取配置
     *
     * @return array
     */
    protected function getConfig(): array
    {
        // Laravel
        if (function_exists('config')) {
            return config('xf_captcha', []);
        }

        // ThinkPHP
        if (function_exists('config') && class_exists('think\Container')) {
            return config('xf_captcha', []);
        }

        // 默认配置
        $configFile = dirname(__DIR__, 2) . '/config/xf_captcha.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }

        return [];
    }

    /**
     * 获取验证码图片
     *
     * @return void
     */
    public function image(): void
    {
        try {
            $this->captcha->make();
        } catch (\Throwable $e) {
            // 输出错误图片
            $this->outputErrorImage($e->getMessage());
        }
    }

    /**
     * 验证滑动结果
     *
     * @return void
     */
    public function check(): void
    {
        try {
            $result = $this->captcha->check();

            $response = [
                'success' => $result,
                'message' => $result ? '验证成功' : '验证失败，请重试',
                'code' => $result ? 200 : 400,
            ];

            $this->jsonResponse($response, $result ? 200 : 400);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '验证出错：' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 发送 JSON 响应
     *
     * @param array $data   响应数据
     * @param int   $status HTTP 状态码
     *
     * @return void
     */
    protected function jsonResponse(array $data, int $status = 200): void
    {
        // 清理输出缓冲区
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 设置响应头
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取 JS 文件
     *
     * @return void
     */
    public function js(): void
    {
        $this->outputStaticFile('js/captcha.js', 'application/javascript');
    }

    /**
     * 获取 CSS 文件
     *
     * @return void
     */
    public function css(): void
    {
        $this->outputStaticFile('css/captcha.css', 'text/css');
    }

    /**
     * 获取图标文件
     *
     * @return void
     */
    public function icon(): void
    {
        $this->outputStaticFile('images/icon.png', 'image/png');
    }

    /**
     * 输出静态文件
     *
     * @param string $path     相对资源目录的路径
     * @param string $mimeType MIME 类型
     *
     * @return void
     */
    protected function outputStaticFile(string $path, string $mimeType): void
    {
        $file = $this->resourcePath . '/' . $path;

        if (!file_exists($file)) {
            $this->sendError('File not found', 404);
            return;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            $this->sendError('Failed to read file', 500);
            return;
        }

        // 清理输出缓冲区
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 设置响应头
        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . strlen($content));
            header('Cache-Control: public, max-age=86400');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
        }

        echo $content;
    }

    /**
     * 发送错误响应
     *
     * @param string $message 错误消息
     * @param int    $code    HTTP 状态码
     *
     * @return void
     */
    protected function sendError(string $message, int $code = 500): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($code);
        }

        echo $message;
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
        // 清理输出缓冲区
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 创建简单的错误提示图片
        $width = 240;
        $height = 150;
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            return;
        }

        // 背景色（浅红色）
        $bgColor = imagecolorallocate($image, 255, 235, 238);
        imagefill($image, 0, 0, $bgColor);

        // 文字颜色（深红色）
        $textColor = imagecolorallocate($image, 198, 40, 40);

        // 绘制错误信息
        $lines = str_split($message, 30);
        $y = 60;
        foreach ($lines as $line) {
            imagestring($image, 3, 10, $y, $line, $textColor);
            $y += 16;
        }

        if (!headers_sent()) {
            header('Content-Type: image/png');
        }

        imagepng($image);
        imagedestroy($image);
    }
}
