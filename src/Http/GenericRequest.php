<?php

declare(strict_types=1);

namespace zxf\Captcha\Http;

use zxf\Captcha\Contracts\RequestInterface;

/**
 * 通用请求适配器
 *
 * 用于非框架环境或通用 PHP 应用
 */
class GenericRequest implements RequestInterface
{
    /**
     * 请求数据
     */
    private array $data;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->data = array_merge($_GET, $_POST);
    }

    /**
     * @inheritDoc
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function getClientIp(): string
    {
        $ipHeaders = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * @inheritDoc
     */
    public function isAjax(): bool
    {
        $header = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strtolower($header) === 'xmlhttprequest';
    }

    /**
     * @inheritDoc
     */
    public function header(string $key, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$key] ?? $default;
    }
}
