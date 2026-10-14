/**
 * xfCaptcha - 高性能滑动验证码 & 点击验证码 JavaScript 库
 *
 * @package     zxf/captcha
 * @license     MIT
 * @version     1.0.9
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

        // 预生成的滑块图层（仅在图片加载时计算一次边缘效果，避免拖拽时逐帧重算）
        _slidePrepared: null,

        // 当前是否展示弹窗（用于 Esc 关闭与键盘交互判断）
        _isOpen: false,

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

        // 画布与显示尺寸的缩放比例（用于把 CSS 像素映射回图片像素）
        _imgScale: 1,    // 每个图片像素对应的 CSS 像素数 (rect.width / canvas.width)
        _imgInvScale: 1, // 每个 CSS 像素对应的图片像素数 (canvas.width / rect.width)

        // 滑动轨迹数据（用于后端防机器人轨迹验证）
        _slideTrack: [],

        // 尺寸配置
        _markWidth: 50,
        _markHeight: 50,
        _imgWidth: 240,
        _imgHeight: 150,

        // 回调函数
        _onSuccess: null,
        _onFail: null,
        _onClose: null,
        _onShow: null,
        _onHide: null,
        _onRefresh: null,
        _onReset: null,

        // 配置选项
        _options: {},

        // 默认配置
        _defaults: {
            handleDom: ".xf-captcha",
            dataUrl: "/xf_captcha/data",
            imageUrl: "/xf_captcha/image",
            checkUrl: "/xf_captcha/check",
            placeholder: "点击按钮进行验证",
            slideText: "拖动左边滑块完成上方拼图",
            clickText: "请按照顺序点击图片中的文字",
            successText: "✓ 验证成功",
            failText: "验证失败，请重试",
            showClose: true,
            showRefresh: true,
            showRipple: true,
            theme: "auto",
            inputName: "xf_captcha_token",
            autoInsertInput: true,
        },

        // 当前验证码类型
        _captchaType: 'slide',

        // 点击验证码数据
        _clickPoints: [],
        _clickCount: 0,

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
         * 带缓存的 DOM 元素查询（性能优化）
         *
         * 验证码弹窗的 DOM 结构在一次 init 内稳定不变，重复 document.querySelector
         * 既无必要也浪费时间。本方法首次查询后缓存结果，并在元素脱离文档时自动重新查询，
         * 兼顾性能与 re-init 后的正确性。
         *
         * @param {string} sel CSS 选择器
         * @param {string} key 缓存键（挂载在 xfCaptcha 上的属性名）
         * @returns {Element|null}
         */
        _el(sel, key) {
            let el = xfCaptcha[key];
            if (!el || !el.isConnected) {
                el = xfCaptcha[key] = document.querySelector(sel);
            }
            return el;
        },

        /**
         * 仅在拖动进行中把 mousemove/mouseup/touchmove/touchend 绑定到 document，
         * 避免全局常驻监听在页面任意移动鼠标时都被触发（性能优化）。
         */
        _bindDragListeners() {
            if (xfCaptcha._dragListenersBound) return;
            xfCaptcha._dragListenersBound = true;
            xfCaptcha._bind(document, "mousemove", xfCaptcha._blockOnMove, { passive: false });
            xfCaptcha._bind(document, "mouseup", xfCaptcha._blockOnEnd, { passive: false });
            xfCaptcha._bind(document, "touchmove", xfCaptcha._blockOnMove, { passive: false });
            xfCaptcha._bind(document, "touchend", xfCaptcha._blockOnEnd, { passive: false });
        },

        /**
         * 拖动结束后解绑 document 上的拖动相关监听，避免长期持有全局事件监听。
         */
        _unbindDragListeners() {
            if (!xfCaptcha._dragListenersBound) return;
            xfCaptcha._dragListenersBound = false;
            document.removeEventListener("mousemove", xfCaptcha._blockOnMove);
            document.removeEventListener("mouseup", xfCaptcha._blockOnEnd);
            document.removeEventListener("touchmove", xfCaptcha._blockOnMove);
            document.removeEventListener("touchend", xfCaptcha._blockOnEnd);
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
            // const textElem = document.querySelector(".captcha_slide_text");
            // if (textElem) textElem.style.display = "none";

            xfCaptcha._drawBg();
            xfCaptcha._blockStartX = evt.clientX;
            xfCaptcha._blockStartY = evt.clientY;
            xfCaptcha._doing = true;
            xfCaptcha._isMoving = true;
            xfCaptcha._slideTrack = [];
            // 记录起始时刻用于轨迹时间戳（首点 t=0 基准）
            xfCaptcha._trackStartTime = Date.now();

            // 仅在拖动进行中绑定全局监听，结束后解绑（避免常驻全局事件影响页面性能）
            xfCaptcha._bindDragListeners();

            // 添加拖动状态样式
            const block = xfCaptcha._el(".captcha_slide_block", "_blockEl");
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

            // 最大可拖动范围（图片像素）换算到 CSS 像素，保证滑块能拖到缺口位置
            const maxOffset = (xfCaptcha._imgWidth - xfCaptcha._markWidth) * (xfCaptcha._imgScale || 1);

            // 限制滑动范围（CSS 像素）
            offset = Math.max(0, Math.min(offset, maxOffset));

            // 记录轨迹点（换算为图片像素空间，与后端 posX/posY 同一坐标系）
            // 后端校验默认轨迹为图片像素尺度，CSS 像素会导致速度/直线度阈值被放大而误判
            const scale = xfCaptcha._imgScale || 1;
            const invScale = scale > 0 ? 1 / scale : 1;
            xfCaptcha._slideTrack.push({
                // 水平：CSS 偏移 -> 图片像素
                x: Math.round(offset * invScale),
                // 垂直：以滑块初始图片纵坐标(_markTopY)为基准的绝对图片像素，
                // 避免相对位移语义与后端不一致
                y: Math.round(
                    (xfCaptcha._markTopY || 0) +
                    (evt.clientY - xfCaptcha._blockStartY) * invScale
                ),
                t: Date.now() - (xfCaptcha._trackStartTime || 0),
            });

            // 如果位置没有变化，不更新
            if (offset === xfCaptcha._markOffset) return;

            // 使用 requestAnimationFrame 优化性能
            if (xfCaptcha._rafId) {
                cancelAnimationFrame(xfCaptcha._rafId);
            }

            xfCaptcha._rafId = requestAnimationFrame(() => {
                // 更新滑块位置
                const block = xfCaptcha._el(".captcha_slide_block", "_blockEl");
                if (block) {
                    block.style.transform = "translateX(" + offset + "px)";
                }

                // 记录 CSS 空间偏移量（用于显示），真实图片空间偏移在发送时换算
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
            if (xfCaptcha._captchaType !== 'slide') return;

            if (e.cancelable) e.preventDefault();
            xfCaptcha._isMoving = false;

            // 移除拖动状态样式
            const block = xfCaptcha._el(".captcha_slide_block", "_blockEl");
            if (block) removeClass(block, "dragging");

            // 解绑拖动期间的全局监听，恢复为“无监听”状态
            xfCaptcha._unbindDragListeners();

            // 发送验证请求
            xfCaptcha._sendResult();
        },

        /**
         * 滑块键盘交互（无障碍）
         *
         * 为可聚焦的滑块（role="slider" tabindex="0"）提供键盘操作：
         * - 左右方向键（按住 Shift 步长加大）移动滑块
         * - Home / End 直接跳到最左 / 最右
         * - 松开按键（keyup）时提交验证（支持长按连续移动后只提交一次）
         *
         * @param {KeyboardEvent} e 键盘事件
         */
        _blockOnKey(e) {
            if (xfCaptcha._captchaType !== 'slide') return;
            if (!xfCaptcha._imgLoaded || xfCaptcha._doing) return;

            const step = e.shiftKey ? 12 : 4;
            let offset = xfCaptcha._markOffset;
            let handled = true;

            switch (e.key) {
                case 'ArrowLeft':
                case 'Left':
                    offset -= step;
                    break;
                case 'ArrowRight':
                case 'Right':
                    offset += step;
                    break;
                case 'Home':
                    offset = 0;
                    break;
                case 'End':
                    offset = xfCaptcha._imgWidth - xfCaptcha._markWidth;
                    break;
                default:
                    handled = false;
            }

            if (!handled) return;
            e.preventDefault();

            // 限制滑动范围（CSS 像素）
            const maxOffset = (xfCaptcha._imgWidth - xfCaptcha._markWidth) * (xfCaptcha._imgScale || 1);
            offset = Math.max(0, Math.min(offset, maxOffset));
            xfCaptcha._markOffset = offset;

            const block = xfCaptcha._el(".captcha_slide_block", "_blockEl");
            if (block) {
                block.style.transform = "translateX(" + offset + "px)";
                // 同步 ARIA 当前值（百分比口径，与 aria-valuemin/max=0/100 对齐）
                const pct = maxOffset > 0 ? Math.round(offset / maxOffset * 100) : 0;
                block.setAttribute('aria-valuenow', String(pct));
            }

            // 拖拽不再逐帧重算，直接复用预生成图层
            xfCaptcha._drawMark();

            // 初始化键盘轨迹记录（仅供后端基础轨迹校验，不阻断提交）
            if (!xfCaptcha._trackStartTime) {
                xfCaptcha._trackStartTime = Date.now();
                xfCaptcha._slideTrack = [];
            }
            xfCaptcha._slideTrack.push({
                x: Math.round(offset * (xfCaptcha._imgInvScale || 1)),
                y: Math.round(xfCaptcha._markTopY || 0),
                t: Date.now() - xfCaptcha._trackStartTime,
            });

            // 键盘抬起时提交验证（支持长按连续移动后只提交一次）
            if (!xfCaptcha._keyUpHandler) {
                const handler = function () {
                    // 自移除监听，避免隐藏/重置后仍残留全局 keyup 监听导致重复提交
                    document.removeEventListener("keyup", handler);
                    xfCaptcha._keyUpHandler = null;
                    if (xfCaptcha._doing || xfCaptcha._captchaType !== 'slide') return;
                    xfCaptcha._doing = true;
                    xfCaptcha._sendResult();
                };
                xfCaptcha._keyUpHandler = handler;
                document.addEventListener("keyup", handler);
            }
        },

        /**
         * 处理点击验证码点击事件
         */
        _handleClickCaptcha(e) {
            if (xfCaptcha._captchaType !== 'click') return;
            if (xfCaptcha._doing) return;
            if (!xfCaptcha._imgLoaded) return;

            // 防止触摸事件和鼠标事件重复触发
            if (e.type === 'touchstart') {
                e.preventDefault();
            }

            const canvas = xfCaptcha._el(".captcha_canvas_bg", "_bgCanvas");
            if (!canvas) return;

            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;

            const evt = e.touches ? e.touches[0] : e;
            const x = Math.round((evt.clientX - rect.left) * scaleX);
            const y = Math.round((evt.clientY - rect.top) * scaleY);

            // 防抖去重：移动端一次物理点击会依次触发 touchstart 与 click，
            // 同一位置 400ms 内的重复事件忽略，避免一次点击被计数两次
            const now = Date.now();
            if (xfCaptcha._lastClickTime && now - xfCaptcha._lastClickTime < 400) {
                const dx = evt.clientX - xfCaptcha._lastClickClientX;
                const dy = evt.clientY - xfCaptcha._lastClickClientY;
                if (dx * dx + dy * dy < 100) { // 10px 内视为同一次点击
                    return;
                }
            }
            xfCaptcha._lastClickTime = now;
            xfCaptcha._lastClickClientX = evt.clientX;
            xfCaptcha._lastClickClientY = evt.clientY;

            // 检查是否在有效范围内（留出边缘容错）
            if (x < -10 || x > xfCaptcha._imgWidth + 10 || y < -10 || y > xfCaptcha._imgHeight + 10) {
                return;
            }

            // 记录点击位置（限制在图片范围内），并附带相对首点的耗时 t（毫秒），
            // 供服务端做“机器人瞬间点完/连点”检测
            const safeX = Math.max(0, Math.min(x, xfCaptcha._imgWidth));
            const safeY = Math.max(0, Math.min(y, xfCaptcha._imgHeight));
            xfCaptcha._clickPoints.push({
                x: safeX,
                y: safeY,
                t: now - (xfCaptcha._clickStartTime || now),
            });
            xfCaptcha._clickCount++;

            // 显示点击标记和动画
            xfCaptcha._showClickMarker(safeX, safeY, xfCaptcha._clickCount);
            xfCaptcha._showClickRipple(evt.clientX, evt.clientY);

            // 检查是否点击完成
            const expectedCount = xfCaptcha._options.charCount || 4;
            if (xfCaptcha._clickCount >= expectedCount) {
                xfCaptcha._doing = true;
                xfCaptcha._sendClickResult();
            }
        },

        /**
         * 显示点击标记（带圈数字）
         */
        _showClickMarker(x, y, number) {
            const canvas = document.querySelector(".captcha_canvas_mark");
            if (!canvas) return;

            const ctx = canvas.getContext("2d");
            const radius = 10;
            const pulseRadius = 16;

            // 绘制外圈发光效果（更淡更细）
            const gradient = ctx.createRadialGradient(x, y, radius, x, y, pulseRadius);
            gradient.addColorStop(0, "rgba(24, 144, 255, 0.25)");
            gradient.addColorStop(1, "rgba(24, 144, 255, 0)");
            
            ctx.beginPath();
            ctx.arc(x, y, pulseRadius, 0, 2 * Math.PI);
            ctx.fillStyle = gradient;
            ctx.fill();

            // 绘制主圆圈（半透明，减少遮挡）
            ctx.save();
            ctx.globalAlpha = 0.85;
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, 2 * Math.PI);
            ctx.fillStyle = "#1890ff";
            ctx.fill();
            ctx.restore();

            // 绘制边框（更细）
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, 2 * Math.PI);
            ctx.strokeStyle = "rgba(255, 255, 255, 0.9)";
            ctx.lineWidth = 1.5;
            ctx.stroke();

            // 绘制数字（缩小字号）
            ctx.fillStyle = "#ffffff";
            ctx.font = "bold 13px -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText(number.toString(), x, y);

            // 添加缩放动画效果
            xfCaptcha._animateMarker(x, y, number);
        },

        /**
         * 标记缩放动画
         */
        _animateMarker(x, y, number) {
            const canvas = document.querySelector(".captcha_canvas_mark");
            if (!canvas) return;
            
            let scale = 1.4;
            let opacity = 0.6;
            const ctx = canvas.getContext("2d");
            
            function animate() {
                if (scale <= 1 || opacity <= 0) return;
                
                scale -= 0.08;
                opacity -= 0.08;
                
                // 在标记周围绘制动画环
                ctx.save();
                ctx.beginPath();
                ctx.arc(x, y, 10 * scale, 0, 2 * Math.PI);
                ctx.strokeStyle = `rgba(24, 144, 255, ${opacity})`;
                ctx.lineWidth = 1;
                ctx.stroke();
                ctx.restore();
                
                requestAnimationFrame(animate);
            }
            
            animate();
        },

        /**
         * 显示点击涟漪效果
         */
        _showClickRipple(clientX, clientY) {
            const modal = document.getElementById("captcha_div");
            if (!modal) return;

            const rect = modal.getBoundingClientRect();
            const ripple = document.createElement("div");
            ripple.className = "captcha_click_ripple";
            ripple.style.cssText = `
                position: absolute;
                left: ${clientX - rect.left - 10}px;
                top: ${clientY - rect.top - 10}px;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                background: rgba(24, 144, 255, 0.4);
                pointer-events: none;
                z-index: 100;
            `;
            
            modal.appendChild(ripple);
            
            // CSS 动画
            ripple.animate([
                { transform: 'scale(1)', opacity: 0.8 },
                { transform: 'scale(3)', opacity: 0 }
            ], {
                duration: 400,
                easing: 'ease-out'
            }).onfinish = () => ripple.remove();
        },

        /**
         * 更新点击验证码提示（已废弃进度提示，保留简洁提示）
         */
        _updateClickHint(remaining) {
            // 需求：删除进度提示，避免干扰用户判断
            // 此函数保留为空，不再显示任何进度信息
            const slideText = document.querySelector(".captcha_slide_text");
            if (!slideText) return;
            
            if (xfCaptcha._captchaType === 'click') {
                // 点击模式下仅显示静态提示文字，不显示剩余数量
                slideText.style.display = "block";
            }
        },

        /**
         * 清除点击标记（带动画效果）
         */
        _clearClickMarkers() {
            const canvas = document.querySelector(".captcha_canvas_mark");
            if (!canvas) return;
            
            const ctx = canvas.getContext("2d");
            let opacity = 1;
            
            function fadeOut() {
                opacity -= 0.1;
                if (opacity <= 0) {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    return;
                }
                
                ctx.save();
                ctx.globalAlpha = opacity;
                ctx.fillStyle = "rgba(255, 255, 255, 0.1)";
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.restore();
                
                requestAnimationFrame(fadeOut);
            }
            
            fadeOut();
        },

        /**
         * 发送点击验证码验证结果
         */
        _sendClickResult() {
            const ajax = new AjaxRequest();
            ajax.request(
                "POST",
                xfCaptcha._options.checkUrl,
                {
                    success: function(response) {
                        xfCaptcha._sendResultSuccess(response);
                    },
                    failure: function(xhr, status) {
                        xfCaptcha._sendResultFailure(xhr, status);
                    },
                },
                { click_points: JSON.stringify(xfCaptcha._clickPoints) }
            );
        },

        /**
         * 发送滑动验证码验证结果
         */
        _sendResult() {
            const ajax = new AjaxRequest();
            // 把 CSS 空间偏移换算回图片空间偏移，与服务端 posX 口径一致
            const sendOffset = Math.round(xfCaptcha._markOffset * (xfCaptcha._imgInvScale || 1));
            ajax.request(
                "POST",
                xfCaptcha._options.checkUrl,
                {
                    success: xfCaptcha._sendResultSuccess,
                    failure: xfCaptcha._sendResultFailure,
                },
                {
                    captcha_r: sendOffset,
                    slide_track: JSON.stringify(xfCaptcha._slideTrack || []),
                }
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

            const token = xfCaptcha._getToken();

            // 无 token（backend_only 模式）：移除可能残留的旧输入框，
            // 避免向表单提交无效/伪造令牌
            if (!token) {
                const staleInput = document.querySelector('input[name="' + xfCaptcha._options.inputName + '"]');
                if (staleInput) staleInput.remove();
                return;
            }

            // 检查是否已存在同名输入框
            const existingInput = document.querySelector('input[name="' + xfCaptcha._options.inputName + '"]');
            if (existingInput) {
                existingInput.value = token;
                return;
            }

            // 创建隐藏输入框
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = xfCaptcha._options.inputName;
            input.value = token;

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
         *
         * 双重验证（dual）模式下返回后端生成的一次性 token；
         * 纯后端验证（backend_only）模式下后端不返回 token，这里返回 null，
         * 由服务端依据会话中的“已通过验证”状态幂等放行，不再生成泄露偏移量的前端假令牌。
         *
         * @returns {string|null}
         */
        _getToken() {
            return xfCaptcha._token || null;
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

            // 对于点击验证码，标记错误的点击位置
            if (xfCaptcha._captchaType === 'click' && xfCaptcha._clickPoints.length > 0) {
                xfCaptcha._showErrorMarkers();
            }

            // 抖动效果
            const modal = document.getElementById("captcha_div");
            if (modal) {
                addClass(modal, "captcha_shake");
                setTimeout(() => removeClass(modal, "captcha_shake"), 500);
            }

            xfCaptcha._showMsg(message, false);

            // 延迟刷新验证码，让用户看到错误反馈
            setTimeout(() => {
                xfCaptcha._clickPoints = [];
                xfCaptcha._clickCount = 0;
                xfCaptcha._doing = false;
                xfCaptcha.refresh();
            }, 1200);

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
         * 显示错误标记（红色 X）
         */
        _showErrorMarkers() {
            const canvas = document.querySelector(".captcha_canvas_mark");
            if (!canvas) return;

            const ctx = canvas.getContext("2d");
            
            xfCaptcha._clickPoints.forEach(point => {
                const x = point.x;
                const y = point.y;
                const size = 12;
                
                // 绘制红色圆圈
                ctx.beginPath();
                ctx.arc(x, y, size, 0, 2 * Math.PI);
                ctx.fillStyle = "rgba(255, 77, 79, 0.9)";
                ctx.fill();
                ctx.strokeStyle = "#ffffff";
                ctx.lineWidth = 2;
                ctx.stroke();
                
                // 绘制 X
                ctx.beginPath();
                ctx.strokeStyle = "#ffffff";
                ctx.lineWidth = 3;
                ctx.moveTo(x - 5, y - 5);
                ctx.lineTo(x + 5, y + 5);
                ctx.moveTo(x + 5, y - 5);
                ctx.lineTo(x - 5, y + 5);
                ctx.stroke();
            });
        },

        /**
         * 绘制完整背景
         */
        _drawFullBg() {
            const canvas = xfCaptcha._el(".captcha_canvas_bg", "_bgCanvas");
            if (!canvas) return;

            const ctx = canvas.getContext("2d");

            if (xfCaptcha._captchaType === 'slide') {
                // 绘制带缺口的背景（合成图第 0 段），让用户一开始就看到缺口位置
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
            } else {
                // 点击验证码直接绘制图片
                ctx.drawImage(xfCaptcha._img, 0, 0, xfCaptcha._imgWidth, xfCaptcha._imgHeight);
            }
        },

        /**
         * 绘制背景（带缺口 - 仅滑动验证码）
         */
        _drawBg() {
            if (xfCaptcha._isDrawBg) return;
            if (xfCaptcha._captchaType !== 'slide') return;

            xfCaptcha._isDrawBg = true;

            const canvas = xfCaptcha._el(".captcha_canvas_bg", "_bgCanvas");
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
         * 绘制滑块（仅滑动验证码）
         *
         * 性能优化：直接绘制预生成的滑块图层（_slidePrepared），
         * 不再在每次拖拽帧中做 getImageData 逐像素边缘处理。
         */
        _drawMark() {
            if (xfCaptcha._captchaType !== 'slide') return;

            const canvas = xfCaptcha._el(".captcha_canvas_mark", "_markCanvas");
            if (!canvas) return;

            const ctx = canvas.getContext("2d", { willReadFrequently: true });
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // 将 CSS 空间偏移换算为图片空间偏移，保证滑块与缺口对齐
            const drawOffset = xfCaptcha._markOffset * (xfCaptcha._imgInvScale || 1);
            const availableWidth = canvas.width - drawOffset;
            const drawWidth = Math.min(xfCaptcha._markWidth, availableWidth);

            if (drawWidth <= 0) return;

            if (xfCaptcha._slidePrepared) {
                ctx.drawImage(
                    xfCaptcha._slidePrepared,
                    0, 0, drawWidth, xfCaptcha._imgHeight,
                    drawOffset, 0, drawWidth, xfCaptcha._imgHeight
                );
            } else if (xfCaptcha._img) {
                // 兜底：预生成失败时使用原始滑块面板（边缘效果略差，但功能正常）
                ctx.drawImage(
                    xfCaptcha._img,
                    0, xfCaptcha._imgHeight, drawWidth, xfCaptcha._imgHeight,
                    drawOffset, 0, drawWidth, xfCaptcha._imgHeight
                );
            }
        },

        /**
         * 预生成滑块图层（仅在图片加载时计算一次）
         *
         * 将合成图的“滑块面板”（第 2 行，y = imgHeight）抠出并做边缘透明/渐变处理，
         * 之后拖拽过程中只需 drawImage，无需逐帧 getImageData 像素遍历，性能大幅提升。
         */
        _prepareSlideImage() {
            if (xfCaptcha._captchaType !== 'slide' || !xfCaptcha._img) return;

            const w = xfCaptcha._markWidth;
            const h = xfCaptcha._imgHeight;
            const temp = document.createElement("canvas");
            temp.width = w;
            temp.height = h;
            const tctx = temp.getContext("2d", { willReadFrequently: true });

            // 从合成图第 2 行抠出滑块面板
            tctx.drawImage(xfCaptcha._img, 0, h, w, h, 0, 0, w, h);

            try {
                const imageData = tctx.getImageData(0, 0, w, h);
                const data = imageData.data;

                for (let y = 0; y < h; y++) {
                    let leftEdge = -1;
                    for (let x = 0; x < w; x++) {
                        const i = (y * w + x) * 4;
                        if (data[i] + data[i + 1] + data[i + 2] >= 160) { leftEdge = x; break; }
                    }

                    let rightEdge = -1;
                    for (let x = w - 1; x >= 0; x--) {
                        const i = (y * w + x) * 4;
                        if (data[i] + data[i + 1] + data[i + 2] >= 160) { rightEdge = x; break; }
                    }

                    const steps = 2;
                    if (leftEdge >= 0) {
                        for (let s = 1; s <= steps; s++) {
                            const targetX = leftEdge - s;
                            if (targetX >= 0) {
                                const idx = (y * w + targetX) * 4;
                                const alpha = Math.floor(80 - (80 / steps) * s);
                                if (data[idx + 3] < alpha) data[idx + 3] = alpha;
                            }
                        }
                    }
                    if (rightEdge >= 0) {
                        for (let s = 1; s <= steps; s++) {
                            const targetX = rightEdge + s;
                            if (targetX < w) {
                                const idx = (y * w + targetX) * 4;
                                const alpha = Math.floor(80 - (80 / steps) * s);
                                if (data[idx + 3] < alpha) data[idx + 3] = alpha;
                            }
                        }
                    }

                    // 透明化处理（亮度低于阈值的像素）
                    for (let x = 0; x < w; x++) {
                        const i = (y * w + x) * 4;
                        if (data[i] + data[i + 1] + data[i + 2] < 60) data[i + 3] = 0;
                    }
                }

                tctx.putImageData(imageData, 0, 0);
            } catch (e) {
                // 读取失败则保留原始抠图（仍可用，只是边缘效果略差）
                console.warn("xfCaptcha: 滑块图层边缘处理失败", e);
            }

            xfCaptcha._slidePrepared = temp;
        },

        /**
         * 重置状态
         */
        _reset() {
            xfCaptcha._markOffset = 0;
            xfCaptcha._token = null;
            xfCaptcha._clickPoints = [];
            xfCaptcha._clickCount = 0;

            xfCaptcha._clearClickMarkers();

            if (xfCaptcha._captchaType === 'slide') {
                xfCaptcha._drawBg();
                xfCaptcha._drawMark();

                const block = xfCaptcha._el(".captcha_slide_block", "_blockEl");
                if (block) block.style.transform = "translateX(0px)";
            }
        },

        /**
         * 显示验证码弹窗
         */
        show() {
            xfCaptcha._result = false;
            xfCaptcha._token = null;
            xfCaptcha._errorCount = 0;
            xfCaptcha._markOffset = 0;
            xfCaptcha._doing = false;
            xfCaptcha._isMoving = false;
            xfCaptcha._slideTrack = [];
            xfCaptcha._clickPoints = [];
            xfCaptcha._clickCount = 0;

            if (xfCaptcha._rafId) {
                cancelAnimationFrame(xfCaptcha._rafId);
                xfCaptcha._rafId = null;
            }

            // 清理可能残留的键盘提交监听，避免上一轮未松手的 keyup 影响新一轮验证
            if (xfCaptcha._keyUpHandler) {
                document.removeEventListener("keyup", xfCaptcha._keyUpHandler);
                xfCaptcha._keyUpHandler = null;
            }

            if (xfCaptcha._successHideTimer) {
                clearTimeout(xfCaptcha._successHideTimer);
                xfCaptcha._successHideTimer = null;
            }

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

            const successMsg = document.querySelector(".captcha_msg_ok");
            if (successMsg) successMsg.style.display = "none";

            xfCaptcha.refresh();
            xfCaptcha._container = handleDom;

            const bg = document.getElementById("captcha_div_bg");
            const modal = document.getElementById("captcha_div");
            if (bg) bg.style.display = "block";
            if (modal) modal.style.display = "block";

            // 无障碍 / 体验增强：锁定背景滚动并聚焦弹窗
            xfCaptcha._lockBodyScroll(true);
            xfCaptcha._isOpen = true;
            if (modal) {
                try { modal.focus(); } catch (e) { /* 某些环境不支持 */ }
            }

            // 触发 onShow 回调
            if (typeof xfCaptcha._onShow === "function") {
                xfCaptcha._onShow();
            }
        },

        /**
         * 锁定 / 解锁背景滚动（弹窗打开时防止背景穿透滚动）
         *
         * @param {boolean} lock 是否锁定
         */
        _lockBodyScroll(lock) {
            try {
                if (lock) {
                    if (xfCaptcha._bodyOverflow === undefined) {
                        xfCaptcha._bodyOverflow = document.body.style.overflow;
                    }
                    document.body.style.overflow = "hidden";
                } else if (xfCaptcha._bodyOverflow !== undefined) {
                    document.body.style.overflow = xfCaptcha._bodyOverflow;
                    xfCaptcha._bodyOverflow = undefined;
                }
            } catch (e) { /* 忽略 */ }
        },

        /**
         * 关闭按钮回调：先触发 onClose（用户主动关闭），再隐藏弹窗
         */
        _handleClose() {
            if (typeof xfCaptcha._onClose === "function") {
                xfCaptcha._onClose();
            }
            xfCaptcha.hide();
        },

        /**
         * 隐藏验证码弹窗
         *
         * 无论因何种原因隐藏（成功自动关闭、点击关闭、重置），都统一在此：
         * 解除背景滚动锁定、标记弹窗已关闭，并触发 onHide 回调。
         */
        hide() {
            const bg = document.getElementById("captcha_div_bg");
            const modal = document.getElementById("captcha_div");
            if (bg) bg.style.display = "none";
            if (modal) modal.style.display = "none";

            // 兜底：清理可能残留的拖动全局监听与键盘提交监听，避免隐藏后误触发
            xfCaptcha._unbindDragListeners();
            if (xfCaptcha._keyUpHandler) {
                document.removeEventListener("keyup", xfCaptcha._keyUpHandler);
                xfCaptcha._keyUpHandler = null;
            }

            // 无障碍 / 体验收尾：恢复背景滚动并标记弹窗已关闭
            xfCaptcha._isOpen = false;
            xfCaptcha._lockBodyScroll(false);

            // 触发 onHide 回调（任意原因导致弹窗隐藏都会触发）
            if (typeof xfCaptcha._onHide === "function") {
                xfCaptcha._onHide();
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
                <div class="captcha_div" id="captcha_div" role="dialog" aria-modal="true" aria-label="人机验证" tabindex="-1">
                    <div class="captcha_loading"></div>
                    <canvas class="captcha_canvas_bg" width="240" height="150" aria-label="验证码图片"></canvas>
                    <canvas class="captcha_canvas_mark" width="240" height="150"></canvas>
                    <div class="captcha_hlight"></div>
                    <div class="captcha_msg_error" role="alert"></div>
                    <div class="captcha_msg_ok" role="status"></div>
                    <div class="captcha_slide">
                        <div class="captcha_slide_block" tabindex="0" role="slider" aria-label="拖动滑块完成拼图" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
                        <div class="captcha_slide_text">${xfCaptcha._options.slideText}</div>
                    </div>
                    <div class="captcha_tools">
                        <a href="http://yoc.cn" target="_blank" class="captcha_copyright" title="来源 yoc.cn">© yoc.cn</a>
                        <div class="captcha_tools_actions">
                            ${xfCaptcha._options.showRefresh ? '<div class="captcha_refresh" role="button" tabindex="0" title="刷新" aria-label="刷新验证码"></div>' : ''}
                            ${xfCaptcha._options.showClose ? '<div class="captcha_close" role="button" tabindex="0" title="关闭" aria-label="关闭"></div>' : ''}
                        </div>
                    </div>
                </div>
            `;

            appendHTML(document.body, html);
        },

        /**
         * 刷新验证码
         *
         * @param {boolean} switchType 是否强制切换验证码类型（仅用户手动点击刷新按钮时传 true；
         *                              失败自动刷新 / show / reset 保持当前类型，避免打断用户操作）
         */
        refresh(switchType) {
            xfCaptcha._errorCount = 0;
            xfCaptcha._isDrawBg = false;
            xfCaptcha._result = false;
            xfCaptcha._imgLoaded = false;
            xfCaptcha._slidePrepared = null;
            // 重置滑块位移/缺口纵坐标，避免上一次失败拖拽的残留偏移
            // 使新验证码的滑块标记停留在错误位置
            xfCaptcha._markOffset = 0;
            xfCaptcha._markTopY = 0;
            xfCaptcha._clickPoints = [];
            xfCaptcha._clickCount = 0;
            xfCaptcha._token = null;
            xfCaptcha._doing = false;
            xfCaptcha._slideTrack = [];
            xfCaptcha._clickStartTime = Date.now();
            xfCaptcha._clearClickMarkers();

            const bgCanvas = document.querySelector(".captcha_canvas_bg");
            const markCanvas = document.querySelector(".captcha_canvas_mark");
            if (bgCanvas) {
                bgCanvas.style.display = "none";
                const ctx = bgCanvas.getContext("2d");
                ctx.clearRect(0, 0, bgCanvas.width, bgCanvas.height);
            }
            if (markCanvas) {
                markCanvas.style.display = "none";
                const ctx = markCanvas.getContext("2d");
                ctx.clearRect(0, 0, markCanvas.width, markCanvas.height);
            }

            const loading = document.querySelector(".captcha_loading");
            if (loading) {
                loading.style.display = "flex";
                loading.innerHTML = "";
                removeClass(loading, "captcha_loading_error");
            }

            // 添加随机种子参数破坏浏览器缓存；仅在手动刷新时附带 type_switch 强制切换类型
            const seed = Math.random().toString(36).substring(2, 15);
            const url = xfCaptcha._options.dataUrl + "?t=" + Date.now() + "&_s=" + seed
                + (switchType ? "&type_switch=1" : "");
            const ajax = new AjaxRequest();
            ajax.request(
                "GET",
                url,
                {
                    success: function (response) {
                        try {
                            const data = JSON.parse(response);
                            if (data.success) {
                                // 先重置状态
                                xfCaptcha._captchaType = data.type;
                                xfCaptcha._imgWidth = data.bg_width || 240;
                                xfCaptcha._imgHeight = data.bg_height || 150;
                                xfCaptcha._options.charCount = data.char_count || 4;

                                if (data.type === 'slide') {
                                    xfCaptcha._markWidth = data.mark_width || 50;
                                    xfCaptcha._markHeight = data.mark_height || 50;
                                    // 记录缺口初始位置（图片像素），仅用于轨迹纵坐标基准；
                                    // 横坐标（正确偏移量答案）绝不接收，否则机器人可直接绕过拼图
                                    xfCaptcha._markTopY = typeof data.pos_y === 'number' ? data.pos_y : 0;
                                }

                                // 更新 UI 前先清理
                                xfCaptcha._updateUIForType(data.type);

                                // 触发 onRefresh 回调（新验证码数据已就绪，可携带当前类型与是否强制切换）
                                if (typeof xfCaptcha._onRefresh === "function") {
                                    xfCaptcha._onRefresh(data.type, switchType === true);
                                }

                                // 更新提示文字
                                const slideText = document.querySelector(".captcha_slide_text");
                                if (slideText) {
                                    if (data.type === 'click') {
                                        slideText.innerHTML = data.hint;
                                        slideText.style.color = "";
                                    } else {
                                        slideText.innerHTML = xfCaptcha._options.slideText;
                                        slideText.style.color = "";
                                    }
                                    slideText.style.display = "block";
                                }

                                xfCaptcha._img = new Image();
                                xfCaptcha._img.onload = function () {
                                    // 先显示画布再测量显示尺寸：display:none 时 getBoundingClientRect()
                                    // 恒为 0，会导致缩放比例恒为 1、坐标换算失真。
                                    // 此阶段 loading 遮罩仍覆盖画布，不会产生可见闪烁。
                                    if (bgCanvas) bgCanvas.style.display = "block";
                                    if (markCanvas) markCanvas.style.display = "block";
                                    if (bgCanvas && bgCanvas.width > 0) {
                                        const rect = bgCanvas.getBoundingClientRect();
                                        const rw = rect.width || bgCanvas.width;
                                        xfCaptcha._imgScale = rw / bgCanvas.width;
                                        xfCaptcha._imgInvScale = bgCanvas.width / rw;
                                    }
                                    xfCaptcha._drawFullBg();
                                    const markCtx = markCanvas.getContext("2d");
                                    markCtx.clearRect(0, 0, markCanvas.width, markCanvas.height);
                                    xfCaptcha._imgLoaded = true;
                                    // 图片加载完成后预生成滑块图层（仅一次，供拖拽时直接使用）
                                    xfCaptcha._prepareSlideImage();
                                    if (loading) {
                                        loading.style.display = "none";
                                        loading.innerHTML = "";
                                        removeClass(loading, "captcha_loading_error");
                                    }
                                    const block = document.querySelector(".captcha_slide_block");
                                    if (block) block.style.transform = "translateX(0px)";
                                };
                                xfCaptcha._img.onerror = function () {
                                    if (loading) {
                                        loading.innerHTML = '<div style="text-align:center;line-height:1.6;">加载失败<br>请刷新重试</div>';
                                        addClass(loading, "captcha_loading_error");
                                    }
                                };
                                xfCaptcha._img.src = data.image_base64;
                            } else {
                                throw new Error(data.message || '获取验证码失败');
                            }
                        } catch (e) {
                            console.error('xfCaptcha: 解析验证码数据失败', e);
                            if (loading) {
                                loading.innerHTML = '<div style="text-align:center;line-height:1.6;">加载失败<br>请刷新重试</div>';
                                addClass(loading, "captcha_loading_error");
                            }
                        }
                    },
                    failure: function () {
                        if (loading) {
                            loading.innerHTML = '<div style="text-align:center;line-height:1.6;">加载失败<br>请刷新重试</div>';
                            addClass(loading, "captcha_loading_error");
                        }
                    }
                }
            );
        },

        /**
         * 更新 UI 以适配当前验证码类型
         */
        _updateUIForType(type) {
            const slideArea = document.querySelector(".captcha_slide");
            const slideBlock = document.querySelector(".captcha_slide_block");
            const slideText = document.querySelector(".captcha_slide_text");
            const bgCanvas = document.querySelector(".captcha_canvas_bg");
            const modal = document.getElementById("captcha_div");

            if (type === 'click') {
                // 点击验证码：完全隐藏滑动组件，仅保留提示区域
                if (slideBlock) slideBlock.style.display = "none";
                if (slideArea) {
                    slideArea.style.display = "block";
                    slideArea.style.height = "auto";
                    slideArea.style.minHeight = "0";
                    slideArea.style.padding = "8px 0";
                    slideArea.style.background = "transparent";
                    slideArea.style.border = "none";
                    slideArea.style.boxShadow = "none";
                }

                if (bgCanvas) {
                    bgCanvas.style.pointerEvents = "auto";
                    bgCanvas.style.cursor = "pointer";
                }
                
                // 设置 modal 属性用于样式控制
                if (modal) modal.setAttribute('data-type', 'click');
            } else {
                // 滑动验证码：显示滑动条
                if (slideArea) {
                    slideArea.style.display = "block";
                    slideArea.style.opacity = "1";
                    slideArea.style.pointerEvents = "auto";
                    slideArea.style.height = "";
                    slideArea.style.minHeight = "";
                    slideArea.style.padding = "";
                    slideArea.style.background = "";
                    slideArea.style.border = "";
                    slideArea.style.boxShadow = "";
                }
                if (slideBlock) slideBlock.style.display = "block";

                if (bgCanvas) {
                    bgCanvas.style.pointerEvents = "none";
                    bgCanvas.style.cursor = "default";
                }

                if (slideText) {
                    slideText.innerHTML = xfCaptcha._options.slideText;
                    slideText.style.display = "block";
                }
                
                // 设置 modal 属性
                if (modal) modal.setAttribute('data-type', 'slide');
            }
        },

        /**
         * 初始化验证码
         *
         * @param {object} options 配置选项
         * @returns {object} xfCaptcha 对象
         */
        init(options) {
            xfCaptcha._options = Object.assign({}, xfCaptcha._defaults, options);

            xfCaptcha._createHTML();

            // 应用主题：显式 light/dark 同时写到 <html> 上，避免被系统暗色媒体查询覆盖；auto 则不写，由媒体查询接管
            const theme = xfCaptcha._options.theme || 'auto';
            const root = document.documentElement;
            if (theme === 'auto') {
                root.removeAttribute('data-theme');
            } else {
                root.setAttribute('data-theme', theme);
            }

            const modal = document.getElementById("captcha_div");
            if (modal) {
                modal.setAttribute('data-theme', theme);
            }

            // 滑动验证码事件绑定（mousedown/touchstart 负责在拖动开始时按需绑定 document 全局监听，
            // 拖动结束即解绑，避免全局常驻 mousemove 监听拖慢整个页面）
            const block = document.querySelector(".captcha_slide_block");
            if (block) {
                xfCaptcha._bind(block, "mousedown", xfCaptcha._blockStartMove, { passive: false });
                xfCaptcha._bind(block, "touchstart", xfCaptcha._blockStartMove, { passive: false });
                // 无障碍：键盘方向键 / Home / End 拖动滑块
                xfCaptcha._bind(block, "keydown", xfCaptcha._blockOnKey, false);
            }

            // 点击验证码事件绑定
            const bgCanvas = document.querySelector(".captcha_canvas_bg");
            if (bgCanvas) {
                xfCaptcha._bind(bgCanvas, "click", xfCaptcha._handleClickCaptcha, { passive: false });
                xfCaptcha._bind(bgCanvas, "touchstart", xfCaptcha._handleClickCaptcha, { passive: false });
            }

            const closeBtn = document.querySelector(".captcha_close");
            if (closeBtn) {
                // 关闭按钮：先触发 onClose（用户主动关闭）再隐藏弹窗（hide 内部触发 onHide）
                xfCaptcha._bind(closeBtn, "click", xfCaptcha._handleClose);
            }

            const refreshBtn = document.querySelector(".captcha_refresh");
            if (refreshBtn) {
                // 手动刷新：both 模式下强制切换验证码类型（type_switch=1）
                xfCaptcha._bind(refreshBtn, "click", function () {
                    xfCaptcha.refresh(true);
                });
            }

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
         * 设置弹窗打开回调
         *
         * @param {function} fn 回调函数
         * @returns {object} xfCaptcha 对象
         */
        onShow(fn) {
            xfCaptcha._onShow = fn;
            return xfCaptcha;
        },

        /**
         * 设置弹窗关闭回调
         *
         * @param {function} fn 回调函数
         * @returns {object} xfCaptcha 对象
         */
        onHide(fn) {
            xfCaptcha._onHide = fn;
            return xfCaptcha;
        },

        /**
         * 设置刷新回调
         *
         * @param {function} fn 回调函数
         * @returns {object} xfCaptcha 对象
         */
        onRefresh(fn) {
            xfCaptcha._onRefresh = fn;
            return xfCaptcha;
        },

        /**
         * 设置重置回调
         *
         * @param {function} fn 回调函数
         * @returns {object} xfCaptcha 对象
         */
        onReset(fn) {
            xfCaptcha._onReset = fn;
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
         * 获取用户点击坐标（点击验证码）
         *
         * 每个元素为 {x, y, t}，x/y 为图片像素坐标，t 为相对首点的耗时（毫秒）。
         * 双重验证模式下前端已上报并通过，这里返回空数组即可；
         * 纯后端模式如需在表单提交时附带坐标可调用本方法。
         *
         * @returns {Array}
         */
        getClickPoints() {
            return xfCaptcha._clickPoints || [];
        },

        /**
         * 动态切换主题
         *
         * @param {string} theme 主题名称
         * @returns {object} xfCaptcha 对象
         */
        setTheme(theme) {
            xfCaptcha._options.theme = theme || 'auto';
            const root = document.documentElement;
            if (xfCaptcha._options.theme === 'auto') {
                root.removeAttribute('data-theme');
            } else {
                root.setAttribute('data-theme', xfCaptcha._options.theme);
            }
            const modal = document.getElementById("captcha_div");
            if (modal) {
                modal.setAttribute('data-theme', xfCaptcha._options.theme);
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
            xfCaptcha.hide();

            if (xfCaptcha._rafId) {
                cancelAnimationFrame(xfCaptcha._rafId);
                xfCaptcha._rafId = null;
            }

            // 清理可能残留的键盘提交监听，避免上一轮未松手的 keyup 影响新一轮验证
            if (xfCaptcha._keyUpHandler) {
                document.removeEventListener("keyup", xfCaptcha._keyUpHandler);
                xfCaptcha._keyUpHandler = null;
            }
            if (xfCaptcha._successHideTimer) {
                clearTimeout(xfCaptcha._successHideTimer);
                xfCaptcha._successHideTimer = null;
            }

            xfCaptcha._result = false;
            xfCaptcha._token = null;
            xfCaptcha._errorCount = 0;
            xfCaptcha._markOffset = 0;
            xfCaptcha._doing = false;
            xfCaptcha._isMoving = false;
            xfCaptcha._slideTrack = [];
            xfCaptcha._clickPoints = [];
            xfCaptcha._clickCount = 0;

            if (xfCaptcha._options.inputName) {
                const existingInput = document.querySelector('input[name="' + xfCaptcha._options.inputName + '"]');
                if (existingInput) {
                    existingInput.remove();
                }
            }

            const triggers = document.querySelectorAll(xfCaptcha._options.handleDom);
            triggers.forEach((elem) => {
                elem.innerHTML = xfCaptcha._options.placeholder;
                removeClass(elem, "verified");
                if (xfCaptcha._options.showRipple) {
                    addClass(elem, "captcha_ripple");
                }
            });

            xfCaptcha.refresh();

            // 触发 onReset 回调（验证码已复位到初始可验证状态）
            if (typeof xfCaptcha._onReset === "function") {
                xfCaptcha._onReset();
            }

            return xfCaptcha;
        },
    };

    // 暴露到全局
    window.xfCaptcha = xfCaptcha;

    /**
     * 统一重置契约：登录 / 注册等使用本验证码的表单，在「提交并收到响应数据后，
     * 若 auth 相关处理结果不成功」需将验证码复位到初始状态，方便用户重试。
     *
     * 约定：承载本验证码的表单在提交失败时应派发自定义事件 `xf.form.error`
     * （xfadmin 的 data-xf-remote 表单在响应非成功时即派发此事件，bubbles 冒泡至 document）。
     * 此处统一监听该事件，仅当出错表单内确实包含本验证码组件时才复位，避免误伤。
     * 非 AJAX（原生表单提交整页刷新）场景下验证码会随页面重新渲染而天然复位，无需处理。
     */
    document.addEventListener('xf.form.error', function (e) {
        var form = e.target;
        if (!form || typeof form.querySelector !== 'function') return;
        if (!form.querySelector('.xf-captcha')) return;
        try {
            if (typeof xfCaptcha.reset === 'function') xfCaptcha.reset();
        } catch (err) {
            /* 复位失败不应阻断页面其他逻辑 */
        }
    });

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
