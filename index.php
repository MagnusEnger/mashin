<?php

/*

TODO

- Cache MARC-record
- Cache whole output? 

- Include this: 

<div class="freebase-attribution">  <img alt="Freebase CC-BY" height="23px" style="float: left; border: 0;" width="61px" src="http://www.freebase.com/policies/freebase-cc-by-61x23.png"/>  <div style="font-size: x-small; margin-left: 70px; height: 30px; ">  Source: <a href="http://www.freebase.com/view/en/blade_runner">Blade Runner</a> on <a href="http://www.freebase.com/">Freebase</a>, licensed under <a href="http://creativecommons.org/licenses/by/2.5/">CC-BY</a><br/>  Other content from <a href="http://en.wikipedia.org/wiki/Blade_Runner">Wikipedia</a>, licensed under the <a href="http://www.gnu.org/copyleft/fdl.html">GFDL</a>  </div>  </div>

*/

if (!empty($_GET['id'])) {
 
	include_once('config.php');
	require_once('File/MARCXML.php');
	require_once('Cache/Lite.php');
	 
	// $_GET['id'] will be on the form koha:biblionumber:123, so chop off "koha:biblionumber:"
	$biblionumber = substr($_GET['id'], 18);
	 
	// Check that $biblionumber can be cast to an integer
	if (!is_int((int) $biblionumber)) { exit; }
	 
	// Get the MARC record from SRU
	$version = '1.2';
	$query = "rec.id=$biblionumber";
	$recordSchema = 'marcxml';
	$startRecord = 1;
	$maximumRecords = 1;
	 
	// Build the SRU url
	$sru_url = $config['sru'];
	$sru_url .= "?operation=searchRetrieve";
	$sru_url .= "&version=$version";
	$sru_url .= "&query=$query";
	$sru_url .= "&recordSchema=$recordSchema";
	$sru_url .= "&startRecord=$startRecord";
	$sru_url .= "&maximumRecords=$maximumRecords";
	 
	// Fetch the SRU data
	$sru_data = file_get_contents($sru_url) or exit("SRU error");
	 
	// Turn the returned XML in to pure MARCXML
	$sru_data = str_replace("<record xmlns=\"http://www.loc.gov/MARC21/slim\">", "<record>", $sru_data);
	preg_match_all('/(<record>.*?<\/record>)/si', $sru_data, $treff);
	$marcxml = implode("\n\n", $treff[0]);
	$marcxml = '<?xml version="1.0" encoding="utf-8"?>' . "\n<collection>\n$marcxml\n</collection>";
	 
	// Parse the XML
	$records = new File_MARCXML($marcxml, File_MARC::SOURCE_STRING);
	// Get the first (and only) record
	$record = $records->next();
	 
	$out = '';
	
	// Decide what to do based on the contents of the MARC record
	// TODO: Handle more that one 024a
	if ($record->getField("024") && $record->getField("024")->getSubfield("a")) {
		$out = get_freebase_data(marctrim($record->getField("024")->getSubfield("a")));
	} else {
		# DEBUG, should probably be silent in production
		$out = "Posten mangler identifikator!";
	}
 
	// Return output
	if ($out) {
		echo('<span class="results_summary">');
		echo($out);
		echo('</span>');
		
	}
	
} else {
 
	echo('<div style="color: red;">Error! biblionumber not found!</div>');
 
}

function get_freebase_data($fid) {
	
	if (substr($fid, 0, 9) != 'freebase:') {
		return false;
	} else {
		$fid = substr($fid, 9);	
	}
	
	// DEBUG
	// $fid = '/en/the_godfather';
	
	// Set a few options for Cache_Lite
	$options = array(
    	'cacheDir' => 'cache/',
    	'lifeTime' => 3600
	);
	$cache_id = 'freebase_' . md5($fid);

	// Create a Cache_Lite object
	$Cache_Lite = new Cache_Lite($options);
	
	$jsonresultstr = '';
	
	if ($jsonresultstr = $Cache_Lite->get($cache_id)) {

	} else {
	
		$simplequery = array('id'=>$fid, 'starring'=>array(array('*'=>null)), '*'=>array(), 'type'=>'/film/film');
		
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
		
		// Cache the data
		$Cache_Lite->save($jsonresultstr);
	
	}
	
	if ($jsonresultstr) {
		$resultarray = json_decode($jsonresultstr, true);
		return format_freebase_result($resultarray); 
	} else {
		return false;
	}
	
}

