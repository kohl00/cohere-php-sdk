<?php

use Dotenv\Dotenv;
use Dotenv\Exception\ValidationException;

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->required('COHERE_API_KEY')->notEmpty();
    $dotenv->load();
} catch (ValidationException $e) {
    // Handle the exception.
    echo 'A validation exception occurred: ' . $e->getMessage();
    // If the missing env variable is critical for your application,
    // you might want to stop the execution here using exit() or die().
    exit();
}
