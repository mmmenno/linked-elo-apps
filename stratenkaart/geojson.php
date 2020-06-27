<?php

include("../functions.php");

$file = "straten.geojson";


$sparql = "
PREFIX dct: <http://purl.org/dc/terms/>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX geo: <http://www.opengis.net/ont/geosparql#>
PREFIX hg: <http://rdf.histograph.io/>

select distinct ?spatial ?straatnaam (SAMPLE(?wkt) AS ?wkt) (count(?item)as ?items) where { 
    ?item dct:spatial ?spatial .
    ?spatial a hg:Street .
    ?spatial rdfs:label ?straatnaam .
    ?spatial geo:hasGeometry/geo:asWKT ?wkt
} 
group by ?spatial ?straatnaam
order by ?items
";


$endpoint = 'http://blazegraph.pre.csuc.cat/sparql';
$response = getSparqlResults($endpoint,$sparql);

$data = json_decode($response,true);


$fc = array("type"=>"FeatureCollection", "features"=>array());

$beenthere = array();

foreach ($data['results']['bindings'] as $k => $v) {

	// we don't want multiple features of one wikidata item, just because it has multiple 'types'
	if(in_array($v['spatial']['value'],$beenthere)){
		continue;
	}
	$beenthere[] = $v['spatial']['value'];


	$straat = array("type"=>"Feature");
	$props = array(
		"wdid" => str_replace("http://www.wikidata.org/entity/","",$v['spatial']['value']),
		"label" => $v['straatnaam']['value'],
		"count" => $v['items']['value']
	);
	
	
	$coords = str_replace(array("Point(",")"), "", $v['wkt']['value']);
	$latlon = explode(" ", $coords);
	$straat['geometry'] = array("type"=>"Point","coordinates"=>array((double)$latlon[0],(double)$latlon[1]));
	
	$straat['properties'] = $props;
	$fc['features'][] = $straat;

}


$gj = json_encode($fc);


file_put_contents($file, $gj);


?>