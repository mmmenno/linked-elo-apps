<?

//$_GET['wdid']

include("../functions.php");

// STEP 1: ALL BUILDINGS IN THIS STREET FROM BAG
$sparql = "
PREFIX dct: <http://purl.org/dc/terms/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX edm: <http://www.europeana.eu/schemas/edm/>
PREFIX bag: <http://bag.basisregistraties.overheid.nl/bag/id/pand/>
PREFIX wd: <http://www.wikidata.org/entity/>
PREFIX sem: <http://semanticweb.cs.vu.nl/2009/11/sem/>

SELECT ?item (SAMPLE(?imgurl) AS ?imgurl) ?shownat ?date WHERE { 
	?item dct:spatial bag:" . $_GET['bagid'] . " .
  	?item a edm:ProvidedCHO .
  	?item sem:hasEarliestBeginTimeStamp ?date .
  	?aggr edm:aggregatedCHO ?item .
  	?aggr edm:isShownBy ?imgurl .
  	?aggr edm:isShownAt ?shownat .
}
GROUP BY ?item ?shownat ?date
ORDER BY DESC(?date)
LIMIT 2000
";

$endpoint = 'http://blazegraph.pre.csuc.cat/sparql';

$response = getSparqlResults($endpoint,$sparql);

$data = json_decode($response,true);

$imgs = $data['results']['bindings'];

$max = count($imgs);
if($max>500){
	$max = 500;
}

for($i=0; $i<$max; $i++) {
	echo '<a href="' . $imgs[$i]['shownat']['value'] . '">';
	echo '<img src="' . str_replace("1000x1000","500x500",$imgs[$i]['imgurl']['value']) . '" title="datum: ' . $imgs[$i]['date']['value'] . '" />';
	echo '</a>';
}

