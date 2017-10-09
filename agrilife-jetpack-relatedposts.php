<?php
/**
 * Plugin Name: AgriLife Today Related Posts
 * Plugin URI: https://github.com/AgriLife/AgriLife-Jetpack-Related-Posts
 * Description: Modifies related posts generated by Jetpack for AgriLife Today
 * Version: 1.0
 * Author: Zach Watkins
 * Author URI: http://github.com/ZachWatkins
 * Author Email: zachary.watkins@ag.tamu.edu
 * License: GPL2+
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_filter( 'jetpack_relatedposts_filter_options', 'jetpackme_no_related_posts' );
add_shortcode( 'jprel', 'jetpackme_custom_related' );

function jetpackme_custom_related( $atts ) {
    $content = '';
    $before = '<div style="display: block;" id="jp-relatedposts" class="jp-relatedposts">
  <h3 class="jp-relatedposts-headline"><em>Related</em></h3>
<div class="jp-relatedposts-items jp-relatedposts-items-visual">';
    $after = '</div></div>';
    $posts = array();
    $i = 0;
    $postid;
    $linebreak = '

';

    if ( class_exists( 'Jetpack_RelatedPosts' ) && method_exists( 'Jetpack_RelatedPosts', 'init_raw' ) ) {

        $postid = get_the_ID();
        $postpermalink = get_permalink( $postid );

        // Provide int values for from and to dates
        $date_int = get_post_time('U', true);
        $to = strtotime('now');
        $from = strtotime('-6 month 0 week 1 day', $to);

        // Get related posts
        $related = Jetpack_RelatedPosts::init_raw()
            ->set_query_name( 'jetpackme-shortcode' ) // Optional, name can be anything
            ->get_for_post_id(
                $postid,
                array(
                    // ElasticSearch filters
                    'size' => 3,
                    'date_range' => array(
                        'from' => $from,
                        'to' => $to
                    )
                )
            );

        if ( $related ) {

            $content .= $before;

            foreach ( $related as $result ) {

                // Get the related post and data
                $related_post = get_post( $result[ 'id' ] );
                $related_id = $related_post->ID;
                $related_permalink = get_permalink( $related_id );
                $related_content = $related_post->post_content;

                // Get the post title
                $posttitle = agrilifejp_gettitle( $related_post->post_title, $related_content );

                // Get the post date
                $postdate = date( 'F j, Y', strtotime( $related_post->post_date ) );

                // Get the image most closely assigned to the post
                $postimageurl = agrilifejp_getimg( $related_id, $related_content );

                // Get the category
                $category = get_the_category( $related_id );

                // Get the contacts for the article
                $contacts = get_post_meta ( $related_id, 'cmb_media_info', true );

                // Provide Google Analytics
                $onclick = 'onclick="_gaq.push([\'_trackEvent\', \'related-post-click\', \'' . $postpermalink . '\', \''. $related_permalink .'\']);"';
                $posts[] = '<div class="jp-relatedposts-post jp-relatedposts-post' . $i . ' jp-relatedposts-post-thumbs" data-post-id="' . $related_id . '" data-post-format="false"><a data-position="' . $i . '" data-origin="' . $postid . '" rel="nofollow" title="' . $posttitle . '" href="' . $related_permalink . '" ' . $onclick . ' class="jp-relatedposts-post-a"><img class="jp-relatedposts-post-img" src="' . $postimageurl . '" alt="' . $related_post->post_title . '" width="350"></a><h4 class="jp-relatedposts-post-title"><a data-position="' . $i . '" data-origin="' . $postid . '" rel="nofollow" title="' . $posttitle . '" href="' . $related_permalink . '" ' . $onclick . ' class="jp-relatedposts-post-a">' . $posttitle . '</a></h4><p class="jp-relatedposts-post-date" style="display: block;">' . $postdate . '</p><p class="jp-relatedposts-post-context">In "' . $category[0]->name . '"</p></div>';

            }

            $content .= implode( '', $posts );
            $content .= $after;

        }
    }

    // Return a list of post titles separated by commas
    return $content;
}

function jetpackme_more_related_posts( $options ) {
    $options['enabled'] = false;
    return $options;
}

function agrilifejp_gettitle( $post_title, $post_content ) {
    // Copied from jetpack-related-posts.php
    if ( ! empty( $post_title ) ) {
        return wp_strip_all_tags( $post_title );
    }

    $post_title = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post_content ) ), 5, '…' );
    if ( ! empty( $post_title ) ) {
        return $post_title;
    }

    return __( 'Untitled Post', 'agrilife-jetpack-relatedposts' );
}

function agrilifejp_getexcerpt( $post_excerpt, $post_content ) {
    // Copied from jetpack-related-posts.php
    if ( empty( $post_excerpt ) )
        $excerpt = $post_content;
    else
        $excerpt = $post_excerpt;

    return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $excerpt ) ), 50, '…' );
}

function agrilifejp_getimg( $post_id, $post_content ){
    $url = get_post_thumbnail_id( $post_id );

    if($url != ''){
        // We can use the post's featured image
        $url = 'http://i0.wp.com/' . str_replace( 'http://', '', wp_get_attachment_url( $url ) );
        $url .= '?resize=350%2C200';
    } else {
        // Match the first image url in the post content
        $pattern = '/src="http[s]?:\/\/([^"]+)/';
        preg_match($pattern, $post_content, $matches);

        if( count($matches) > 1 ){
            $url = 'http://i2.wp.com/' . $matches[1] . '?resize=350%2C200&matches=' . count($matches);
        } else {
            $url = get_template_directory_uri() . '/images/default-featured-image.jpg';
        }
    }

    return $url;
}

function jetpackme_filter_exclude_category( $filters ) {
    $filters[] = array( 'not' =>
        array( 'term' => array( 'category.slug' => 'farm-ranch' ) )
    );

    return $filters;
}

function jetpackme_no_related_posts( $options ) {
    if ( is_single( array( 52425 ) ) || ( class_exists( 'Jetpack_RelatedPosts' ) && method_exists( 'Jetpack_RelatedPosts', 'init_raw' ) ) ) {
        $options['enabled'] = false;
    }
    return $options;
}
