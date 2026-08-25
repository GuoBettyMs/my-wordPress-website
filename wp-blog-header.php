<?php
/**
 * Loads the WordPress environment and template.
 * 加载 WordPress 环境、处理当前请求并输出页面内容
 *
 * @package WordPress
 */


/**
 * isset 检查一个全局标志变量 $wp_did_header 是否已被设置。
 * 该变量用于防止同一个请求中重复加载 wp-blog-header.php，避免环境重复初始化或意外循环
 */
if ( ! isset( $wp_did_header ) ) {	

	//表示当前请求已进入该入口文件，后续执行不会再重复进入
	$wp_did_header = true;

	/**
	 * Load the WordPress library. 负责加载核心配置、数据库连接、全局函数、主题支持等基础环境
	 * __DIR__ 是 PHP 的魔术常量，代表当前执行文件所在的目录的绝对路径
	 * 定位方式：相对于当前文件的位置。例如，在 wp-blog-header.php 中调用，就表示加载与 wp-blog-header.php 同级的 wp-load.php 文件。
	 */
	require_once __DIR__ . '/wp-load.php';


	/**
	 * Set up the WordPress query.
	 * 该函数会解析当前请求（根据 URL 中的参数或固定链接结构），并生成主查询对象（$wp_query）
	 * 会判断用户访问的是首页、单篇文章、页面、分类归档还是其他内容类型，并将结果存储在全局变量中供后续模板使用
	 */
	wp();

	/**
	 * Load the theme template. 
	 * 加载完该文件,自动根据 wp() 执行后的查询结果 $wp_query，决定使用哪个主题模板文件来呈现页面（例如 index.php、single.php、page.php 等）
	 * 
	 * ABSPATH . WPINC 使用全局常量拼接路径
	 * - ABSPATH：WordPress 站点的绝对根目录（通常以斜杠结尾）。
	 * - WPINC：WordPress 核心文件夹名称，固定为 wp-includes。
	 * 定位方式：绝对路径，不依赖于当前文件的位置。无论你在哪个文件里调用，它都指向站点的 wp-includes 目录。
	 */
	require_once ABSPATH . WPINC . '/template-loader.php';

}
