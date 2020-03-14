<?php

/**
 * Plugin Name: Fotorama + Wordpress Plugin für den GPXviewer von J. Berkemeier
 * Plugin URI: http://www.mvb1.de/info/
 * Description: Plugin zur Einbindung von Karten mit Tracks mit GPXviewer. Shortcode: [gpxview gpxfile="..."]
 * Version: 0.1
 * Author: Martin von Berg
 * Author URI: http://www.mvb1.de/
 * License: GPL2
 */
// Anzeige Trackdaten von Anfang an fehlt und rechts unten

// Sicherheitsabfrage
defined('ABSPATH') || die('Are you ok?');

add_shortcode('gpxview', 'show_gpxview');

function show_gpxview($attr, $content = null)
{
	// Variablen vordefinieren:
	$string = '';
	$files = [];

	// Parameter extrahieren und vordefinieren
	extract(shortcode_atts(array(
		'gpxpath' => 'gpx',
		'gpxfile' => 'test.gpx',
		'mapheight' => '450',
		'chartheight' => '150',
		'imgpath' => 'Bilder'
	), $attr));

	// Spracheinstellungen
	$lang = substr(get_locale(), 0, 2);
	$languages = array("de", "en", "fr", "es");
	if (!in_array($lang, $languages)) {
		$lang = "en";
	}

	// Karten- und Chartvorgaben prüfen und Fehler abfangen
	if (($mapheight < 50) || ($mapheight > 900)) {
		$mapheight = 450;
	}
	if (($chartheight < 50) || ($chartheight > 500)) {
		$chartheight = 150;
	}

	// Pfade und Verzeichnisse definieren
	$up_path = gpxview_get_upload_dir('baseurl');
	$up_dir = wp_get_upload_dir()['basedir'];
	$gpx_local = $up_dir . '/' . $gpxpath . '/';
	$gpx_path = $up_path . '/' . $gpxpath . '/';
	$path = $up_dir . '/' . $imgpath;

	$id = 0;
	// Bilddateien auslesen
	foreach (glob($path . "/*.jpg") as $file) {
		// EXIF und IPTC-Daten auslesen und in Array $Exif speichern
		getimagesize($path . "/" . basename($file), $info);
		if (isset($info['APP13'])) {
			$iptc = iptcparse($info['APP13']);
			if (array_key_exists('2#005', $iptc)) {
				$title =  $iptc["2#005"][0];
			} else {
				$title = 'Galleriebild' . strval($id);
			}
		}
		$jpgdatei = basename($file);
		$Exif = exif_read_data($file, 0, true);
		if ($Exif === false) {
			//echo"Keine Exif-Daten gefunden..";
			// Überprüfung, wenn keine GPS-Daten enthalten sind : Datei wird nicht angezeigt
		} else {
			//$GPSdata = $Exif["GPS"];
			$lon = getGps($Exif["GPS"]["GPSLongitude"], $Exif["GPS"]['GPSLongitudeRef']);
			$lat = getGps($Exif["GPS"]["GPSLatitude"], $Exif["GPS"]['GPSLatitudeRef']);

			if ((is_null($lon)) || (is_null($lat))) {
				// do nothing;
			} else {
				$exptime = $Exif["EXIF"]["ExposureTime"];
				$apperture = strtok($Exif["EXIF"]["FNumber"], '/');
				$iso = $Exif["EXIF"]["ISOSpeedRatings"];
				$focal = $Exif["EXIF"]["FocalLengthIn35mmFilm"] . 'mm';
				// Überprüfung von Make und $camera entsprechend setzen
				$make = $Exif["IFD0"]["Make"];
				$make = str_replace(' ', '', $make);
				if (ctype_alpha($make)) {
					$make = '';
					$camera = $Exif["IFD0"]["Model"];
				} else {
					$camera = $Exif["IFD0"]["Model"] . ' + ' . $Exif["IFD0"]["Make"];
				}
				$datetaken = explode(":", $Exif["EXIF"]["DateTimeOriginal"]); // muss angepasst werden für die Sortierung!
				$datesort = $Exif["EXIF"]["DateTimeOriginal"];
				$tags = $iptc["2#025"];
				$datetaken = strtok((string) $datetaken[2], ' ') . '.' . (string) $datetaken[1] . '.' . (string) $datetaken[0];
				$data2[] = array('id' => $id, 'lat' => $lat, 'lon' => $lon, 'title' => $title, 'file' => $jpgdatei, 'exptime' => $exptime, 'apperture' => $apperture, 'iso' => $iso, 'focal' => $focal, 'camera' => $camera, 'date' => $datetaken, 'tags' => $tags, 'sort' => $datesort);
				$id++;
			}
		}
	}
	if ($id>0) {
	    $csort = array_column($data2, 'sort');
	    array_multisort($csort, SORT_ASC, $data2);
	}

	// GPX-Track-Dateien parsen und prüfen
	$files = explode(",", $gpxfile);
	$i = 0;
	$gpxfile = '';
	foreach ($files as $f) { // Prüfung, ob die Gpx-Dateien vorhanden sind!
		if (is_file($gpx_local . $f)) {
			$files[$i] = $f;
			if ($i == 0) {
				$gpxfile .= $f;
			} else {
				$gpxfile .= ',' . $f;
			}
			$i++;
		}
	}

	// Div für gpxviewer erzeigen, wenn mind. eine GPX-Datei vorhanden ist    18mm/ƒ/8.0/1/400s/ISO 200
	if (strlen($gpxfile) > 3 && ($i > 0)) {
		$string .= '<div id=box1>';
		//Fotorama ab hier
		if ($id>0) {
			$string  .= '<div id="Bilder" style="display : none"><figure><img alt=" "><figcaption></figcaption></figure></div>';
			$string  .= '<div id="fotorama" class="fotorama" data-auto="false" data-width="100%" data-fit="cover" data-ratio="1.5" data-nav="thumbs" data-allowfullscreen="native" data-keyboard="true" data-hash="true">';
			foreach ($data2 as $data) {
				$string .= '<img src="' . $up_path . '/' . $imgpath . '/' . $data["file"] . '" data-caption="'. $data["title"] .'<br> '. $data['camera'].' <br> '.$data['focal'].' / f/'.$data['apperture'].' / '.$data['exptime'].'s / ISO'.$data['iso'].' / '.$data['date'].'">';
			}
			$string  .= '</div>';
	    }
		$string  .= '<div id=boxmap>';
		$string  .= '<div id=map0 class="map gpxview:' . $gpxfile . ':OPENTOPO" style="width:100%;height:' . $mapheight . 'px"></div>';
		$string  .= '<div id="map0_profiles" style="width:100%;height:' . $chartheight . 'px"><div id="map0_hp" class="map" style="width:100%;height:' . $chartheight . 'px"></div></div>';
		$string  .= '<div id="map0_img">';
		if ($id>0) {
		    foreach ($data2 as $data) {
			    $string  .= '<a href="' . $up_path . '/' . $imgpath . '/' . $data["file"] . '" data-geo="lat:' . $data["lat"] . ',lon:' . $data["lon"] . '">' . $data["title"] . '<br>' . $data["camera"] . '</a>';
		    }
	    }
		$string  .= '</div></div></div>';
		$string  .= '<script>var Gpxpfad = "' . $gpx_path . '"; var Fullscreenbutton = false; var Arrowtrack = true; var Doclang="' . $lang . '"; ';
		$string  .= '</script>';
	}
	return $string;
}

