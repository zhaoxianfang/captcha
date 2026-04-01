<?php

declare(strict_types=1);

namespace zxf\Captcha\Http;

use zxf\Captcha\Contracts\ResponseInterface;

/**
 * 通用响应适配器
 *
 * 用于非框架环境或通用 PHP 应用
 */
class GenericResponse implements ResponseInterface
{
    /**
     * @inheritDoc
     */
    public function image(string $imageData, string $format): mixed
    {
        $mimeType = $format === 'webp' ? 'image/webp' : 'image/png';

        header('Content-Type: ' . $mimeType);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo $imageData;
        return null;
    }

    /**
     * @inheritDoc
     */
    public function json(array $data, int $statusCode = 200): mixed
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        return null;
    }

    /**
     * @inheritDoc
     */
    public function asset(string $content, string $mimeType, int $maxAge = 86400): mixed
    {
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=' . $maxAge);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');

        echo $content;
        return null;
    }
}
