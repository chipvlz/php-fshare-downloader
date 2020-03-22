<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\RequestException;

$link = [
    'folder' => [
        'TR9CCF1TKT',
    ],
    'file' => [
        //'EOYG3SGNO5D6', // poster instagram
    ],
    'skip' => [
        //'MLT1DKF8IMBH52I',
    ]
];

$fshareAccount = [
/*
    'type' => 'free',
    'user' => '',
    'pass' => '',
*/

    'fcode' => '',

    

    'type' => 'premium',
    'user' => '',
    'pass' => '',
    
];

$storagePath = realpath('./downloads');
$perPage = 50;
$cookieFile = 'cook.ie';
$cookieJar = new FileCookieJar($cookieFile, true);

$color = new Colors();
$client = new Client([
//    'base_uri' => 'http://httpbin.org',
    'cookies' => $cookieJar,
    'timeout' => 0,
    'allow_redirects' => false,
    'headers' => [
        'Referer' => 'https://www.fshare.vn/',
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36',
    ],
]);

// Check login state by visit manager page
$request = $client->get('https://www.fshare.vn/site/login');

$cookieArray = $cookieJar->toArray();

/*

$cookie = $cookieJar->getCookieByName('fshare-app');
if (empty($cookie)) {
    die('Cookie params not found !');
}

$cookie = explode(';', $cookie);
$cookieParams = $cookie[0];*/


if ($request->getStatusCode() != 302) { // need relogin
    echo "need login\n";

    $response = $client->get('https://www.fshare.vn/')->getBody()->getContents();

    preg_match('/meta(?:\s+)name="csrf-token"(?:\s+)content="(.*)"/i', $response, $matches);

    $csrfToken = $matches[1];
    if (empty($csrfToken)) {
        die('Không tìm thấy csrf token');
    }

    $response = $client->post('https://www.fshare.vn/site/login', [
            'form_params' => [
                '_csrf-app' => $csrfToken,
                'LoginForm' => [
                    'email' => $fshareAccount['user'],
                    'password' => $fshareAccount['pass'],
                    'rememberMe' => 0,
                ]
            ]
        ]
    )->getBody()->getContents();

    file_put_contents('result.html', $response);
}

// Try again
$request = $client->get('https://www.fshare.vn/file/manager');
if ($request->getStatusCode() == 302) { // need relogin
    echo "Wrong account/password !\n";
    die;
}


// still has logged state
echo "logged in\n\n";

// Xử lý folder trước
foreach ($link['folder'] as $folder) {
    listFolder($folder, 1);
}

// Xử lý file
echo $color->getColoredString("\nSingle List", 'black', 'green');

foreach ($link['file'] as $file) {
    downloadItem([
        'linkcode' => $file
    ], 'single');
}

function listFolder($folder, $currentPage = 1)
{
    global $client, $color, $link, $perPage;

    $request = $client->get(sprintf('https://www.fshare.vn/api/v3/files/folder?linkcode=%s&sort=type,name&page=%d&per-page=%d', $folder, $currentPage, $perPage), [
        'headers' => [
            'Accept' => 'application/json',
            'Referer' => 'https://www.fshare.vn/folder/' . $folder
        ]
    ]);

    $folders = json_decode($request->getBody()->getContents());
    $nextPage = isset($folders->_links->next);

    // Liệt kê danh sách folder ở root trước
    foreach ($folders->items as $item) {
        // skip list
        if (in_array($item->linkcode, $link['skip'])) {
            echo $color->getColoredString(sprintf("\n%s : file except skipping %s", 'https://www.fshare.vn/file/' . $item->linkcode, $item->name), 'black', 'yellow');
            continue;
        }

        // check item is folder or file base on type and size
        if ($item->type == 0 && $item->size == 0) { // is folder
            echo $color->getColoredString(sprintf("\nfolder: %s", $item->name), 'black', 'green');

            // reset lại current page sau đó đệ quy tiếp xem bên trong có còn folder con ko
            listFolder($item->linkcode, 1);
        } else { // nếu có file thì download
            downloadItem($item);
        }
    }

    // Duyệt tiếp next page
    if ($nextPage) {
        $currentPage++;
        listFolder($folder, $currentPage);
    }

}

/**
 * Tải file với path theo file
 *
 * @param $item array|object
 * @param $fileType string
 */
