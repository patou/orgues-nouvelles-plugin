<?php
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

add_action( 'save_post', 'extract_thumbnail_from_embed_block', 10, 3 );

function extract_thumbnail_from_embed_block( $post_id, $post, $update ) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
        return;
    }

    if (wp_is_post_revision($post)) {
        return;
    }

    if (wp_is_post_autosave($post)) {
        return;
    }

    if ( has_post_thumbnail( $post ) ) {
        return;
    }

    $content = $post->post_content; // Obtenir le contenu du post

    // Vérifier si le bloc Embed est présent
    if ( ! has_block( 'core/embed', $content ) ) {
        return;
    }

    // Extraire l'URL du bloc Embed
    $embed_block = parse_blocks( $content );
    $embed_url = '';
    foreach ( $embed_block as $block ) {
        if ( $block['blockName'] === 'core/embed' && $block['attrs']['type'] === 'video' && isset( $block['attrs']['url'] )) {
            $embed_url = $block['attrs']['url'];
            break;
        }
    }

    if ( ! $embed_url ) {
        return;
    }

    // Récupérer la miniature de la vidéo
    $thumbnail_url = get_video_thumbnail( $embed_url );

    // Mettre à jour la miniature du post
    if ( $thumbnail_url ) {
        $attachment_id = media_sideload_image( $thumbnail_url . "#.jpg", $post_id, '', 'id' );
        if ( ! is_wp_error( $attachment_id ) ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }
    }
}

function get_video_thumbnail( $url ) {
    $oembed = _wp_oembed_get_object();
	$data = $oembed->get_data( $url );
    if ( ! $data && ! isset( $data->thumbnail_url )) {
        return '';
    }
    $thumbnail_url = $data->thumbnail_url;
    return $thumbnail_url;
}
