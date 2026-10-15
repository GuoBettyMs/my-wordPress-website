<?php
/** 如果后台 设置 → 阅读 中设置了“静态首页”，那应该修改 front-page.html。
 * 如果没设置，默认显示最新文章，应修改 home.html。
 * 如果以上都没有，才会用到 index.html 
 */

/**在加载样式时，优先加载父主题的样式表，从而保证子主题能继承父主题的所有样式 */
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

/** 为所有用户关闭前端工具栏  */
add_filter('show_admin_bar', '__return_false');


/**
 * 获取 WordPress 内置"标签页（Tabs）块"中每个标题
 */
add_filter( 'render_block_core/tab-list', function( $block_content, $block ) {
    if ( ! is_string( $block_content ) ) {
        return $block_content;
    }

    /** 提取按钮文本 */
    preg_match_all( '/<button[^>]*>(.*?)<\/button>/si', $block_content, $matches );
    $button_texts = array_map( 'trim', array_map( 'wp_strip_all_tags', $matches[1] ) );

    if ( ! empty( $button_texts ) ) {
        $category_names = array_map( 'get_category_name_from_tab_text', $button_texts );

        /** 用静态变量存储，避免被多次覆盖 */
        static $map_index = 0;
        $map_index++;
        $GLOBALS['tab_category_map_' . $map_index] = $category_names;

        /** 同时存到一个有序数组中 */
        if ( ! isset( $GLOBALS['tab_category_maps'] ) ) {
            $GLOBALS['tab_category_maps'] = array();
        }
        $GLOBALS['tab_category_maps'][] = $category_names;
    }
    // write_debug_log( 'Tab list: ' . print_r( $button_texts, true ) );
    // write_debug_log( '分类名称列表: ' . print_r( $category_names, true ) );

    return $block_content;
}, 10, 2 );

/**
 * 利用 pre_render_block 过滤器，动态修改 wp-block-query 的 query 参数
 */
add_filter( 'pre_render_block', function( $block_content, $block ) {
    if ( $block['blockName'] !== 'core/query' ) {
        return $block_content;
    }

    /**  检查是否有 taxQuery（说明需要按分类过滤） */
    if ( empty( $block['attrs']['query']['taxQuery'] ) ) {
        return $block_content;
    }

    /**  从 tab_category_maps 中按顺序取分类 */
    if ( ! isset( $GLOBALS['tab_category_maps'] ) || empty( $GLOBALS['tab_category_maps'] ) ) {
        return $block_content;
    }

    /** 用静态计数器追踪第几个 query 块 */
    static $query_counter = 0;
    $map_index = $query_counter % count( $GLOBALS['tab_category_maps'] );
    $category_names = $GLOBALS['tab_category_maps'][ $map_index ];
    $query_counter++;

    /**  取当前 query 对应的分类名称 */
    $name = $category_names[0] ?? '';

    if ( ! $name ) {
        return $block_content;
    }

    $category_id = get_term_id_by_name_and_parent( $name );

    // write_debug_log( "当前分类名称 '{$name}' , 翻译id: '{$category_id}'" );

    if ( $category_id ) {
        $block['attrs']['query']['taxQuery']['category'] = array( $category_id );
        /** 不再调用 render_block() 递归渲染，直接返回修改后的 $block_content
         * WordPress 会自动用修改后的 attrs 重新渲染    */
    }

    return $block_content;
}, 10, 2 );

/** 
 * 添加 Tab 切换 JavaScript
 */
add_action( 'wp_footer', 'my_child_theme_tab_js' );
function my_child_theme_tab_js() {
    if ( ! is_front_page() && ! is_home() ) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var tabs = document.querySelectorAll('.wp-block-tab-list button[role="tab"]');
        var panels = document.querySelectorAll('.wp-block-tab-panel');
        
        if (tabs.length === 0 || panels.length === 0) {
            return;
        }

        function activateTab(index) {
            // 移除所有 tab 的 active 状态
            tabs.forEach(function (t) {
                t.classList.remove('is-active');
                t.removeAttribute('aria-selected');
            });

            // 隐藏所有 panel
            panels.forEach(function (p) {
                p.classList.remove('is-active');
                p.hidden = true;
            });

            var tab = tabs[index];
            var targetId = tab.getAttribute('aria-controls');
            var panel = targetId ? document.getElementById(targetId) : panels[index];
            // 激活当前 tab
            if (tab) {
                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');
            }

            // 激活当前 panel
            if (panel) {
                panel.classList.add('is-active');
                panel.hidden = false;
            }
        }

        /**
         * 增加点击事件
         */
        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                activateTab(i);
            });
        });

        // 默认激活第一个 tab
        activateTab(0);
    });
    </script>
    <?php
}

