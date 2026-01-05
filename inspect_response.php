<?php

require __DIR__ . '/vendor/autoload.php';

$factory = new \Illuminate\Routing\ResponseFactory(
    new \Illuminate\View\Factory(
        new \Illuminate\View\Engines\EngineResolver,
        new \Illuminate\View\FileViewFinder(new \Illuminate\Filesystem\Filesystem, []),
        new \Illuminate\Events\Dispatcher
    ),
    new \Illuminate\Routing\Redirector(new \Illuminate\Routing\UrlGenerator(new \Illuminate\Routing\RouteCollection, new \Illuminate\Http\Request))
);

echo "Class: " . get_class($factory) . "\n";
echo "Traits: " . implode(', ', class_uses($factory)) . "\n";
echo "Has macro: " . (method_exists($factory, 'macro') ? 'yes' : 'no') . "\n";
