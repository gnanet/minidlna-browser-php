<?php


/*
 * MiniDLNA Database Browser
 *
 * Author: Gergely Nagy <gna@r-us.hu>
 * URL: https://github.com/gnanet/minidlna-browser-php
 *
 */

define('BASEPATH', __DIR__);
define('APP_SETTINGS_FILE', __DIR__.'/.env');

if ( ! file_exists(APP_SETTINGS_FILE) ) {
    echo "Need ".APP_SETTINGS_FILE." for configuration".PHP_EOL;
    echo "Use example.env as basis".PHP_EOL;
    exit;
}

if ( ! file_exists(__DIR__ . '/vendor/autoload.php') ) {
    echo "Run composer install, first".PHP_EOL;
    exit;
}
// Autoload files using composer
require_once __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli-server') {
    echo "Usage:".PHP_EOL;
    echo "php -S 0.0.0.0:8290 browser.php".PHP_EOL;
    exit;
}

// Use this namespace
use Symfony\Component\Dotenv\Dotenv;

// Use this namespace
use Steampixel\Route;

$dotenv = new Dotenv();
$dotenv->load(APP_SETTINGS_FILE);

$app_config = array(
    'DATABASE' => $_SERVER['DATABASE'],
    'BASE_URL' => $_SERVER['BASE_URL'],
    'ROOT_ID' => $_SERVER['ROOT_ID'],
    'PFW_CMD' => (isset($_SERVER['PFW_CMD']) ? $_SERVER['PFW_CMD'] : ''),
    'PFW_URL' => (isset($_SERVER['PFW_URL']) ? $_SERVER['PFW_URL'] : ''),
);

if ( ! file_exists($app_config['DATABASE']) ) {
    error_log("Missing ".$app_config['DATABASE']);
    exit;
}

// Initialize TWIG
$loader = new \Twig\Loader\FilesystemLoader( BASEPATH . '/templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false,
]);


function filesizeformat($bytes, $binary = false) {
    $unit     = 'Byte';
    $prefix   = '';
    $bytes    = (float)$bytes;
    $base     = $binary ? 1024 : 1000;
    $prefixes = array('K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y');

    if ($bytes == 1) {
        return '1 Byte';
    } elseif ($bytes < $base) {
        return $bytes . ' Bytes';
    }

    foreach ($prefixes as $i => $prefix) {
        $unit = pow($base, ($i + 2));
        if ($bytes < $unit) {
            return sprintf('%.1f %s', ($base * $bytes / $unit), $prefix . ($binary ? 'iB' : 'B'));
        }
    }

    return sprintf('%.1f %s', ($base * $bytes / $unit), $prefix . ($binary ? 'iB' : 'B'));
}


function browse($object_id = NULL) {
    global $app_config, $twig;
    $db = new SQLite3($app_config['DATABASE']);
    $root_path = $db->querySingle("SELECT VALUE FROM SETTINGS WHERE KEY = 'media_dir'");
    $items = array();
    $template_vars = array();
    if (( $object_id == NULL ) || ( $object_id == $app_config['ROOT_ID'] )) {
        $object_id = $app_config['ROOT_ID'];
        $path_array = array( 'PARENT_ID' => NULL, 'PATH' => NULL);
    } else {
        $path_array = $db->querySingle('select PARENT_ID, PATH FROM OBJECTS JOIN DETAILS ON OBJECTS.DETAIL_ID=DETAILS.ID WHERE OBJECT_ID = "'. $object_id.'"', true);
    }
    $cur = $db->query('select OBJECTS.ID, CLASS, OBJECT_ID, NAME, PATH, TITLE, MIME, SIZE, DURATION, DETAIL_ID FROM OBJECTS JOIN DETAILS ON OBJECTS.DETAIL_ID=DETAILS.ID WHERE PARENT_ID="'.$object_id.'" ORDER BY CLASS ASC, NAME ASC, OBJECT_ID ASC');
    while ($item = $cur->fetchArray()) {
        $item = array_change_key_case($item);
        $item['basename'] = basename($item['path']);
        $item['filesizeformat'] = filesizeformat($item['size']);
        $items[] = $item;
    }

    $template_vars = array(
        'root_path' => $root_path,
        'parent_id' => $path_array['PARENT_ID'],
        'path' => $path_array['PATH'],
        'breadcrumbs' => str_replace('/','</li><li class="breadcrumb-item">',str_replace($root_path."/","",$path_array['PATH'])),
        'items' => $items,
        'BASE_URL' => $app_config['BASE_URL'],
        'root_id' => $app_config['ROOT_ID'],
        'PFW_URL' => $app_config['PFW_URL'],
        'PFW_CMD' => $app_config['PFW_CMD'],
    );

    return $twig->render('browse.twig.html', $template_vars);
}


// build routes
Route::add('/', function() {
    global $app_config, $twig;
    return browse();
});

Route::add('/browse/(.*)', function($object_id) {
    global $app_config, $twig;
    return browse($object_id);
});


// Run the router
Route::run('/');