require_once __DIR__ . '/wp_gpxviewer_enque.php';

function gpxview_get_upload_dir($param, $subfolder = '')
{
	/* Get the upload URL/path in right way (works with SSL).
    *         @param $param string "basedir" or "baseurl" and @return string */
	$upload_dir = wp_get_upload_dir();
	$url = $upload_dir[$param];

	if ($param === 'baseurl' && is_ssl()) {
		$url = str_replace('http://', 'https://', $url);
	}

	return $url . $subfolder;
}

function getGps($exifCoord, $hemi)
{

	$degrees = count($exifCoord) > 0 ? gps2Num($exifCoord[0]) : 0;
	$minutes = count($exifCoord) > 1 ? gps2Num($exifCoord[1]) : 0;
	$seconds = count($exifCoord) > 2 ? gps2Num($exifCoord[2]) : 0;

	$flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;

	$gpsvalue = $flip * ($degrees + $minutes / 60 + $seconds / 3600);
	if (($gpsvalue <= 180.0) && ($gpsvalue >= -180.0) && is_numeric($gpsvalue)) {
		return $gpsvalue;
	} else {
		return null;
	}
}

function gps2Num($coordPart)
{

	$parts = explode('/', $coordPart);

	if (count($parts) <= 0)
		return 0;

	if (count($parts) == 1)
		return $parts[0];

	return floatval($parts[0]) / floatval($parts[1]);
}
