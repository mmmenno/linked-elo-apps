<?php


function getSparqlResults($endpoint,$query){

	// params
	$url = $endpoint . '?query=' . urlencode($query) . "&format=json";
	$urlhash = hash("md5",$url);
	$datafile = __DIR__ . "/data/" . $urlhash . ".json";
	$maxcachetime = 60*60*24*5;

	// get cached data if recent
	if(file_exists($datafile)){
		//echo $datafile . " found";
		$mtime = filemtime($datafile);
		$timediff = time() - $mtime;
		if($timediff < $maxcachetime){
			$json = file_get_contents($datafile);
			return $json;
		}
	}

	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($ch,CURLOPT_USERAGENT,'RotterdamsPubliek');
	$headers = [
	    'Accept: application/sparql-results+json'
	];

	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$response = curl_exec ($ch);
	curl_close ($ch);

	file_put_contents($datafile, $response);
	
	return $response;
}




function wkt2geojson($wkt){
	$coordsstart = strpos($wkt,"(");
	$type = trim(substr($wkt,0,$coordsstart));
	$coordstring = substr($wkt, $coordsstart);

	switch ($type) {
	    case "LINESTRING":
	    	$geom = array("type"=>"LineString","coordinates"=>array());
			$coordstring = str_replace(array("(",")"), "", $coordstring);
	    	$pairs = explode(",", $coordstring);
	    	foreach ($pairs as $k => $v) {
	    		$coords = explode(" ", trim($v));
	    		$geom['coordinates'][] = array((double)$coords[0],(double)$coords[1]);
	    	}
	    	return $geom;
	    	break;
	    case "POLYGON":
	    	$geom = array("type"=>"Polygon","coordinates"=>array());
			preg_match_all("/\([0-9. ,]+\)/",$coordstring,$matches);
	    	//print_r($matches);
	    	foreach ($matches[0] as $linestring) {
	    		$linestring = str_replace(array("(",")"), "", $linestring);
		    	$pairs = explode(",", $linestring);
		    	$line = array();
		    	foreach ($pairs as $k => $v) {
		    		$coords = explode(" ", trim($v));
		    		$line[] = array((double)$coords[0],(double)$coords[1]);
		    	}
		    	$geom['coordinates'][] = $line;
	    	}
	    	return $geom;
	    	break;
	    case "MULTILINESTRING":
	    	$geom = array("type"=>"MultiLineString","coordinates"=>array());
	    	preg_match_all("/\([0-9. ,]+\)/",$coordstring,$matches);
	    	//print_r($matches);
	    	foreach ($matches[0] as $linestring) {
	    		$linestring = str_replace(array("(",")"), "", $linestring);
		    	$pairs = explode(",", $linestring);
		    	$line = array();
		    	foreach ($pairs as $k => $v) {
		    		$coords = explode(" ", trim($v));
		    		$line[] = array((double)$coords[0],(double)$coords[1]);
		    	}
		    	$geom['coordinates'][] = $line;
	    	}
	    	return $geom;
	    	break;
	    case "POINT":
			$coordstring = str_replace(array("(",")"), "", $coordstring);
	    	$coords = explode(" ", $coordstring);
	    	//print_r($coords);
	    	$geom = array("type"=>"Point","coordinates"=>array((double)$coords[0],(double)$coords[1]));
	    	return $geom;
	        break;
	}
}








function durationInfo($start,$end){

	$months = array("nam","januari","februari","maart","april","mei","juni","juli","augustus","september","oktober","november","december");

	// fromto line, in Dutch
	$line = "";
	if($start['datatype']=="http://www.w3.org/2001/XMLSchema#date"){
		$line .= date("j",strtotime($start['value'])) . " ";
		$month = substr($start['value'],5,2);
		$line .= $months[(int)$month] . " ";
		$from = strtotime($start['value']);
		$day = date("d",strtotime($start['value'])) . " ";
	}elseif($start['datatype']=="http://www.w3.org/2001/XMLSchema#gYearMonth"){
		$month = substr($start['value'],5,2);
		$line .= $months[(int)$month] . " ";
		$day = "01";
	}else{
		$month = "01";
	}
	$startyear = substr($start['value'],0,4);
	$startdate = $startyear . "-" . $month . "-" . $day;
	$line .= $startyear . " ";

	$line .= "tot ";

	if($end['datatype']=="http://www.w3.org/2001/XMLSchema#date"){
		$line .= date("j",strtotime($end['value'])) . " ";
		$month = substr($end['value'],5,2);
		$line .= $months[(int)$month] . " ";
		$to = strtotime($end['value']);
		$day = date("d",strtotime($start['value'])) . " ";
	}elseif($end['datatype']=="http://www.w3.org/2001/XMLSchema#gYearMonth"){
		$month = substr($end['value'],5,2);
		$line .= $months[(int)$month] . " ";
		$day = "01";
	}else{
		$day = "31";
		$month = "12";
	}
	$endyear = substr($end['value'],0,4);
	$enddate = $endyear . "-" . $month . "-" . $day;
	$line .= $endyear . " ";


	// nr of days, if start and end are proper dates
	$days = false;
	if(isset($from) && isset($to)){
		$diffsecs = $to - $from;
		$days = $diffsecs / (60*60*24);
	}

	return array(
		"line" => $line,
		"days" => $days,
		"startyear" => $startyear,
		"startdate" => $startdate,
		"enddate" => $enddate
	);

}


















?>