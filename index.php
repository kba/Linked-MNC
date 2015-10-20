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
$PROP_CODES = json_decode(file_get_contents('data/wikidata.json'));

$ID = @$_GET['id'];
$PROP = @$_GET['prop'];
$LANG = isset($_GET['lang']) ? $_GET['lang'] : 'en';

function isBrowserRequest()
{
    return strpos($_SERVER['HTTP_ACCEPT'], 'html') !== false;
}

function validateParameters()
{
    global $PROP_CODES;
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
}

function sendBrowserResponse()
{
    global $PROP_CODES, $PROP, $ID, $LANG;
    $items = getWikidataItems($PROP, $ID);
    header('Content-Type: text/html ; charset=UTF-8');
    echo '<html>';
    echo '<head>';
    echo '<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css"/>';
    echo '</head>';
    echo '<body>';
    if (array_key_exists("localLabels", $PROP_CODES->$PROP))
    {
        $localLabels = json_decode(file_get_contents($PROP_CODES->{$PROP}->{"localLabels"}));
        $localLang = array_key_exists($LANG, $localLabels) ? $LANG :
            array_key_exists('de', $localLabels) ? 'de' : 'en';
        echo "<h1>" . strtoupper($PROP) . " " . $ID;
        if (array_key_exists($ID, $localLabels->$localLang))
        {
            echo "&mdash; {$localLabels->$localLang->$ID}";
        }
        echo "</h1>";
    }
    if (array_key_exists("urlFormat", $PROP_CODES->$PROP))
    {
        echo 'Authoritative Source: <a href="';
        echo preg_replace('/\$1/', $ID, $PROP_CODES->$PROP->{"urlFormat"});
        echo '">';
        echo parse_url($PROP_CODES->$PROP->{"urlFormat"}, PHP_URL_HOST);
        echo '&rarr;';
        echo $ID;
        echo '</a>';

    }
    if (count($items) === 0)
    {
        echo "<p>No results from Wikidata</p>";
    }
    else {
        echo '<ul>';
        foreach ($items as $item)
        {
            $meta = getMetadataFor($item);
            $label = $meta->{"labels"}->{$LANG}->{"value"};
            $wpLink = $meta->{"sitelinks"}->{"{$LANG}wiki"}->{"url"};
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
    }
    echo '</body></html>';
}

function sendLinkedDataResponse()
{
    $items = getWikidataItems($PROP, $ID);
    if (count($items) == 0)
    {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo "Wikidata has no information about {$PROP} '{$ID}'";
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
        header('Content-Type: text/plain');
        foreach ($items as $item)
        {
            echo createItemLink($item);
            echo "\n";
        }

    }
}

function getMetadataFor($numericId)
{
    global $API_WP;
    $ID = 'Q' . $numericId;
    $query = http_build_query(array(
        "action" => "wbgetentities",
        "format" => "json",
        "props"  => "labels|sitelinks/urls",
        "ids"   =>  $ID
    ));
    $apiResponse = json_decode(file_get_contents($API_WP . '?' . $query));
    return $apiResponse->{"entities"}->{$ID};
}

function createItemLink($numericId)
{
    return 'http://wikidata.org/wiki/Special:EntityData/Q' . $numericId;
}

function createPropLink($numericId)
{
    return 'http://wikidata.org/wiki/Special:EntityData/P' . $numericId;
}

function getWikidataItems($PROP, $ID)
{
    global $PROP_CODES, $API_QUERY;
    $propCode = $PROP_CODES->$PROP->{"wikidataProp"};
    $apiQuery = http_build_query(array(
        'q' => "string[$propCode:\"{$ID}\"]"
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
 * Actual handling
 *
 */

validateParameters();


if (isBrowserRequest())
{
    sendBrowserResponse();
}
else {
    sendLinkedDataResponse();
}


// vim: sw=4 ts=4:
?>
