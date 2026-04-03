<?php

/**
 * zxf/captcha - HTTP 控制器
 *
 * @package     zxf\Captcha\Http
 * @license     MIT
 */

declare(strict_types=1);

namespace zxf\Captcha\Http;

use zxf\Captcha\Captcha;

/**
 * 判断是否在 Laravel 环境中
 */
if (!function_exists('isLaravel')) {
    function isLaravel(): bool
    {
        return class_exists('Illuminate\Http\Response');
    }
}

/**
 * 验证码 HTTP 控制器
 *
 * 处理验证码相关的 HTTP 请求，包括：
 * - 获取验证码图片
 * - 验证滑动结果
 * - 提供静态资源（JS、CSS、图标）
 *
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
     * @return mixed
     */
    public function image(): mixed
    {
        try {
            // 确定图片格式
            $format = $this->getOutputFormat();
            $contentType = 'image/' . $format;

            // 在 Laravel 中使用 makeRaw 获取二进制数据
            if (isLaravel()) {
                $imageData = $this->captcha->makeRaw();
                return response($imageData, 200, [
                    'Content-Type' => $contentType,
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]);
            }

            // 原生 PHP 环境直接输出
            $this->captcha->make();
            return null;
        } catch (\Throwable $e) {
            // 输出错误图片
            return $this->outputErrorImage($e->getMessage());
        }
    }

    /**
     * 获取图片输出格式
     *
     * @return string 图片格式（webp 或 png）
     */
    protected function getOutputFormat(): string
    {
        // 如果强制指定了格式或 WebP 不支持
        if (isset($_GET['nowebp']) || !function_exists('imagewebp')) {
            return 'png';
        }

        // 从配置中获取格式
        $config = $this->getConfig();
        return ($config['output_format'] ?? 'webp') === 'webp' ? 'webp' : 'png';
    }

    /**
     * 验证滑动结果
     *
     * @return mixed
     */
    public function check(): mixed
    {
        try {
            $result = $this->captcha->check();

            $response = [
                'success' => $result,
                'message' => $result ? '验证成功' : '验证失败，请重试',
                'code' => $result ? 200 : 400,
            ];

            // Laravel 环境返回 Response 对象
            if (isLaravel()) {
                return response()->json($response, $result ? 200 : 400);
            }

            $this->jsonResponse($response, $result ? 200 : 400);
            return null;
        } catch (\Throwable $e) {
            $response = [
                'success' => false,
                'message' => '验证出错：' . $e->getMessage(),
                'code' => 500,
            ];

            if (isLaravel()) {
                return response()->json($response, 500);
            }

            $this->jsonResponse($response, 500);
            return null;
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
     * @return mixed
     */
    public function js(): mixed
    {
        return $this->outputStaticFile('js/captcha.js', 'application/javascript');
    }

    /**
     * 获取 CSS 文件
     *
     * @return mixed
     */
    public function css(): mixed
    {
        return $this->outputStaticFile('css/captcha.css', 'text/css');
    }

    /**
     * 获取图标文件
     *
     * @return mixed
     */
    public function icon(): mixed
    {
        return $this->outputStaticFile('images/icon.png', 'image/png');
    }

    /**
     * 输出静态文件
     *
     * @param string $path     相对资源目录的路径
     * @param string $mimeType MIME 类型
     *
     * @return mixed 返回 Response 对象或 void
     */
    protected function outputStaticFile(string $path, string $mimeType): mixed
    {
        $file = $this->resourcePath . '/' . $path;

        if (!file_exists($file)) {
            return $this->sendError('File not found', 404);
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $this->sendError('Failed to read file', 500);
        }

        // Laravel 环境：返回 Response 对象
        if (isLaravel()) {
            return response($content, 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => strlen($content),
                'Cache-Control' => 'public, max-age=86400',
                'Expires' => gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT',
            ]);
        }

        // 原生 PHP 环境：直接输出
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
        return null;
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
     * @return mixed
     */
    protected function outputErrorImage(string $message): mixed
    {
        // 创建简单的错误提示图片
        $width = 240;
        $height = 150;
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            return $this->sendError('Failed to create image', 500);
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

        // 在 Laravel 环境中返回 Response
        if (isLaravel()) {
            ob_start();
            imagepng($image);
            $imageData = ob_get_clean();
            imagedestroy($image);

            return response($imageData, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        // 原生 PHP 环境直接输出
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: image/png');
        }

        imagepng($image);
        imagedestroy($image);
        return null;
    }
}
