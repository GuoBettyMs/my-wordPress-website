<?php

//在加载样式时，优先加载父主题的样式表，从而保证子主题能继承父主题的所有样式
add_action( 'wp_enqueue_scripts', 'my_child_theme_styles' );
function my_child_theme_styles() {
    // 加载父主题的 style.css
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );

    // 加载子主题样式，get_stylesheet_uri 会自动处理 SSL（https）和目录路径，返回类似 https://example.com/wp-content/themes/child-theme/style.css?ver=1.0
    //⚠️: 如果子主题 style.css 文件没有包含标准的文件头（Theme Name, Template 等），get_stylesheet_uri() 仍然能获取 URL，但 WordPress 可能不会将其识别为有效主题
    wp_enqueue_style( 'child-style', get_stylesheet_uri(), array( 'parent-style' ) );

    // //get_stylesheet_directory_uri 返回当前主题目录的 URL，然后手动拼接 /style.css
    // wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array('parent-style') );
}

// 为所有用户关闭前端工具栏
add_filter('show_admin_bar', '__return_false');

// 创建一个短代码，用于在任意位置显示 Polylang 语言切换器
add_shortcode('custom_lang_switcher', 'custom_language_switcher');
function custom_language_switcher() {
    if ( function_exists( 'pll_the_languages' ) ) {
        $html = pll_the_languages( array(
            'echo' => 0,                    // 如果设置为 1，则回显列表(ui 上显示多语言列表)，默认为 1
            'hide_if_empty' => 0 ,           // 如果设置为 1，则隐藏没有文章（或页面）的语言，默认值为 1
            'dropdown' => 1,                // 0:列表，1:下拉菜单,默认为 0
            // 'raw' => 1,                      //如果设置为 1，则返回原始数组(可看具体信息)而不是 HTML 标记；默认为 0
        ) );

        // 调试：将 HTML 写入日志（可选）
        // write_debug_log('语言切换器 HTML: ' . print_r($html, true));
        return $html;
    }
    return '<!-- Polylang 未激活 -->';
}

/**
 * 写入自定义调试日志
 *
 * @param string $message  日志内容
 * @param string|null $log_file 日志文件路径（可选，默认在主题目录下生成 custom-debug.log）
 * @param string $timezone 时区（可选，默认为 'Asia/Shanghai'）
 * @return bool 是否写入成功
 */
function write_debug_log( $message, $log_file = null, $timezone = 'Asia/Shanghai' ) {
    //通过常量控制是否启用调试日志，避免生产环境产生过多文件
    if ( ! defined( 'WP_DEBUG_LOG_CUSTOM' ) || ! WP_DEBUG_LOG_CUSTOM ) {
        return false;
    }

    // 默认日志文件路径：当前主题目录下的 custom-debug.log
    if ( null === $log_file ) {
        // $log_file = get_template_directory() . '/custom-debug.log';      //自定义日志在父主题文件夹下
        // 如果使用子主题且希望日志写在子主题目录，可替换为 get_stylesheet_directory()
        $log_file = get_stylesheet_directory() . '/custom-debug.log';
    }

    // 保存当前时区，避免影响其他代码
    $original_timezone = date_default_timezone_get();
    // 设置目标时区
    date_default_timezone_set( $timezone );
    $local_time = date( 'Y-m-d H:i:s' );
    // 恢复原时区
    date_default_timezone_set( $original_timezone );

    // 组装日志行（含换行符）
    $full_message = "[{$local_time}] {$message}" . PHP_EOL;

    // 写入文件（error_log 的 type = 3 表示追加到指定文件）
    return error_log( $full_message, 3, $log_file );
}
?>