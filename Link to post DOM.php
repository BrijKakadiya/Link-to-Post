<?php
/*
Plugin Name: Link to Post
Description: Fetch image and article headline from a link and create a post.
Version: 1.0
Author: Brij Kakadiya
*/

require_once plugin_dir_path(__FILE__) . 'simple_html_dom.php';

add_action('admin_menu', 'link_to_post_menu');
function link_to_post_menu()
{
    add_menu_page('Link to Post', 'Link to Post', 'manage_options', 'link-to-post', 'link_to_post_page');
}

function link_to_post_page()
{
    ?>
    <div class="wrap">
        <h2>Link to Post</h2>

        <form method="post" action="">
            <label for="link">Enter Link:</label>
            <input type="text" name="link" id="link" required>
            <label for="category">Select Category:</label>
            <?php wp_dropdown_categories(array('name' => 'category', 'show_option_none' => 'Select Category', 'show_count' => true, 'hide_empty' => false)); ?>
            <label for="status">Post Status:</label>
            <select name="status">
                <option value="draft">Save as Draft</option>
                <option value="publish">Publish</option>
            </select>
            <input type="submit" name="submit" class="button button-primary" value="Create Post">
        </form>

        <form method="post" action="">
            <h3>Delete Existing Post</h3>
            <label for="delete_post">Select Post to Delete:</label>
            <?php
            $posts = get_posts(array('numberposts' => -1));
            if ($posts) {
                echo '<select name="delete_post" id="delete_post">';
                foreach ($posts as $post) {
                    echo '<option value="' . $post->ID . '">' . $post->post_title . '</option>';
                }
                echo '</select>';
                echo '<input type="submit" name="delete_submit" class="button button-secondary" value="Delete Post">';
            } else {
                echo '<p>No posts found.</p>';
            }
            ?>
        </form>

        <?php
        if (isset($_POST['submit'])) {
            $link = esc_url($_POST['link']);
            $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
            $status = isset($_POST['status']) && $_POST['status'] === 'draft' ? 'draft' : 'publish';
            create_post_from_link($link, $category, $status);
        } elseif (isset($_POST['delete_submit'])) {
            $post_id_to_delete = isset($_POST['delete_post']) ? intval($_POST['delete_post']) : 0;
            delete_post_by_id($post_id_to_delete);
        }
        ?>
    </div>
    <?php
}

function create_wordpress_link($external_link)
{
    $post_data = array(
        'post_title' => 'External Link',
        'post_content' => '<a href="' . esc_url($external_link) . '" target="_blank">Visit External Link</a>',
        'post_status' => 'publish',
        'post_author' => 1,
        'post_category' => array(1),
    );

    $post_id = wp_insert_post($post_data);

    if (!is_wp_error($post_id)) {
        echo '<div class="updated"><p>WordPress link created successfully! <a href="' . get_permalink($post_id) . '" target="_blank">View Link</a></p></div>';
    } else {
        echo '<div class="error"><p>Error creating WordPress link: ' . $post_id->get_error_message() . '</p></div>';
    }
}

function delete_posts_by_ids($post_ids)
{
    foreach ($post_ids as $post_id) {
        wp_delete_post($post_id, true);
    }
    echo '<div class="updated"><p>Selected posts deleted successfully!</p></div>';
}

function create_post_from_link($link, $category_id, $status = 'publish')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $link);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    $html_content = curl_exec($ch);
    curl_close($ch);

    if (!$html_content) {
        echo '<div class="error"><p>Error fetching content from the provided link using cURL. Please check the URL and try again.</p></div>';
        return;
    }

    try {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML(mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        $headline = '';
        $image_url = '';

        $og_title = $xpath->query('//meta[@property="og:title"]/@content');
        $og_image = $xpath->query('//meta[@property="og:image"]/@content');

        if ($og_title->length > 0) {
            $headline = $og_title[0]->nodeValue;
        }

        if ($og_image->length > 0) {
            $image_url = $og_image[0]->nodeValue;
        }

        $host = parse_url($link, PHP_URL_HOST);
        $home_link = esc_url(home_url('/'));

        $post_content = '';
        $post_content .= '<p><a href="' . esc_url(home_url('/')) . '">Nimbus27</a></p>';

        if ($host) {
            $post_content .= '<p>Read more at: <a href="' . esc_url($link) . '" target="_blank">' . esc_html($host) . '</a></p>';
        } else {
            $post_content .= '<p>Read more at: <a href="' . esc_url($link) . '" target="_blank">' . esc_url($link) . '</a></p>';
        }


        //         $post_content = '';
//         $post_content .= '<p><a href="https://www.nimbus27.com/" class="button">NIMBUS27</a></p>';

        //         $host = parse_url($link, PHP_URL_HOST);
//         if ($host) {
//             $post_content .= '<p>read more > <a href="' . esc_url($link) . '" target="_blank">' . esc_html($host) . '</a></p>';
//         } else {
//             $post_content .= '<p>read more > <a href="' . esc_url($link) . '" target="_blank">' . esc_url($link) . '</a></p>';
//         }


        //         $prev_post = get_previous_post();
//         $next_post = get_next_post();

        //         if ($prev_post) {
//             $post_content .= '<p><a href="' . esc_url(get_permalink($prev_post->ID)) . '" rel="prev">Prev</a></p>';
//         }


        if ($next_post && $next_post->ID !== get_posts(array('numberposts' => 1, 'order' => 'DESC'))[0]->ID) {
            $post_content .= '<p><a href="' . esc_url(get_permalink($next_post->ID)) . '" rel="next">Next</a></p>';
        }

        $post_data = array(
            'post_title' => esc_html($headline),
            'post_content' => $post_content,
            'post_status' => $status,
            'post_author' => 1,
            'post_category' => array($category_id),
        );

        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            update_post_meta($post_id, 'external_url', esc_url($link));

            if ($image_url) {
                $image_attachment_id = media_sideload_image($image_url, $post_id, 'Thumbnail for ' . $headline, 'id');
                set_post_thumbnail($post_id, $image_attachment_id);
            }

            echo '<div class="updated"><p>Post created successfully! <a href="' . esc_url(get_permalink($post_id)) . '">View Post</a></p></div>';
        } else {
            echo '<div class="error"><p>Error creating post: ' . $post_id->get_error_message() . '</p></div>';
        }
    } catch (Exception $e) {
        echo '<div class="error"><p>Error processing the HTML: ' . $e->getMessage() . '</p></div>';
    }
}
