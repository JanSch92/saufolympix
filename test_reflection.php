<?php
require_once 'vendor/autoload.php';

try {
    $reflection = new ReflectionClass('App\Controller\GameController');
    
    echo "Klasse gefunden! Alle public Methoden:\n";
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() === 'App\\Controller\\GameController') {
            echo "- " . $method->getName() . "\n";
        }
    }
} catch (Exception $e) {
    echo "FEHLER: " . $e->getMessage() . "\n";
}
?>