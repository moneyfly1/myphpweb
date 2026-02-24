/**
 * 全站统一前端封装：请求、提示、表单提交
 * 依赖：jQuery（全局 $）
 */
(function (global) {
    'use strict';

    var hasLayer = typeof global.layer !== 'undefined';

    function toast(msg, type) {
        type = type || 'info';
        if (hasLayer && global.layer && global.layer.msg) {
            var icon = type === 'success' ? 1 : type === 'error' ? 2 : 0;
            global.layer.msg(msg, { icon: icon, time: 2500 });
        } else {
            global.alert(msg);
        }
    }

    function alert(msg) {
        if (hasLayer && global.layer && global.layer.alert) {
            global.layer.alert(msg);
        } else {
            global.alert(msg);
        }
    }

    function confirm(msg, title) {
        title = title || '确认';
        return new Promise(function (resolve, reject) {
            if (hasLayer && global.layer && global.layer.confirm) {
                global.layer.confirm(msg, { title: title, icon: 3 }, function (index) {
                    global.layer.close(index);
                    resolve(true);
                }, function () {
                    resolve(false);
                });
            } else {
                resolve(global.confirm(msg));
            }
        });
    }

    /**
     * 统一 Ajax 请求
     * @param {string} url
     * @param {object} options - { method, data, success, error, complete }
     * @returns {$.Deferred}
     */
    function request(url, options) {
        options = options || {};
        var method = (options.method || 'GET').toUpperCase();
        var data = options.data || {};
        var success = options.success || function () {};
        var error = options.error || function () {};
        var complete = options.complete || function () {};

        return $.ajax({
            url: url,
            type: method,
            data: data,
            dataType: 'json'
        }).done(function (res) {
            if (res && (res.code === 0 || res.status === 'success' || res.success === true)) {
                success(res);
            } else {
                var msg = (res && (res.msg || res.message)) || '操作失败，请重试';
                toast(msg, 'error');
                error(res);
            }
        }).fail(function (xhr, status, err) {
            var msg = '网络错误，请重试';
            if (xhr && xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) {
                msg = xhr.responseJSON.msg || xhr.responseJSON.message;
            }
            toast(msg, 'error');
            error(xhr, status, err);
        }).always(complete);
    }

    /**
     * 表单 Ajax 提交：防重复、统一 loading、成功/失败提示
     * @param {HTMLFormElement|jQuery} form
     * @param {object} options - { url, success, error, successMsg, successRedirect }
     */
    function formSubmit(form, options) {
        options = options || {};
        var $form = form instanceof jQuery ? form : $(form);
        var url = options.url || $form.attr('action') || location.href;
        var successMsg = options.successMsg || '操作成功';
        var successRedirect = options.successRedirect;
        var submitBtn = $form.find('button[type="submit"]').add($form.find('input[type="submit"]'));
        var originalText = submitBtn.length ? submitBtn.first().html() || submitBtn.first().val() : '';

        $form.off('submit.app').on('submit.app', function (e) {
            e.preventDefault();
            if (submitBtn.length && submitBtn.first().prop('disabled')) return;

            if (submitBtn.length) {
                submitBtn.prop('disabled', true);
                submitBtn.first().html ? submitBtn.first().html('<span>提交中...</span>') : submitBtn.first().val('提交中...');
            }

            $.ajax({
                url: url,
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (res) {
                if (res && (res.code === 0 || res.status === 'success' || res.success === true)) {
                    toast(successMsg, 'success');
                    if (typeof options.success === 'function') options.success(res);
                    if (successRedirect) {
                        setTimeout(function () { location.href = successRedirect; }, 1000);
                    }
                } else {
                    var msg = (res && (res.msg || res.message)) || '操作失败，请重试';
                    toast(msg, 'error');
                    if (typeof options.error === 'function') options.error(res);
                }
            }).fail(function (xhr) {
                var msg = (xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) || '网络错误，请重试';
                toast(msg, 'error');
                if (typeof options.error === 'function') options.error(xhr);
            }).always(function () {
                if (submitBtn.length) {
                    submitBtn.prop('disabled', false);
                    if (originalText) (submitBtn.first().html ? submitBtn.first().html(originalText) : submitBtn.first().val(originalText));
                }
                if (typeof options.complete === 'function') options.complete();
            });
        });
    }

    /* ---------- 导航栏交互 ---------- */
    function initNav() {
        // 移动端菜单切换
        var toggle = document.getElementById('nav-toggle');
        var mobile = document.getElementById('nav-mobile');
        if (toggle && mobile) {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                mobile.classList.toggle('show');
            });
        }
        // 用户下拉菜单
        var btn = document.getElementById('userMenuBtn');
        var menu = document.getElementById('userMenu');
        if (btn && menu) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('show');
            });
        }
        // 点击外部关闭
        document.addEventListener('click', function (e) {
            if (menu && btn && !btn.contains(e.target)) menu.classList.remove('show');
            if (mobile && toggle && !toggle.contains(e.target) && !mobile.contains(e.target)) mobile.classList.remove('show');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNav);
    } else {
        initNav();
    }

    global.App = {
        toast: toast,
        alert: alert,
        confirm: confirm,
        request: request,
        formSubmit: formSubmit
    };
})(typeof window !== 'undefined' ? window : this);
