<?php
/**
 * 邮件模板类
 * 用于生成美观的HTML邮件模板
 */
class EmailTemplate {
    
    /**
     * 基础邮件模板
     */
    private static function getBaseTemplate($title, $content, $footerText = '') {
        $currentYear = date('Y');
        $siteName = env('SITE_NAME') ?: '订阅服务';
        
        return '
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }
        .header .subtitle {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 40px 30px;
        }
        .content h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: 400;
        }
        .content p {
            line-height: 1.6;
            margin-bottom: 16px;
            color: #555;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .info-table th,
        .info-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .info-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            width: 30%;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            margin: 20px 0;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .success-box {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            color: #155724;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .footer p {
            margin: 5px 0;
            color: #6c757d;
            font-size: 14px;
        }
        .footer .social-links {
            margin: 20px 0;
        }
        .footer .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #6c757d;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }
            .content {
                padding: 20px !important;
            }
            .header {
                padding: 20px !important;
            }
            .header h1 {
                font-size: 24px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>' . htmlspecialchars($siteName) . '</h1>
            <p class="subtitle">' . htmlspecialchars($title) . '</p>
        </div>
        <div class="content">
            ' . $content . '
        </div>
        <div class="footer">
            <p><strong>' . htmlspecialchars($siteName) . '</strong></p>
            <p>' . ($footerText ?: '感谢您选择我们的服务') . '</p>
            <p style="font-size: 12px; color: #999;">此邮件由系统自动发送，请勿直接回复</p>
            <p style="font-size: 12px; color: #999;">© ' . $currentYear . ' ' . htmlspecialchars($siteName) . '. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * 用户注册激活邮件模板
     */
    public static function getActivationTemplate($username, $activationLink) {
        $title = '账户激活';
        $content = '
            <h2>欢迎注册！</h2>
            <p>亲爱的用户 <strong>' . htmlspecialchars($username) . '</strong>，</p>
            <p>感谢您注册我们的服务！为了确保账户安全，请点击下方按钮激活您的账户：</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . htmlspecialchars($activationLink) . '" class="btn">立即激活账户</a>
            </div>
            
            <div class="info-box">
                <p><strong>重要提醒：</strong></p>
                <ul>
                    <li>此激活链接仅限本次使用</li>
                    <li>如果按钮无法点击，请复制以下链接到浏览器：</li>
                    <li style="word-break: break-all; color: #667eea;">' . htmlspecialchars($activationLink) . '</li>
                </ul>
            </div>
            
            <p>激活成功后，您将可以享受我们提供的所有服务功能。</p>';
            
        return self::getBaseTemplate($title, $content, '开启您的专属网络体验');
    }
    
    /**
     * 订阅地址通知邮件模板
     */
    public static function getSubscriptionTemplate($username, $mobileUrl, $clashUrl, $expireDate = null) {
        $title = '订阅地址通知';
        $expireInfo = $expireDate ? '<tr><th>到期时间</th><td style="color: #e74c3c; font-weight: bold;">' . date('Y年m月d日', $expireDate) . '</td></tr>' : '';
        
        $content = '
            <h2>您的专属订阅地址</h2>
            <p>亲爱的用户，</p>
            <p>您的订阅地址已生成完成，请查收以下信息：</p>
            
            <table class="info-table">
                <tr>
                    <th>用户账号</th>
                    <td>' . htmlspecialchars($username) . '</td>
                </tr>
                <tr>
                    <th>通用订阅地址</th>
                    <td style="word-break: break-all; color: #667eea;">' . htmlspecialchars($mobileUrl) . '</td>
                </tr>
                <tr>
                    <th>Clash专用地址</th>
                    <td style="word-break: break-all; color: #667eea;">' . htmlspecialchars($clashUrl) . '</td>
                </tr>
                ' . $expireInfo . '
            </table>
            
            <div class="warning-box">
                <p><strong>⚠️ 安全提醒：</strong></p>
                <ul>
                    <li>请妥善保管您的订阅地址，切勿分享给他人</li>
                    <li>如发现地址泄露，请及时联系客服重置</li>
                    <li>建议定期更换订阅地址以确保安全</li>
                </ul>
            </div>
            
            <h3>使用说明：</h3>
            <ol>
                <li>复制对应的订阅地址</li>
                <li>在您的客户端中添加订阅</li>
                <li>更新订阅配置即可开始使用</li>
            </ol>';
            
        return self::getBaseTemplate($title, $content, '享受高速稳定的网络服务');
    }
    
    /**
     * 订单通知邮件模板
     */
    public static function getOrderTemplate($orderNo, $planName, $price, $duration, $status = '已支付', $username = '', $mobileUrl = '', $clashUrl = '', $expireDate = '', $isAdmin = false) {
        $title = '订单支付成功通知';
        $statusColor = $status === '已支付' ? '#28a745' : '#ffc107';
        $siteDomain = self::getSiteDomain();
        
        if ($isAdmin) {
            $title = '新订单通知';
            $content = '
                <h2>📋 新订单支付成功</h2>
                <p>管理员您好，</p>
                <p>有用户完成了订单支付，以下是订单详细信息：</p>
                
                <table class="info-table">
                    <tr>
                        <th>用户账号</th>
                        <td>' . htmlspecialchars($username) . '</td>
                    </tr>
                    <tr>
                        <th>订单编号</th>
                        <td>' . htmlspecialchars($orderNo) . '</td>
                    </tr>
                    <tr>
                        <th>套餐名称</th>
                        <td>' . htmlspecialchars($planName) . '</td>
                    </tr>
                    <tr>
                        <th>订单金额</th>
                        <td style="color: #e74c3c; font-weight: bold;">¥' . htmlspecialchars($price) . '</td>
                    </tr>
                    <tr>
                        <th>服务时长</th>
                        <td>' . htmlspecialchars($duration) . '</td>
                    </tr>
                    <tr>
                        <th>订单状态</th>
                        <td style="color: ' . $statusColor . '; font-weight: bold;">' . htmlspecialchars($status) . '</td>
                    </tr>
                    <tr>
                        <th>处理时间</th>
                        <td>' . date('Y年m月d日 H:i:s') . '</td>
                    </tr>
                    ' . ($expireDate ? '<tr><th>到期时间</th><td style="color: #e74c3c; font-weight: bold;">' . $expireDate . '</td></tr>' : '') . '
                </table>
                
                <div class="success-box">
                    <p><strong>✅ 订单处理完成！</strong></p>
                    <p>用户服务已自动开通，请关注后续使用情况。</p>
                </div>';
        } else {
            $content = '
                <h2>🎉 订单支付成功！</h2>
                <p>亲爱的用户 <strong>' . htmlspecialchars($username) . '</strong>，</p>
                <p>恭喜您！您的订单已支付成功，服务已自动开通。以下是详细信息：</p>
                
                <table class="info-table">
                    <tr>
                        <th>订单编号</th>
                        <td>' . htmlspecialchars($orderNo) . '</td>
                    </tr>
                    <tr>
                        <th>套餐名称</th>
                        <td>' . htmlspecialchars($planName) . '</td>
                    </tr>
                    <tr>
                        <th>订单金额</th>
                        <td style="color: #e74c3c; font-weight: bold;">¥' . htmlspecialchars($price) . '</td>
                    </tr>
                    <tr>
                        <th>服务时长</th>
                        <td>' . htmlspecialchars($duration) . '</td>
                    </tr>
                    <tr>
                        <th>订单状态</th>
                        <td style="color: ' . $statusColor . '; font-weight: bold;">' . htmlspecialchars($status) . '</td>
                    </tr>
                    <tr>
                        <th>处理时间</th>
                        <td>' . date('Y年m月d日 H:i:s') . '</td>
                    </tr>
                    ' . ($expireDate ? '<tr><th>到期时间</th><td style="color: #e74c3c; font-weight: bold;">' . $expireDate . '</td></tr>' : '') . '
                </table>
                
                <div class="success-box">
                    <p><strong>✅ 服务已开通！</strong></p>
                    <p>您的订阅服务已自动开通，现在可以开始使用了！</p>
                </div>';
        }
            
        // 只有用户邮件才显示订阅地址和使用方法
        if (!$isAdmin) {
            // 如果有订阅地址，添加订阅信息
            if ($mobileUrl || $clashUrl) {
                $content .= '
                <h3>📱 您的订阅地址</h3>
                <div class="info-box">
                    <p><strong>🔗 通用订阅地址（推荐）：</strong></p>
                    <p style="margin-bottom: 5px; color: #666; font-size: 14px;">适用于shadowrocket 和 V2rayN客户端，包括手机和电脑</p>
                    <p style="word-break: break-all; color: #667eea; font-family: monospace; background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0;">' . htmlspecialchars($mobileUrl) . '</p>';
                
                if ($clashUrl) {
                    $content .= '
                    <p style="margin-top: 20px;"><strong>⚡ Clash专用地址：</strong></p>
                    <p style="margin-bottom: 5px; color: #666; font-size: 14px;">专为Clash客户端优化，支持规则分流</p>
                    <p style="word-break: break-all; color: #667eea; font-family: monospace; background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0;">' . htmlspecialchars($clashUrl) . '</p>';
                }
                
                // 添加苹果手机扫码二维码
                $content .= '
                    <div style="margin-top: 20px; text-align: center;">
                        <p><strong>📱 苹果手机扫码订阅</strong></p>
                        <p style="color: #666; font-size: 14px; margin-bottom: 10px;">使用相机扫描下方二维码即可快速添加订阅</p>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($mobileUrl) . '" style="border: 1px solid #ddd; border-radius: 8px; max-width: 200px;" alt="订阅二维码">
                    </div>
                </div>';
            }
            
            $content .= '
                <h3>📖 使用方法</h3>
                <div class="info-box">
                    <p><strong>客户端配置步骤：</strong></p>
                    <ol>
                        <li><strong>复制订阅地址</strong>：点击上方订阅地址进行复制</li>
                        <li><strong>添加订阅</strong>：在您的客户端中添加订阅</li>
                        <li><strong>更新配置</strong>：点击更新订阅获取最新配置</li>
                        <li><strong>开始使用</strong>：选择节点并连接即可</li>
                    </ol>
                </div>
                
                <h3>🔧 支持的客户端</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0;">
                    <span style="background: #667eea; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px;">Clash</span>
                    <span style="background: #667eea; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px;">V2rayN</span>
                    <span style="background: #667eea; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px;">Shadowrocket</span>
                    <span style="background: #667eea; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px;">Quantumult X</span>
                    <span style="background: #667eea; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px;">Surge</span>
                    <span style="background: #667eea; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px;">Sparkle</span>
                    <span style="background: #667eea; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px;">Mihomo</span>
                </div>
                
                <div class="warning-box">
                    <p><strong>⚠️ 重要提醒：</strong></p>
                    <ul>
                        <li>请妥善保管您的订阅地址，切勿分享给他人</li>
                        <li>如发现地址泄露，请及时联系客服重置</li>
                        <li>建议定期更换订阅地址以确保安全</li>
                        <li>服务到期前会收到续费提醒邮件</li>
                    </ul>
                </div>';
        }
        
        // 添加底部按钮和联系信息
        if (!$isAdmin) {
            $content .= '
            <div style="text-align: center; margin: 30px 0;">
                <a href="https://' . $siteDomain . '/" class="btn">查看我的订阅</a>
            </div>
            
            <p style="text-align: center; color: #666; font-size: 14px;">如有任何问题，请随时联系我们的客服团队</p>';
        } else {
            $content .= '
            <p style="text-align: center; color: #666; font-size: 14px;">此邮件由系统自动发送，请勿回复</p>';
        }
            
        return self::getBaseTemplate($title, $content, '感谢您的信任与支持');
    }
    
    /**
     * 获取站点域名，优先用.env中的SITE_DOMAIN
     */
    private static function getSiteDomain() {
        $domain = getenv('SITE_DOMAIN');
        if ($domain) return $domain;
        if (!empty($_SERVER['HTTP_HOST'])) return $_SERVER['HTTP_HOST'];
        return 'yourdomain.com';
    }
    
    /**
     * 到期提醒邮件模板
     */
    public static function getExpirationTemplate($username, $expireDate, $isExpired = false) {
        $title = $isExpired ? '订阅已到期' : '订阅即将到期';
        $dateStr = date('Y年m月d日', $expireDate);
        $siteDomain = self::getSiteDomain();
        
        if ($isExpired) {
            $content = '
                <h2>⚠️ 订阅已到期</h2>
                <p>亲爱的用户 <strong>' . htmlspecialchars($username) . '</strong>，</p>
                <p>您的订阅服务已于 <strong style="color: #e74c3c;">' . $dateStr . '</strong> 到期。</p>
                
                <div class="warning-box">
                    <p><strong>服务已暂停：</strong></p>
                    <ul>
                        <li>您的订阅地址已停止更新</li>
                        <li>无法获取最新的节点配置</li>
                        <li>请及时续费以恢复服务</li>
                    </ul>
                </div>';
        } else {
            $daysLeft = ceil(($expireDate - time()) / 86400);
            $content = '
                <h2>订阅即将到期</h2>
                <p>亲爱的用户 <strong>' . htmlspecialchars($username) . '</strong>，</p>
                <p>您的订阅服务将于 <strong style="color: #ffc107;">' . $dateStr . '</strong> 到期。</p>
                <p>距离到期还有 <strong style="color: #e74c3c;">' . $daysLeft . '</strong> 天。</p>
                
                <div class="warning-box">
                    <p><strong>温馨提醒：</strong></p>
                    <ul>
                        <li>为避免服务中断，请提前续费</li>
                        <li>到期后订阅地址将停止更新</li>
                        <li>续费后服务将自动恢复</li>
                    </ul>
                </div>';
        }
        
        $content .= '
            <table class="info-table">
                <tr>
                    <th>用户账号</th>
                    <td>' . htmlspecialchars($username) . '</td>
                </tr>
                <tr>
                    <th>' . ($isExpired ? '到期时间' : '剩余时间') . '</th>
                    <td style="color: #e74c3c; font-weight: bold;">' . ($isExpired ? $dateStr : $daysLeft . ' 天') . '</td>
                </tr>
            </table>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="https://' . $siteDomain . '/tc" class="btn">立即续费</a>
            </div>
            
            <p>如有任何问题，请随时联系我们的客服团队。</p>';
            
        return self::getBaseTemplate($title, $content, '我们期待继续为您服务');
    }
    
    /**
     * 密码重置邮件模板
     */
    public static function getPasswordResetTemplate($username, $resetLink) {
        $title = '密码重置';
        $content = '
            <h2>🔐 密码重置请求</h2>
            <p>亲爱的用户 <strong>' . htmlspecialchars($username) . '</strong>，</p>
            <p>我们收到了您的密码重置请求。如果这不是您本人的操作，请忽略此邮件。</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . htmlspecialchars($resetLink) . '" class="btn">重置密码</a>
            </div>
            
            <div class="info-box">
                <p><strong>安全提醒：</strong></p>
                <ul>
                    <li>此重置链接仅在24小时内有效</li>
                    <li>链接仅可使用一次</li>
                    <li>如果链接失效，请重新申请密码重置</li>
                    <li>重置链接：<span style="word-break: break-all; color: #667eea;">' . htmlspecialchars($resetLink) . '</span></li>
                </ul>
            </div>
            
            <p>为了您的账户安全，建议设置一个强密码，包含字母、数字和特殊字符。</p>';
            
        return self::getBaseTemplate($title, $content, '保护您的账户安全');
    }
}