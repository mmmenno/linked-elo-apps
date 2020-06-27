<?php


if(!file_exists(__DIR__ . "/straten.geojson") || isset($_GET['uncache'])){
  include("geojson.php");
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

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.1.0/dist/leaflet.css" integrity="sha512-wcw6ts8Anuw10Mzh9Ytw4pylW8+NAD4ch3lqm9lzAsTxg0GFeJgoAtxuCLREZSC5lUXdVyo/7yfsqFjQ4S+aKw==" crossorigin=""/>
  <script src="https://unpkg.com/leaflet@1.1.0/dist/leaflet.js" integrity="sha512-mNqn2Wg7tSToJhvHcqfzLMU6J4mkOImSPTxVZAdo+lcPlk+GhZmYgACEe0x35K7YzW1zJ7XyJV/TT1MrdXvMcA==" crossorigin=""></script>
  <link rel="stylesheet" href="styles.css" />

  
</head>
<body>

<div id="bigmap"></div>


<div id="legenda">
  <h1>Straten in Leiden, weergegeven naar aantallen afbeeldingen in de beeldbank van Erfgoed Leiden</h1>
  
  <div id="straatlabel"></div>
  <div id="aantal"></div>
  
  <p class="small">(alleen de afbeeldingen met open licenties zijn geteld)</p>
</div>

<script>
  $(document).ready(function() {
    createMap();
    refreshMap();
  });

  function createMap(){
    center = [52.159138, 4.492482];
    zoomlevel = 14;
    
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
          url: 'straten.geojson',
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
                    radius: getSize(feature.properties.count),
                    color: getColor(feature.properties.count),
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

  function getSize(d) {
    return d > 800 ? 16 :
             d > 500 ? 14 :
             d > 300  ? 12 :
             d > 100  ? 10 :
             d > 50 ? 8 :
             d > 20  ? 7 :
             d > 8  ? 6 :
             d > 3  ? 5 : //3
                      4 ; //2
  }

  function getColor(d) {
    return d > 700 ? '#a50026' :
             d > 300 ? '#f46d43' :
             d > 100  ? '#fdae61' :
             d > 50  ? '#fee090' :
             d > 20  ? '#ffffbf' :
             d > 8  ? '#abd9e9' :
             d > 3   ? '#74add1' :
                       '#4575b4';
  }

function whenClicked(){
   $("#intro").hide();

   var props = $(this)[0].feature.properties;
   console.log(props);
   $("#straatlabel").html('<h2><a target="_blank" href="' + props['wdid'] + '">' + props['label'] + '</a></h2>');

   if(props['count'] == 1){
      $("#aantal").html('is afgebeeld op <strong>' + props['count'] + '</strong> afbeelding');
   }else if(props['count'] != null){
      $("#aantal").html('is afgebeeld op <strong>' + props['count'] + '</strong> afbeeldingen');
   }else{
      $("#aantal").html('');
   }

   
    
}

</script>



</body>
</html>
