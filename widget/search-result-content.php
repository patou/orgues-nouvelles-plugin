<?php

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Search_Result_Content extends Widget_Base {

    public function get_name() {
        return 'search_result_content';
    }

    public function get_title() {
        return __( 'Contenu du Résultat de Recherche', 'orgues-nouvelles' );
    }

    public function get_icon() {
        return 'eicon-search'; // Icône de recherche
    }

    public function get_categories() {
        return [ 'single' ]; // Ou une catégorie personnalisée
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Paramètres du Contenu', 'orgues-nouvelles' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'post_id',
            [
                'label' => __( 'ID du Post', 'orgues-nouvelles' ),
                'type' => Controls_Manager::NUMBER,
                'description' => __( 'Laissez vide pour le post actuel (dans une boucle de recherche). Entrez un ID de post spécifique.', 'orgues-nouvelles' ),
                'placeholder' => get_the_ID(),
            ]
        );

        $this->add_control(
            'words_around_search',
            [
                'label' => __( 'Mots avant/après le terme de recherche', 'orgues-nouvelles' ),
                'type' => Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'description' => __( 'Nombre de mots à afficher avant et après chaque occurrence du terme de recherche.', 'orgues-nouvelles' ),
            ]
        );

        $this->add_control(
            'excerpt_length',
            [
                'label' => __('Longueur de l\'extrait (mots)', 'mon-widget-elementor-sans-html'),
                'type' => Controls_Manager::NUMBER,
                'default' => 50, // 0 pour afficher tout le contenu.
                'min' => 0,
                'description' => __('Définissez 0 pour afficher le contenu complet. Spécifiez un nombre pour limiter le contenu (extrait).', 'mon-widget-elementor-sans-html'),
            ]
        );

        $this->add_control(
            'highlight_tag',
            [
                'label' => __( 'Balise de mise en évidence', 'orgues-nouvelles' ),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'strong' => __( 'Strong (Gras)', 'orgues-nouvelles' ),
                    'em' => __( 'Em (Italique)', 'orgues-nouvelles' ),
                    'mark' => __( 'Mark (Surligné)', 'orgues-nouvelles' ),
                    'span' => __( 'Span (Personnalisé)', 'orgues-nouvelles' ),
                ],
                'default' => 'strong',
            ]
        );

        $this->add_control(
            'highlight_class',
            [
                'label' => __( 'Classe CSS pour la mise en évidence', 'orgues-nouvelles' ),
                'type' => Controls_Manager::TEXT,
                'default' => 'search-highlight',
                'description' => __( 'Ajoutez une classe CSS personnalisée si vous utilisez une balise <span>.', 'orgues-nouvelles' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $post_id = ! empty( $settings['post_id'] ) ? intval( $settings['post_id'] ) : get_the_ID();
        $words_around = intval( $settings['words_around_search'] );
        $highlight_tag = esc_attr( $settings['highlight_tag'] );
        $excerpt_length = intval($settings['excerpt_length']);
        $highlight_class = ! empty( $settings['highlight_class'] ) ? ' class="' . esc_attr( $settings['highlight_class'] ) . '"' : '';

        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $content = excerpt_remove_blocks($post->post_content);

                // Supprimer toutes les balises HTML du contenu pour avoir le texte brut
                $clean_content = wp_strip_all_tags( $content, true );

                // Obtenir les termes de recherche
                $search_query = get_search_query();
                $search_terms = array_filter( explode( ' ', $search_query ) ); // Diviser par espace et filtrer les vides

                $output_content = '';

                if ( ! empty( $search_terms ) && ! empty( $clean_content ) ) {
                    $processed_segments = [];
                    $content_words = explode( ' ', $clean_content );
                    $content_lower = strtolower( $clean_content );

                    foreach ( $search_terms as $term ) {
                        $term_lower = strtolower( $term );
                        $offset = 0;

                        // Trouver toutes les occurrences du terme de recherche
                        while ( ( $pos = strpos( $content_lower, $term_lower, $offset ) ) !== false ) {
                            // Calculer la position des mots
                            $start_word_index = count( explode( ' ', substr( $clean_content, 0, $pos ) ) ) -1;
                            $end_word_index = $start_word_index + count( explode(' ', $term) );

                            // Déterminer les indices de début et de fin pour l'extrait
                            $extract_start_index = max( 0, $start_word_index - $words_around );
                            $extract_end_index = min( count( $content_words ), $end_word_index + $words_around );

                            // Ajouter le segment au tableau des segments traités
                            $processed_segments[] = [
                                'start' => $extract_start_index,
                                'end' => $extract_end_index,
                                'highlight_start' => $start_word_index,
                                'highlight_end' => $end_word_index
                            ];

                            $offset = $pos + strlen( $term ); // Passer au-delà du terme trouvé
                        }
                    }

                    // Fusionner les segments qui se chevauchent
                    usort($processed_segments, function($a, $b) {
                        return $a['start'] <=> $b['start'];
                    });

                    $merged_segments = [];
                    foreach ($processed_segments as $segment) {
                        if (empty($merged_segments) || $segment['start'] > $merged_segments[count($merged_segments) - 1]['end']) {
                            $merged_segments[] = $segment;
                        } else {
                            $last_index = count($merged_segments) - 1;
                            $merged_segments[$last_index]['end'] = max($merged_segments[$last_index]['end'], $segment['end']);
                            $merged_segments[$last_index]['highlight_start'] = min($merged_segments[$last_index]['highlight_start'], $segment['highlight_start']);
                            $merged_segments[$last_index]['highlight_end'] = max($merged_segments[$last_index]['highlight_end'], $segment['highlight_end']);
                        }
                    }

                    foreach ( $merged_segments as $segment ) {
                        $segment_words = array_slice( $content_words, $segment['start'], $segment['end'] - $segment['start'] );
                        $segment_text = '';

                        for ($i = 0; $i < count($segment_words); $i++) {
                            $current_word_index_in_full_content = $segment['start'] + $i;
                            $word = $segment_words[$i];
                            $is_highlighted = false;

                            foreach ($search_terms as $term) {
                                if (stripos($word, $term) !== false) {
                                    $is_highlighted = true;
                                    // Remplacer uniquement le terme trouvé à l'intérieur du mot
                                    $word = preg_replace(
                                        '/' . preg_quote( $term, '/' ) . '/i',
                                        '<' . $highlight_tag . $highlight_class . '>$0</' . $highlight_tag . '>',
                                        $word
                                    );
                                    break;
                                }
                            }
                            $segment_text .= $word . ' ';
                        }

                        $output_content .= trim( $segment_text ) . ' ... '; // Ajout de points de suspension pour indiquer un extrait
                    }
                    $output_content = trim( $output_content, ' .' ); // Nettoyer les points de suspension en trop
                } else {
                    // Si pas de termes de recherche ou contenu vide, afficher un extrait par défaut
                    $output_content = wp_trim_words( $clean_content, $excerpt_length ); // Par défaut 50 mots
                }


                echo '<div class="elementor-search-result-content">';
                echo wp_kses_post( $output_content ); // wp_kses_post est utilisé pour la sécurité
                echo '</div>';
            } else {
                echo '<p>' . esc_html__( 'Post non trouvé.', 'orgues-nouvelles' ) . '</p>';
            }
        } else {
            echo '<p>' . esc_html__( 'Aucun ID de post spécifié ou post actuel introuvable.', 'orgues-nouvelles' ) . '</p>';
        }
    }

    protected function content_template() {
        // Pour l'aperçu en temps réel dans Elementor.
        // La logique de recherche et de surlignage est trop complexe pour le rendu JS.
        // On affiche un placeholder.
        ?>
        <div class="elementor-search-result-content">
            <#
            var post_id = settings.post_id ? settings.post_id : elementor.config.post_id;
            var excerpt_length = settings.excerpt_length ? settings.excerpt_length : 50;
            if ( post_id && elementorFrontend && elementorFrontend.documentsCache && elementorFrontend.documentsCache.models ) {
                var postModel = elementorFrontend.documentsCache.models[ post_id ];
                if ( postModel && postModel.get('post_content') ) {
                    var content = postModel.get('post_content');
                    // Supprimer les balises HTML pour obtenir le texte brut
                    var clean_content = content.replace(/(<([^>]+)>)/gi, "");
                    var words = clean_content.split(/\s+/);
                    var excerpt = words.slice(0, excerpt_length).join(" ");
                    if (words.length > excerpt_length) {
                        excerpt += " ...";
                    }
                    print( excerpt );
                } else {
                    print( '<p>Contenu du post non disponible dans l\'aperçu.</p>' );
                }
            } else {
                print( '<p>Aucun ID de post spécifié ou post actuel introuvable.</p>' );
            }
            #>
        </div>
        <?php
    }
}