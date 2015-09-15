<?php

function getWikidataQ($numericId)
{
    return 'http://wikidata.org/wiki/Special:EntityData/Q' . $numericId;
}

if (!isset($_GET['id'])) 
{
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Must set 'id'";
    exit;
}

$api = 'http://wdq.wmflabs.org/api';

$query = http_build_query(array(
    'lang' => isset($_GET['lang']) ? $_GET['lang'] : 'en',
    'q' => "string[1036:\"{$_GET['id']}\"]"
));

$apiResponse = json_decode(file_get_contents($api . '?' . $query));

$items = $apiResponse->items;

if (count($items) == 0)
{
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "Wikidata knows nothing about {$_GET['id']}";
    exit;
}

if (isset($_GET['debug']))
{
    header('Content-Type: text/plain');
    echo $query;
    echo "\n";
    echo json_encode($apiResponse);
    echo "\n";
    foreach ($items as $item)
    {
        echo "\n";
        echo $item;
        echo ": ";
        echo getWikidataQ($item);
    }
}
else
{
    if (count($items) == 1)
    {
        http_response_code(303);
        header('Location: ' . getWikidataQ($items[0]));
    }
    else 
    {
        http_response_code(300);
        if (strpos('html', $_SERVER['ACCEPT']) !== false)
        {
            header('Content-Type: text/html');
            echo '<html><body>';
            echo '<ul>';
            foreach ($items as $item)
            {
                $uri = getWikidataQ($item);
                echo "<li><a href='$uri'>$uri</a></li>";
            }
            echo '</ul>';
            echo '</body></html>';
        }
        else {
            header('Content-Type: text/plain');
            foreach ($items as $item)
            {
                echo getWikidataQ($item);
                echo "\n";
            }
        }

    }
}

?>
