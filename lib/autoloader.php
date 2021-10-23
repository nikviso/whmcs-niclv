<?php

function autoloadRegistry($className) {
    $fileName = str_replace('Metaregistrar\\EPP\\', '', $className);
    $fileName = __DIR__ . '/Registries/' . $fileName . '/eppConnection.php';

    //echo "Test autoload registry epp $fileName\n";
    if (is_readable($fileName)) {
        //echo "Autoloaded registry epp $fileName\n";
        require($fileName);
    }
}

function autoloadEPP($className) {
    // First load data elements
    $fileName = str_replace('Metaregistrar\\EPP\\', '', $className);
    $fileName = __DIR__ . '/Protocols/EPP/eppData/' . $fileName . '.php';

    //echo "Test autoload data $fileName\n";
    if (is_readable($fileName)) {
        //echo "Autoloaded data $fileName\n";
        require($fileName);
    }

    // Then load protocol files
    $fileName = str_replace('Metaregistrar\\', '', $className);
    $fileName = __DIR__ . '/Protocols/' . str_replace('\\', '/', $fileName) . '.php';

    // Support for EPP Request file structure
    if (strpos($className, 'Request')) {
        $fileName = str_replace('Protocols/EPP/', 'Protocols/EPP/eppRequests/', $fileName);
    }

    // Support for EPP Response file structure
    if (strpos($className, 'Response')) {
        $fileName = str_replace('Protocols/EPP/', 'Protocols/EPP/eppResponses/', $fileName);
    }

    //echo "Test autoload EPP $fileName\n";
    if (is_readable($fileName)) {
        //echo "Autoloaded EPP $fileName\n";
        require($fileName);
    }
}

spl_autoload_register('autoloadEPP');
spl_autoload_register('autoloadRegistry');