function downloadItem($item, $fileType = 'inFolder')
{
    global $client, $color, $fshareAccount, $storagePath;

    if ($fileType != 'single') {

        // Kiểm tra file đã tồn tại hay chưa và dung lượng bằng với file trên mạng
        $storageFilePath = $storagePath . $item->path;

        $itemPath = $storageFilePath . '/' . $item->name;

        if (file_exists($itemPath) && $item->size == filesize($itemPath)) {
            echo sprintf("\n\n%s : file existed then skipping %s\n", 'https://www.fshare.vn/file/' . $item->linkcode, $item->path . '/' . $item->name);
            return;
        }
    }

    if (is_array($item)) {
        /*[
            'linkcode' => 'abcdef',
            'name' => 'abc.zip',
            'path' => ''
        ]*/
        $item = (object)$item;
    }

    // go to file page
    $response = $client->get(sprintf('https://www.fshare.vn/file/%s?token=' . time(), $item->linkcode))->getBody()->getContents();
    preg_match('/meta(?:\s+)name="csrf-token"(?:\s+)content="(.*)"/i', $response, $matches);

    $csrfToken = $matches[1];

    if (empty($csrfToken)) {
        echo sprintf("%s :csrf token not found\n", $item->linkcode);
    }

    // have csrf token
    $formParams = [
        '_csrf-app' => $csrfToken,
        'linkcode' => $item->linkcode,
        'withFcode5' => 0
    ];

    if ($fshareAccount['type'] == 'free') {
        $formParams = array_merge($formParams, ['fcode' => $fshareAccount['fcode']]);
    }

    $request = $client->post('https://www.fshare.vn/download/get', [
        'headers' => [
            'Referer' => 'https://www.fshare.vn/file/' . $item->linkcode
        ],
        'form_params' => $formParams
    ]);

    $response = json_decode($request->getBody()->getContents());

    if (empty($response->url)) {
        echo sprintf("%s :download url not found\n", 'https://www.fshare.vn/file/' . $item->linkcode);
        file_put_contents('fails.txt', sprintf("%s :download url not found\n", 'https://www.fshare.vn/file/' . $item->linkcode), FILE_APPEND);
    }

    if (isset($response->wait_time) && $response->wait_time > 0) {
        echo $color->getColoredString(sprintf("\nwaiting to download in: %ds", $response->wait_time), 'black', 'yellow');

        sleep($response->wait_time + 1);
    }

    // Set lại path và tên cho file
    if ($fileType == 'single') {
        $item->name = $response->name;
        $item->path = '/other';

        $storageFilePath = $storagePath . $item->path;
        $itemPath = $storageFilePath . '/' . $item->name;

        if (file_exists($itemPath)) {
            echo sprintf("\n\n%s : file existed then skipping %s\n", 'https://www.fshare.vn/file/' . $item->linkcode, $item->path . '/' . $item->name);
            return;
        }
    }

    // process download file

    if (!file_exists($storageFilePath)) {
        mkdir($storageFilePath, 0777, true);
    }

    // Kiểm tra xem file có tồn tại trên fshare hay không
    $request = $client->head($response->url);


    if ($fileType == 'single') {
        if ($fshareAccount['type'] == 'free') {
            if (strlen($request->getHeaderLine('location')) < 50) {

                echo $color->getColoredString(sprintf("\n%s :file not exist on fshare %s\n%s", 'https://www.fshare.vn/file/' . $item->linkcode, $item->name, $response->url), 'white', 'red');
                file_put_contents('fails.txt', sprintf("\n%s :file not exist on fshare %s", 'https://www.fshare.vn/file/' . $item->linkcode, $item->name), FILE_APPEND);
                return;
            }

            // gán lại link download thật
            $response->url = $request->getHeaderLine('location');
        }

    } else {
        // folder type
        if ($fshareAccount['type'] == 'free') {
            if ($request->getStatusCode() != 302) {
                echo $color->getColoredString(sprintf("\n%s :file not exist on fshare %s\n%s", 'https://www.fshare.vn/file/' . $item->linkcode, $item->name, $response->url), 'white', 'red');
                file_put_contents('fails.txt', sprintf("\n%s :file not exist on fshare %s", 'https://www.fshare.vn/file/' . $item->linkcode, $item->name), FILE_APPEND);
                return;
            }

            // gán lại link download thật
            $response->url = $request->getHeaderLine('location');
        }

    }

    echo sprintf("\n\n%s :downloading %s\n", 'https://www.fshare.vn/file/' . $item->linkcode, $item->name);
    echo sprintf("\n\n%s | %s | %s\n", $item->name, $item->size, $response->url);
}
