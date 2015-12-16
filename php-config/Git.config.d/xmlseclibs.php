<?php

Git::$repositories['xmlseclibs'] = [
    'remote' => 'https://github.com/robrichards/xmlseclibs.git',
    'originBranch' => '1.4',
    'workingBranch' => '1.4',
    'trees' => [
        'php-classes/XMLSecEnc.php' => 'src/XMLSecEnc.php',
        'php-classes/XMLSecurityDSig.php' => 'src/XMLSecurityDSig.php',
        'php-classes/XMLSecurityKey.php' => 'src/XMLSecurityKey.php'
    ]
];