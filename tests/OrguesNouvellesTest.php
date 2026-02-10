<?php

use PHPUnit\Framework\TestCase;

class OrguesNouvellesTest extends TestCase {

    /**
     * @dataProvider dateMagazineProvider
     */
    public function test_on_date_magazine_to_numero($expected, $date) {
        $actual = on_date_magazine_to_numero($date);
        $this->assertEquals($expected, $actual, "Failed for date: $date");
    }

    public function dateMagazineProvider() {
        return [
            [0, '2000-04-15'],
            [0, '2008-04-15'],
            [1, '2008-05-16'], // 16th May -> June (#1)
            [1, '2008-09-15'], // 15th Sep -> June (#1)
            [2, '2008-09-16'], // 16th Sep -> Oct (#2)
            [63, '2023-11-16'], // 16th Nov -> Dec (#63)
            [63, '2024-01-15'],
            [64, '2024-02-16'], // 16th Feb -> Mar (#64)
            [70, '2025-11-15'], // 15th Nov -> Oct (#70)
            [71, '2025-11-16'], // 16th Nov -> Dec (#71)
            [71, '2025-12-15'],
            [71, '2026-02-15'], // 15th Feb -> Dec (#71)
            // [72, '2026-02-16'], // 16th Feb -> Mar (#72)
        ];
    }

    /**
     * @dataProvider numeroToDateMagazineProvider
     */
    public function test_on_numero_to_date_magazine($expected, $numero) {
        $actual = on_numero_to_date_magazine($numero);
        $this->assertEquals($expected, $actual, "Failed for numero: $numero");
    }

    public function numeroToDateMagazineProvider() {
        return [
            ['2008-01', 0],
            ['2008-01', -1],
            ['2008-06', 1],
            ['2008-10', 2],
            ['2023-12', 63],
            ['2024-03', 64],
            ['2026-03', 72],
            ['2027-03', 76],
        ];
    }

    /**
     * @dataProvider subscriptionInfoProvider
     */
    public function test_on_get_subscription_info($start, $end, $expected) {
        $actual = on_get_subscription_info($start, $end);
        $this->assertEquals($expected, $actual, "Failed for range: $start to $end");
    }

    public function subscriptionInfoProvider() {
        return [
            // 16 Nov 2025 -> 71 to 74
            ['2025-11-16', '2026-11-16', [
                'numero_debut' => 71,
                'mois_debut' => '2025-12',
                'numero_fin' => 74,
                'mois_fin' => '2026-10',
                'nombre_numeros' => 4
            ]],
            // 15 Nov 2025 -> 70 to 73
            ['2025-11-15', '2026-11-15', [
                'numero_debut' => 70,
                'mois_debut' => '2025-10',
                'numero_fin' => 73,
                'mois_fin' => '2026-06',
                'nombre_numeros' => 4
            ]],
            // 14 Feb 2026 -> 71 to 74
            ['2026-02-14', '2027-02-14', [
                'numero_debut' => 71,
                'mois_debut' => '2025-12',
                'numero_fin' => 74,
                'mois_fin' => '2026-10',
                'nombre_numeros' => 4
            ]],
            // 16 Feb 2026 -> 72 to 75
            ['2026-02-16', '2027-02-16', [
                'numero_debut' => 72,
                'mois_debut' => '2026-03',
                'numero_fin' => 75,
                'mois_fin' => '2026-12',
                'nombre_numeros' => 4
            ]],
        ];
    }
}



