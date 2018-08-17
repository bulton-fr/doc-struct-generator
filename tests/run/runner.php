<?php

require_once(__DIR__.'/../../vendor/autoload.php');

$expected = file_get_contents(__DIR__.'/expected.txt');


ob_start();
$project = new \bultonFr\DocStructGenerator\ProjectParser(
    __DIR__.'/../../vendor',
    'bultonFr\\'
);
$project->run();

$result = ob_get_contents();
ob_end_clean();

if ($result === $expected) {
    echo "\033[1;37;42mResult returned corresponding to expected\033[0m\n";
    exit(0);
} else {
    echo "\033[1;37;41mResult returned not corresponding to expected\033[0m\n\n";
    echo 
        'Expected Return ('.strlen($expected).'): '."\n"
        .$expected
        ."\n----------------------------\n"
        .'Obtained Return ('.strlen($result).'): '."\n"
        .$result
        ."\n----------------------------\n"
    ;
    
    exit(1);
}
