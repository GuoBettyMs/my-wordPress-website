<?php
/**
 * Template canvas file to render the current 'wp_template'.
 * 负责将当前查询的 wp_template（即模板）渲染成完整的 HTML 页面
 *
 * @package WordPress
 */

/*
 * Get the template HTML.
 * This needs to run before <head> so that blocks can add scripts and styles in wp_head().
 */
$template_html = get_the_block_template_html();

?><!DOCTYPE html>
<!-- (可选)language_attributes() 输出语言属性（如 lang="zh-CN"）,可在网页的‘检查’->‘Elements’显示 -->
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<!-- (必选)输出样式、脚本、SEO 元标签等）,可在网页的‘检查’->‘Elements’显示 -->
	<?php wp_head(); ?>
</head>

<!-- (可选)body_class() 输出用于样式定位的 CSS 类名,可在网页的‘检查’->‘Elements’显示-->
<body <?php body_class(); ?>>
<!-- wp_body_open() 是用于在 <body> 开始后插入内容的钩子,可以通过它，向页面 <body> 的最顶部注入自定义内容-->
<?php wp_body_open(); ?>

<!-- 输出之前生成的模板 HTML 内容，这是页面的主体部分 -->
<?php echo $template_html; ?>

<!-- (可选)输出页面底部的脚本（如统计代码、异步加载的 JS）,可在网页的‘检查’->‘Elements’显示 -->
<?php wp_footer(); ?>
</body>
</html>
