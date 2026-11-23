<?php
/*
 * MiniDLNA Database Browser
 *
 * Author: Gergely Nagy <gna@r-us.hu>
 * URL: https://github.com/gnanet/minidlna-browser-php
 *
 * Runs and tested with PHP8.3
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
    echo "php -S 0.0.0.0:8299 browser.php".PHP_EOL;
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
    'MPV_PLAY_URI' => $_SERVER['MPV_PLAY_URI'],
    'ROOT_ID' => $_SERVER['ROOT_ID'],
    'PFW_CMD' => (isset($_SERVER['PFW_CMD']) ? $_SERVER['PFW_CMD'] : ''),
    'PFW_URL' => (isset($_SERVER['PFW_URL']) ? $_SERVER['PFW_URL'] : ''),
    'AUTH_USER' => (isset($_SERVER['AUTH_ALLOW']) && isset($_SERVER['AUTH_PASS']) && ( ! empty($_SERVER['AUTH_ALLOW'])) && ( ! empty($_SERVER['AUTH_PASS'])) ? $_SERVER['AUTH_ALLOW'] : ''),
    'AUTH_PASS' => (isset($_SERVER['AUTH_ALLOW']) && isset($_SERVER['AUTH_PASS']) && ( ! empty($_SERVER['AUTH_ALLOW'])) && ( ! empty($_SERVER['AUTH_PASS'])) ? $_SERVER['AUTH_PASS'] : ''),
);

$login_langs = [];

$login_langs['en'] = [
    'login_please' => 'Please log in',
    'username_label' => 'Username',
    'username_placeholder' => 'Username',
    'password_label' => 'Password',
    'password_placeholder' => 'Password',
    'btn_login_text' => 'Login',
];

$login_langs['hu'] = [
    'login_please' => 'Kérem jelentkezzen be',
    'username_label' => 'Felhasználónév',
    'username_placeholder' => 'Felhasználónév',
    'password_label' => 'Jelszó',
    'password_placeholder' => 'Jelszó',
    'btn_login_text' => 'Belépés',
];

$use_lang = 'hu';

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

function items_date_sort($a, $b)
{
    return strtotime($a["mtime"]) <=> strtotime($b["mtime"]);
}

function items_duration_sort($a, $b)
{
    return strtotime($a["duration"],0) <=> strtotime($b["duration"],0);
}

function asset2gz($asset_fullpath) {
    if ( file_exists($asset_fullpath) ) {
        if ( file_exists($asset_fullpath.'.gz') ) {
            $asset_mtime = filemtime($asset_fullpath);
            $gz_mtime = filemtime($asset_fullpath.'.gz');
            if ( $asset_mtime < $gz_mtime ) {
                return $asset_fullpath.'.gz';
            }
        }
        $asset_content = file_get_contents($asset_fullpath);
        $gz = gzopen($asset_fullpath.'.gz','w9');
        gzwrite($gz, $asset_content);
        gzclose($gz);
        return $asset_fullpath.'.gz';
    }
    return false;
}

function browse($object_id = NULL) {
    global $app_config, $twig;
    $db = new SQLite3($app_config['DATABASE'], SQLITE3_OPEN_READONLY );
    $root_path = $db->querySingle("SELECT VALUE FROM SETTINGS WHERE KEY = 'media_dir'");
    $items = [];
    $folderitems = [];
    $mediaitems = [];
    $template_vars = [];
    $breadcrumb_ids = [];
    $breadcrumbs_string="";
    $sorting = 'none';
    $cookie_options = array (
            'expires' => time() + 60*60*24*365, // 1 year
            'path' => '/',
            'domain' => explode(':',$_SERVER['HTTP_HOST'])[0],
            'secure' => true,     // true or false
            'httponly' => true,    // true or false
            'samesite' => 'None' // None || Lax  || Strict
        );
    if ( isset($_GET['sort']) ) {
        if ( trim($_GET['sort']) == 'date' ) {
            $sorting = 'date';
        } else
        if ( trim($_GET['sort']) == 'len') {
            $sorting = 'len';
        }
    }
    if ( $sorting == 'none' ) {
        if ( setcookie('sort_method', '', time() - 3600, "/", explode(':',$_SERVER['HTTP_HOST'])[0], 1) === false ) {
            error_log('clear cookie sort_method failed');
        }
        if ( setcookie('sort_direction', '', time() - 3600, "/", explode(':',$_SERVER['HTTP_HOST'])[0], 1) === false ) {
            error_log('clear cookie sort_direction failed');
        }
    } else {
        if ( setcookie('sort_method', $sorting, $cookie_options) === false ) {
            error_log('set cookie sort_method to '.$sorting.' failed');
        }
        if ( setcookie('sort_direction', 'ascending', $cookie_options) === false ) {
            error_log('set cookie sort_direction to ascending failed');
        }
    }
    if (( $object_id == NULL ) || ( $object_id == $app_config['ROOT_ID'] )) {
        $object_id = $app_config['ROOT_ID'];
        $path_array = array( 'PARENT_ID' => NULL, 'PATH' => NULL);
    } else {
        $path_array = $db->querySingle('select PARENT_ID, PATH FROM OBJECTS JOIN DETAILS ON OBJECTS.DETAIL_ID=DETAILS.ID WHERE OBJECT_ID = "'. $object_id.'"', true);
        // Start making crumbles from bread
        $path_elements = explode('$',$object_id);
        $path_elements_cnt = count($path_elements);
        $rpath_elements = array_reverse($path_elements);
        $ei = 1; // skip the "home" item
        while ($ei < $path_elements_cnt ) {
            $breadcrumb_ids[] = urlencode(implode('$',$path_elements));
            array_pop($path_elements);
            $ei++;
        };
        $breadcrumb_ids=array_reverse($breadcrumb_ids);
        $path_elements_title = explode("/",str_replace($root_path."/","",$path_array['PATH']));
        $breadcrumbs = array_combine($breadcrumb_ids,$path_elements_title);
        $bcqry = "";
        if ( $sorting != 'none' ) {
            $bcqry = "?sort=".$sorting;
        }
        // build the HTML code-snippet for breadcrumbs
        foreach ( $breadcrumbs as $crumb_id => $crumb_title ) {
            $breadcrumbs_string .= '            <li class="breadcrumb-item';
            if ( urlencode($object_id) == $crumb_id ) { $breadcrumbs_string .= " active"; }
            $breadcrumbs_string .= '">'.PHP_EOL;
            if ( urlencode($object_id) == $crumb_id ) {
            $breadcrumbs_string .= '                <span data-minidlna-object-id="'.urldecode($crumb_id).'">'.$crumb_title.'</span>'.PHP_EOL;
            } else {
            $breadcrumbs_string .= '                <a href="/browse/'.$crumb_id.'/'.$bcqry.'" data-minidlna-object-id="'.urldecode($crumb_id).'">'.$crumb_title.'</a>'.PHP_EOL;
            }
            $breadcrumbs_string .= '            </li>'.PHP_EOL;
        }
        // Finish building breadcrumbs
    }
    // build the media items of the current folder
    $cur = $db->query('select OBJECTS.ID, CLASS, OBJECT_ID, NAME, PATH, TITLE, MIME, RESOLUTION, SIZE, DURATION, TIMESTAMP, DETAIL_ID FROM OBJECTS JOIN DETAILS ON OBJECTS.DETAIL_ID=DETAILS.ID WHERE PARENT_ID="'.$object_id.'" ORDER BY CLASS ASC, NAME ASC, OBJECT_ID ASC');
    while ($item = $cur->fetchArray()) {
        $item = array_change_key_case($item);
        $item['basename'] = basename($item['path']);
        $item['filesizeformat'] = filesizeformat($item['size']);
        if ( str_starts_with($item['class'], 'container' )) {
            $item['mtime'] = 0;
            $folderitems[] = $item;
        } else {
            $item['mtime'] = date ("Y-m-d H:i:s", $item['timestamp']);
            $mediaitems[] = $item;
        }
    }

    if ( $sorting != 'none' ) {
        if ( $sorting == 'date' ) {
            usort($mediaitems, "items_date_sort");
        } else
        if ( $sorting == 'len' ) {
            usort($mediaitems, "items_duration_sort");
        }
    }

    foreach($folderitems as $folderitem) {
        $items[] = $folderitem;
    }
    foreach($mediaitems as $mediaitem) {
        $items[] = $mediaitem;
    }

    // set template variables
    $template_vars = array(
        'request_uri' => $_SERVER['SCRIPT_NAME'],
        'sorting' => $sorting,
        'root_path' => $root_path,
        'parent_id' => $path_array['PARENT_ID'],
        'path' => $path_array['PATH'],
        'title_path' => str_replace('/',' / ',str_replace($root_path,'',$path_array['PATH'])),
        'breadcrumbs' => $breadcrumbs_string,
        'items' => $items,
        'BASE_URL' => $app_config['BASE_URL'],
        'MPV_PLAY_URI' => $app_config['MPV_PLAY_URI'],
        'root_id' => $app_config['ROOT_ID'],
        'PFW_URL' => $app_config['PFW_URL'],
        'PFW_CMD' => $app_config['PFW_CMD'],
    );
    // Render the result using twig
    return $twig->render('browse.twig.html', $template_vars);
}


function login() {
    global $app_config, $twig, $login_langs, $use_lang;

    $returnuri = '/';
    if (isset($_SESSION['returnto'])) {
        $returnuri = $_SESSION['returnto'];
    }

    // If User has already logged in, redirect to dashboard page.
    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === TRUE)
    {
        header('Location: '.$returnuri);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
        $posted_return_uri = filter_input(INPUT_POST, 'return_uri');
        if ( $returnuri != $posted_return_uri ) {
            error_log("inside login() posted return_uri ".$posted_return_uri." mismatch (session return_uri ".$returnuri.")");
        }

        $username = filter_input(INPUT_POST, 'username');
        $password = filter_input(INPUT_POST, 'password');

        // check user + pass
        if (($username == $app_config['AUTH_USER']) && ($password == $app_config['AUTH_PASS']))
        {
            $_SESSION['user_logged_in'] = TRUE;
            // Authentication successfull redirect user
            unset($_SESSION['returnto']);
            header('Location: '.$returnuri);
            exit;
        }
        else
        {
            $_SESSION['danger'] = 'Invalid username or password';
            error_log("inside login() after failed login session: ".print_r($_SESSION,true).PHP_EOL);
            header('Location: /login');
            exit;
        }
    }
    else if ( ($_SERVER['REQUEST_METHOD'] !== 'POST') && ($_SERVER['REQUEST_METHOD'] !== 'GET') )
    {
        die('Method not allowed');
    }

    // set template variables
    $template_vars = array(
        'request_uri' => $_SERVER['SCRIPT_NAME'],
        'return_uri' => $returnuri,
        'login_please' => $login_langs[$use_lang]['login_please'],
        'username_label' => $login_langs[$use_lang]['username_label'],
        'username_placeholder' => $login_langs[$use_lang]['username_placeholder'],
        'password_label' => $login_langs[$use_lang]['password_label'],
        'password_placeholder' => $login_langs[$use_lang]['password_placeholder'],
        'btn_login_text' => $login_langs[$use_lang]['btn_login_text'],
    );
    if ( isset($_SESSION['danger']) ) {
        $template_vars['alert_danger'] = trim($_SESSION['danger']);
        unset($_SESSION['danger']);
    }
    // Render the result using twig
    return $twig->render('login.twig.html', $template_vars);
}

// build routes

// images
Route::add('/images/(.*\.(jpg|png))', function($imagefile) {
    global $app_config, $twig;
    if ( file_exists(BASEPATH .'/images/'.$imagefile) )
    {
        $imagefile = BASEPATH .'/images/'.$imagefile;
        header('Content-Type: ' . mime_content_type($imagefile));
        header('Content-Length: ' . filesize($imagefile));
        ob_clean();
        flush();
        readfile($imagefile);
        exit;
    }
});

// css
Route::add('/css/(.*\.css)', function($cssfile) {
    global $app_config, $twig;
    if ( file_exists(BASEPATH .'/css/'.$cssfile) )
    {
        header('Content-Type: text/css; charset=utf-8');
        $cssfile = BASEPATH .'/css/'.$cssfile;
        $css_mtime = filemtime($cssfile);
        asset2gz($cssfile);
        if ( file_exists($cssfile.'.gz') )
        {
            $css_gz_mtime = filemtime($cssfile.'.gz');
            if ( $css_mtime <= $css_gz_mtime )
            {
                $cssfile = $cssfile.'.gz';
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
            }
        }
        header('Content-Length: ' . filesize($cssfile));
        ob_clean();
        flush();
        readfile($cssfile);
        exit;
    }
});

// js
Route::add('/js/(.*\.js)', function($jsfile) {
    global $app_config, $twig;
    if ( file_exists(BASEPATH .'/js/'.$jsfile) )
    {
        header('Content-Type: application/javascript; charset=utf-8');
        $jsfile = BASEPATH .'/js/'.$jsfile;
        $js_mtime =  filemtime($jsfile);
        asset2gz($jsfile);
        if ( file_exists($jsfile.'.gz') )
        {
            $js_gz_mtime = filemtime($jsfile.'.gz');
            if ( $js_mtime <= $js_gz_mtime )
            {
                $jsfile = $jsfile.'.gz';
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
            }
        }
        header('Content-Length: ' . filesize($jsfile));
        ob_clean();
        flush();
        readfile($jsfile);
        exit;
    }
});

// home
Route::add('/', function() {
    global $app_config, $twig;
    if ( empty($app_config['AUTH_USER']) !== true )
    {
        session_start();
        // If User is not logged in, redirect to login page
        if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in'])
        {
            $_SESSION['returnto'] = '/';
            header('Location: /login');
            return;
        }
    }
    return browse();
});

// browse folders
Route::add('/browse/(.*)', function($object_id) {
    global $app_config, $twig;
    if ( empty($app_config['AUTH_USER']) !== true )
    {
        session_start();
        // If User is not logged in, redirect to login page
        if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in'])
        {
            $_SESSION['returnto'] = '/browse/'.$object_id;
            header('Location: /login');
            exit;
        }
    }
    return browse($object_id);
});

// login
Route::add('/login', function() {
    global $app_config, $twig;
    if ( empty($app_config['AUTH_USER']) !== true )
    {
        session_start();
        if (! isset($_SESSION['returnto']) ) {
            $_SESSION['returnto'] = '/';
            $_SESSION['fallback_return'] = 'yes';
        }
        return login();
    }
    else
    {
        header('Location: /');
        exit;
    }
}, ['get','post']);

// Run the router
Route::run('/');
