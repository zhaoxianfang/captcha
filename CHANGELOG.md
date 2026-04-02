# 更新日志

## v2.0.0 - 2024-04-02

### 重大改进

- **命名空间更新**: 使用 `zxf\Captcha` 命名空间，包名 `zxf/captcha`
- **配置文件更名**: 配置文件改为 `xf_captcha.php`，避免与其他验证码包冲突
- **验证器名称**: Laravel 验证器规则改为 `xfCaptcha`
- **前端对象名**: JavaScript 调用对象改为 `xfCaptcha`

### 修复的问题

1. **路径问题修复**
   - 修复了 `vendor:publish` 无法找到资源的问题
   - 配置文件路径现在自动处理，如果配置的路径不存在会自动使用包内默认路径
   - 改进了路径计算方式，使用 `dirname(__DIR__, 2)` 代替相对路径拼接

2. **代码健壮性增强**
   - 添加了空值检查和默认值处理
   - 改进了 JSON 响应处理，支持多种成功标记格式
   - 添加了网络错误和超时处理
   - 添加了输出缓冲清理，防止图片输出乱码

3. **Laravel 集成优化**
   - 修复了路由定义方式，使用完整类名引用
   - 改进了服务提供者的路径检测逻辑
   - 添加了文件存在性检查

4. **配置优化**
   - 配置文件现在使用空字符串作为默认路径
   - 运行时自动检测并修复无效路径
   - 所有配置项都添加了详细的中文注释

### 新特性

- **框架支持**: 支持 Laravel、ThinkPHP 和原生 PHP
- **辅助函数**: 提供 `xf_captcha()`、`xf_captcha_check()` 等便捷函数
- **资源路由**: 内置 CSS/JS/图片资源路由
- **自适应路径**: 自动处理开发和生产环境的路径差异

### 使用方法

1. 安装包：
   ```bash
   composer require zxf/captcha
   ```

2. 发布配置（Laravel）：
   ```bash
   php artisan vendor:publish --tag=xf-captcha-config
   ```

3. 如果发布失败，请尝试：
   ```bash
   php artisan cache:clear
   php artisan config:clear
   composer dump-autoload
   php artisan vendor:publish --tag=xf-captcha-config
   ```

### 升级指南

从旧版本升级时，需要注意：

1. 修改 `composer.json` 中的包名
2. 更新命名空间引用
3. 将配置文件 `captcha.php` 重命名为 `xf_captcha.php`
4. 修改验证规则从 `captcha` 改为 `xfCaptcha`
5. 修改前端代码从 `$TN` 改为 `xfCaptcha`
