<?php

/* Get the upload URL/path in right way (works with SSL).
    *         @param $param string "basedir" or "baseurl" and @return string */
function gpxview_get_upload_dir($param, $subfolder = '')
{
	
	$upload_dir = wp_get_upload_dir();
	$url = $upload_dir[$param];

	if ($param === 'baseurl' && is_ssl()) {
		$url = str_replace('http://', 'https://', $url);
	}

	return $url . $subfolder;
}

function getGps($exifCoord, $hemi)
{
	if ( ! is_array($exifCoord)) {
		return null;
	}
	
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

function wp_setpostgps($pid, $lat, $lon)
{
	// es wurde vorab schon geprüft, dass die Werte $lat und $lon existieren. Stimmt nur für setzen aus Foto
	// Wenn Struktur GPX-XML abweicht, dann liefert simplexml leere Strings
	$oldlat = get_post_meta($pid,'lat');
	$oldlon = get_post_meta($pid,'lon');
	if ((count($oldlon)==0) && (count($oldlat)==0)) {
		update_post_meta($pid,'lat',$lat,''); 
		update_post_meta($pid,'lon',$lon,'');
		echo ('Update Post-Meta lat und lon');
	} elseif (strlen($oldlon[0]<1) && strlen($oldlat[0]<1)) {
		delete_post_meta($pid,'lat');
		delete_post_meta($pid,'lon');
		update_post_meta($pid,'lat',$lat,''); 
		update_post_meta($pid,'lon',$lon,'');
		//echo ('Update Post-Meta lat und lon');
	}
}

function getLonLat($Exif)
{
	if (array_key_exists('GPS',$Exif)) {
		$lon = getGps($Exif["GPS"]["GPSLongitude"], $Exif["GPS"]['GPSLongitudeRef']);
		$lat = getGps($Exif["GPS"]["GPSLatitude"], $Exif["GPS"]['GPSLatitudeRef']);
	} else {
		// "No GPS-Data available.."
		$lon = null;
		$lat = null;
	}
	
	return array($lon, $lat);
}

function getEXIFData($Exif, $file, $imageNumber)
{
	// get title from IPTC-data
	getimagesize($file, $info);
	if (isset($info['APP13'])) {
		$iptc = iptcparse($info['APP13']);
		if (array_key_exists('2#005', $iptc)) {
			$title =  htmlspecialchars($iptc["2#005"][0]);
		} else {
			$title = 'Galeriebild ' . strval($imageNumber+1);
		}
	}
	
	$exptime = $Exif["EXIF"]["ExposureTime"] ?? '--';
	$apperture = strtok(($Exif["EXIF"]["FNumber"] ?? '-'), '/');
	$iso = $Exif["EXIF"]["ISOSpeedRatings"] ?? '--';
	
	if (array_key_exists('FocalLengthIn35mmFilm', $Exif["EXIF"])) {
		$focal = $Exif["EXIF"]["FocalLengthIn35mmFilm"] . 'mm';
	} else {
		$focal = '--mm';
	}
	// Check setting of exif-field make and set $camera accordingly
	if (array_key_exists('Make', $Exif['IFD0'])) {
		$make = $Exif["IFD0"]["Make"] ?? '';
		$make = preg_replace('/\s+/', ' ', $make);
	} else {
		$make = '';
	}
	
	if (array_key_exists('Model', $Exif['IFD0'])) {
		$model = $Exif["IFD0"]["Model"];
	} else {
		$model = '';
	}
	if (!ctype_alpha($make) && strlen($make)>0) {
		$camera = $model . ' + '. $make;
	} else {
		$camera = $model;
	}
	$datetaken = explode(":", $Exif["EXIF"]["DateTimeOriginal"]);
	$datesort = $Exif["EXIF"]["DateTimeOriginal"];
	$tags = $iptc["2#025"] ?? $title; 
	if (array_key_exists('ImageDescription', $Exif["IFD0"])) {
		$description = $Exif["IFD0"]["ImageDescription"];
	} elseif ((!empty($tags) && is_array($tags))) {
		$description = implode(", ", $tags);
		$description = ""; // Tags kommen doch nicht in den Alt-Tag. das wird zu lang!
	} else {
		$description = $tags;
		$description = ""; // sonst steht der Titel 2-mal im Alt-Tag
	}
	$datetaken = strtok((string) $datetaken[2], ' ') . '.' . (string) $datetaken[1] . '.' . (string) $datetaken[0];
	
	return array($exptime, $apperture, $iso, $focal, $camera, $datetaken, $datesort, $tags, $description, $title);
}