function format_freebase_result($f) {
	
	# Convert from keys in the array to readable labels
	$tr = array(
		'cinematography' => 'Cinematografi??',
		'costume_design_by' => 'Kostymedesign', 
		'country' => 'Land', 
		'directed_by' => 'Regi', #
		'distributors' => 'Distributører', 
		'dubbing_performances' => 'Dubbet av', 
		'edited_by' => 'Redigert av', 
		'estimated_budget' => 'Anslått budsjett', #
		'executive_produced_by' => 'Executivprodusent', 
		'featured_film_locations' => 'Locations', 
		'featured_song' => 'Sanger', 
		'film_art_direction_by' => 'Art direction', 
		'film_casting_director' => 'Casting', 
		'film_collections' => 'Samlinger', 
		'film_festivals' => 'Festivaler', #
		'film_format' => 'Format', 
		'film_production_design_by' => 'Produksjonsdesign', 
		'film_series' => 'Serier', #
		'film_set_decoration_by' => 'Filmsettdekor', 
		'genre' => 'Sjangre', #
		'gross_revenue' => 'Inntjening', #
		'initial_release_date' => 'Premiere', #
		'language' => 'Språk', 
		'locations' => 'Locations', 
		'music' => 'Musikk', 
		'name' => 'Navn', 
		'other_crew' => 'Andre bidragsytere', #
		'other_film_companies' => 'Andre filmselskaper', 
		'personal_appearances' => 'Personlige opptredener', 
		'prequel' => 'Forløper', #
		'produced_by' => 'Produsent(er)', #
		'production_companies' => 'Produksjonsselskaper', 
		'rating' => 'Rating', #
		'release_date_s' => 'Premieredatoer', #
		'runtime' => 'Spilletid', 
		'search' => 'Søk', 
		'sequel' => 'Oppfølger', #
		'soundtrack' => 'Lydspor', #
		'starring' => 'Medvirkende', #
			'actor' => 'Skuspesiller', 
			'character' => 'Rolle', 
			'character_note' => 'Om rollen', 
			'special_performance_type' => '??', 
		'story_by' => 'Fortelling av', 
		'subjects' => 'Emner', #
		'tagline' => 'Slagord', 
		'trailers' => 'Trailere', 
		'written_by' => 'Skrevet av', #
	);
	
	$h = '<p>Tilleggsdata fra Freebase</p>';
	$h .= '<table>';
	$h .= '<tr>';
	
	$h .= '<td valign="top">';
	$h .= get_freebase_point('directed_by', $f, $tr);
	$h .= get_freebase_point('produced_by', $f, $tr);
	$h .= get_freebase_point('written_by', $f, $tr);
	$h .= '</td>';
	
	$h .= '<td valign="top">';	
	$h .= get_freebase_starring($f, $tr);
	$h .= get_freebase_point('other_crew', $f, $tr);
	$h .= '</td>';
	
	$h .= '<td valign="top">';
	$h .= get_freebase_point('rating', $f, $tr);
	$h .= get_freebase_point('subjects', $f, $tr);
	$h .= get_freebase_point('genre', $f, $tr);
	$h .= get_freebase_point('film_series', $f, $tr);
	$h .= get_freebase_point('prequel', $f, $tr);
	$h .= get_freebase_point('sequel', $f, $tr);
	$h .= get_freebase_point('initial_release_date', $f, $tr);
	$h .= get_freebase_point('release_date_s', $f, $tr);
	$h .= get_freebase_point('soundtrack', $f, $tr);
	$h .= get_freebase_point('film_festivals', $f, $tr);
	$h .= '</td>';
	
	$h .= '<td valign="top">';
	$h .= get_freebase_point('estimated_budget', $f, $tr);
	$h .= get_freebase_point('gross_revenue', $f, $tr);
	$h .= get_freebase_links($f);
	
	$h .= '<div class="freebase-attribution">  <img alt="Freebase CC-BY" height="23px" style="float: left; border: 0;" width="61px" src="http://www.freebase.com/policies/freebase-cc-by-61x23.png"/>  <div style="font-size: x-small; margin-left: 70px; height: 30px; ">  Source: <a href="http://www.freebase.com/view' . $f['q1']['result']['id'] . '">' . $f['q1']['result']['name'][0] . '</a> on <a href="http://www.freebase.com/">Freebase</a>, licensed under <a href="http://creativecommons.org/licenses/by/2.5/">CC-BY</a>  </div>  </div>';
	
	$h .= '</td>';
	
	$h .= '</tr>';
	$h .= '</table>';
	
	return $h;
	
}

