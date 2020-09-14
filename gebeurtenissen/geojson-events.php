<?php

include("functions.php");


$sparql = "
PREFIX geo: <http://www.opengis.net/ont/geosparql#>
PREFIX dct: <http://purl.org/dc/terms/>
PREFIX foaf: <http://xmlns.com/foaf/0.1/>
PREFIX dc: <http://purl.org/dc/elements/1.1/>
PREFIX edm: <http://www.europeana.eu/schemas/edm/>
PREFIX wd: <http://www.wikidata.org/entity/>
PREFIX wdt: <http://www.wikidata.org/prop/direct/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX sem: <http://semanticweb.cs.vu.nl/2009/11/sem/>
PREFIX dbo: <http://dbpedia.org/ontology/>
SELECT DISTINCT ?item ?label ?place ?placelabel ?eventtype ?typelabel ?begin ?end ?wkt (COUNT(?cho) AS ?images) WHERE {
?item a sem:Event ;
  sem:eventType ?eventtype ;
  sem:hasPlace ?place ;
  rdfs:label ?label ;
  sem:hasEarliestBeginTimeStamp ?begin;
  sem:hasLatestEndTimeStamp ?end .
?place geo:hasGeometry/geo:asWKT ?wkt ;
  rdfs:label ?placelabel .
?eventtype rdfs:label ?typelabel .
?cho dc:subject ?item .
?aggr edm:aggregatedCHO ?cho .
?aggr edm:isShownBy ?imgurl .
FILTER (STR(?imgurl) != \"https://images.memorix.nl/lei/thumb/1000x1000/.jpg\") .
MINUS { ?cho dc:type <http://vocab.getty.edu/aat/300263837> }
} 
GROUP BY ?item ?label ?place ?placelabel ?eventtype ?typelabel ?begin ?end ?wkt
ORDER BY ?begin ?item 
LIMIT 1000";


//$endpoint = 'https://api.druid.datalegend.net/datasets/menno/elo/services/elo/sparql';
$endpoint = 'http://blazegraph.pre.csuc.cat/sparql';

$json = getSparqlResults($endpoint,$sparql);
$data = json_decode($json,true);

//print_r($data);



$venues = array();
// eerst even platslaan
foreach ($data['results']['bindings'] as $k => $v) {

	if(isset($_GET['startyear'])){
		$endyear = substr($v['end']['value'],0,4);
		if($endyear < $_GET['startyear']){
			continue;
		}
	}

	if(isset($_GET['endyear'])){
		$startyear = substr($v['begin']['value'],0,4);
		if($startyear > $_GET['endyear']){
			continue;
		}
	}

	if(isset($_GET['eventtype']) && $_GET['eventtype'] != "all"){
		if($_GET['eventtype'] != str_replace("http://www.wikidata.org/entity/", "", $v['eventtype']['value'])){
			continue;
		}
	}

	$wdid = str_replace("http://www.wikidata.org/entity/", "", $v['place']['value']);

	$venues[$wdid]['type'] = "Feature";

	if(strlen($v['wkt']['value'])){
		$venues[$wdid]['geometry'] = wkt2geojson($v['wkt']['value']);	
	}elseif(strlen($v['coords']['value'])){
		$coords = str_replace(array("Point(",")"), "", $v['coords']['value']);
		$latlon = explode(" ", $coords);
		$venues[$wdid]['geometry'] = array("type"=>"Point","coordinates"=>array((double)$latlon[0],(double)$latlon[1]));
	}

	$props = array(
		"wdid" => $wdid,
		"label" => $v['placelabel']['value']
	);
	
	if(!isset($venues[$wdid]['properties'])){
		$venues[$wdid]['properties'] = $props;
	}
	$venues[$wdid]['properties']['nr'] ++;
}



$fc = array("type"=>"FeatureCollection", "features"=>array());

foreach ($venues as $k => $venue) {

	$fc['features'][] = $venue;

}

$json = json_encode($fc);

echo $json;


















