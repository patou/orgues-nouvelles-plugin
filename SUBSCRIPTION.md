# Intégration WooCommerce Subscriptions

Ce fichier ajoute l'affichage des numéros de magazines dans WooCommerce Subscriptions.

## Fonctionnalités

### 1. Affichage dans la metabox de détails d'abonnement

Dans l'écran de détails d'un abonnement (`/wp-admin/post.php?post=XXX&action=edit`), une section supplémentaire affiche :
- **Numéro de début** : calculé à partir de la date de début de l'abonnement
- **Numéro de fin** : calculé à partir de la date du prochain paiement (ou date de fin si plus récente)

Format : `ON-XX` (exemple: ON-71)

### 2. Colonnes dans la liste des abonnements

Dans la liste des abonnements (`/wp-admin/edit.php?post_type=shop_subscription`), deux nouvelles colonnes sont ajoutées :
- **N° Début** : numéro de magazine de début
- **N° Fin** : numéro de magazine de fin

Ces colonnes sont triables et affichent le format `ON-XX`.

## Hooks utilisés

- `woocommerce_subscription_details_after_subscription_table` : affiche les numéros après le tableau des détails
- `manage_edit-shop_subscription_columns` : ajoute les colonnes personnalisées
- `manage_shop_subscription_posts_custom_column` : remplit les colonnes avec les données
- `manage_edit-shop_subscription_sortable_columns` : rend les colonnes triables

## Calcul des numéros

Les numéros sont calculés avec la fonction `on_date_magazine_to_numero()` définie dans `orgues-nouvelles.php`.

### Logique pour le numéro de fin

1. Si une date de prochain paiement existe ET qu'il n'y a pas de date de fin : utiliser le prochain paiement
2. Si une date de fin existe ET qu'elle est antérieure au prochain paiement : utiliser la date de fin
3. Sinon : utiliser le prochain paiement (ou la date de début si aucune date future n'existe)

## Internationalisation

Toutes les chaînes de texte utilisent le domaine `orgues-nouvelles` pour la traduction.

## Tests

Des tests sont disponibles dans `tests/test-subscription.php` pour valider les calculs.
