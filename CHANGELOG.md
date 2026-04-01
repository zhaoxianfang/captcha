# 更新日志

## [2.0.0] - 2026-04-01

### 新增
- 完全重写为 PHP 8.2+ 现代 PHP 扩展包
- 新增命名空间 `zxf\Captcha`
- 新增存储接口 `StorageInterface`，支持 Session、Array、自定义存储
- 新增多框架适配器：
  - Laravel（服务提供者、门面、控制器）
  - ThinkPHP（服务、控制器）
  - Yii（模块、控制器）
- 新增资源路由自动提供 CSS/JS/图片
- 新增 Cookie + Session 双重验证机制
- 新增频率限制功能（IP 级别）
- 新增详细的错误码和异常处理
- 新增 `CaptchaHelper` 辅助类
- 新增 WebP 格式自动检测和浏览器兼容性回退
- 新增前端 `zxfCaptcha.getOffset()` 方法
- 新增请求频率限制存储适配器
- 新增完善的单元测试

### 改进
- 完全类型化的代码（PHP 8.2+）
- 优化的性能和内存使用
- 更好的错误处理和调试信息
- 统一的配置系统
- 统一的 API 接口
- 前端 JS 代码重构和优化
- 添加 Cookie 工具类

### 安全性增强
- 添加 Cookie HttpOnly 支持
- 添加 SameSite Cookie 属性
- 添加 IP 频率限制
- 添加验证码有效期控制
- 添加错误次数限制
- 使用更安全的随机数生成器（`random_bytes`）

### 文件结构变更
```
zxf/captcha
├── composer.json          # 包定义
├── LICENSE                # MIT 许可证
├── README.md              # 完整文档
├── CHANGELOG.md           # 更新日志
├── phpunit.xml            # 测试配置
├── .gitignore             # Git 忽略文件
├── .gitattributes         # Git 属性
├── config/
│   └── captcha.php        # 配置文件
├── resources/
│   └── assets/            # 静态资源
│       ├── css/captcha.css
│       ├── js/captcha.js
│       ├── img/           # 滑块图片
│       └── bg/            # 背景图片
├── src/
│   ├── Captcha.php        # 核心验证码类
│   ├── CaptchaHelper.php  # 辅助类
│   ├── Contracts/         # 接口定义
│   ├── Storage/           # 存储实现
│   ├── Http/              # HTTP 控制器
│   ├── Adapters/          # 框架适配器
│   │   ├── Laravel/
│   │   ├── ThinkPHP/
│   │   └── Yii/
│   └── Exceptions/
├── examples/              # 使用示例
│   ├── laravel_example.php
│   ├── thinkphp_example.php
│   ├── yii_example.php
│   ├── native_example.php
│   ├── redis_storage_example.php
│   └── advanced_usage.php
└── tests/                 # 单元测试
    ├── CaptchaTest.php
    └── StorageTest.php
```

### 变更
- 包名保持 `zxf/captcha`
- 核心类重命名为 `Captcha`
- 配置结构重新设计，更加灵活
- 前端全局变量改为 `zxfCaptcha`（保持 `$TN` 兼容）
- 删除旧版本所有文件（TnCode.php、check.php、get_img.php 等）

### 废弃
- 移除对 PHP < 8.2 的支持
- 移除旧的非命名空间代码

---

## [1.2.0] - 之前版本

- 原始版本实现
- 基础滑动验证码功能
- 支持原生 PHP
- 简单的 Session 存储
