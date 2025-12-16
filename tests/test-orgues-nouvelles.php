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
        ];
    }
}

test_on_date_magazine_to_numero(63, '2024-01');
test_on_date_magazine_to_numero(64, '2024-03');
test_on_date_magazine_to_numero(72, '2026-03');
test_on_date_magazine_to_numero(76, '2027-03');

function test_on_numero_to_date_magazine($expected, $numero) {
    echo "Test with n°$numero $expected : ";
    $actual = on_numero_to_date_magazine($numero);
    if ($expected === $actual) {
        echo "OK\n";
    } else {
        echo "KO $actual != $expected\n";
    }
}

echo "Test on_numero_to_date_magazine\n";
test_on_numero_to_date_magazine('2008-01', 0);
test_on_numero_to_date_magazine('2008-01', -1);
test_on_numero_to_date_magazine('2008-06', 1);
test_on_numero_to_date_magazine('2008-10', 2);
test_on_numero_to_date_magazine('2023-12', 63);
test_on_numero_to_date_magazine('2024-03', 64);
test_on_numero_to_date_magazine('2026-03', 72);
test_on_numero_to_date_magazine('2027-03', 76);


