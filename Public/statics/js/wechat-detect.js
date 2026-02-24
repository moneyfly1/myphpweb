/**
 * 微信/QQ内置浏览器检测脚本
 * 检测用户是否在微信或QQ内置浏览器中访问，如果是则提示跳转到外部浏览器
 */
(function() {
    'use strict';
    
    // 检测是否为移动设备
    function isMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }
    
    // 检测是否在微信内置浏览器
    function isWechat() {
        return /micromessenger/i.test(navigator.userAgent);
    }
    
    // 检测是否在QQ内置浏览器
    function isQQ() {
        const ua = navigator.userAgent.toLowerCase();
        return (ua.indexOf('qq/') > -1 || ua.indexOf('qzone/') > -1) && ua.indexOf('mqqbrowser') === -1;
    }
    
    // 检测是否在QQ浏览器
    function isQQBrowser() {
        return /mqqbrowser/i.test(navigator.userAgent);
    }
    
    // 检测是否在QQ空间
    function isQZone() {
        return /qzone/i.test(navigator.userAgent);
    }
    
    // 创建提示遮罩层
    function createMask() {
        const mask = document.createElement('div');
        mask.id = 'wechat-mask';
        mask.style.cssText = `
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: rgba(0, 0, 0, 0.9) !important;
            z-index: 2147483647 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        `;
        
        const content = document.createElement('div');
        content.style.cssText = `
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            margin: 20px;
            max-width: 350px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
        `;
        
        // 添加动画样式
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-50px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
        
        let browserName = '';
        let instruction = '';
        
        if (isWechat()) {
            browserName = '微信';
            instruction = '请点击右上角 "···" 菜单，选择 "在浏览器中打开"';
        } else if (isQQ() || isQZone()) {
            browserName = 'QQ';
            instruction = '请复制链接地址，然后在手机浏览器中打开<br>或者点击右上角菜单选择 "用浏览器打开"';
        } else if (isQQBrowser()) {
            browserName = 'QQ浏览器';
            instruction = '请点击右上角菜单，选择 "在其他浏览器中打开"';
        }
        
        content.innerHTML = `
            <div style="font-size: 48px; margin-bottom: 20px;">⚠️</div>
            <h3 style="color: #333; margin-bottom: 15px; font-size: 18px;">检测到您正在${browserName}中访问</h3>
            <p style="color: #666; line-height: 1.6; margin-bottom: 20px; font-size: 14px;">
                为了获得更好的浏览体验和功能支持，建议您使用外部浏览器访问本站点。
            </p>
            <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p style="color: #333; font-size: 13px; margin: 0;">
                    <strong>操作步骤：</strong><br>
                    ${instruction}
                </p>
            </div>
            <button onclick="document.getElementById('wechat-mask').style.display='none'" 
                    style="
                        background: #007AFF;
                        color: white;
                        border: none;
                        padding: 12px 24px;
                        border-radius: 6px;
                        font-size: 14px;
                        cursor: pointer;
                        margin-right: 10px;
                    ">我知道了</button>
            <button onclick="window.location.reload()" 
                    style="
                        background: #34C759;
                        color: white;
                        border: none;
                        padding: 12px 24px;
                        border-radius: 6px;
                        font-size: 14px;
                        cursor: pointer;
                    ">刷新页面</button>
        `;
        
        mask.appendChild(content);
        return mask;
    }
    
    // 显示提示
    function showTip() {
        // 避免重复显示
        if (document.getElementById('wechat-mask')) {
            return;
        }
        
        const mask = createMask();
        document.body.appendChild(mask);
        
        // 记录用户已经看过提示
        sessionStorage.setItem('wechat_tip_shown', 'true');
    }
    
    // 主检测函数
    function detectAndShow() {
        // 只在移动设备上检测
        if (!isMobile()) {
            return;
        }
        
        // 检查是否已经显示过提示（本次会话）
        if (sessionStorage.getItem('wechat_tip_shown')) {
            return;
        }
        
        // 调试信息
        console.log('User Agent:', navigator.userAgent);
        console.log('isWechat:', isWechat());
        console.log('isQQ:', isQQ());
        console.log('isQZone:', isQZone());
        console.log('isQQBrowser:', isQQBrowser());
        
        // 检测微信或QQ内置浏览器
        if (isWechat() || isQQ() || isQZone() || isQQBrowser()) {
            // 强制显示，覆盖系统提示
            setTimeout(function() {
                // 尝试阻止系统默认提示
                document.body.style.overflow = 'hidden';
                showTip();
            }, 500);
        }
    }
    
    // 立即执行检测，不等待页面加载完成
    detectAndShow();
    
    // 页面加载完成后再次执行检测
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', detectAndShow);
    } else {
        setTimeout(detectAndShow, 100);
    }
    
    // 窗口加载完成后再次检测
    window.addEventListener('load', detectAndShow);
    
})();