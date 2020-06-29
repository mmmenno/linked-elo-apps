<?php


include("../functions.php");

if(isset($_GET['wdid'])){
    $wdid = $_GET['wdid'];
}else{
    $wdid = "Q2946957"; // Breestraat
}


$sparql = "
SELECT ?item ?itemLabel ?itemDescription ?bagid (SAMPLE(?coords) AS ?coords)
        ?precision ?date ?alt ?vernoeming ?vernoemingLabel ?vernoemingafb ?vernoemingarticle WHERE{
    VALUES ?item { wd:" . $wdid . " }
    ?item wdt:P625 ?coords .
    ?item wdt:P5207 ?bagid .
    OPTIONAL{
        ?item p:P571/psv:P571 ?date_node . 
        ?date_node wikibase:timePrecision ?precision . 
        ?date_node wikibase:timeValue ?date 
    }
    OPTIONAL{
      ?item skos:altLabel ?alt .
    }
    OPTIONAL{
      ?item wdt:P138 ?vernoeming .
      OPTIONAL{
         ?vernoeming wdt:P18 ?vernoemingafb .  
      }
      OPTIONAL{
        ?vernoemingarticle schema:about ?vernoeming .
        ?vernoemingarticle schema:isPartOf <https://nl.wikipedia.org/> .
      }
    }
    SERVICE wikibase:label { bd:serviceParam wikibase:language \"nl,en\". }
}
GROUP BY ?item ?itemLabel ?itemDescription ?bagid ?precision ?date ?date_node ?alt ?vernoeming ?vernoemingLabel ?vernoemingafb ?vernoemingarticle
";


$endpoint = 'https://query.wikidata.org/sparql';
$response = getSparqlResults($endpoint,$sparql);

$data = json_decode($response,true);
$straat = $data['results']['bindings'][0];

$wkt = $straat['coords']['value'];
$coords = str_replace(array("Point(",")"), "", $wkt);
$latlon = explode(" ", $coords);

$namedAfter = "";
if(strlen($straat['vernoemingLabel']['value'])){
    if(strlen($straat['vernoemingafb']['value'])){
        $namedAfter .= "<img src=\"" . $straat['vernoemingafb']['value'] . "?width=200px\" />";
    }
    if(strlen($straat['vernoemingarticle']['value'])){
        $namelink = $straat['vernoemingarticle']['value'];
    }else{
        $namelink = $straat['vernoeming']['value'];
    }
    $namedAfter .= "vernoemd naar:<br /><a href=\"" . $namelink . "\">" . $straat['vernoemingLabel']['value'] . "</a>";
}

if(strlen($straat['date']['value'])){
    $aanleg = "aanleg omstreeks ";
    if($straat['precision']['value']=="9"){
        $aanleg .= substr($straat['date']['value'],0,4);
    }elseif($straat['precision']['value']=="8"){
        $aanleg .= substr($straat['date']['value'],0,3) . "0's";
    }elseif($straat['precision']['value']=="7"){
        $eeuw = (int)substr($straat['date']['value'],0,2);
        $eeuw = $eeuw+1;
        $aanleg .= $eeuw . "ste eeuw";
    }else{
        $aanleg .= substr($straat['date']['value'],0,10);
    }
}



?><!DOCTYPE html>
<html>
<head>
  
<title>Leidse straten, afgebeeld</title>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <script
  src="https://code.jquery.com/jquery-3.2.1.min.js"
  integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
  crossorigin="anonymous"></script>

  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.1.0/dist/leaflet.css" integrity="sha512-wcw6ts8Anuw10Mzh9Ytw4pylW8+NAD4ch3lqm9lzAsTxg0GFeJgoAtxuCLREZSC5lUXdVyo/7yfsqFjQ4S+aKw==" crossorigin=""/>
  <script src="https://unpkg.com/leaflet@1.1.0/dist/leaflet.js" integrity="sha512-mNqn2Wg7tSToJhvHcqfzLMU6J4mkOImSPTxVZAdo+lcPlk+GhZmYgACEe0x35K7YzW1zJ7XyJV/TT1MrdXvMcA==" crossorigin=""></script>
  <link rel="stylesheet" href="styles.css" />

  
</head>
<body>

<div id="streetinfo" class="container-fluid">
    <div class="row">
        <div id="straatnaam" class="col-md-6">
            <h1><a href="index.php?wdid=<?= $wdid ?>"><?= $straat['itemLabel']['value'] ?></a></h1>
            <?= $aanleg ?>
        </div>
        <div id="straatlinks" class="col-md-6">
            <?= $namedAfter ?>
        </div>
    </div>
</div>

<div id="bigmap"></div>

<div id="pandinfo" class="container-fluid">
    <div class="row">
        <div id="pandnaam" class="col-md-6">
            
        </div>
         <div id="pandimg" class="col-md-2">
            
        </div>
         <div id="pandlinks" class="col-md-4">
            
        </div>
    </div>
</div>


<div id="imgs" class="container-fluid">
    
</div>


<script>

$(document).ready(function() {
    createMap();
    refreshMap();

    $("#imgs").load("straatimgs.php?wdid=<?= $wdid ?>");
});

function createMap(){
    center = [<?= $latlon[1] ?>, <?= $latlon[0] ?>];
    zoomlevel = 18;

    map = L.map('bigmap', {
        center: center,
        zoom: zoomlevel,
        minZoom: 1,
        maxZoom: 20,
        scrollWheelZoom: true,
        zoomControl: false
    });

    L.control.zoom({
        position: 'bottomright'
    }).addTo(map);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);
}

