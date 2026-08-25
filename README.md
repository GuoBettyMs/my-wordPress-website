## my-wordPress-website
使用 Studio 在本地部署 WordPress 

第1步：下载与安装
访问官方网站：打开浏览器，进入 Studio 的官方下载页面：https://developer.wordpress.com/studio/。
选择并下载：根据操作系统（macOS），点击对应的下载按钮。
安装程序：下载完成后，运行安装文件。根据屏幕提示完成安装即可。

第2步：创建第一个本地站点
启动 Studio：安装完成后，打开 Studio 应用程序。
添加新站点：在主界面，点击左下角的 “Add Site”（添加站点）按钮。

填写基本信息：
Site Name（站点名称）：本地网站名字。
Local Path（本地路径）：选择或确认网站文件在电脑上的保存位置。
完成创建：点击 “Add Site” 按钮。Studio 会自动完成 WordPress 的安装和配置

第3步：开始使用本地网站
创建完成后，会进入站点的管理界面，可以找到以下关键入口：
WP Admin：点击此按钮，会直接在浏览器中打开本地网站的 WordPress 管理后台，可以像管理在线网站一样进行登录和设置。
文件浏览器：点击可以快速打开网站的文件夹，方便管理主题、插件等文件。
终端：点击可以直接进入命令行界面，方便使用 WP-CLI 等工具进行高级操作

## 外置编辑器
站点概览 -> 在...中打开,选择 Visual Studio Code

### 在 VS Code 中启用调试功能, 在线调试通过 Studio 打开的 WordPress 项目
第一步：在 Studio 中启用 Xdebug
在 Studio 中打开你的本地站点。
进入站点的 设置 (Settings) 页面，找到并点击 调试 (Debugging) 标签页。
勾选 “启用 Xdebug” (Enable Xdebug) 复选框。Studio 会自动重启站点以使配置生效。
启用后，会在站点侧边栏看到一个类似“臭虫”的图标，表示 Xdebug 已激活。

注意：Xdebug 一次只能在一个站点上激活。并且由于它会降低性能，建议仅在调试期间开启。

第二步：配置 VS Code
安装 PHP Debug 扩展：在 VS Code 的扩展商店中，搜索并安装由 Felix Becker 开发的 “PHP Debug” 扩展。这是 VS Code 连接 Xdebug 的桥梁。
创建调试配置 (launch.json)：
在 VS Code 中，点击左侧的“运行和调试”图标（或按 Ctrl+Shift+D)。
点击 “创建 launch.json 文件”，并在弹出的环境列表中选择 “PHP”。
将生成的 launch.json 文件内容替换为以下配置：
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003
        }
    ]
}
这个配置告诉 VS Code 在 9003 端口上监听来自 Xdebug 的连接。

第三步：确保路径映射正确（关键）
为了让断点生效，VS Code 需要知道服务器上的文件路径对应电脑上的哪个文件。需要在 launch.json 的配置中添加 pathMappings 字段。
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/var/www/html": "${workspaceFolder}"    //默认路径
            }
        }
    ]
}
说明：/var/www/html 是 Studio 内部 WordPress 的默认根目录。${workspaceFolder} 是 VS Code 当前打开的、包含你 WordPress 代码的文件夹路径。
若安装 Studio 更改过文件路径,可按照以下步骤确定服务器的真实文档根目录(标题为: 确定 pathMappings 实际路径)

第四步：开始调试
在 VS Code 中，确保 “Listen for Xdebug” 配置已被选中，然后点击绿色的启动按钮，让 VS Code 进入监听状态。
在想调试的 PHP 文件（如主题的 functions.php 或插件的主文件）的代码行号左侧点击，设置一个断点 (Breakpoint)。
打开 Studio 应用，在左侧的站点列表中选中站点。在站点的 “Overview” 页面中，会看到 “Site URL” 字段，它通常显示为类似 http://your-site-name.local 的地址。直接点击该地址旁边的 “Open Site” 按钮，浏览器就会自动打开站点
(如果需要进入后台进行编辑或调试，同样在 “Overview” 页面中，点击 “WP Admin” 按钮即可跳转到登录页面，输入创建站点时设置的用户名和密码即可登录)
但触发到包含断点的代码时，页面会暂停加载，VS Code 会自动弹出来，并高亮显示断点所在的行。

#### 显示打印信息
1.显示在 VS Code 的 Debug Console
使用 VS Code 的“日志点”（Logpoint）
在 wp-blog-header.php 文件中
在代码行的行号上 右键，选择 “添加日志点”（Add Logpoint）。
在输入框中填写：
"=== 进入 wp-blog-header.php ==="
当前值: {$wp_did_header}    // 插入变量
当前值: { var_export($wp_did_header, true) }  //显示布尔值原文

日志点会显示为一个菱形图标，执行到该行时，信息会输出到 VS Code 的 Debug Console，而不会中断程序。

2. 使用 Studio 的调试日志
注: WordPress 的错误日志处理系统（wp_debug_mode()）是在 wp-settings.php 中才被初始化的,将以下代码添加到主题文件夹下的 functions.php 文件中：
add_filter('template_include', function($template) {
    error_log('=== 最终使用的模板文件 ===');
    error_log($template);
    return $template;
});
然后刷新页面，查看 Studio 的调试日志（wp-content/debug.log）或 VS Code 的 Debug Console（如果设置了日志点），就会看到类似这样的输出：
/xxxx/wp-content/themes/twentytwentyfive/single.html

#### 确定 pathMappings 实际路径
在站点根目录创建一个 info.php，内容为：<?php phpinfo();    
然后在浏览器访问 http://localhost:xxxx/info.php，查找 DOCUMENT_ROOT 的值。这个值就是服务器上的实际根目录路径
假设 DOCUMENT_ROOT 显示为 /app，那么映射应改为：
"pathMappings": {
    "/app": "${workspaceFolder}"
}
若路径不对,将无法开启调试功能。

