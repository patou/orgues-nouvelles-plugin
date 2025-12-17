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
}



