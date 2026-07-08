<?php
/**
 * Lottie logo <img> 占位单测。
 *
 * 背景：<img src="*.json"> 会被浏览器当图片抓取（解码失败+控制台报错+与 preload 重复抓取）。
 * 修复：mac_logo_img_src() 对 .json logo 返回透明占位 src + data-mac-logo-lottie-url，
 * 由 mac-logo-lottie.js（k()/E() 本就读该 data 属性）升级为动画，机制不变。
 * 本用例守护 .json 分支与普通图片分支；真实渲染链路由 tests/regression/frontend-assets.sh 复现。
 */

namespace MaccmsTest\Unit;

use PHPUnit\Framework\TestCase;

class LogoLottieImgTest extends TestCase
{
    private $savedUpload;

    protected function setUp(): void
    {
        // mac_url_img 依赖上传配置；测试环境未走 app init，补一个本地模式最小桩
        $this->savedUpload = $GLOBALS['config']['upload'] ?? null;
        $GLOBALS['config']['upload'] = ['mode' => 'local', 'protocol' => 'http', 'remoteurl' => ''];
    }

    protected function tearDown(): void
    {
        if ($this->savedUpload === null) {
            unset($GLOBALS['config']['upload']);
        } else {
            $GLOBALS['config']['upload'] = $this->savedUpload;
        }
    }

    public function test_json_logo_uses_placeholder_and_data_attr()
    {
        $out = mac_logo_img_src('lottie/logo/logo.json');
        $this->assertStringContainsString('src="data:image', $out, 'json logo 应用透明占位 src');
        $this->assertStringContainsString('data-mac-logo-lottie-url=', $out);
        $this->assertStringContainsString('logo.json', $out, '真实 url 应写进 data 属性');
        // 关键：src 不再是 .json（浏览器不会当图片抓取）
        $this->assertDoesNotMatchRegularExpression('#src="[^"]*\.json"#', $out);
    }

    public function test_json_with_query_still_detected()
    {
        $out = mac_logo_img_src('lottie/logo/logo.json?v=2');
        $this->assertStringContainsString('data-mac-logo-lottie-url=', $out);
        $this->assertStringContainsString('src="data:image', $out);
    }

    public function test_plain_image_keeps_normal_src()
    {
        $out = mac_logo_img_src('images/logo.png');
        $this->assertStringContainsString('src="', $out);
        $this->assertStringContainsString('logo.png', $out);
        $this->assertStringNotContainsString('data-mac-logo-lottie-url', '普通图片不应加 data 属性');
        $this->assertStringNotContainsString('data:image', '普通图片不应是占位');
    }
}
