<?php
class TestClass {
    public function anydisplaymasterkategoriaset() {
    }
}
$obj = new TestClass();
echo "method_exists anyDisplaymasterkategoriaset: " . (method_exists($obj, 'anyDisplaymasterkategoriaset') ? 'TRUE' : 'FALSE') . "\n";
echo "method_exists anydisplaymasterkategoriaset: " . (method_exists($obj, 'anydisplaymasterkategoriaset') ? 'TRUE' : 'FALSE') . "\n";
