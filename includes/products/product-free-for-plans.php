<?php

function on_set_product_price_to_zero($price, $product)
{

    // Assurez-vous d'avoir accès à la fonction personnalisée (simulée ici pour l'exemple)
    if (!function_exists('on_liste_numeros')) {
        // En cas d'erreur ou si la fonction n'est pas définie ailleurs.
        return $price;
    }

    // Récupérer les valeurs des champs Pods
    $plans_field = pods_field('product',$product->get_id(), 'plans', false);
    $numero_field = pods_field('product',$product->get_id(), 'numero', true);

    // 1. Vérifier si les deux champs Pods 'plans' et 'numero' ont une valeur
    if (!empty($plans_field) && !empty($numero_field)) {

        // --- 2. Préparer la liste des Slugs pour 'on_liste_numeros' ---
        $listes_slugs = array();

        // Le champ 'plans' est généralement un champ relation. Il peut retourner un tableau
        // d'objets Pods, d'ID ou de slugs. Nous allons extraire les slugs.
        if (is_array($plans_field)) {
            foreach ($plans_field as $plan_item) {
                // Si le résultat est un objet Pods complet, utilisez 'post_name' pour le slug
                if (is_object($plan_item) && isset($plan_item->post_name)) {
                    $listes_slugs[] = $plan_item->post_name;
                }
                // Si c'est un tableau (moins commun), essayez de récupérer le slug
                elseif (is_array($plan_item) && isset($plan_item['post_name'])) {
                    $listes_slugs[] = $plan_item['post_name'];
                }
                // Si c'est un ID (et non un objet Pods complet) vous aurez besoin d'une requête pods()
                // Pour simplifier, si le champ est configuré pour retourner des Slugs, il sera directement dans $plans_field
                else {
                    // Si vous avez configuré le champ Pods pour retourner les Slugs directement :
                    $listes_slugs[] = $plan_item;
                }
            }
        } elseif (is_string($plans_field)) {
            // Si le champ 'plans' n'est pas multiple et retourne une chaîne (le slug ou l'ID)
            $listes_slugs[] = $plans_field;
        }

        // Nettoyer les slugs vides ou en double
        $listes_slugs = array_filter(array_unique($listes_slugs));


        // --- 3. Appeler la fonction et vérifier la présence du numéro ---
        if (!empty($listes_slugs)) {

            // Exécute la fonction personnalisée avec les slugs
            $numeros_disponibles = on_liste_numeros($listes_slugs);

            // Vérifie si la valeur du champ "numero" est dans la liste des numéros retournés
            // Convertir le champ "numero" en nombre (entier) et normaliser la liste pour comparer
            $numero_norm = is_scalar($numero_field) && is_numeric($numero_field) ? intval($numero_field) : $numero_field;
            if (in_array($numero_norm, $numeros_disponibles, true)) {
                return 0;
            }
        }
    }

    // Sinon, retourne le prix normal
    return $price;
}
add_filter('woocommerce_product_get_price', 'on_set_product_price_to_zero', 10, 2);
add_filter('woocommerce_product_get_regular_price', 'on_set_product_price_to_zero', 10, 2);
// Ajoutez également pour les produits variables et les prix à l'affichage
add_filter('woocommerce_product_get_sale_price', 'on_set_product_price_to_zero', 10, 2);
add_filter('woocommerce_get_price_html', 'on_price_html', 20, 2);


/**
 * Empêche WooCommerce d'afficher le prix barré s'il est à 0.
 * Cette fonction est optionnelle mais recommandée pour un affichage propre.
 */
function on_price_html($price_html, $product)
{
    if ($product->get_price() === 0) {
        return '<span class="woocommerce-price-change">' . __('Inclus', 'orgues-nouvelles') . '</span>';
    }
    return $price_html;
}