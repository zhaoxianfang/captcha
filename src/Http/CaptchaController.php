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
 * - 获取验证码图片/数据
 * - 验证滑动/点击结果
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

        // 默认配置
        $configFile = dirname(__DIR__, 2) . '/config/xf_captcha.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }

        return [];
    }

    /**
     * 获取验证码数据（支持滑动和点击验证码）
     *
     * @return mixed
     */
    public function data(): mixed
    {
        try {
            // 检测是否为刷新操作（通过参数判断）
            $isRefresh = isset($_GET['refresh']) || isset($_GET['_s']);
            $result = $this->captcha->makeData([], $isRefresh);

            $response = [
                'success' => true,
                'code' => 200,
                'type' => $result['type'],
                'image_base64' => $result['image_base64'],
                'hint' => $result['hint'],
                'bg_width' => $result['bg_width'],
                'bg_height' => $result['bg_height'],
            ];

            // 滑动验证码特有字段
            if ($result['type'] === Captcha::TYPE_SLIDE) {
                $response['mark_width'] = $result['mark_width'];
                $response['mark_height'] = $result['mark_height'];
            } else {
                $response['char_count'] = $result['char_count'];
            }

            if (isLaravel()) {
                return response()->json($response);
            }

            $this->jsonResponse($response);
            return null;
        } catch (\Throwable $e) {
            $response = [
                'success' => false,
                'message' => '生成验证码失败：' . $e->getMessage(),
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
     * 获取验证码图片（向后兼容）
     *
     * @return mixed
     */
    public function image(): mixed
    {
        try {
            $format = $this->getOutputFormat();
            $contentType = 'image/' . $format;

            if (isLaravel()) {
                $imageData = $this->captcha->makeRaw();
                return response($imageData, 200, [
                    'Content-Type' => $contentType,
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]);
            }

            $this->captcha->make();
            return null;
        } catch (\Throwable $e) {
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
        if (isset($_GET['nowebp']) || !function_exists('imagewebp')) {
            return 'png';
        }

        $config = $this->getConfig();
        return ($config['output_format'] ?? 'webp') === 'webp' ? 'webp' : 'png';
    }

    /**
     * 验证验证码结果
     *
     * @return mixed
     */
    public function check(): mixed
    {
        try {
            $offset = request('captcha_r') ?? $_REQUEST['captcha_r'] ?? null;
            $token = request('xf_captcha_token') ?? $_REQUEST['xf_captcha_token'] ?? null;
            $clickPoints = request('click_points') ?? $_REQUEST['click_points'] ?? [];

            // 解析点击坐标
            if (is_string($clickPoints)) {
                $clickPoints = json_decode($clickPoints, true) ?: [];
            }

            $result = $this->captcha->verify($offset, $token, $clickPoints);

            $response = [
                'success' => $result['success'],
                'message' => $result['message'],
                'code' => $result['success'] ? 200 : 400,
            ];

            if (!empty($result['token'])) {
                $response['token'] = $result['token'];
            }

            if (isLaravel()) {
                return response()->json($response, $result['success'] ? 200 : 400);
            }

            $this->jsonResponse($response, $result['success'] ? 200 : 400);
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
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

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

        if (isLaravel()) {
            return response($content, 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => strlen($content),
                'Cache-Control' => 'public, max-age=86400',
                'Expires' => gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT',
            ]);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

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
        $width = 240;
        $height = 150;
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            return $this->sendError('Failed to create image', 500);
        }

        $bgColor = imagecolorallocate($image, 255, 235, 238);
        imagefill($image, 0, 0, $bgColor);

        $textColor = imagecolorallocate($image, 198, 40, 40);

        $lines = str_split($message, 30);
        $y = 60;
        foreach ($lines as $line) {
            imagestring($image, 3, 10, $y, $line, $textColor);
            $y += 16;
        }

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
