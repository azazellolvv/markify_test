<?php

require ("vendor/autoload.php");

use Sunra\PhpSimple\HtmlDomParser;

define("FORM_ACTION", "https://search.ipaustralia.gov.au/trademarks/search/doSearch");
define('START_PAGE', 'https://search.ipaustralia.gov.au/trademarks/search/advanced');
define('ROOT_DOMAIN', 'https://search.ipaustralia.gov.au');

$result_content = [];

if (isset($argv[1])) {
    $word = $argv[1];
} else {
    exit("\n\nScript expect 1 argument - trademark name\n\n");
}

$ch = getCurl(START_PAGE);
$html = getPage($ch);

$dom = HtmlDomParser::str_get_html($html);
$csrf = $dom->find('input[name="_csrf"]', 0)->value;

$post_value = http_build_query([
    '_csrf' => $csrf,
    'wv[0]' => $word
]);

$ch = getCurl(FORM_ACTION);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_value);
$html = getPage($ch);

$dom = HtmlDomParser::str_get_html($html);
$pagination  = $dom->find('div.pagination-bottom ', 0)->find('a.goto-page');

$page_url = [];
foreach ($pagination as $element) {
    $page_url[] = ROOT_DOMAIN . html_entity_decode($element->href);
}

array_pop($page_url);
array_shift($page_url);

parseResultTable($dom, $result_content);

foreach ($page_url as $url) {

    $ch = getCurl($url);
    $html = getPage($ch);

    $dom = HtmlDomParser::str_get_html($html);

    parseResultTable($dom, $result_content);

}

print_r($result_content);

echo "\n Total result:" . count($result_content) . "\n";

//----------------------------------------------------------------------------------------------------------------------

function getPage($ch) {

    $html = curl_exec($ch);
    curl_close($ch);

    return $html;
}

function getCurl(string $url = null) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt( $ch, CURLOPT_COOKIEJAR, 'cookiefile.txt' );
    curl_setopt( $ch, CURLOPT_COOKIEFILE, 'cookiefile.txt' );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);

    return $ch;
}
function parseResultTable(simplehtmldom_1_5\simple_html_dom $dom, array &$content) {

    $rows = $dom->find('#resultsTable tr');
    array_shift($rows);

    foreach ($rows as $row) {

        $status_class = !empty($row->find('td.status i', 0)) ? $row->find('td.status i', 0)->class : '';
        $status = strpos($status_class, 'removed') === false ? 'removed' : 'registered';
        if (!empty($row->find('td.status span', 0))) {
            $status_text = $row->find('td.status span', 0)->innertext;
        } else {
            $status_text = 'Status not available';
        }

        $content[] = [
            'number'            => !empty($row->find('td.number a', 0)) ? $row->find('td.number a', 0)->innertext : null,
            'image'             => !empty($row->find('td.image img', 0))? $row->find('td.image img', 0)->src      : null,
            'words'             => !empty($row->find('td.words', 0))    ? $row->find('td.words', 0)->innertext    : null,
            'classes'           => !empty($row->find('td.classes', 0))  ? $row->find('td.classes', 0)->innertext  : null,
            'status'            => $status,
            'status_text'       => $status_text,
            'details_page_url'  => !empty($row->find('td.number a', 0)) ?
                ROOT_DOMAIN . $row->find('td.number a', 0)->href : null,
        ];
    }
}
