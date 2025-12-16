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
            [0, '2000-04'],
            [0, '2008-04'],
            [1, '2008-06'],
            [1, '2008-09'],
            [63, '2023-12'],
            [63, '2024-01'],
            [64, '2024-03'],
            [72, '2026-03'],
            [76, '2027-03'],
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



