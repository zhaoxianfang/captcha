<?php

/**
 * Yii 框架使用示例
 */

// ========================================
// 1. 配置文件 (config/web.php)
// ========================================

/*
return [
    'modules' => [
        'captcha' => [
            'class' => 'zxf\Captcha\Adapters\Yii\CaptchaModule',
            'config' => [
                'fault_tolerance' => 3,
                'max_error_count' => 10,
                'security' => [
                    'frequency_limit_enabled' => true,
                    'min_request_interval' => 1,
                ],
            ],
        ],
    ],
    // ... 其他配置
];
*/

// ========================================
// 2. 控制器中使用
// ========================================

namespace app\controllers;

use Yii;
use yii\web\Controller;
use zxf\Captcha\Captcha;

class SiteController extends Controller
{
    /**
     * 登录页面
     */
    public function actionLogin()
    {
        return $this->render('login');
    }

    /**
     * 登录处理
     */
    public function actionDoLogin()
    {
        $request = Yii::$app->request;
        
        // 获取验证码偏移量
        $offset = $request->post('tn_r');
        
        // 获取验证码实例
        $captcha = new Captcha(Yii::$app->getModule('captcha')->config);
        
        // 验证
        $result = $captcha->verify($offset);
        
        if (!$result['success']) {
            Yii::$app->session->setFlash('error', $result['message']);
            return $this->redirect(['login']);
        }
        
        // 继续处理登录...
        $username = $request->post('username');
        $password = $request->post('password');
        
        // ...
        
        return $this->redirect(['index']);
    }
}

// ========================================
// 3. 视图文件 (views/site/login.php)
// ========================================

/*
<?php
use yii\helpers\Html;
use yii\helpers\Url;
?>

<div class="login-box">
    <h2>登录</h2>
    
    <?php if (Yii::$app->session->hasFlash('error')): ?>
        <div class="alert alert-danger">
            <?= Yii::$app->session->getFlash('error') ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="<?= Url::to(['do-login']) ?>">
        <input type="hidden" name="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
        
        <div class="form-group">
            <label>用户名</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label>验证码</label>
            <div class="tncode"></div>
            <input type="hidden" name="tn_r" id="tn_r">
        </div>
        
        <button type="submit" class="btn btn-primary">登录</button>
    </form>
</div>

<?php
$this->registerCssFile(Url::to(['/captcha/captcha/css']));
$this->registerJsFile(Url::to(['/captcha/captcha/js']), ['depends' => ['yii\web\JqueryAsset']]);
?>

<script>
$(function() {
    zxfCaptcha.init({
        handleDom: '.tncode',
        getImgUrl: '<?= Url::to(['/captcha/captcha/image']) ?>',
        checkUrl: '<?= Url::to(['/captcha/captcha/verify']) ?>'
    }).onSuccess(function() {
        $('#tn_r').val(zxfCaptcha.getOffset());
    });
});
</script>
*/