/** 
 * 通过后台分类管理动态生成 Tab
 */
add_shortcode('monthly_posts_tabs', 'monthly_posts_tabs_shortcode');
function monthly_posts_tabs_shortcode( $atts ) {
    /**  
     * $atts 是 shortcode 传入的属性参数数组。
     * 比如用户在编辑器里写 [monthly_posts_tabs parent_slug="news"]，那 $atts 里就会有 'parent_slug' => 'news'
     * 没传 parent_slug，就用空字符串 ''
     * */ 
    $atts = shortcode_atts( array(
        'parent_slug' => '',
    ), $atts );

    $current_lang = pll_current_language();
    if ( ! $current_lang ) {
        return '';
    }

    $parent = get_term_by( 'slug', $current_lang, 'category' );
    if ( ! $parent ) {
        return '';
    }

    $sub_terms = get_terms( array(
        'taxonomy'   => 'category',
        'parent'     => $parent->term_id,
        'hide_empty' => false,
    ) );

    if ( empty( $sub_terms ) ) {
        return '';
    }

    /**  按 name 排序（26.07, 26.08 这样字符串排序是对的） */ 
    usort( $sub_terms, function( $a, $b ) {
        return strcmp( $b->name, $a->name ); // 降序，最新的在前
    } );

    /**  生成月份标签 */ 
    $tabs = array();
    foreach ( $sub_terms as $term ) {
        /** 假设分类名称格式为 "26.08" -> 标签 "8月" */ 
        if ( preg_match( '/\d+\.(\d{2})$/', $term->name, $m ) ) {
            $month_num = intval( $m[1] );

            // 用日期本地化生成月份名
            if ( strpos( get_locale(), 'en' ) === 0 ) {
                $month_label = wp_date( 'F', mktime( 0, 0, 0, $month_num, 1, 2000 ) );
            } else {
                $month_label =$month_num . '月';
            }
            $tabs[] = array(
                'label'   => $month_label,
                'id'      => 'tab-' . $term->term_id,
                'panel_id'=> 'panel-' . $term->term_id,
                'cat_id'  => $term->term_id,
            );
        }
    }

    if ( empty( $tabs ) ) {
        return '';
    }

    ob_start();
    ?>
    
    <!-- <div class="wp-block-tab-list" role="tablist">                 =>  Tab 切换按钮组 
        <button type="button" role="tab"                                => 标识为 Tab 按钮, 防止被当成表单提交按钮
        id="... $tab['id'] .."                                          => 唯一 ID ,对应后面 panel 的 aria-labelledby
        aria-controls="... $tab['panel_id'] ... >"                      => 声明这个按钮控制的是哪个 panel（内容面板），值是 panel 的 ID
        echo $i === 0 ? 'aria-selected="true" class="is-active"' : ''   => 默认激活并高亮显示第0个
        echo esc_html( $tab['label'] )                                  => 输出显示 tab 文本
    -->
    <div class="wp-block-group has-accent-2-background-color has-background"
        style="padding-right:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
        <div class="wp-block-tabs has-accent-5-background-color has-background">
            <!-- Tab 按钮 -->
            <div class="wp-block-tab-list" role="tablist">
                <?php foreach ( $tabs as $i => $tab ) : ?> 
                    <button type="button" role="tab"
                            id="<?php echo esc_attr( $tab['id'] ); ?>"
                            aria-controls="<?php echo esc_attr( $tab['panel_id'] ); ?>"
                        <?php echo $i === 0 ? 'aria-selected="true" class="is-active"' : ''; ?>
                    ><?php echo esc_html( $tab['label'] ); ?> 
                    </button>
                <?php endforeach; ?> 
            </div>
            <!-- Tab 面板 --><!-- 全局去重数组 --><?php static $seen_ids = array(); ?>
            <div class="wp-block-tab-panels">
                <?php foreach ( $tabs as $i => $tab ) : ?>
                    <section role="tabpanel"
                            id="<?php echo esc_attr( $tab['panel_id'] ); ?>"
                            aria-labelledby="<?php echo esc_attr( $tab['id'] ); ?>"
                            tabindex="0"
                            class="wp-block-tab-panel <?php echo $i === 0 ? 'is-active' : ''; ?>">

                        <ul class="monthly-posts-list">
                            <?php
                                global $post;
                                $original_post = $post;

                                /**
                                 * tax_query + field => term_id	    明确按当前子分类 ID 查询
                                 * include_children => false	    避免再带出更下层的子分类文章
                                 * lang => $current_lang	        限制只查当前语言的文章
                                 * posts_per_page => 10	            保持每个 Tab 最多 10 篇
                                 * lang => $current_lang           当前语言限制
                                 */
                                $posts = get_posts( array(
                                    'post_type'      => 'post',
                                    'post_status'    => 'publish',
                                    'posts_per_page' => 10,
                                    'orderby'        => 'date',
                                    'order'          => 'DESC',
                                    'tax_query'      => array(
                                        array(
                                            'taxonomy'         => 'category',
                                            'field'            => 'term_id',
                                            'terms'            => array( $tab['cat_id'] ),
                                            'include_children' => false,
                                        ),
                                    ),
                                    'lang'             => $current_lang,
                                ) );

                                /**
                                 * 单 Tab 内部按 “标题 + 内容前 30 字”去重
                                 * 若是采集、导入、误操作产生的重复文章，建议在后台或数据库里清理，而不是每次查询都过滤。
                                 * 比较安全的方式是用插件，比如 Delete Duplicate Posts，可以按标题扫描，保留最早或最新的一篇，再批量删除重复项
                                 */
                                $unique = array();
                                foreach ( $posts as $p ) {
                                    $key = md5( $p->post_title . wp_trim_words( $p->post_content, 30, '' ) );
                                    if ( ! isset( $unique[ $key ] ) ) {
                                        $unique[ $key ] = $p;
                                        continue;
                                    }
                                    /** 已经存在相同内容时，保留 ID 更大的那篇，通常就是最新的一篇 */
                                    if ( $p->ID > $unique[ $key ]->ID ) { 
                                        $unique[ $key ] = $p; 
                                    }
                                }
                                $posts = array_values( $unique );

                                /** 跨 Tab 按 ID 去重 */
                                if ( $posts ) {
                                    foreach ( $posts as $key => $p ) {
                                        if ( isset( $seen_ids[ $p->ID ] ) ) {
                                            unset( $posts[ $key ] );
                                            continue;
                                        }
                                        $seen_ids[ $p->ID ] = true;
                                    }
                                    /** 重新索引，避免 foreach 时 key 断裂 */
                                    $posts = array_values( $posts );
                                }

                                /** 输出搜索结果 */
                                if ( $posts ) :
                                    foreach ( $posts as $post ) :
                                        setup_postdata( $post );
                                        ?>
                                        <li>
                                            <div class="wp-block-group" style="display:flex;gap:1rem;align-items:flex-start;">
                                                <!-- 如果有特色图 -->
                                                <?php if ( has_post_thumbnail( $post ) ) : ?>
                                                    <div style="flex:0 0 120px;">
                                                        <?php echo get_the_post_thumbnail( $post, 'medium' ); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div style="flex:1;">
                                                    <!-- 调试用,确认每篇 ID 不同,如果 ID 都不同，说明查询没问题，只是显示出了问题
                                                        <small>ID: <?php echo $post->ID; ?></small>-->
                                                    <h3 class="has-large-font-size"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                                                    <p class="has-medium-font-size"><!-- 自动截取正文前 50 字 --><?php echo wp_trim_words( get_the_excerpt(), 50 ); ?></p>
                                                    <time class="has-medium-font-size"><!-- get_the_date(): 每次都会返回当前文章的日期，不受"同一天只输出一次"的限制 --><?php echo get_the_date( '', $post ); ?></time>
                                                </div>
                                            </div>
                                        </li>
                                    <?php
                                    endforeach;
                                    wp_reset_postdata();
                                    $post = $original_post;
                                else :
                                    ?>
                                    <div style="flex:1;">
                                        <?php echo '<p>' . esc_html__( 'No articles available.', 'twentytwentyfive-child' ) . '</p>'; ?>
                                    </div>
                                <?php
                                endif;
                            ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <?php
    $output = ob_get_clean();

    /** 去掉 wpautop 可能塞进来的空段落和多余换行 */
    $output = preg_replace( '#<p>\s*</p>#', '', $output );
    $output = str_replace( array( "\r\n", "\r" ), "\n", $output );
    $output = preg_replace( "#\n\s*\n#", "\n", $output );
    
    return $output;
}


