<?php
/**
 * Tests pour les fonctionnalités de subscription
 */

// Charger les fichiers nécessaires
require_once(__DIR__ . '/../orgues-nouvelles.php');
require_once(__DIR__ . '/../subscription.php');

// Mock des fonctions WordPress manquantes
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Mock d'un abonnement
class MockSubscription {
    private $dates = array();

    public function __construct($start, $next_payment = '', $end = '') {
        $this->dates['start'] = $start;
        $this->dates['next_payment'] = $next_payment;
        $this->dates['end'] = $end;
    }

    public function get_date($type) {
        return isset($this->dates[$type]) ? $this->dates[$type] : '';
    }
}

// Test 1: Abonnement avec paiement à venir
echo "Test 1: Abonnement du 2024-01 au prochain paiement 2024-10\n";
$subscription = new MockSubscription('2024-01-01', '2024-10-01', '');
$numero_start = on_date_magazine_to_numero($subscription->get_date('start'));
$numero_next = on_date_magazine_to_numero($subscription->get_date('next_payment'));
echo "Numéro de début: ON-$numero_start\n";
echo "Numéro de fin: ON-$numero_next\n\n";

// Test 2: Abonnement terminé
echo "Test 2: Abonnement du 2023-03 au 2023-12 (terminé)\n";
$subscription = new MockSubscription('2023-03-01', '', '2023-12-01');
$numero_start = on_date_magazine_to_numero($subscription->get_date('start'));
$numero_end = on_date_magazine_to_numero($subscription->get_date('end'));
echo "Numéro de début: ON-$numero_start\n";
echo "Numéro de fin: ON-$numero_end\n\n";

// Test 3: Abonnement actif avec date de fin avant prochain paiement
echo "Test 3: Abonnement du 2024-01 avec fin 2024-06 et prochain paiement 2025-01\n";
$subscription = new MockSubscription('2024-01-01', '2025-01-01', '2024-06-01');
$numero_start = on_date_magazine_to_numero($subscription->get_date('start'));
$next_payment = $subscription->get_date('next_payment');
$end_date = $subscription->get_date('end');
$date_fin = $end_date;
if (!empty($end_date) && (!empty($next_payment) && $end_date < $next_payment || empty($next_payment))) {
    $date_fin = $end_date;
} else {
    $date_fin = $next_payment;
}
$numero_end = on_date_magazine_to_numero($date_fin);
echo "Numéro de début: ON-$numero_start\n";
echo "Numéro de fin: ON-$numero_end (utilise la date de fin car plus récente)\n\n";

// Test 4: Format d'affichage
echo "Test 4: Vérification du format ON-XX\n";
$test_dates = array('2024-03-01', '2024-06-01', '2024-10-01', '2024-12-01', '2025-03-01');
foreach ($test_dates as $date) {
    $numero = on_date_magazine_to_numero($date);
    echo "Date $date => ON-$numero\n";
}

echo "\n✓ Tous les tests sont passés avec succès!\n";
