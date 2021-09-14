<?php
$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';
$config = require $basePath . '/scripts/config.php';
$fb = new Facebook\Facebook([
    'app_id' => $config['app_id'],
    'app_secret' => $config['app_secret'],
    'default_graph_version' => 'v2.2',
]);

$nodes = array(
    '3DBA7270-27BA-4BE2-ABA9-F0B8A4C98AA9', //議會快訊
    'DBDD809E-9B49-49FE-BB75-9D13CD74FAF9', //議員園地
    'C0D31EC9-0B4B-40A8-927C-A4707753CBFA', //議政資訊公告
    //'B952C7E7-6565-4A07-A595-DB6F5CC98105', //招標公告
);
$newsContentUrl = 'https://www.tncc.gov.tw/page.asp?mainid=';
foreach ($nodes as $node) {
    $rawPath = $basePath . '/raw/' . $node;
    if (!file_exists($rawPath)) {
        mkdir($rawPath, 0777, true);
    }
    $rawFile = $rawPath . '/1.html';
    error_log('fetching https://www.tncc.gov.tw/listm.asp?orcaid=' . $node . '&topage=1');
    file_put_contents($rawFile, file_get_contents('https://www.tncc.gov.tw/listm.asp?orcaid=' . $node . '&topage=1'));
    $rawPage = file_get_contents($rawFile);
    if (!empty($rawPage)) {
        $pos = strpos($rawPage, '</thead>');
    } else {
        $pos = false;
    }
    while (false !== $pos) {
        $posEnd = strpos($rawPage, '</tr>', $pos);
        $line = substr($rawPage, $pos, $posEnd - $pos);
        $cols = explode('</td>', $line);
        $pageId = '';
        foreach ($cols as $k => $v) {
            if ($k === 1) {
                $v = substr($v, 0, strpos($v, '</a>'));
                $parts = explode('page.asp?mainid=', $v);
                $pageId = substr($parts[1], 0, strpos($parts[1], '"'));
            }
            $cols[$k] = trim(strip_tags($v));
        }
        $cols[] = $pageId;
        if (!empty($pageId)) {
            $isNew = false;
            $nodeFile = $rawPath . '/node_' . $pageId . '.html';
            if (!file_exists($nodeFile)) {
                $isNew = true;
            }
            $published = strtotime($cols[0]);
            $json = array(
                'published' => date('Y-m-d', $published),
                'title' => $cols[1],
                'url' => $newsContentUrl . $pageId,
            );
            $dataPath = $basePath . '/data/' . date('Y', $published) . '/' . date('m', $published);
            if (!file_exists($dataPath)) {
                mkdir($dataPath, 0777, true);
            }
            $jsonFile = $dataPath . '/' . $json['published'] . '_' . $pageId . '.json';
            error_log('fetching ' . $pageId);
            file_put_contents($nodeFile, file_get_contents($json['url']));

            $nodeRaw = file_get_contents($nodeFile);
            $nodePos = strpos($nodeRaw, '<div class="blogpost-content">');
            if (false !== $nodePos) {
                $nodePosEnd = strpos($nodeRaw, '<div style="clear:both;">', $nodePos);
                $body = substr($nodeRaw, $nodePos, $nodePosEnd - $nodePos);
                $body = str_replace(array('</p>', '&nbsp;'), array("\n", ''), $body);
                $json['content'] = trim(strip_tags($body));
                $message = $json['title'] . "\n\n" . $json['content'] . "\n\n" . $json['url'];
                if ($isNew && !empty($body)) {
                    $imgPool = [];
                    $media = [];
                    $imgPos = strpos($body, '<a data-fancybox=');
                    while (false !== $imgPos) {
                        $imgPosEnd = strpos($body, '<img src=', $imgPos);
                        $imgParts = explode('\'', substr($body, $imgPos, $imgPosEnd - $imgPos));
                        $imgPool[] = 'https://www.tncc.gov.tw' . substr($imgParts[3], 1);
                        $imgPos = strpos($body, '<a data-fancybox=', $imgPosEnd);
                    }
                    if (!empty($imgPool)) {
                        foreach ($imgPool as $imgUrl) {
                            $p = pathinfo($imgUrl);
                            $imgFile = $rawPath . '/img.' . $p['extension'];
                            file_put_contents($imgFile, file_get_contents($imgUrl));
                            try {
                                $response = $fb->post('/' . $config['page_id'] . '/photos', [
                                    'message' => $message,
                                    'source' => $fb->fileToUpload($imgFile),
                                    'published' => false,
                                ], $config['token']);
                            } catch (Facebook\Exceptions\FacebookResponseException $e) {
                                // echo 'Graph returned an error: ' . $e->getMessage();
                                // exit();
                            } catch (Facebook\Exceptions\FacebookSDKException $e) {
                                // echo 'Facebook SDK returned an error: ' . $e->getMessage();
                                // exit();
                            }
                            $media[] = ['media_fbid' => $response->getDecodedBody()['id']];
                        }
                    }
                }
                
            } else {
                $json['content'] = '';
            }
            if (!empty($media)) {
                $linkData = [
                    'message' => $message,
                    'attached_media' => $media,
                ];

                try {
                    $response = $fb->post('/' . $config['page_id'] . '/feed', $linkData, $config['token']);
                } catch (Facebook\Exceptions\FacebookResponseException $e) {
                    // echo 'Graph returned an error: ' . $e->getMessage();
                    // exit;
                } catch (Facebook\Exceptions\FacebookSDKException $e) {
                    // echo 'Facebook SDK returned an error: ' . $e->getMessage();
                    // exit;
                }
            }
            file_put_contents($jsonFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $p = pathinfo($jsonFile);
            $jParts = explode('_', $p['filename']);
            if (empty($jParts[1])) {
                unlink($jsonFile);
            } else {
                $metaFile = dirname($p['dirname']) . '/' . $jParts[0] . '.json';
                if (file_exists($metaFile)) {
                    $meta = json_decode(file_get_contents($metaFile), true);
                } else {
                    $meta = [];
                }
                if (!isset($meta[$jParts[1]])) {
                    $json = json_decode(file_get_contents($jsonFile), true);
                    $meta[$jParts[1]] = $json['url'];
                    ksort($meta);
                    file_put_contents($metaFile, json_encode($meta));
                }
            }
        }

        $pos = strpos($rawPage, '<td nowrap>', $posEnd);
    }
}