/** 
 * 创建一个短代码，用于在任意位置显示 Polylang 语言切换器
 */
add_shortcode('custom_lang_switcher', 'custom_language_switcher');
function custom_language_switcher() {
    if ( function_exists( 'pll_the_languages' ) ) {
        $html1 = pll_the_languages( array(
            'echo' => 0,                     // 如果设置为 1，则回显列表(ui 上显示多语言列表)，默认为 1
            'hide_if_empty' => 0 ,           // 如果设置为 1，则隐藏没有文章（或页面）的语言，默认值为 1
            'dropdown' => 1,                 // 0:列表，1:下拉菜单,默认为 0
            'raw' => 1,                      //如果设置为 1，则返回原始数组(可看具体信息)而不是 HTML 标记；默认为 0
        ) );

        // 调试：将 HTML 写入日志（可选）
        // write_debug_log('语言切换器 HTML: ' . print_r($html1, true));
        
        $html = pll_the_languages( array(
            'echo' => 0,                     // 如果设置为 1，则回显列表(ui 上显示多语言列表)，默认为 1
            'hide_if_empty' => 0 ,           // 如果设置为 1，则隐藏没有文章（或页面）的语言，默认值为 1
            'dropdown' => 1,                 // 0:列表，1:下拉菜单,默认为 0
            'raw' => 0,                      //如果设置为 1，则返回原始数组(可看具体信息)而不是 HTML 标记；默认为 0
        ) );

        // 将返回的 HTML 包裹在一个 <div> 中，并添加一个自定义类名
        return '<div class="custom-lang-switcher">' . $html . '</div>';
    }
    return '<!-- Polylang 未激活 -->';
}

