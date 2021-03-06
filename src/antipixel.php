<?php
/**
 * Data collecting script for eStats
 * @author Emdek <http://emdek.pl>
 * @version 4.9.50
 */

error_reporting(E_ALL);

define('ESTATS_ANTIPIXEL', TRUE);

if (isset($_GET['count']))
{
	if ($_GET['count'])
	{
		define('ESTATS_COUNT', TRUE);

		if (!empty($_GET['address']))
		{
			$address = parse_url($_GET['address']);

			define('ESTATS_ADDRESS', $address['path'].(isset($address['query'])?'?'.$address['query']:''));
		}

		if (!empty($_GET['title']))
		{
			define('ESTATS_TITLE', $_GET['title']);
		}

		if (!empty($_GET['referrer']))
		{
			$_SERVER['HTTP_REFERER'] = $_GET['referrer'];
		}
	}

	define('ESTATS_JSINFORMATION', TRUE);

	$jSInformation = array
	(
	'info' => 1,
	'javascript' => (isset($_GET['javascript']) && $_GET['javascript']),
	'java' => (isset($_GET['java']) && $_GET['java']),
	'cookies' => (isset($_GET['cookies']) && $_GET['cookies']),
	'flash' => ((isset($_GET['flash']) && is_numeric($_GET['flash']))?(float) $_GET['flash']:'?'),
	'screen' => ((isset($_GET['width']) && (int) $_GET['width'] && isset ($_GET['height']) && (int) $_GET['height'])?(int) $_GET['width'].' x '.(int) $_GET['height']:'?')
	);
}

require ('./stats.php');

$fileName = 'share/antipixels/'.((isset($_GET['antipixel']) && $_GET['antipixel'] && is_file('./share/antipixels/'.urldecode($_GET['antipixel'])))?urldecode($_GET['antipixel']):((defined('ESTATS_CRITICAL') || !class_exists('EstatsCore'))?'default/fresh.png':EstatsCore::option('Antipixel')));
$tmpArray = explode('.', basename($fileName));

header('Content-type: image/'.end($tmpArray));
die(file_get_contents($fileName));
?>