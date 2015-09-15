<?php

/*
 * Examples
 * ========
 *
 * Add ?noredirect to the URL to prevent 303 redirects.
 *
 * /ddc/515
 *
 * /orcid/0000-0001-6786-7723
 *
 * /gnd/4099246-9?lang=ru
 *
 * /ddc/2--6721?lang=ar
 *
 * /coden/NATUAS?noredirect
 *
 * /rvk/HM%203134
 *
 * /issn/0028-0836?noredirect
 *
 * /isfdb/7658?noredirect
 *
 * /parsons/*dduuudrrddudu%20udduuuddrddudu%20urrrdrdduududurrr%20rddu%20druuddduuddrr?noredirect
 *
 * /musicbrainz/5fd5831a-c692-4ad2-904e-ceda1aeda9e6
 *
 *
 *
 * TODO: Optionally exclude works
 * TODO: Use 'tree[]' query to get parent classes
 *
 *
 */

$API_WP = 'http://www.wikidata.org/w/api.php';
$API_QUERY = 'http://wdq.wmflabs.org/api';
$PROP_CODES = json_decode(file_get_contents('props.json'));

function getMetadataFor($numericId)
{
    global $API_WP;
    $id = 'Q' . $numericId;
    $query = http_build_query(array(
        "action" => "wbgetentities",
        "format" => "json",
        "props"  => "labels|sitelinks/urls",
        "ids"   =>  $id
    ));
    $apiResponse = json_decode(file_get_contents($API_WP . '?' . $query));
    return $apiResponse->{"entities"}->{$id};
}

function createItemLink($numericId)
{
    return 'http://wikidata.org/wiki/Special:EntityData/Q' . $numericId;
}

function createPropLink($numericId)
{
    return 'http://wikidata.org/wiki/Special:EntityData/P' . $numericId;
}

function getItems($prop, $id)
{
    global $PROP_CODES, $API_QUERY;
    $propCode = $PROP_CODES->$prop;
    $apiQuery = http_build_query(array(
        'q' => "string[$propCode:\"{$id}\"]"
    ));
    $apiResponse = json_decode(file_get_contents($API_QUERY . '?' . $apiQuery));
    $items = $apiResponse->items;
    if (isset($_GET['debug']))
    {
        header('Content-Type: text/plain');
        echo "Query: $apiQuery\n\n";
        echo "Query API Response:\n";
        echo json_encode($apiResponse, JSON_PRETTY_PRINT);
        foreach ($items as $item)
        {
            echo "\n";
            echo "WP API response:\n";
            echo json_encode(getMetadataFor($item), JSON_PRETTY_PRINT);
            echo "\n";
            echo $item;
            echo ": ";
            echo createItemLink($item);
        }
        exit;
    }
    return $items;
}


/*
 *
 *
 */

if (!isset($_GET['prop'])) 
{
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Must set 'prop'";
    exit;
}
else if (!array_key_exists($_GET['prop'], $PROP_CODES))
{
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Unknown property '{$_GET['prop']}'. Known identifiers: ";
    echo json_encode($PROP_CODES, JSON_PRETTY_PRINT);
    exit;
}
if (!isset($_GET['id'])) 
{
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Must set 'id'";
    exit;
}

















$id = $_GET['id'];
$prop = $_GET['prop'];
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$items = getItems($prop, $id);

if (count($items) == 0)
{
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "Wikidata has no information about {$prop} '{$id}'";
    exit;
}
else if (count($items) == 1 && !isset($_GET['noredirect']))
{
    http_response_code(303);
    header('Location: ' . createItemLink($items[0]));
}
else 
{
    http_response_code(300);
    if (strpos($_SERVER['HTTP_ACCEPT'], 'html') !== false)
    {
        header('Content-Type: text/html ; charset=UTF-8');
        echo '<html><body>';
        echo '<ul>';
        foreach ($items as $item)
        {
            $meta = getMetadataFor($item);
            $label = $meta->{"labels"}->{$lang}->{"value"};
            $wpLink = $meta->{"sitelinks"}->{"{$lang}wiki"}->{"url"};
            $uri = createItemLink($item);
            echo "<li><a href='$uri'>$label (Q$item)</a>";
            echo " <a href='http://tools.wmflabs.org/reasonator/?q=$item'>";
            echo '<img src="http://upload.wikimedia.org/wikipedia/commons/thumb/e/e8/Reasonator_logo_proposal.png/16px-Reasonator_logo_proposal.png"/>';
            echo '</a>';
            echo " <a href='$wpLink'>";
            echo '<img style="height:16px" src="https://upload.wikimedia.org/wikipedia/commons/2/20/Wikipedia-icon.png"/>';
            echo '</a>';
            echo "</li>";
        }
        echo '</ul>';
        echo '</body></html>';
    }
    else {
        header('Content-Type: text/plain');
        foreach ($items as $item)
        {
            echo createItemLink($item);
            echo "\n";
        }
    }

}

?>