/** 
 * 注册短代码，用于输出翻译后的标题
 */
add_shortcode('blog_heading', function() {
    $text = pll__('首页博客');       // pll__() 返回翻译，不直接输出
    // 输出与原始标题一致样式的 HTML
    return '<h1 class="wp-block-heading has-text-align-left">' . esc_html($text) . '</h1>';
});


/** 
 * 使用后台 Loco Translate 插件的翻译文本
 */
add_action( 'after_setup_theme', function () {
    load_child_theme_textdomain(
        'twentytwentyfive-child',
        get_stylesheet_directory() . '/languages'
    );
} );

add_action('init', function() {
    // write_debug_log('--- init ---');
    // write_debug_log('is_user_logged_in = ' . (is_user_logged_in() ? 'yes' : 'no'));
    // write_debug_log('user ID = ' . get_current_user_id());
    // write_debug_log('can manage_options = ' . (current_user_can('manage_options') ? 'yes' : 'no'));

    /** Polylang 中注册字符串,pll_register_string() 函数参数: 一个唯一的名称、要翻译的原文，以及一个分组名称（用于在界面中归类）*/
    pll_register_string('blog_heading', '首页博客', 'My Theme');
});


/**
 * 根据选项卡按钮文本,动态获取对应的分类名称（用于查询）
 *
 * @param string $tab_text 按钮文本，如 '8月'
 * @return string|false 返回分类名称，如 '26.08'；若未找到则返回 false
 */
function get_category_name_from_tab_text( $tab_text ) {
    // 提取数字部分，如 '8月' -> '8'
    if ( preg_match( '/^(\d+)月$/', $tab_text, $matches ) ) {
        $month = str_pad( $matches[1], 2, '0', STR_PAD_LEFT );
        return '26.' . $month;
    }
    return false;
}


/**
 * 获取指定子分类名称的分类 ID
 *
 * @param string $name  子分类的名称
 * @return int 子分类 ID
 */
function get_term_id_by_name_and_parent( $name ) {
     /** 检测当前语言 */
    $current_lang = pll_current_language();
    if ( ! $current_lang ) {
        write_debug_log( '语言未检测到，返回 false' );
        return false;
    }

    /** 获取父分类 ID */
    $parent = get_term_by( 'slug', $current_lang, 'category' );
    if ( ! $parent ) {
        write_debug_log( "父分类 '{$current_lang}' 未找到" );
        return false;
    }
    //通过名称和父 ID 查找子分类
    $terms = get_terms( array(
        'taxonomy'   => 'category',
        'name'       => $name,
        'parent'     => $parent->term_id,
        'hide_empty' => false,
    ) );
    if ( ! empty( $terms ) ) {
        return $terms[0]->term_id;
    }
    write_debug_log( "子分类 '{$name}' 在父分类 '{$current_lang}' 下未找到" );
    return false;
}

/**
 * 获取当前语言下的子分类 id 列表
 *
 * @return Array 子分类 id 列表
 */
function get_all_term_ids_by_current_language_parent() {
    $sub_terms = get_all_terms_by_current_language_parent();
    // foreach ( $sub_terms as $term ) {
    //     write_debug_log( '子分类名称: ' . $term->name . ', ID: ' . $term->term_id );
    // }
    return wp_list_pluck( $sub_terms, 'term_id' );
}

/**
 * 获取当前语言下的子分类对象数组
 *
 * @return Array 子分类对象数组
 */
function get_all_terms_by_current_language_parent() {
    $current_lang = pll_current_language();
    if ( ! $current_lang ) {
        return array();
    }
    // 获取父分类（slug 为当前语言代码）
    $parent = get_term_by( 'slug', $current_lang, 'category' );
    if ( ! $parent ) {
        return array();
    }
    // 查询该父分类下的所有子分类
    $terms = get_terms( array(
        'taxonomy'   => 'category',
        'parent'     => $parent->term_id,
        'hide_empty' => false,
    ) );
    return $terms; // 返回子分类对象数组
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