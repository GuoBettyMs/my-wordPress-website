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
?>