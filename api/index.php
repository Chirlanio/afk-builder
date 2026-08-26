<?php

/*
|--------------------------------------------------------------------------
| Front controller serverless (Vercel)
|--------------------------------------------------------------------------
|
| Na Vercel o filesystem do deploy é somente-leitura: apenas /tmp aceita
| escrita. O vercel.json aponta LARAVEL_STORAGE_PATH para /tmp/storage, mas
| a árvore de diretórios precisa existir antes do framework subir — senão
| sessão, cache de views e logs falham no cold start.
|
*/

$storage = '/tmp/storage';

$directories = [
    $storage.'/app/public',
    $storage.'/framework/cache/data',
    $storage.'/framework/sessions',
    $storage.'/framework/views',
    $storage.'/logs',
];

foreach ($directories as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
}

require __DIR__.'/../public/index.php';
