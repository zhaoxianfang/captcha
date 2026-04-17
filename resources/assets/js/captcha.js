/**
 * xfCaptcha - 高性能滑动验证码 JavaScript 库
 *
 * @package     zxf/captcha
 * @license     MIT
 * @version     1.0.0
 */
(function (window, document) {
    "use strict";

    /**
     * 工具函数：检查元素是否包含指定类名
     *
     * @param {Element} elem 目标元素
     * @param {string}  cls  类名
     * @returns {boolean}
     */
    function hasClass(elem, cls) {
        if (!elem || !cls) return false;
        cls = cls.trim();
        if (cls.length === 0) return false;
        return new RegExp("\\b" + cls + "\\b").test(elem.className);
    }

    /**
     * 工具函数：为元素添加类名
     *
     * @param {Element} elem  目标元素
     * @param {string}  cName 类名
     */
    function addClass(elem, cName) {
        if (!elem || !cName) return;
        if (!hasClass(elem, cName)) {
            elem.className += " " + cName;
        }
    }

    /**
     * 工具函数：移除元素的类名
     *
     * @param {Element} elem  目标元素
     * @param {string}  cName 类名
     */
    function removeClass(elem, cName) {
        if (!elem || !cName) return;
        if (hasClass(elem, cName)) {
            elem.className = elem.className.replace(
                new RegExp("(\\s|^)" + cName + "(\\s|$)"),
                " "
            ).trim();
        }
    }

    /**
     * 工具函数：解析 HTML 字符串并添加到目标元素
     *
     * @param {Element} parent 父元素
     * @param {string}  html   HTML 字符串
     */
    function appendHTML(parent, html) {
        const div = document.createElement("div");
        const fragment = document.createDocumentFragment();
        div.innerHTML = html;
        while (div.firstChild) {
            fragment.appendChild(div.firstChild);
        }
        parent.appendChild(fragment);
    }

    /**
     * AJAX 请求类
     */
    class AjaxRequest {
        /**
         * 创建 XMLHttpRequest 对象
         *
         * @returns {XMLHttpRequest|null}
         */
        createXHR() {
            const methods = [
                () => new XMLHttpRequest(),
                () => new ActiveXObject("Msxml2.XMLHTTP"),
                () => new ActiveXObject("Microsoft.XMLHTTP"),
            ];

            for (const method of methods) {
                try {
                    return method();
                } catch (e) {
                    continue;
                }
            }
            return null;
        }

        /**
         * 发送请求
         *
         * @param {string}   method   请求方法
         * @param {string}   url      请求地址
         * @param {object}   callbacks 回调函数 {success, failure}
         * @param {object}   data     请求数据
         */
        request(method, url, callbacks, data) {
            const xhr = this.createXHR();
            if (!xhr) {
                console.error("xfCaptcha: 无法创建 XMLHttpRequest 对象");
                if (callbacks.failure) {
                    callbacks.failure({ responseText: "无法创建 XMLHttpRequest 对象" }, 0);
                }
                return;
            }

            xhr.onreadystatechange = () => {
                if (xhr.readyState !== 4) return;

                if (xhr.status >= 200 && xhr.status < 300) {
                    if (callbacks.success) {
                        callbacks.success(xhr.responseText);
                    }
                } else {
                    if (callbacks.failure) {
                        callbacks.failure(xhr, xhr.status);
                    }
                }
            };

            xhr.onerror = () => {
                if (callbacks.failure) {
                    callbacks.failure(xhr, 0);
                }
            };

            xhr.ontimeout = () => {
                if (callbacks.failure) {
                    callbacks.failure(xhr, -1);
                }
            };

            // 处理 GET 请求参数
            if (method.toUpperCase() === "GET" && data) {
                const separator = url.indexOf("?") > -1 ? "&" : "?";
                const params = new URLSearchParams(data).toString();
                url += separator + params;
                data = null;
            }

            xhr.open(method, url, true);
            xhr.setRequestHeader("Accept", "application/json, text/plain, */*");

            if (method.toUpperCase() === "POST" && data) {
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.send(new URLSearchParams(data).toString());
            } else {
                xhr.send();
            }
        }
    }

    /**
     * 验证码主对象
     */
    const xfCaptcha = {
        // DOM 元素
        _container: null,
        _modal: null,
        _modalBg: null,
        _img: null,

        // 状态标记
        _imgLoaded: false,
        _isDrawBg: false,
        _isMoving: false,
        _doing: false,
        _result: false,
        _errorCount: 0,
        _token: null,

        // 位置信息
        _blockStartX: 0,
        _blockStartY: 0,
        _markOffset: 0,
        _rafId: null,
        _successHideTimer: null,

        // 尺寸配置
        _markWidth: 50,
        _markHeight: 50,
        _imgWidth: 240,
        _imgHeight: 150,

        // 回调函数
        _onSuccess: null,
        _onFail: null,
        _onClose: null,

        // 配置选项
        _options: {},

        // 默认配置
        _defaults: {
            handleDom: ".xf-captcha",
            getImgUrl: "/xf_captcha/image",
            checkUrl: "/xf_captcha/check",
            placeholder: "点击按钮进行验证",
            slideText: "拖动左边滑块完成上方拼图",
            successText: "✓ 验证成功",
            failText: "验证失败，请重试",
            showClose: true,
            showRefresh: true,
            showRipple: true,
            theme: "auto",
            inputName: "xf_captcha_token",
            autoInsertInput: true,
        },

        /**
         * 绑定事件
         *
         * @param {Element} elem    目标元素
         * @param {string}  evType  事件类型
         * @param {function} fn      处理函数
         * @param {object}  options  事件选项
         */
        _bind(elem, evType, fn, options) {
            if (!elem) return;

            // 确定事件选项
            const defaultOptions = { passive: true };
            const eventOptions = options || defaultOptions;

            if (elem.addEventListener) {
                elem.addEventListener(evType, fn, eventOptions);
            } else if (elem.attachEvent) {
                elem.attachEvent("on" + evType, fn);
            }
        },

        /**
         * 滑块开始拖动
         *
         * @param {Event} e 事件对象
         */
        _blockStartMove(e) {
            if (xfCaptcha._doing || !xfCaptcha._imgLoaded) return;

            if (e.cancelable) e.preventDefault();
            const evt = e.touches ? e.touches[0] : e;

            // 隐藏提示文字
            const textElem = document.querySelector(".captcha_slide_text");
            if (textElem) textElem.style.display = "none";

            xfCaptcha._drawBg();
            xfCaptcha._blockStartX = evt.clientX;
            xfCaptcha._blockStartY = evt.clientY;
            xfCaptcha._doing = true;
            xfCaptcha._isMoving = true;

            // 添加拖动状态样式
            const block = document.querySelector(".captcha_slide_block");
            if (block) addClass(block, "dragging");
        },

        /**
         * 滑块拖动中
         *
         * @param {Event} e 事件对象
         */
        _blockOnMove(e) {
            if (!xfCaptcha._doing || !xfCaptcha._isMoving) return;

            if (e.cancelable) e.preventDefault();
            const evt = e.touches ? e.touches[0] : e;

            let offset = evt.clientX - xfCaptcha._blockStartX;
            const maxOffset = xfCaptcha._imgWidth - xfCaptcha._markWidth;

            // 限制滑动范围
            offset = Math.max(0, Math.min(offset, maxOffset));

            // 如果位置没有变化，不更新
            if (offset === xfCaptcha._markOffset) return;

            // 使用 requestAnimationFrame 优化性能
            if (xfCaptcha._rafId) {
                cancelAnimationFrame(xfCaptcha._rafId);
            }

            xfCaptcha._rafId = requestAnimationFrame(() => {
                // 更新滑块位置
                const block = document.querySelector(".captcha_slide_block");
                if (block) {
                    block.style.transform = "translateX(" + offset + "px)";
                }

                // 计算比例位置
                xfCaptcha._markOffset = offset;
                xfCaptcha._drawMark();

                xfCaptcha._rafId = null;
            });
        },

        /**
         * 滑块拖动结束
         *
         * @param {Event} e 事件对象
         */
        _blockOnEnd(e) {
            if (!xfCaptcha._doing) return;

            if (e.cancelable) e.preventDefault();
            xfCaptcha._isMoving = false;

            // 移除拖动状态样式
            const block = document.querySelector(".captcha_slide_block");
            if (block) removeClass(block, "dragging");

            // 发送验证请求
            xfCaptcha._sendResult();
        },

        /**
         * 发送验证结果
         */
        _sendResult() {
            const ajax = new AjaxRequest();
            ajax.request(
                "POST",
                xfCaptcha._options.checkUrl,
                {
                    success: xfCaptcha._sendResultSuccess,
                    failure: xfCaptcha._sendResultFailure,
                },
                { captcha_r: xfCaptcha._markOffset }
            );
        },

        /**
         * 验证成功回调
         *
         * @param {string} response 响应文本
         */
        _sendResultSuccess(response) {
            xfCaptcha._doing = false;

            let result;
            try {
                result = JSON.parse(response);
            } catch (e) {
                // 兼容旧版本直接返回 "ok" 或纯文本
                const trimmed = response.trim().toLowerCase();
                result = {
                    success: trimmed === "ok" || trimmed === '"ok"' || trimmed === "true" || trimmed === "1"
                };
            }

            // 支持多种成功标记方式
            const isSuccess = result.success === true ||
                             result.code === 200 ||
                             result.status === "ok" ||
                             result.status === "success";

            if (isSuccess) {
                // 保存后端返回的 token（双重验证模式使用）
                if (result.token) {
                    xfCaptcha._token = result.token;
                }
                xfCaptcha._handleSuccess();
            } else {
                xfCaptcha._handleFail(result.message || result.error || xfCaptcha._options.failText);
            }
        },

        /**
         * 验证失败回调（网络或服务器错误）
         *
         * @param {XMLHttpRequest} xhr    请求对象
         * @param {number}         status 状态码
         */
        _sendResultFailure(xhr, status) {
            xfCaptcha._doing = false;

            let message = xfCaptcha._options.failText;

            // 处理超时
            if (status === -1) {
                message = "请求超时，请重试";
            } else if (status === 0) {
                message = "网络错误，请检查网络连接";
            } else {
                // 尝试解析错误响应
                try {
                    const result = JSON.parse(xhr.responseText);
                    message = result.message || result.error || message;
                } catch (e) {
                    // 解析失败使用默认消息
                    if (xhr.responseText) {
                        message = xhr.responseText.substring(0, 100);
                    }
                }
            }

            xfCaptcha._handleFail(message);
        },

        /**
         * 处理验证成功
         */
        _handleSuccess() {
            xfCaptcha._result = true;

            // 更新 UI
            const captchaCode = xfCaptcha._container;
            if (captchaCode) captchaCode.innerHTML = xfCaptcha._options.successText;

            xfCaptcha._showMsg(xfCaptcha._options.successText, true);

            // 移除水波纹
            const handleDom = document.querySelector(xfCaptcha._options.handleDom);
            if (handleDom && hasClass(handleDom, "captcha_ripple")) {
                removeClass(handleDom, "captcha_ripple");
            }

            // 生成验证令牌并创建隐藏输入框
            if (xfCaptcha._options.autoInsertInput && xfCaptcha._options.inputName) {
                xfCaptcha._insertHiddenInput();
            }

            // 自动关闭（保存 timer 以便 show/reset 时取消，避免干扰新一轮验证）
            if (xfCaptcha._successHideTimer) {
                clearTimeout(xfCaptcha._successHideTimer);
            }
            xfCaptcha._successHideTimer = setTimeout(() => {
                xfCaptcha.hide();
                xfCaptcha._successHideTimer = null;
            }, 2000);

            // 触发回调
            if (typeof xfCaptcha._onSuccess === "function") {
                xfCaptcha._onSuccess(xfCaptcha._getToken());
            }
        },

        /**
         * 插入隐藏输入框
         */
        _insertHiddenInput() {
            const handleDom = document.querySelector(xfCaptcha._options.handleDom);
            if (!handleDom) return;

            // 检查是否已存在同名输入框
            const existingInput = document.querySelector('input[name="' + xfCaptcha._options.inputName + '"]');
            if (existingInput) {
                existingInput.value = xfCaptcha._getToken();
                return;
            }

            // 创建隐藏输入框
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = xfCaptcha._options.inputName;
            input.value = xfCaptcha._getToken();

            // 优先插入到最近的表单中，否则插入到触发元素之后
            const form = handleDom.closest("form");
            if (form) {
                form.appendChild(input);
            } else if (handleDom.parentNode) {
                handleDom.parentNode.insertBefore(input, handleDom.nextSibling);
            }
        },

        /**
         * 获取验证令牌
         */
        _getToken() {
            // 优先使用后端返回的 token（双重验证模式）
            if (xfCaptcha._token) {
                return xfCaptcha._token;
            }
            // 如果没有后端 token，生成前端临时令牌
            const timestamp = Date.now();
            const random = Math.random().toString(36).substring(2, 15);
            return timestamp + "_" + random + "_" + xfCaptcha._markOffset;
        },

        /**
         * 处理验证失败
         *
         * @param {string} message 错误消息
         */
        _handleFail(message) {
            xfCaptcha._result = false;
            xfCaptcha._token = null;
            xfCaptcha._errorCount++;

            // 移除隐藏输入框（如果存在）
            if (xfCaptcha._options.inputName) {
                const existingInput = document.querySelector('input[name="' + xfCaptcha._options.inputName + '"]');
                if (existingInput) {
                    existingInput.remove();
                }
            }

            // 抖动效果
            const modal = document.getElementById("captcha_div");
            if (modal) {
                addClass(modal, "captcha_shake");
                setTimeout(() => removeClass(modal, "captcha_shake"), 500);
            }

            xfCaptcha._showMsg(message, false);

            // 刷新验证码
            setTimeout(() => xfCaptcha.refresh(), 500);

            // 更新按钮文字为失败提示
            const handleDom = document.querySelector(xfCaptcha._options.handleDom);
            if (handleDom) {
                handleDom.innerHTML = xfCaptcha._options.failText;
                removeClass(handleDom, "captcha_ripple");
            }

            // 触发回调
            if (typeof xfCaptcha._onFail === "function") {
                xfCaptcha._onFail(message);
            }
        },

        /**
         * 绘制完整背景
         */
        _drawFullBg() {
            const canvas = document.querySelector(".captcha_canvas_bg");
            if (!canvas) return;

            const ctx = canvas.getContext("2d");
            ctx.drawImage(
                xfCaptcha._img,
                0,
                xfCaptcha._imgHeight * 2,
                xfCaptcha._imgWidth,
                xfCaptcha._imgHeight,
                0,
                0,
                xfCaptcha._imgWidth,
                xfCaptcha._imgHeight
            );
        },

        /**
         * 绘制背景（带缺口）
         */
        _drawBg() {
            if (xfCaptcha._isDrawBg) return;
            xfCaptcha._isDrawBg = true;

            const canvas = document.querySelector(".captcha_canvas_bg");
            if (!canvas) return;

            const ctx = canvas.getContext("2d");
            ctx.drawImage(
                xfCaptcha._img,
                0,
                0,
                xfCaptcha._imgWidth,
                xfCaptcha._imgHeight,
                0,
                0,
                xfCaptcha._imgWidth,
                xfCaptcha._imgHeight
            );
        },

        /**
         * 绘制滑块
         */
        _drawMark() {
            const canvas = document.querySelector(".captcha_canvas_mark");
            if (!canvas) return;

            const ctx = canvas.getContext("2d", { willReadFrequently: true });
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // 计算实际可绘制的宽度（防止超出画布边界）
            const availableWidth = canvas.width - xfCaptcha._markOffset;
            const drawWidth = Math.min(xfCaptcha._markWidth, availableWidth);
            
            if (drawWidth <= 0) return;

            // 创建临时画布处理边缘效果
            const tempCanvas = document.createElement("canvas");
            tempCanvas.width = xfCaptcha._markWidth;
            tempCanvas.height = xfCaptcha._imgHeight;
            const tempCtx = tempCanvas.getContext("2d", { willReadFrequently: true });

            // 在临时画布绘制滑块图片
            tempCtx.drawImage(
                xfCaptcha._img,
                0,
                xfCaptcha._imgHeight,
                xfCaptcha._markWidth,
                xfCaptcha._imgHeight,
                0,
                0,
                xfCaptcha._markWidth,
                xfCaptcha._imgHeight
            );

            // 添加边缘效果
            try {
                const imageData = tempCtx.getImageData(0, 0, xfCaptcha._markWidth, xfCaptcha._imgHeight);
                const data = imageData.data;
                const width = xfCaptcha._markWidth;
                const height = xfCaptcha._imgHeight;

                // 边缘检测和透明化处理
                for (let y = 0; y < height; y++) {
                    // 从左向右扫描，找到左边缘
                    let leftEdge = -1;
                    for (let x = 0; x < width; x++) {
                        const i = (y * width + x) * 4;
                        const r = data[i];
                        const g = data[i + 1];
                        const b = data[i + 2];

                        // 检查是否为非透明像素（亮度阈值）
                        if (r + g + b >= 160) {
                            leftEdge = x;
                            break;
                        }
                    }

                    // 从右向左扫描，找到右边缘
                    let rightEdge = -1;
                    for (let x = width - 1; x >= 0; x--) {
                        const i = (y * width + x) * 4;
                        const r = data[i];
                        const g = data[i + 1];
                        const b = data[i + 2];

                        if (r + g + b >= 160) {
                            rightEdge = x;
                            break;
                        }
                    }

                    // 处理边缘渐变效果
                    const steps = 2;

                    // 左边缘渐变（向外）
                    if (leftEdge >= 0) {
                        for (let s = 1; s <= steps; s++) {
                            const targetX = leftEdge - s;
                            if (targetX >= 0) {
                                const idx = (y * width + targetX) * 4;
                                const alpha = Math.floor(80 - (80 / steps) * s);
                                if (data[idx + 3] < alpha) {
                                    data[idx + 3] = alpha;
                                }
                            }
                        }
                    }

                    // 右边缘渐变（向外）
                    if (rightEdge >= 0) {
                        for (let s = 1; s <= steps; s++) {
                            const targetX = rightEdge + s;
                            if (targetX < width) {
                                const idx = (y * width + targetX) * 4;
                                const alpha = Math.floor(80 - (80 / steps) * s);
                                if (data[idx + 3] < alpha) {
                                    data[idx + 3] = alpha;
                                }
                            }
                        }
                    }

                    // 透明化处理（亮度低于阈值的像素）
                    for (let x = 0; x < width; x++) {
                        const i = (y * width + x) * 4;
                        const r = data[i];
                        const g = data[i + 1];
                        const b = data[i + 2];

                        if (r + g + b < 60) {
                            data[i + 3] = 0;
                        }
                    }
                }

                tempCtx.putImageData(imageData, 0, 0);
                
                // 将处理后的图片绘制到主画布
                ctx.drawImage(tempCanvas, 0, 0, drawWidth, xfCaptcha._imgHeight, xfCaptcha._markOffset, 0, drawWidth, xfCaptcha._imgHeight);
            } catch (e) {
                // 如果图像处理失败，直接绘制原始图片
                ctx.drawImage(tempCanvas, 0, 0, drawWidth, xfCaptcha._imgHeight, xfCaptcha._markOffset, 0, drawWidth, xfCaptcha._imgHeight);
                console.warn("xfCaptcha: 边缘效果处理失败", e);
            }
        },

        /**
         * 重置状态
         */
        _reset() {
            xfCaptcha._markOffset = 0;
            xfCaptcha._token = null;
            xfCaptcha._drawBg();
            xfCaptcha._drawMark();

            const block = document.querySelector(".captcha_slide_block");
            if (block) block.style.transform = "translateX(0px)";
        },

        /**
         * 显示验证码弹窗
         */
        show() {
            // 每次打开弹窗时强制重置验证状态，防止旧 token 被重复利用
            xfCaptcha._result = false;
            xfCaptcha._token = null;
            xfCaptcha._errorCount = 0;
            xfCaptcha._markOffset = 0;
            xfCaptcha._doing = false;
            xfCaptcha._isMoving = false;

            if (xfCaptcha._rafId) {
                cancelAnimationFrame(xfCaptcha._rafId);
                xfCaptcha._rafId = null;
            }

            if (xfCaptcha._successHideTimer) {
                clearTimeout(xfCaptcha._successHideTimer);
                xfCaptcha._successHideTimer = null;
            }

            // 移除旧的隐藏输入框
            if (xfCaptcha._options.inputName) {
                const existingInput = document.querySelector('input[name="' + xfCaptcha._options.inputName + '"]');
                if (existingInput) {
                    existingInput.remove();
                }
            }

            const handleDom = document.querySelector(xfCaptcha._options.handleDom);
            if (handleDom) {
                handleDom.innerHTML = xfCaptcha._options.placeholder;
                removeClass(handleDom, "verified");
                if (xfCaptcha._options.showRipple && !hasClass(handleDom, "captcha_ripple")) {
                    addClass(handleDom, "captcha_ripple");
                }
            }

            // 重置成功提示
            const successMsg = document.querySelector(".captcha_msg_ok");
            if (successMsg) successMsg.style.display = "none";

            xfCaptcha.refresh();
            xfCaptcha._container = handleDom;

            const bg = document.getElementById("captcha_div_bg");
            const modal = document.getElementById("captcha_div");
            if (bg) bg.style.display = "block";
            if (modal) modal.style.display = "block";
        },

        /**
         * 隐藏验证码弹窗
         */
        hide() {
            const bg = document.getElementById("captcha_div_bg");
            const modal = document.getElementById("captcha_div");
            if (bg) bg.style.display = "none";
            if (modal) modal.style.display = "none";

            if (typeof xfCaptcha._onClose === "function") {
                xfCaptcha._onClose();
            }
        },

        /**
         * 显示消息提示
         *
         * @param {string}  msg    消息内容
         * @param {boolean} isOk   是否为成功消息
         */
        _showMsg(msg, isOk) {
            const okElem = document.querySelector(".captcha_msg_ok");
            const errorElem = document.querySelector(".captcha_msg_error");

            if (isOk) {
                if (okElem) {
                    okElem.innerHTML = msg;
                    okElem.style.display = "block";
                    okElem.style.opacity = "1";
                }
                if (errorElem) errorElem.style.display = "none";

                // 3秒后淡出
                setTimeout(() => {
                    if (okElem) xfCaptcha._fadeOut(okElem);
                }, 3000);
            } else {
                if (errorElem) {
                    errorElem.innerHTML = msg;
                    errorElem.style.display = "block";
                    errorElem.style.opacity = "1";
                }
                if (okElem) okElem.style.display = "none";

                setTimeout(() => {
                    if (errorElem) xfCaptcha._fadeOut(errorElem);
                }, 2000);
            }
        },

        /**
         * 淡出效果
         *
         * @param {Element} elem 目标元素
         */
        _fadeOut(elem) {
            if (!elem) return;
            let opacity = 1;
            const timer = setInterval(() => {
                opacity -= 0.1;
                if (opacity <= 0) {
                    elem.style.opacity = "0";
                    elem.style.display = "none";
                    clearInterval(timer);
                } else {
                    elem.style.opacity = opacity;
                }
            }, 50);
        },

        /**
         * 生成 HTML 结构
         */
        _createHTML() {
            if (document.getElementById("captcha_div_bg")) return;

            const html = `
                <div class="captcha_div_bg" id="captcha_div_bg"></div>
                <div class="captcha_div" id="captcha_div">
                    <div class="captcha_loading"></div>
                    <canvas class="captcha_canvas_bg" width="240" height="150"></canvas>
                    <canvas class="captcha_canvas_mark" width="240" height="150"></canvas>
                    <div class="captcha_hlight"></div>
                    <div class="captcha_msg_error"></div>
                    <div class="captcha_msg_ok"></div>
                    <div class="captcha_slide">
                        <div class="captcha_slide_block"></div>
                        <div class="captcha_slide_text">${xfCaptcha._options.slideText}</div>
                    </div>
                    <div class="captcha_tools">
                        <a href="http://yoc.cn" target="_blank" class="captcha_copyright" title="来源 yoc.cn">© yoc.cn</a>
                        <div class="captcha_tools_actions">
                            ${xfCaptcha._options.showRefresh ? '<div class="captcha_refresh" title="刷新"></div>' : ''}
                            ${xfCaptcha._options.showClose ? '<div class="captcha_close" title="关闭"></div>' : ''}
                        </div>
                    </div>
                </div>
            `;

            appendHTML(document.body, html);
        },

        /**
         * 刷新验证码
         */
        refresh() {
            // 检测 WebP 支持
            const isSupportWebp = (() => {
                try {
                    return (
                        document.createElement("canvas")
                            .toDataURL("image/webp")
                            .indexOf("data:image/webp") === 0
                    );
                } catch (e) {
                    return false;
                }
            })();

            xfCaptcha._errorCount = 0;
            xfCaptcha._isDrawBg = false;
            xfCaptcha._result = false;
            xfCaptcha._imgLoaded = false;

            // 隐藏画布
            const bgCanvas = document.querySelector(".captcha_canvas_bg");
            const markCanvas = document.querySelector(".captcha_canvas_mark");
            if (bgCanvas) bgCanvas.style.display = "none";
            if (markCanvas) markCanvas.style.display = "none";

            // 显示加载中
            const loading = document.querySelector(".captcha_loading");
            if (loading) loading.style.display = "flex";

            // 加载图片
            xfCaptcha._img = new Image();
            let imgUrl = xfCaptcha._options.getImgUrl + "?t=" + Date.now();
            if (!isSupportWebp) {
                imgUrl += "&nowebp=1";
            }

            xfCaptcha._img.crossOrigin = "anonymous";
            xfCaptcha._img.src = imgUrl;

            xfCaptcha._img.onload = function () {
                xfCaptcha._drawFullBg();

                const markCtx = markCanvas.getContext("2d");
                markCtx.clearRect(0, 0, markCanvas.width, markCanvas.height);

                xfCaptcha._imgLoaded = true;

                // 显示画布
                if (bgCanvas) bgCanvas.style.display = "block";
                if (markCanvas) markCanvas.style.display = "block";
                if (loading) {
                    loading.style.display = "none";
                    loading.innerHTML = "";
                    removeClass(loading, "captcha_loading_error");
                }

                // 重置滑块
                const block = document.querySelector(".captcha_slide_block");
                const text = document.querySelector(".captcha_slide_text");
                if (block) block.style.transform = "translateX(0px)";
                if (text) text.style.display = "block";
            };

            xfCaptcha._img.onerror = function () {
                if (loading) {
                    loading.innerHTML = '<div style="text-align:center;line-height:1.6;">加载失败<br>请刷新重试</div>';
                    addClass(loading, 'captcha_loading_error');
                }
            };
        },

        /**
         * 初始化验证码
         *
         * @param {object} options 配置选项
         * @returns {object} xfCaptcha 对象
         */
        init(options) {
            // 合并配置
            xfCaptcha._options = Object.assign({}, xfCaptcha._defaults, options);

            // 创建 HTML 结构
            xfCaptcha._createHTML();

            // 应用主题
            const modal = document.getElementById("captcha_div");
            if (modal && xfCaptcha._options.theme && xfCaptcha._options.theme !== 'auto') {
                modal.setAttribute('data-theme', xfCaptcha._options.theme);
            }

            // 绑定事件（需要 preventDefault 的事件使用 passive: false）
            const block = document.querySelector(".captcha_slide_block");
            if (block) {
                xfCaptcha._bind(block, "mousedown", xfCaptcha._blockStartMove, { passive: false });
                xfCaptcha._bind(block, "touchstart", xfCaptcha._blockStartMove, { passive: false });
            }

            xfCaptcha._bind(document, "mousemove", xfCaptcha._blockOnMove, { passive: false });
            xfCaptcha._bind(document, "mouseup", xfCaptcha._blockOnEnd, { passive: false });
            xfCaptcha._bind(document, "touchmove", xfCaptcha._blockOnMove, { passive: false });
            xfCaptcha._bind(document, "touchend", xfCaptcha._blockOnEnd, { passive: false });

            // 关闭按钮
            const closeBtn = document.querySelector(".captcha_close");
            if (closeBtn) {
                xfCaptcha._bind(closeBtn, "click", xfCaptcha.hide);
            }

            // 刷新按钮
            const refreshBtn = document.querySelector(".captcha_refresh");
            if (refreshBtn) {
                xfCaptcha._bind(refreshBtn, "click", xfCaptcha.refresh);
            }

            // 绑定触发元素
            const triggers = document.querySelectorAll(xfCaptcha._options.handleDom);
            triggers.forEach((elem) => {
                elem.innerHTML = xfCaptcha._options.placeholder;
                if (xfCaptcha._options.showRipple) {
                    addClass(elem, "captcha_ripple");
                }
                xfCaptcha._bind(elem, "click", xfCaptcha.show);
            });

            return xfCaptcha;
        },

        /**
         * 获取验证结果
         *
         * @returns {boolean} 是否验证通过
         */
        result() {
            return xfCaptcha._result;
        },

        /**
         * 设置成功回调
         *
         * @param {function} fn 回调函数
         * @returns {object} xfCaptcha 对象
         */
        onSuccess(fn) {
            xfCaptcha._onSuccess = fn;
            return xfCaptcha;
        },

        /**
         * 设置失败回调
         *
         * @param {function} fn 回调函数
         * @returns {object} xfCaptcha 对象
         */
        onFail(fn) {
            xfCaptcha._onFail = fn;
            return xfCaptcha;
        },

        /**
         * 设置关闭回调
         *
         * @param {function} fn 回调函数
         * @returns {object} xfCaptcha 对象
         */
        onClose(fn) {
            xfCaptcha._onClose = fn;
            return xfCaptcha;
        },

        /**
         * 获取验证令牌（双重验证模式）
         *
         * @returns {string|null}
         */
        getToken() {
            return xfCaptcha._getToken();
        },

        /**
         * 动态切换主题
         *
         * @param {string} theme 主题名称
         * @returns {object} xfCaptcha 对象
         */
        setTheme(theme) {
            xfCaptcha._options.theme = theme || 'auto';
            const modal = document.getElementById("captcha_div");
            if (modal) {
                modal.setAttribute('data-theme', theme);
            }
            return xfCaptcha;
        },

        /**
         * 重置验证码状态
         * 表单提交失败后调用此方法重新验证
         *
         * @returns {object} xfCaptcha 对象
         */
        reset() {
            // 隐藏弹窗
            xfCaptcha.hide();

            // 取消未完成的动画帧和定时器
            if (xfCaptcha._rafId) {
                cancelAnimationFrame(xfCaptcha._rafId);
                xfCaptcha._rafId = null;
            }
            if (xfCaptcha._successHideTimer) {
                clearTimeout(xfCaptcha._successHideTimer);
                xfCaptcha._successHideTimer = null;
            }

            // 重置状态
            xfCaptcha._result = false;
            xfCaptcha._token = null;
            xfCaptcha._errorCount = 0;
            xfCaptcha._markOffset = 0;
            xfCaptcha._doing = false;
            xfCaptcha._isMoving = false;

            // 移除隐藏输入框
            if (xfCaptcha._options.inputName) {
                const existingInput = document.querySelector('input[name="' + xfCaptcha._options.inputName + '"]');
                if (existingInput) {
                    existingInput.remove();
                }
            }

            // 重置触发按钮
            const triggers = document.querySelectorAll(xfCaptcha._options.handleDom);
            triggers.forEach((elem) => {
                elem.innerHTML = xfCaptcha._options.placeholder;
                removeClass(elem, "verified");
                if (xfCaptcha._options.showRipple) {
                    addClass(elem, "captcha_ripple");
                }
            });

            // 刷新验证码
            xfCaptcha.refresh();

            return xfCaptcha;
        },
    };

    // 暴露到全局
    window.xfCaptcha = xfCaptcha;

    // AMD 支持
    if (typeof define === "function" && define.amd) {
        define("xfCaptcha", [], function () {
            return xfCaptcha;
        });
    }

    // CommonJS 支持
    if (typeof module !== "undefined" && module.exports) {
        module.exports = xfCaptcha;
    }
})(window, document);
