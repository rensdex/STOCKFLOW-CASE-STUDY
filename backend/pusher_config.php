<?php


require_once __DIR__ . '/../vendor/autoload.php';


$pusher = new Pusher\Pusher(
    getenv('PUSHER_KEY') ?: 'e5ac4e30f057cddc45c9',  
    getenv('PUSHER_SECRET') ?: '03f786493429e3253117',  
    getenv('PUSHER_APP_ID') ?: '2155764',   
    [
        'cluster' => getenv('ap1') ?: 'ap1', 
        'useTLS' => true
    ]
);
?>