<?php

declare(strict_types=1);

namespace zxf\Captcha\Adapters\Yii;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use zxf\Captcha\Captcha;
use zxf\Captcha\Exceptions\CaptchaException;

/**
 * Yii 验证码控制器
 * 
 * @package zxf\Captcha\Adapters\Yii
 */
class CaptchaController extends Controller
{
    /**
     * 验证码实例
     */
    private ?Captcha $captcha = null;

    /**
     * 获取验证码实例
     *
     * @return Captcha
     */
    protected function getCaptcha(): Captcha
    {
        if ($this->captcha === null) {
            $config = $this->module->config ?? [];
            $this->captcha = new Captcha($config);
        }
        return $this->captcha;
    }

    /**
     * 关闭 CSRF 验证
     */
    public $enableCsrfValidation = false;

    /**
     * 获取验证码图片
     *
     * @return Response
     */
    public function actionImage(): Response
    {
        try {
            if ($this->isRateLimited()) {
                Yii::$app->response->statusCode = 429;
                return $this->asJson([
                    'success' => false,
                    'message' => '请求过于频繁，请稍后再试',
                    'code' => 'rate_limited',
                ]);
            }

            $result = $this->getCaptcha()->generate();

            // 设置 Cookie
            $this->getCaptcha()->setKeyToCookie($result['key']);

            $this->recordRequest();

            $mimeType = $result['format'] === 'webp' ? 'image/webp' : 'image/png';

            Yii::$app->response->format = Response::FORMAT_RAW;
            Yii::$app->response->headers->set('Content-Type', $mimeType);
            Yii::$app->response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');

            return Yii::$app->response->content = $result['image'];
        } catch (CaptchaException $e) {
            Yii::$app->response->statusCode = 500;
            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
        }
    }

    /**
     * 验证验证码
     *
     * @return Response
     */
    public function actionVerify(): Response
    {
        $request = Yii::$app->request;
        $offset = $request->get('tn_r') ?? $request->post('tn_r');

        if ($offset === null) {
            Yii::$app->response->statusCode = 400;
            return $this->asJson([
                'success' => false,
                'message' => '缺少验证参数',
                'code' => 'missing_parameter',
            ]);
        }

        $result = $this->getCaptcha()->verify($offset);

        // 验证成功后清除 Cookie
        if ($result['success']) {
            $this->getCaptcha()->clearCookie();
        }

        if (!$result['success']) {
            Yii::$app->response->statusCode = 400;
        }

        return $this->asJson($result);
    }

    /**
     * 输出 CSS
     *
     * @return Response
     */
    public function actionCss(): Response
    {
        $content = $this->getAssetContent('css/captcha.css');

        if ($content === null) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson(['message' => 'Not Found']);
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/css');
        Yii::$app->response->headers->set('Cache-Control', 'public, max-age=86400');

        return Yii::$app->response->content = $content;
    }

    /**
     * 输出 JS
     *
     * @return Response
     */
    public function actionJs(): Response
    {
        $content = $this->getAssetContent('js/captcha.js');

        if ($content === null) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson(['message' => 'Not Found']);
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'application/javascript');
        Yii::$app->response->headers->set('Cache-Control', 'public, max-age=86400');

        return Yii::$app->response->content = $content;
    }

    /**
     * 输出图片资源
     *
     * @param string $filename
     * @return Response
     */
    public function actionImg(string $filename): Response
    {
        $allowedFiles = ['icon.png', 'mark.png', 'mark2.png'];

        if (!in_array($filename, $allowedFiles, true)) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson(['message' => 'Not Found']);
        }

        $content = $this->getAssetContent('img/' . $filename);

        if ($content === null) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson(['message' => 'Not Found']);
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'image/png');
        Yii::$app->response->headers->set('Cache-Control', 'public, max-age=86400');

        return Yii::$app->response->content = $content;
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
        $config = $this->module->config ?? [];
        $security = $config['security'] ?? [];

        if (!($security['frequency_limit_enabled'] ?? true)) {
            return false;
        }

        $ip = Yii::$app->request->userIP;
        $key = 'captcha_rate_limit:' . md5($ip);
        $lastRequest = Yii::$app->session->get($key, 0);
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
        $ip = Yii::$app->request->userIP;
        $key = 'captcha_rate_limit:' . md5($ip);
        Yii::$app->session->set($key, time());
    }
}