function refreshMap(){
    $.ajax({
        type: 'GET',
        url: 'geojsonpanden.php?bagid=<?= $straat['bagid']['value'] ?>',
        dataType: 'json',
        success: function(jsonData) {
            if (typeof streets !== 'undefined') {
                map.removeLayer(streets);
            }

            streets = L.geoJson(null, {
                pointToLayer: function (feature, latlng) {                    
                    return new L.CircleMarker(latlng, {
                        color: "#FC2211",
                        radius:2,
                        weight: 1,
                        opacity: 0.8,
                        fillOpacity: 0.8
                    });
                },
                style: function(feature) {
                    return {
                    color: getColor(feature.properties),
                    weight:1,
                    fillOpacity: getOpacity(feature.properties),
                    clickable: true
                    };
                },
                onEachFeature: function(feature, layer) {
                layer.on({
                    click: whenClicked
                });
            }
        }).addTo(map);

        streets.addData(jsonData).bringToFront();

        //map.fitBounds(streets.getBounds());
        //$('#straatinfo').html('');
        },
        error: function() {
            console.log('Error loading data');
        }
    });
}

function getColor(props) {
    if (typeof props['wd'] === 'undefined') {
        return '#ccc';
    }
    if (typeof props['wd']['rm'] === 'undefined') {
        return '#ccc';
    }
    return '#4575b4';
}

function getOpacity(props) {
    if (typeof props['imgcount'] === 'undefined') {
        return 0;
    }
    if(props['imgcount']<3){
        return 0.3;
    }
    if(props['imgcount']<5){
        return 0.4;
    }
    if(props['imgcount']<8){
        return 0.5;
    }
    if(props['imgcount']<12){
        return 0.6;
    }
    if(props['imgcount']<20){
        return 0.7;
    }
    if(props['imgcount']<50){
        return 0.8;
    }
    return 0.9;
}

function whenClicked(){

    $("#imgs").html('');
    $("#pandnaam").html('');
    $("#pandlinks").html('');
    $("#pandimg").html('');

    var props = $(this)[0].feature.properties;

    $("#imgs").load("pandimgs.php?bagid=" + props['pandid']);

    $("#pandinfo").show();

    var kop = '';
    var links = '';
    var img = '';

    if (typeof props['wd'] === 'undefined') {
        kop = props['label'];
    }else{
        kop = props['label'] + ', ' + props['wd']['itemLabel'];
        links += 'Wikidata: <a href="' + props['wd']['item'] + '">' + props['wd']['item'] + '</a><br />';
        if (typeof props['wd']['rm'] !== 'undefined') {
            links += 'Rijksmonument: <a href="https://monumentenregister.cultureelerfgoed.nl/monumenten/' + props['wd']['rm'] + '">' + props['wd']['rm'] + '</a><br />';
        }
        if (typeof props['wd']['wp'] !== 'undefined' && props['wd']['wp'] !== null) {
            links += 'Wikipedia: <a href="' + props['wd']['wp'] + '">' + props['wd']['wp'] + '</a><br />';
        }
    }
    var info = 'Bouwjaar: ' + props['bouwjaar'];
    $("#pandnaam").html('<h2>' + kop + '</h2>' + info);

    links += 'BAG pandid: <a href="http://bag.basisregistraties.overheid.nl/bag/id/pand/' + props['pandid'] + '">' + props['pandid'] + '</a><br />';
    $("#pandlinks").html(links);

    if (typeof props['wd'] !== 'undefined' && props['wd']['afb'].length) {
        img = '<img src="' + props['wd']['afb'] + '?width=200px" />';
    }
    $("#pandimg").html(img);

    console.log(props);
    
}

</script>



</body>
</html>