function get_freebase_point($item, $f, $tr) {

	if ($f['q1']['result'][$item]) {
		$h = '<p>' . $tr[$item] . '</p>';
		$h .= '<ul>';
		foreach($f['q1']['result'][$item] as $value) {
			$h .= '<li>' . $value . '</li>';
		}
		$h .= '</ul>';
		return $h;		
	} else {
		return;
	}
	
}

function get_freebase_starring($f, $tr) {

	if ($f['q1']['result']['starring']) {
		$h = '<p>' . $tr['starring'] . '</p>';
		$h .= '<ul>';
		foreach($f['q1']['result']['starring'] as $star) {
			$h .= '<li>' . $star['actor'];
			if ($star['character']) {
				$h .= ' (' . $star['character'] . ')';
			}
			$h . '</li>';
		}
		$h .= '</ul>';
		return $h;		
	} else {
		return;
	}
	
}

function get_freebase_links($f) {

	$keys = array(
		# 'apple_movietrailer_id' => '', 
		# 'fandango_id' => '',
		'imdb_id'           => array('name' => 'IMDb',            'url_pre' => 'http://www.imdb.com/title/',      'url_post' => '/'), 
		'metacritic_id'     => array('name' => 'Metacritic',      'url_pre' => 'http://www.metacritic.com/film/titles/', 'url_post' => ''), 
		'netflix_id'        => array('name' => 'Netflix',         'url_pre' => 'http://www.netflix.com/Movie/',    'url_post' => ''), 
		# nytimes_id
		'rottentomatoes_id' => array('name' => 'Rotten tomatoes', 'url_pre' => 'http://www.rottentomatoes.com/m/', 'url_post' => '/'), 
		'traileraddict_id'  => array('name' => 'Trailer Addict',  'url_pre' => 'http://www.traileraddict.com/tags/', 'url_post' => ''), 
	);
	
	$l = '<p>Lenker</p>';
	$l .= '<ul>';
	$l .= '<li><a href="http://www.freebase.com/view' . $f['q1']['result']['id'] . '">Freebase</a></li>';
	foreach ($keys as $key => $title) {
		if ($f['q1']['result'][$key]) {
			foreach ($f['q1']['result'][$key] as $id) {
				$l .= '<li><a href="' . $keys[$key]['url_pre'] . $id . $keys[$key]['url_post'] . '">' . $keys[$key]['name'] . '</a></li>';
			}
		}
	}
	$l .= '</ul>';
	
	return $l;
	
}

/*
For some reason this:
$post->getField("zzz")->getSubfield("a")
always returns this:
[a]: Title...
This function chops off the first 5 characters...
*/
 
function marctrim($s) {
	return substr($s, 5);
}

?>