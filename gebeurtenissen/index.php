<?php

include("functions.php");

function dutchdate($date){

	$maanden = array("","jan","feb","maart","april","mei","juni","juli","aug","sept","okt","nov","dec");
	$dutch = date("j ",strtotime($date)) . $maanden[date("n",strtotime($date))] . date(" Y",strtotime($date));

	return $dutch;
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
SELECT DISTINCT ?item ?label ?place ?placelabel ?eventtype ?typelabel ?begin ?end (COUNT(?cho) AS ?images) WHERE {
?item a sem:Event ;
  sem:eventType ?eventtype ;
  sem:hasPlace ?place ;
  rdfs:label ?label ;
  sem:hasEarliestBeginTimeStamp ?begin;
  sem:hasLatestEndTimeStamp ?end .
?place rdfs:label ?placelabel .
?eventtype rdfs:label ?typelabel .
?cho dc:subject ?item .
?aggr edm:aggregatedCHO ?cho .
?aggr edm:isShownBy ?imgurl .
FILTER (STR(?imgurl) != \"https://images.memorix.nl/lei/thumb/1000x1000/.jpg\")
} 
GROUP BY ?item ?label ?place ?placelabel ?eventtype ?typelabel ?begin ?end
ORDER BY ?begin ?item 
LIMIT 800";


//$endpoint = 'https://api.druid.datalegend.net/datasets/menno/elo/services/elo/sparql';
$endpoint = 'http://blazegraph.pre.csuc.cat/sparql';

$json = getSparqlResults($endpoint,$sparql);
$data = json_decode($json,true);


$events = array();
$eventtypes = array();
$earliest = 2020;
$latest = 0;

foreach ($data['results']['bindings'] as $k => $v) {

	$wwwid = str_replace("https://watwaarwanneer.info/event/", "www-", $v['item']['value']);
	$events[$wwwid] = array(
		"label" => $v['label']['value'],
		"place" => str_replace("http://www.wikidata.org/entity/","",$v['place']['value']),
		"placelabel" => $v['placelabel']['value'],
		"eventtype" => str_replace("http://www.wikidata.org/entity/","",$v['eventtype']['value']),
        "typelabel" => $v['typelabel']['value'],
        "nrimgs" => $v['images']['value'],
		"begin" => dutchdate($v['begin']['value']),
		"end" => dutchdate($v['end']['value']),
        "startyear" => date("Y",strtotime($v['begin']['value'])),
        "endyear" => date("Y",strtotime($v['end']['value'])),
	);

    if($events[$wwwid]['startyear'] < $earliest){
        $earliest = $events[$wwwid]['startyear'];
    }

    if($events[$wwwid]['endyear'] > $latest){
        $latest = $events[$wwwid]['endyear'];
    }

    $eventtypes[str_replace("http://www.wikidata.org/entity/","",$v['eventtype']['value'])] = mb_strtolower($v['typelabel']['value']);


}

asort($eventtypes);

?><!DOCTYPE html>
<html>
<head>
  
<title>Rotterdams Publiek - R'dam. Made it Happen.</title>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  
  <script
  src="https://code.jquery.com/jquery-3.2.1.min.js"
  integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4="
  crossorigin="anonymous"></script>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.1.0/dist/leaflet.css" integrity="sha512-wcw6ts8Anuw10Mzh9Ytw4pylW8+NAD4ch3lqm9lzAsTxg0GFeJgoAtxuCLREZSC5lUXdVyo/7yfsqFjQ4S+aKw==" crossorigin=""/>

  <script src="https://unpkg.com/leaflet@1.1.0/dist/leaflet.js" integrity="sha512-mNqn2Wg7tSToJhvHcqfzLMU6J4mkOImSPTxVZAdo+lcPlk+GhZmYgACEe0x35K7YzW1zJ7XyJV/TT1MrdXvMcA==" crossorigin=""></script>

  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
  
  <link rel="stylesheet" href="assets/styles.css" />

  
</head>
<body>

  

<div class="container-fluid">
    <div class="row">
      <div class="col-md-12">
         <h1>Er gebeurt van alles in Leiden</h1>
      </div> 
    </div>

    <div class="row" style="padding:12px 0;">
        <div class="col-md-7">
            <div id="map"></div>
        </div>
        <div class="col-md-5">

            <input type="hidden" id="placeid" value="all" />
            <p><span id="placelabel"></span> <a style="display: none;" id="showalllink"> [toon alle]</a></p>

            <div class="slider">toon vanaf <span id="earliest"><?= $earliest ?></span></div> <input type="range" min="<?= $earliest ?>" max="<?= $latest ?>" value="<?= $earliest ?>" class="slider" id="start"> <br />

            <div class="slider">toon tot en met <span id="latest"><?= $latest ?></span></div> <input type="range" min="<?= $earliest ?>" max="<?= $latest ?>" value="<?= $latest ?>" class="slider" id="end"> <br />

            <select name="eventtype" class="form-control">
                  <option value="all">toon elk type</option>
                <?php foreach ($eventtypes as $k => $v) { ?>
                  <option value="<?= $k ?>"><?= $v ?></option>
                <?php } ?>
            </select>

        </div>
    </div>

<?php 
	$i = 1;
	foreach ($events as $k => $v) {
		$i++;
		if($v['begin']==$v['end']){
			$datum = $v['begin'];
		}else{
			$datum = $v['begin'] . " - " . $v['end'];
		}
?>
<div class="row">

	<div class="col-md event <?= $v['eventtype'] ?> <?= $v['place'] ?>" id="<?= $k ?>">

        <div class="imgcount">
        <?php for($x=0; $x<$v['nrimgs']; $x++){ ?>
        <div></div>
        <?php } ?>
        </div>

        <h3><a href="" style="font-weight: normal;"><?= $v['label'] ?></a></h3>
        <span class="startyear"><?= $v['startyear'] ?></span> <span class="endyear"><?= $v['endyear'] ?></span>
        <p class="small"><?= $v['typelabel'] ?> | <?= $datum ?> | <?= $v['placelabel'] ?></p>

        <div class="eventcontent"></div>

    </div>

</div>
<?php } ?>
</div>



<div id="eventimgs">
</div>


<script>
	$(document).ready(function() {

		$(".event h3 a").click(function(e){
            e.preventDefault;
            var event = $(this).parent().parent().attr("id");
            var infodiv = $(this).parent().siblings('.eventcontent');
            	
            //console.log(event);
            infodiv.load("event.php?eventid=" + event);
            infodiv.toggle();
        	return false;
        });


        $("#close").click(function(){
        	$('#eventimgs').hide();
        });


        $('select[name="eventtype"]').change(function(){
            refreshList();
            refreshMap();
            showAll();
        });

        $('#start').on('input', function () {
            var earliest = $("#start").val();
            $("#earliest").html(earliest);
        });

        $('#start').change(function(){
            refreshList();
            refreshMap();
            showAll();
        });

        $('#end').on('input', function () {
            var latest = $("#end").val();
            $("#latest").html(latest);
        });

        $('#end').change(function(){
            refreshList();
            refreshMap();
            showAll();
        });

        $('#showalllink').click(function(){
            showAll();
        });
	});

    function showAll(){
        $('#placeid').val('all');
        $('#placelabel').html('');
        $('#showalllink').hide();
        refreshList();
    }


    function refreshList(){

        var eventtype = $('select[name="eventtype"]').val();
        var earliest = $("#start").val();
        var latest = $("#end").val();
        var placeid = $("#placeid").val();

        if(eventtype == "all"){
            $(".event").show();
        }else{
            $(".event").hide();
            $("." + eventtype).show();
        }

        $(".event").each(function(){
            var eventstart = $( this ).children( '.startyear' ).html();
            var eventend = $( this ).children( '.endyear' ).html();

            if(eventend < earliest){
                $(this).hide();
            }
            if(eventstart > latest){
                $(this).hide();
            }

        });

        if(placeid != "all"){
            $(".event").each(function(){
                if($(this).hasClass(placeid) === false){
                    $(this).hide();
                }
            });
        }
    }


</script>



<script>
  $(document).ready(function() {
    createMap();
    refreshMap();
  });

  function createMap(){
    center = [51.916857, 4.476839];
    zoomlevel = 14;
    
    map = L.map('map', {
          center: center,
          zoom: zoomlevel,
          minZoom: 1,
          maxZoom: 19,
          scrollWheelZoom: true,
          zoomControl: false
      });

    L.control.zoom({
        position: 'bottomright'
    }).addTo(map);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19
}).addTo(map);
  }

  function refreshMap(){
    $.ajax({
          type: 'GET',
          url: 'geojson-events.php?startyear=' + $("#start").val() + '&endyear=' + $("#end").val() + '&eventtype=' + $('select[name="eventtype"]').val(),
          dataType: 'json',
          success: function(jsonData) {
            if (typeof gebeurtenissen !== 'undefined') {
              map.removeLayer(gebeurtenissen);
            }

            gebeurtenissen = L.geoJson(null, {
              pointToLayer: function (feature, latlng) {                    
                  return new L.CircleMarker(latlng, {
                      color: "#c62eb7",
                      radius: 8,
                      weight: 2,
                      opacity: 0.8,
                      fillOpacity: 0.5
                  });
              },
              style: function(feature) {
                return {
                    radius: getSize(feature.properties.nr),
                    clickable: true
                };
              },
              onEachFeature: function(feature, layer) {
                layer.on({
                    click: whenClicked
                  });
                }
              }).addTo(map);

              gebeurtenissen.addData(jsonData).bringToFront();
          
              map.fitBounds(gebeurtenissen.getBounds());
          },
          error: function() {
              console.log('Error loading data');
          }
      });
  }

  function getSize(d) {

    return   d > 12 ? 34 :
             d > 9  ? 30 :
             d > 6  ? 26 :
             d > 4 ? 22 :
             d > 3  ? 18 :
             d > 2  ? 14 :
             d > 1  ? 10 : //3
                      6 ; //2
  }

  function whenClicked(){
    var props = $(this)[0].feature.properties;
    //console.log(props);
    $('#placeid').val(props['wdid']);
    $('#placelabel').html(props['label']);
    $('#showalllink').show();
    refreshList();
  }

</script>

</body>
</html>
