<?php

include("../functions.php");

// STEP 1: ALL BUILDINGS IN THIS STREET FROM BAG
$sparql = "
PREFIX bag: <http://bag.basisregistraties.overheid.nl/def/bag#>
PREFIX geo: <http://www.opengis.net/ont/geosparql#>
select DISTINCT ?pand ?wkt ?bouwjaar (sample(?nr) as ?nr) (count(?nr) as ?nrcount)
where {
  ?na bag:bijbehorendeOpenbareRuimte <http://bag.basisregistraties.overheid.nl/bag/id/openbare-ruimte/" . $_GET['bagid'] . "> .
  ?na bag:huisnummer ?nr .
  optional {
   	?na bag:huisletter ?letter . 
  }
  BIND( if(BOUND(?letter),
      CONCAT(STR(?nr),STR(?letter)),
      CONCAT(STR(?nr))
    ) AS ?adres )
  ?vo bag:hoofdadres ?na .
  ?vo bag:pandrelatering ?pand .
  graph ?pandVoorkomen {
    ?pand geo:hasGeometry ?geom .
    ?geom geo:asWKT ?wkt .
  	?pand bag:oorspronkelijkBouwjaar ?bouwjaar .
  }
  filter not exists { ?pandVoorkomen bag:eindGeldigheid [] } 
}
GROUP BY ?pand ?wkt ?bouwjaar
limit 1000
";

//$endpoint = 'https://data.pdok.nl/sparql';
$endpoint = 'https://bag.basisregistraties.overheid.nl/sparql/now';

$response = getSparqlResults($endpoint,$sparql);

$pdokdata = json_decode($response,true);

//echo "bag done\n";

// STEP 2: ALL BAGBUILDINGS KNOWN TO WIKIDATA
$sparql = "
SELECT DISTINCT ?item ?itemLabel ?itemDescription ?bagid ?rm ?afb ?pandarticle
WHERE {
	VALUES ?bagid { ";

foreach ($pdokdata['results']['bindings'] as $k => $v) {
	$sparql .= "\"" . str_replace("http://bag.basisregistraties.overheid.nl/bag/id/pand/","",$v['pand']['value']) . "\" ";
}

$sparql .="
	}
	?item wdt:P5208 ?bagid .
    OPTIONAL{
      ?item wdt:P359 ?rm .
    }
	OPTIONAL{
      ?item wdt:P18 ?afb .
    }
	OPTIONAL{
		?pandarticle schema:about ?item .
		?pandarticle schema:isPartOf <https://nl.wikipedia.org/> .
	}
	SERVICE wikibase:label { bd:serviceParam wikibase:language \"nl,en\". }
}
limit 1000";

$endpoint = 'https://query.wikidata.org/sparql';

$response = getSparqlResults($endpoint,$sparql);
$wikidata = json_decode($response,true);

$wdpanden = array();
foreach ($wikidata['results']['bindings'] as $k => $v) {
	$wdpanden[$v['bagid']['value']] = array(
		"item" => $v['item']['value'],
		"itemLabel" => $v['itemLabel']['value'],
		"itemDescription" => $v['itemDescription']['value'],
		"rm" => $v['rm']['value'],
		"afb" => $v['afb']['value'],
		"wp" => $v['pandarticle']['value']
	);
}


//echo "wikidata done\n";

// STEP 3: ALL BAGBUILDINGS WITH IMAGES
$sparql = "
PREFIX dct: <http://purl.org/dc/terms/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX edm: <http://www.europeana.eu/schemas/edm/>
PREFIX bag: <http://bag.basisregistraties.overheid.nl/bag/id/pand/>

SELECT DISTINCT ?bagpand (COUNT(?item) AS ?items) WHERE { 
	VALUES ?bagpand { ";

foreach ($pdokdata['results']['bindings'] as $k => $v) {
	$sparql .= "bag:" . str_replace("http://bag.basisregistraties.overheid.nl/bag/id/pand/","",$v['pand']['value']) . " ";
}

$sparql .="
	}
	?item dct:spatial ?bagpand .
  	?item a edm:ProvidedCHO .
} 
GROUP BY ?bagpand";


$endpoint = 'http://blazegraph.pre.csuc.cat/sparql';

$response = getSparqlResults($endpoint,$sparql);
$elodata = json_decode($response,true);


//echo "echoes done\n";


$elopanden = array();
foreach ($elodata['results']['bindings'] as $k => $v) {
	$elopanden[str_replace("http://bag.basisregistraties.overheid.nl/bag/id/pand/","",$v['bagpand']['value'])] = array(
		"count" => $v['items']['value']
	);
}
//print_r($elopanden);die;


$fc = array("type"=>"FeatureCollection", "features"=>array());

$beenthere = array();

foreach ($pdokdata['results']['bindings'] as $k => $v) {

	// we don't want multiple features of one wikidata item, just because it has multiple 'types'
	if(in_array($v['pand']['value'],$beenthere)){
		continue;
	}
	$beenthere[] = $v['pand']['value'];


	$straat = array("type"=>"Feature");
	$bag = str_replace("http://bag.basisregistraties.overheid.nl/bag/id/pand/","",$v['pand']['value']);
	$props = array(
		"pandid" => $bag,
		"label" => $v['nr']['value'],
		"count" => $v['items']['value'],
		"bouwjaar" => $v['bouwjaar']['value']
	);

	if(isset($wdpanden[$bag])){
		$props['wd'] = $wdpanden[$bag];
	}
	if(isset($elopanden[$bag])){
		$props['imgcount'] = $elopanden[$bag]['count'];
	}
	
	
	$straat['geometry'] = wkt2geojson($v['wkt']['value']);
	$straat['properties'] = $props;
	$fc['features'][] = $straat;

}


$gj = json_encode($fc);


echo $gj;
die;


?>