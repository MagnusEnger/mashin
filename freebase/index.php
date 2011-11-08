<html>

<head>	
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"></meta>
</head>

<body>

<h1>Data fra Freebase</h1> 

<?php

// if($_POST["freebasewriter"] != null){
	
	#slightly fancier - get the name and id of each of the films in the array
	$simplequery = array('id'=>$_GET['id'], 'starring'=>array(array('*'=>null)), '*'=>array(), 'type'=>'/film/film');
	
	$queryarray = array('q1'=>array('query'=>$simplequery));
	$jsonquerystr = json_encode($queryarray);
	
	#run the query
	$apiendpoint = "http://sandbox.freebase.com/api/service/mqlread?queries";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "$apiendpoint=$jsonquerystr");
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$jsonresultstr = curl_exec($ch);
	curl_close($ch); 
	
	$resultarray = json_decode($jsonresultstr, true);
	
	echo('<table><tr><td valign="top">');
	
	
	echo('<h2>Datastruktur</h2><pre>');
	print_r($resultarray);
	echo('</pre>');
	
	echo('</td><td valign="top"><h2>JSON</h2>');
	
	echo($jsonresultstr);
	
	echo('</td></tr></table>');
	
	/*
	
	$writername = $resultarray["q1"]["result"]["name"]; 
	$filmarray = $resultarray["q1"]["result"]["/film/writer/film"];
	$freebaseserver = "http://sandbox.freebase.com";
	
	print "<h2>Films by: $writername</h2>";
	
	if(count($filmarray > 0)){
	   foreach($filmarray as $film){
	      $filmname = $film['name'];
	      $filmid = $film['id'];
	      print "<a href=$freebaseserver/view/$filmid>$filmname</a><br>";
	   }
	}
	
	*/
// }
?>

<p>
<div style="font-size: x-small">
<img src="http://www.freebase.com/api/trans/raw/freebase/attribution" 
style="float:left; margin-right: 5px" />
<div style="margin-left="30px"> Source: 
<a href="http://www.freebase.com" title="Freebase – The World's Database">Freebase</a> 
– The World&apos;s Database <br/> Freely licensed under 
<a href="http://www.freebase.com/view/common/license/cc_attribution_25">CC-BY</a>. 

</div> </div>

</body>
</html>
