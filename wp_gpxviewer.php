<?php

/**
 * Plugin Name: Fotorama mit GPXviewer Kombination (17.03.2019)
 * Description: Plugin zur Einbindung von Karten mit Tracks mit GPXviewer. Shortcode: [gpxview imppath=" " gpxfile="..." alttext=""]. Nur einmal pro Seite Verwenden!
 * Version: 0.5.1
 * Author: Martin von Berg
 * Author URI: http://www.mvb1.de/
 * License: GPL2
 */
// Sicherheitsabfrage
defined('ABSPATH') || die('Are you ok?');

add_shortcode('gpxview', 'show_gpxview');

function show_gpxview($attr, $content = null)
{
	// Variablen vordefinieren:
	$string = '';
	$files = [];
	$thumbsdir = 'thumbs';

	// Parameter extrahieren und vordefinieren
	extract(shortcode_atts(array(
		'gpxpath' => 'gpx',
		'gpxfile' => 'test.gpx',
		'mapheight' => '450',
		'chartheight' => '150',
		'imgpath' => 'Bilder',
		'dload' => 'yes',
		'alttext' => 'Fotorama Bildergallerie als Javascript-Slider',
		'scale' => 1.0
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
	$path = $up_dir . '/' . $imgpath; // Pfad zu den Bildern

	$id = 0;
	// Bilddateien auslesen
	foreach (glob($path . "/*.jpg") as $file) {
		// EXIF und IPTC-Daten auslesen und in Array $Exif speichern
		$jpgdatei = basename($file, ".jpg");
		$thumbavail = is_file($path . '/' . $jpgdatei . '_thumb.jpg'); // gibt es zur Bilddatei ein Thumbnail?
		$thumbinsubdir = is_file($path . '/' . $thumbsdir . '/' . $jpgdatei . '_thumb.jpg'); //gibt es zur Bilddatei ein Thumbnail im Sub-Directory?
		$isthumb = stripos($jpgdatei, 'thumb'); // Schleife überspringen, wenn jpg eine thumbnail-Datei ist
		if (!$isthumb) {
			getimagesize($path . "/" . basename($file), $info);
			if (isset($info['APP13'])) {
				$iptc = iptcparse($info['APP13']);
				if (array_key_exists('2#005', $iptc)) {
					$title =  $iptc["2#005"][0];
				} else {
					$title = 'Galleriebild' . strval($id);
				}
			}

			$Exif = exif_read_data($file, 0, true);
			$withgps = array_key_exists('GPS',$Exif);
			if ($withgps === false) {
				//echo"Keine GPS-Daten vorhanden..";
				// Wenn keine GPS-Daten enthalten sind : Datei wird nicht angezeigt
			} else {
				$lon = getGps($Exif["GPS"]["GPSLongitude"], $Exif["GPS"]['GPSLongitudeRef']);
				$lat = getGps($Exif["GPS"]["GPSLatitude"], $Exif["GPS"]['GPSLatitudeRef']);

				if ((is_null($lon)) || (is_null($lat))) {
					// do nothing, GPS-data invalid;
				} else {
					$exptime = $Exif["EXIF"]["ExposureTime"] ?? '--';
					$apperture = strtok(($Exif["EXIF"]["FNumber"] ?? '-'), '/');
					$iso = $Exif["EXIF"]["ISOSpeedRatings"] ?? '--';
					if (array_key_exists('FocalLengthIn35mmFilm', $Exif["EXIF"])) {
						$focal = $Exif["EXIF"]["FocalLengthIn35mmFilm"] . 'mm';
					} else {
						$focal = '--mm';
					}
					// Überprüfung von Make und $camera entsprechend setzen
					$make = $Exif["IFD0"]["Make"] ?? '';
					$make = str_replace(' ', '', $make);
					if (!ctype_alpha($make)) {
						$camera = $Exif["IFD0"]["Model"] . ' + ' . $Exif["IFD0"]["Make"];
					} else {
						$camera = $Exif["IFD0"]["Model"];
					}
					$datetaken = explode(":", $Exif["EXIF"]["DateTimeOriginal"]);
					$datesort = $Exif["EXIF"]["DateTimeOriginal"];
					$tags = $iptc["2#025"] ?? $title;
					if (array_key_exists('ImageDescription', $Exif["IFD0"])) {
						$description = $Exif["IFD0"]["ImageDescription"];
					} elseif ((!empty($tags) && is_array($tags))) {
						$description = implode(", ", $tags);
					} else {
						$description = $tags;
					}
					$datetaken = strtok((string) $datetaken[2], ' ') . '.' . (string) $datetaken[1] . '.' . (string) $datetaken[0];
					$data2[] = array(
						'id' => $id, 'lat' => $lat, 'lon' => $lon, 'title' => $title, 'file' => $jpgdatei, 'exptime' => $exptime,
						'apperture' => $apperture, 'iso' => $iso, 'focal' => $focal, 'camera' => $camera, 'date' => $datetaken, 'tags' => $tags,
						'sort' => $datesort, 'descr' => $description, 'thumbavail' => $thumbavail, 'thumbinsubdir' => $thumbinsubdir
					);
					$id++;
				}
			}
		}
	}
	if ($id > 0) {
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

	// Div für gpxviewer erzeigen, wenn mind. eine GPX-Datei vorhanden ist 
	$string .= '<div id=box1>';
	$imgnr = 1;
	//Fotorama ab hier
	if ($id > 0) {
		$string  .= '<div id="Bilder" style="display : none"><figure><img alt="' . $alttext . '"><figcaption></figcaption></figure></div>';
		$string  .= '<div id="fotorama" class="fotorama" data-auto="false" data-width="100%" data-fit="contain" data-ratio="1.5" data-nav="thumbs" data-allowfullscreen="native" data-keyboard="true" data-hash="true">';
		foreach ($data2 as $data) {
			if ($data['thumbinsubdir']) {
				$string .= '<a href="' . $up_path . '/' . $imgpath . '/' . $data["file"] . '.jpg' . '" data-caption="'.$imgnr.' / '.$id .': ' . $data["title"] . 
				'<br> ' . $data['camera'] . ' <br> ' . $data['focal'] . ' / f/' . $data['apperture'] . ' / ' . $data['exptime'] . 's / ISO' . $data['iso'] . ' / ' . $data['date'] . '">';
				$string .= '<img src="' . $up_path . '/' . $imgpath . '/' . $thumbsdir . '/' . $data["file"] . '_thumb.jpg' . '"></a>';
			} elseif ($data['thumbavail']) {
				$string .= '<a href="' . $up_path . '/' . $imgpath . '/' . $data["file"] . '.jpg' . '" data-caption="'.$imgnr.' / '.$id .': ' . $data["title"] . 
				'<br> ' . $data['camera'] . ' <br> ' . $data['focal'] . ' / f/' . $data['apperture'] . ' / ' . $data['exptime'] . 's / ISO' . $data['iso'] . ' / ' . $data['date'] . '">';
				$string .= '<img src="' . $up_path . '/' . $imgpath . '/' . $data["file"] . '_thumb.jpg' . '"></a>';
			} else {
				$string .= '<img src="' . $up_path . '/' . $imgpath . '/' . $data["file"] . '.jpg' . '" data-caption="'.$imgnr.' / '.$id .': ' . $data["title"] . '<br> ' . $data['camera'] . ' <br> ' . $data['focal'] . ' / f/' . $data['apperture'] . ' / ' . $data['exptime'] . 's / ISO' . $data['iso'] . ' / ' . $data['date'] . '">';
			}
			$imgnr++;
		}
		$string  .= '</div>';
	}

	// Map nur bei gültigen GPX-Dateien
	if (strlen($gpxfile) > 3 && ($i > 0)) {
		$string  .= '<div id=boxmap>';
		$string  .= '<div id=map0 class="map gpxview:' . $gpxfile . ':OPENTOPO" style="width:100%;height:' . $mapheight . 'px"></div>';
		$string  .= '<div id="map0_profiles" style="width:100%;height:' . $chartheight . 'px"><div id="map0_hp" class="map" style="width:100%;height:' . $chartheight . 'px"></div></div>';
		$string  .= '<div id="map0_img">';
	}
	// Bildinfo ausgeben, auch für SEO! 
	foreach ($data2 as $data) {
		if ($data['thumbinsubdir']) {
			$string  .= '<a class="gpxpluga" alt="' . $data['descr'] . '" href="' . $up_path . '/' . $imgpath . '/' . $thumbsdir . '/' . $data["file"] . '_thumb.jpg' . '" data-geo="lat:' . $data["lat"] . ',lon:' . $data["lon"] . '">'
				. $data["title"] . '<br>' . $data["camera"] . '</a>';
		} elseif ($data['thumbavail']) {
			$string  .= '<a class="gpxpluga" alt="' . $data['descr'] . '" href="' . $up_path . '/' . $imgpath . '/' . $data["file"] . '_thumb.jpg' . '" data-geo="lat:' . $data["lat"] . ',lon:' . $data["lon"] . '">'
				. $data["title"] . '<br>' . $data["camera"] . '</a>';
		} else {
			$string  .= '<a class="gpxpluga" alt="' . $data['descr'] . '" href="' . $up_path . '/' . $imgpath . '/' . $data["file"] . '.jpg' . '" data-geo="lat:' . $data["lat"] . ',lon:' . $data["lon"] . '">'
				. $data["title"] . '<br>' . $data["camera"] . '</a>';
		}
	}

	if (strlen($gpxfile) > 3 && ($i > 0)) {
		$string  .= '</div></div>';
	}
	$string  .= '</div>';
	if (($dload == 'yes') && ($i == 1)) {
		$string .= '<p><strong>GPX-Datei: <a download="' . $gpxfile . '" href="' . $gpx_path . $gpxfile . '">Download GPX-Datei</a></strong></p>';
	}
	$string  .= '<script>var g_numb_gpxfiles = "' . $i . '"; var Gpxpfad = "' . $gpx_path . '"; var Fullscreenbutton = false; var Arrowtrack = true; 
	var Doclang="' . $lang . '"; var g_maprescale = '. $scale .'';
	$string  .= '</script>';

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
