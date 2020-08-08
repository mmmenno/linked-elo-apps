<?php

include("functions.php");


if(!isset($_GET['eventid'])){
	$eventnr = "3593";
}else{
	$eventuri = $_GET['eventid'];
	$eventnr = str_replace("-","",str_replace("https://id.erfgoedleiden.nl/event/", "", $_GET['eventid']));
}


$sparql = "
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
SELECT DISTINCT ?item ?label ?place ?placelabel ?typelabel ?begin ?end ?cho ?actor ?actorlabel ?actorwiki ?actordescription WHERE {
VALUES ?item {<" . $eventuri . ">}
?item a sem:Event ;
	sem:eventType ?eventtype ;
	sem:hasPlace ?place ;
	rdfs:label ?label ;
	sem:hasEarliestBeginTimeStamp ?begin;
	sem:hasLatestEndTimeStamp ?end .
OPTIONAL{
	?item sem:hasActor ?actor .
    ?actor rdfs:label ?actorlabel .
    OPTIONAL{
     	?actor dc:description ?actordescription .
    }
    OPTIONAL{
     	?actor foaf:isPrimaryTopicOf ?actorwiki .
    }
}
?cho dc:subject ?item .
}
ORDER BY ?begin 
LIMIT 100
";

//echo $sparql;

$endpoint = 'https://api.druid.datalegend.net/datasets/menno/elo/services/elo/sparql';
$json = getSparqlResults($endpoint,$sparql);
$data = json_decode($json,true);

// for now get actors and list of images from druid endpoint
$actors = array();
$beenthereactors = array();
$imgs = array();

foreach ($data['results']['bindings'] as $k => $v) {

	if(!in_array($v['cho']['value'], $imgs)){
		$imgs[] = $v['cho']['value'];
	}

	if(!in_array($v['actor']['value'], $beenthereactors) && strlen($v['actor']['value'])){
		$actors[] = array(
			"actorwdid" => $v['actor']['value'],
			"actorlabel" => str_replace(array("\"","'"),"`",$v['actorlabel']['value']),
			"actordescription" => $v['actordescription']['value'],
			"actorwiki" => $v['actorwiki']['value']
		);
		$beenthereactors[] = $v['actor']['value'];
	}
}

// then get images from echoes endpoint
$valuesstring = "";
foreach ($imgs as $key => $value) {
	$valuesstring .= "<" . $value . "> ";
}

$sparql = "
PREFIX dct: <http://purl.org/dc/terms/>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX dc: <http://purl.org/dc/elements/1.1/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX edm: <http://www.europeana.eu/schemas/edm/>

select distinct ?cho ?imglink ?imgurl ?title where { 
    VALUES ?cho  { " . $valuesstring . " }
    ?cho dc:title ?title .
    ?aggr edm:aggregatedCHO ?cho .
    ?aggr edm:isShownBy ?imgurl .
    ?aggr edm:isShownAt ?imglink .
} 
";


$endpoint = 'http://blazegraph.pre.csuc.cat/sparql';
$response = getSparqlResults($endpoint,$sparql);

$data = json_decode($response,true);






//print_r($data);
$imgs = array();
$beenthereimgs = array();

foreach ($data['results']['bindings'] as $k => $v) {

	if(!in_array($v['cho']['value'], $beenthereimgs)){
		$imgs[] = array(
			"imgurl" => $v['imgurl']['value'],
			"imgtitle" => str_replace(array("\"","'"),"`",$v['title']['value']),
			"imgcreator" => $v['creator']['value'],
			"imglink" => $v['imglink']['value']
		);
		$beenthereimgs[] = $v['cho']['value'];
	}

}

//print_r($imgs);

$imgsjson = json_encode($imgs);

if($imgs[0]['eventbegin'] == $imgs[0]['eventbegin']){
	$eventdatum = $imgs[0]['eventbegin'];
}else{
	$eventdatum = $imgs[0]['eventbegin'] . " - " . $eventdatum = $imgs[0]['eventend'];;
}


?>



	
<div class="row" id="e<?= $eventnr ?>">

	<div class="col-md-6">
		
		<a id="e<?= $eventnr ?>-imglink" href="<?= $imgs[0]['imglink'] ?>"><img id="e<?= $eventnr ?>-img" style="width: 100%; margin-bottom: 15px;" src="<?= $imgs[0]['imgurl'] ?>" /></a>


	</div>

	<div class="col-md-6 thumbs">
		

		
		<?php 
		if(count($imgs)>1){
			foreach ($imgs as $key => $img) { 

				echo '<img id="e' . $eventnr . '-' . $key . '" style="height:100px; margin-right:15px; margin-bottom:15px;" src="' . $img['imgurl'] . '" />';
			}
		}
		?>

		<?php if(count($actors)>0){ ?>

			<h3>betrokken:</h3>
			<?php 
			foreach ($actors as $key => $actor) { 

				echo '<strong>' . $actor['actorlabel'] . '</strong> | ';
				echo $actor['actordescription'];
				if(strlen($actor['actorwiki'])){
					echo ' ... <a target="_blank" href="' . $actor['actorwiki'] . '">meer op wikipedia</a>';
				}
				echo '<br />';

			}
			?>
		<?php } ?>

		<h3>beschrijving foto</h3>
		<div id="e<?= $eventnr ?>-imgtitle"><?= $imgs[0]['imgtitle'] ?></div>
		<div id="e<?= $eventnr ?>-imgcreator"><?= $imgs[0]['imgcreator'] ?></div>
		<div id="e<?= $eventnr ?>-imgdate"><?= $imgs[0]['imgdate'] ?></div>

		

		<br /><br />
		
	</div>
</div>



<script>
	$(document).ready(function() {


		$('#e<?= $eventnr ?> .thumbs img').click(function(){

			var allimgs = JSON.parse('<?= $imgsjson ?>');

			var splitted = $(this).attr('id').split('-');
			//var eventid = splitted[0];
			var key = splitted[1];
			console.log(key);

			$('#e<?= $eventnr ?>-img').attr('src',allimgs[key]['imgurl']);
			$('#e<?= $eventnr ?>-imglink').attr('href',allimgs[key]['imglink']);
			$('#e<?= $eventnr ?>-imgtitle').html(allimgs[key]['imgtitle']);
			$('#e<?= $eventnr ?>-imgcreator').html(allimgs[key]['imgcreator']);

			console.log('<?= $eventnr ?>-img');

		});


	});
</script>

