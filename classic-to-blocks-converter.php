<?php
/**
 * Plugin Name: Classic to Blocks Converter
 * Description: Convert all posts (or a specific post type) from Classic to Blocks format with the click of a button.
 * Version: 1.0
 * Author: Shane Jennings
 * Author URI: https://stacktobasics.com/
 */

// Register the admin menu item
add_action('admin_menu', 'ctg_converter_menu');

function ctg_converter_menu() {
    add_menu_page('Classic to Blocks Converter', 'Classic to Blocks Converter', 'manage_options', 'ctg-converter', 'ctg_converter_page');
}

function ctg_converter_page() {
    $custom_post_types = get_post_types(array('_builtin' => false), 'objects');
    
    echo '<div class="wrap">';
    echo '<h1>Classic to Blocks Converter</h1>';
    echo '<div style="background-color: #cc0000; color: white; padding: 10px; margin-bottom: 20px;">';
    echo '<strong>WARNING:</strong> Make sure to take a back up of your files before you attempt this!';
    echo '</div>';
    echo '<form method="post" action="">';
    wp_nonce_field('ctg_converter_action', 'ctg_converter_nonce');
    echo '<label for="post_type">Select Post Type: </label>';
    echo '<select name="post_type" id="post_type">';
    echo '<option value="all">All content</option>';
    foreach ($custom_post_types as $post_type) {
        echo '<option value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->labels->singular_name) . '</option>';
    }
    echo '</select><br><br>';
    echo '<input type="hidden" name="convert_posts" value="1">';
    echo '<input type="submit" class="button-primary" value="Convert All Posts">';
    echo '</form>';
    echo '</div>';

    if (isset($_POST['convert_posts']) && check_admin_referer('ctg_converter_action', 'ctg_converter_nonce')) {
        $post_type = sanitize_text_field($_POST['post_type']);
        ctg_convert_all_posts($post_type);
    }
}

function ctg_convert_all_posts($post_type) {
    $args = array(
        'post_status' => 'any',
        'posts_per_page' => -1
    );

    if ($post_type !== 'all') {
        $args['post_type'] = $post_type;
    } else {
        $args['post_type'] = get_post_types(array('public' => true));
    }

    $posts = get_posts($args);

    foreach ($posts as $post) {
        $content = $post->post_content;
        $converted_content = ctg_convert_to_blocks($content);
        wp_update_post(array(
            'ID' => $post->ID,
            'post_content' => $converted_content
        ));
    }

    echo '<div class="updated"><p>All posts have been converted.</p></div>';
}

function ctg_convert_to_blocks($classicHtml) {
    $elements = array(
        array('div', 'group'),
        array('img', 'image'),
        array('table', 'table', 'figure class="wp-block-table"'),
        array('h1', 'heading', 'level:1'),
        array('h2', 'heading', 'level:2'),
        array('h3', 'heading', 'level:3'),
        array('h4', 'heading', 'level:4'),
        array('h5', 'heading', 'level:5'),
        array('h6', 'heading', 'level:6'),
        array('ul', 'list'),
        array('ol', 'list'),
        array('li', 'list-item'),
        array('header', 'group'),
        array('section', 'group'),
        array('pre', 'preformatted'),
        array('blockquote', 'quote'),
        array('style', 'html'),
        array('script', 'html'),
        array('strong', 'paragraph', 'p')
    );

    foreach ($elements as $element) {
        $tag = $element[0];
        $blockType = $element[1];
        $additionalAttributes = isset($element[2]) ? $element[2] : '';

        if ($tag === 'table') {
            $blockStart = '<!-- wp:' . $blockType . ' -->' . '<' . $additionalAttributes . '>';
            $blockEnd = '</figure>' . '<!-- /wp:' . $blockType . ' -->';
        } else if ($tag === 'strong') {
		$blockStart = '<!-- wp:' . $blockType . ' -->' . '<' . $additionalAttributes . '>';
		$blockEnd = '</' . $additionalAttributes . '>' . '<!-- /wp:' . $blockType . ' -->';
	}
	{
            $blockStart = '<!-- wp:' . $blockType;
            if ($additionalAttributes) {
                $blockStart .= ' {' . $additionalAttributes . '}';
            }
            $blockStart .= ' -->';
            $blockEnd = '<!-- /wp:' . $blockType . ' -->';
        }

        $pattern = '/<' . $tag . '([^>]*)>(.*?)<\/' . $tag . '>/s';
        $classicHtml = preg_replace_callback($pattern, function($matches) use ($blockStart, $blockEnd, $tag) {
            return $blockStart . '<' . $tag . $matches[1] . '>' . $matches[2] . '</' . $tag . '>' . $blockEnd;
        }, $classicHtml);
    }

    // Remove width attribute from table, tr, td, and th tags
    $tagsToClean = array('table', 'tr', 'td', 'th');
    foreach ($tagsToClean as $tag) {
        $pattern = '/<' . $tag . '([^>]*)>/';
        $classicHtml = preg_replace_callback($pattern, function($matches) use ($tag) {
            $attributes = preg_replace('/\s*width="[^"]*"/', '', $matches[1]);
            return '<' . $tag . $attributes . '>';
        }, $classicHtml);
    }

    // Convert remaining plain text lines to Block paragraphs
    $lines = explode("\n", $classicHtml);
    $finalHtml = '';

    foreach ($lines as $line) {
        if (trim($line) && !preg_match('/^</', trim($line))) {
            $finalHtml .= '<!-- wp:paragraph -->' . "\n<p>" . trim($line) . "</p>\n" . '<!-- /wp:paragraph -->' . "\n";
        } else {
            $finalHtml .= $line . "\n";
        }
    }

    return $finalHtml;
}
?>