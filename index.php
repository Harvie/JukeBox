<?php require_once('./http_auth.php'); /*Delete this line to disable password protection*/ ?>
<?php $exec_time = round(microtime(true), 3); /*
Harvie's JuKe!Box
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
Version info:
 * 0.3.5 - Fixed security bug - directory traversal in filelisting (upgrade recommended)
 * 0.3.4 - Generating playlist for flashplayer, searching for bugs, cleaning code and preparing for new version release
 * 0.3.3 - Shorter URLs for flashplayer (due to discussion at #skola ;o), nicer national characters handling
 * 0.3.2 - Better support for national charsets, few small bugfixes, css improvements, modular search engines
 * 0.3.1 - Buckfickses in m3u generation, better navigation, magic_quotes_gpc handled, css improvements
 * 0.3   - Migrated to standalone WPAudioPlayer (better, nicer, with more functions)
 * 0.2   - Few new functions (search playlist, random,...)
 * 0.1.1 - Few little fixups, written help.html in Czech language ;o)
 * 0.1   - All functions works - TODO: bugfix & replace ugly code
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
*/

//Config-basic
$title = 		'Harvie\'s&nbsp;JuKe!Box'; //Title of jukebox
$music_dir = 		'./music'; //Local path to directory with music
$music_dir_url = 	'http://your-server.net/jukebox/music'; //URL path to the same directory
$cache_passwd = 	'renew123'; //You need this passwd to refresh search cache
$sort =			3; //Sort? 0 = none, 1 = playlists, 2 = 1+listings; 3 = 2+search-EXPERIMENTAL! (sorting could eat lot of memory)
$access_limit =		40; //How many files could be accessed without using cache (while searching)

//Encoding settins
$charset =		'UTF-8'; //Charset for page
$national_characters =	1; //Support searching in filenames with national characters? 0 = no; 1 = yes; (may slowdown search a little)

//Playlist settings
$playlist_name = 	'playlist.m3u'; //Name of downloaded pl
$m3u_exts = 		'ogg|mp[0-9]|wma|wmv|wav'; //Allow only these files
$default_random_count =	30; //How many random songs by defaul?

//External files
$indexlist = 		array('index.html', 'index.txt'); //Search for this file in each directory
$bonus_dir =		'./jbx'; //Misc. files directory
////
$search_cache = 	$bonus_dir.'/cache.db'; //Database for searching music (php +rw) - .htaccess: Deny from all!!!
$css_file =		$bonus_dir.'/themes/default/jukebox.css'; //CSS (Design)
$favicon_file =		'./favicon.png'; //favicon
$header_file =		$bonus_dir.'/header.html'; //header file
$footer_file =		$bonus_dir.'/footer.html'; //footer file

//Search engines extend search experience
$search_engines = array(
	'Google.com' 			=> 'http://google.com/search?q=',
	'Images' 			=> 'http://google.com/images?q=',
	'Karaoke-Lyrics.net' 		=> 'http://www.karaoke-lyrics.net/index.php?page=find&q=',
	'Jyxo.cz multimedia' 		=> 'http://jyxo.cz/s?d=mm&q=',
	'Centrum.cz mp3' 		=> 'http://search.centrum.cz/index.php?sec=mp3&q=',
	'YOUTube.com' 			=> 'http://youtube.com/results?search_query='
);

//Flash MusicPlayer (info about settings: http://wpaudioplayer.com/standalone)
$flash_player_swf =	$bonus_dir.'/player.swf'; //path to musicplayer
$flash_player_frame =	'playframe-show'; //FlashPlayer Target (playframe-show|playframe-hide) - usefull for compatibility with old music player
$flash_player_options = '?bg=000099&loader=000000&tracker=AAAAFF&skip=FFFFFF' //.'&leftbg=000077&rightbg=000077&righticon=999999'
                        .'&autostart=yes&initialvolume=100&soundFile='; //& arguments (urlencoded song url will be added)

//Security
error_reporting(0); //This will disable error reporting, wich can pass sensitive data to users

//External configuration file (overrides index.php configuration)
@include('./_config.php');

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

//Init
srand(time());
@ini_set('magic_quotes_gpc' , 'off');
if(get_magic_quotes_gpc()) die("Error: magic_quotes_gpc needs to be disabled!\n");

//Enable flash?
$useflash = is_file($flash_player_swf);

//Little magic with directories ;o)
$current_dir = ereg_replace('/+', '/', '/'.$_GET['dir'].'/');
if(eregi('(/|\\\\)\\.\\.(/|\\\\)', $current_dir)) { //check for directory traversal ;)
	header('Location: ?');
	die('Error - directory not found!');
}
$dir = $music_dir.$current_dir;
$url = $music_dir_url.$current_dir;
$parent_dir = dirname($current_dir);

//FCs
function serve_download($filename) {
	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
	header('Pragma: no-cache');

	//header('Content-Type: application/force-download');
	header('Content-Type: audio/x-mpegurl');
	header("Content-Disposition: attachment; filename={$filename}");
	header('Content-Transfer-Encoding: binary');

	header('X-PHP-Application: Harvie\'s JuKe!Box');
}

$nchars_f = array('Á','Ä','Č','Ç','Ď','É','Ě','Ë','Í','Ň','Ó','Ö','Ř','Š','Ť','Ú','Ů','Ü','Ý','Ž','á','ä','č','ç','ď','é','ě','ë','í','ň','ó','ö','ř','š','ť','ú','ů','ü','ý','ž');
$nchars_t = array('A','A','C','C','D','E','E','E','I','N','O','O','R','S','T','U','U','U','Y','Z','a','a','c','c','d','e','e','e','i','n','o','o','r','s','t','u','u','u','y','z');

function unational($text) {
	if(!$GLOBALS['national_characters']) return $text;
	return(str_replace($GLOBALS['nchars_f'], $GLOBALS['nchars_t'], $text));
}

function generate_m3u($dir, $prefix='', $recursive=0, $nl="\r\n", $doubleenc=0) {
	$dir = $dir . '/';
	if(isset($_GET['newline'])) $nl = $_GET['newline'];
	if(!isset($_GET['search'])) {
		$dd = opendir($dir);
		while(($item = readdir($dd)) != false) {
        		if($item == '.' || $item == '..') continue;
	                if( is_file($dir.$item) && eregi(('\.('.$GLOBALS['m3u_exts'].')$'), $item) ) {
				if($GLOBALS['sort'] > 0) {
					$temp[] = $item;
				} else {
					$item=($prefix.'/'.str_replace('%2F', '/', (rawurlencode($dir.$item))).$nl);
					if($doubleenc) $item = rawurlencode($item);
					echo($item);
				}
			}
	                if($recursive && is_dir($dir.$item)) {
				generate_m3u($dir.$item, $prefix, $recursive, $nl, $doubleenc);
	                }
		}
	} else {
		if(!($searchfp = fopen($GLOBALS['search_cache'], 'r')))
			die("Cannot read cache from $outfile<br />Refresh cache or set permissions properly!<br />\n");
		while(!feof($searchfp)) {
			$line = trim(fgets($searchfp));
			if(@eregi(str_replace(' ', '(.*)', unational($_GET['search'])), unational($line))) {
				$line=(dirname($GLOBALS['music_dir_url']).'/'.str_replace('%2F', '/', (rawurlencode($line))).$nl);
				if($doubleenc) $line = rawurlencode($line);
				echo($line);
			}
		}
	}

	if($GLOBALS['sort'] > 0) {
		@sort($temp);
		foreach($temp as $item) {
			$temp=($prefix.'/'.str_replace('%2F', '/', (rawurlencode($dir.$item))).$nl);
			if($doubleenc) $temp = rawurlencode($temp);
			echo($temp);
		}
	}
}

function write_search_cache($dir, $outfp) {
        $dir = $dir . '/';
        $dd = opendir($dir);
        while($item = readdir($dd)) {
                if($item == '.' || $item == '..') continue;
                if( is_file($dir.$item) && eregi(('\.('.$GLOBALS['m3u_exts'].')$'), $item) ) {
                        fwrite($outfp, $dir.$item."\n");
                }
                if(is_dir($dir.$item)) {
                        write_search_cache($dir.$item, $outfp);
                }
        }
}

function generate_search_cache($dir, $outfile) {
	echo("Generating search cache. Please wait...<br />\n"); flush();
	@chmod($outfile, 0755); //At least i tryed ;D
	if(!($outfp = fopen($outfile, 'w')))
		die("Cannot write cache to $outfile<br />You probably haven't set the permissions properly!<br />\n");
	write_search_cache($dir, $outfp);
	fclose($outfp);
	$osize = filesize($outfile); clearstatcache();
	if($GLOBALS['sort'] > 2) {
		echo("Sorting search cache. Please wait...<br />\n"); flush();

		$items = file($outfile); @sort($items);
		$total = ' ('.sizeof($items).' files)';
		file_put_contents($outfile, @implode('', $items));
		unset($items);
		if(abs(filesize($outfile)-$osize) > 2)
			die('ERROR! Please disable sorting of search cache ($sort < 3)<br />'."\nSorted only ".
			filesize($outfile).' of '.$osize.' bytes!!!\n');
	}
	echo('Total: '.filesize($outfile).' of '.$osize.' bytes'.$total.' <a href="?">DONE!</a>'.'<br /><META http-equiv="refresh" content="2;URL=?">'."\n");
}

function render_file_line($dir, $item, $dir_url, $index, $filesize, $parent = false) {
	$parclass=($index%2?'even':'odd'); $parcolor=($index%2?'lightblue':'white');
	$temp=str_replace('&', '%26', dirname($dir_url)).'/'.str_replace('%2F', '/', (rawurlencode($dir.$item)));
	if(is_numeric($filesize)) $filesize = round($filesize/(1024*1024), 2);
	echo("<tr class=\"$parclass\" bgcolor=\"$parcolor\">".'<td><a href="#up">'.$index.'</a></td><td class="btntd">');
	echo('<a href="?download&song='.rawurlencode($temp).'" class="icon iplay">P</a>');
	if($parent) {
		echo('/<a href="?dir='.
			substr(str_replace(array('&','%2F'), array('%26','/'), (rawurlencode(dirname($dir.$item)))), strlen($GLOBALS['music_dir'])).
			'" class="icon ifolder">D</a>');
	}
	if($GLOBALS['useflash'] && eregi(('\.('.$GLOBALS['m3u_exts'].')$'), $item)) {
		echo('/<a href="?f&song='.rawurlencode($temp).
			'" target="'.$GLOBALS['flash_player_frame'].'" class="icon ifplay">F</a>/'.
			'<a href="?blank" target="'.$GLOBALS['flash_player_frame'].'" class="icon ifstop">S</a>');
	}
	echo('&nbsp;</td><td><a href="'.$temp.'">'.unxss(str_replace('_', ' ', $item)).'</a></td><td>'.$filesize."&nbsp;MiB&nbsp;</td></tr>\n");			
}

function render_tr_playframe_show() {
	if($GLOBALS['flash_player_frame'] == 'playframe-show' && $GLOBALS['useflash']) { ?>
<tr id="playframe-tr">
<td><a href="?blank" target="playframe-show" title="Stop playback">S</a></td>
<td colspan="100%">
<iframe
src="?blank"
name="playframe-show"
width="100%"
height="24"
style="border: none;"
transparentpagebg="yes"
></iframe></td></tr>
	<?php }
}

function render_footer() {
	$quotes = array(
		'This is NOT advertisments. This is just good text to think about... Remove it if you want!',
		'Downloading without sharing and other forms of leeching equals STEALING! ;P',
		'Do NOT support Microsoft!!! Use Linux! ;D',
		'Don\'t steal! Steal and share!!! ;P',
		'Linux is not matter of price, it\'s matter of freedom!',
		'This software brought to you by <a href="http://blog.Harvie.cz">Harvie</a> free of charge! Of course...',
		'Don\'t be looser, use GNU/Linux! ;P',
		'Make love and not war!',
		'Take your chance! Prove yourself!',
		'This software is free of charge. If you wan\'t to donate, please send some money to children in Africa/etc...'
	);

	echo('<span id="quote" style="float: left;"><i><small>'.$quotes[rand(0,sizeof($quotes)-1)]."</small></i></span>\n");
	echo('<span id="exectime" style="float: right;"><small>Page was generated in '.(round(microtime(true), 3) - $GLOBALS['exec_time']).' 
seconds</small></span>');
	@readfile($GLOBALS['footer_file']);
	echo('</body></html>');
}

function unxss($string) {
	return htmlspecialchars($string);
}

function explode_path($dir) {
	$dir = substr($dir, strlen($GLOBALS['music_dir'])+1);
	$temp = split('/', ereg_replace('/+', '/', $dir));
	$out = '';
	for($j=sizeof($temp)-1;$j>0;$j--) {
		$dir = '';
		for($i=0;$i<(sizeof($temp)-$j);$i++) {
			$dir.=$temp[$i].'/';
		}
		$out.='<a href="?dir='.rawurlencode($dir).'">'.unxss($temp[$i-1]).'</a>/';
	}
	return('<a href="?">.</a>/'.$out);
}

function flash_mp3_player() {
	?>
<html><head><title><?=$GLOBALS['title']?>: Flash Music Player Plugin</title>
<style> * { margin: 0; padding: 0; border: 0; } </style></head><body>
<object width="100%" height="344">
        <embed src="<?php
		echo($GLOBALS['flash_player_swf'].$GLOBALS['flash_player_options']);
		if(isset($_GET['song'])) echo(rawurlencode($_GET['song']));
		if(isset($_GET['playlist'])) generate_m3u($GLOBALS['dir'], dirname($GLOBALS['music_dir_url']), isset($_GET['recursive']), ',', true);
	?>"
                type="application/x-shockwave-flash"
                allowscriptaccess="never"
                allowfullscreen="true"
                transparentpagebg="yes" 
                quality="low"
                width="100%" height="24px"                
        >You need Adobe Flash enabled browser to play records directly in website.</embed>
</object></body></html>
<?php die();
}

//GET
if(isset($_GET['download'])) serve_download($playlist_name);
if(isset($_GET['f'])) flash_mp3_player();
if(isset($_GET['song'])) {
	die($_GET['song']."\r\n");
}



if($_POST['cache-refresh'] == $cache_passwd) {
	generate_search_cache($music_dir, $search_cache);
	die("\n");
}

if(isset($_GET['playlist'])) {
	generate_m3u($dir, dirname($music_dir_url), isset($_GET['recursive']));
	die();
}

if(isset($_GET['random'])) {
	$flen = 0;
	if(!($searchfp = fopen($search_cache, 'r')))
		die("Cannot read cache from $outfile<br />Refresh cache or set permissions properly!<br />\n");
	while(!feof($searchfp)) { fgets($searchfp); $flen++; }
	for($i=0; $i<$_GET['random']; $i++) {
		rewind($searchfp);
		for($j=0; $j<rand(0, $flen-1); $j++) fgets($searchfp);
		echo(dirname($music_dir_url).'/'.str_replace('%2F', '/', (rawurlencode(trim(fgets($searchfp)))))."\r\n");
	}
	die();
}

if(isset($_GET['blank'])) {
	?>
	<link rel="stylesheet" type="text/css" href="<?=$css_file?>" />
	<body class="blank"><div class="blank"><b>Music player</b> <small><i>(click 'F' link next to the song name to start, 'S' to stop...)</i></small></div></body>
	<?php die();
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<meta http-equiv="Content-Type" content="text/html; charset=<?=$charset?>" />
<html>
	<head>
		<link rel="stylesheet" type="text/css" href="<?=$css_file?>" />
		<link rel="shortcut icon" href="<?=$favicon_file?>" />
		<link href="<?=$favicon_file?>" rel="icon" type="image/gif" />
	</head>
	<body>

<div align="right" style="position: absolute; top: 5px; right: 5px;">
	<a name="up"></a>
	<iframe src="about:blank" name="playframe-hide" width="0" height="0" style="border: none;" class="hide"></iframe><!-- -----------???--------------- -->
	<span class="icon">&lt;</span> <a href="javascript: history.go(-1)" class="icon iback">BACK</a>
	| <a href="?"><span class="icon ihome">HOME</span> (<?=$music_dir?>)</a>
	| <a href="?help" class="icon ihelp">ABOUT/HELP</a>
	| <a href="?logout" class="icon ilogout">LOGOUT</a>
</div>

<?php
if(isset($_GET['help'])) {
        ?><h1>About/Help</h1><?php
        readfile($bonus_dir.'/help.html');
	die();
}


if(!isset($_GET['search'])) {
	echo('<title>'.$title.': '.unxss($dir).'</title>');
	echo('<a href="?" style="color: black;"><h1 style="float: left;">'.$title.'</h1></a><h2 style="clear: left; display: inline; float: left;">Index of: '.explode_path($dir).'</h2>');
} else {
	echo('<title>'.$title.': '.unxss($_GET['search']).'</title>');
	echo('<a href="?" style="color: black;"><h1 style="float: left;">'.$title.'</h1></a><h2 style="clear: left; display: inline; float: left;">Searching for: '.unxss($_GET['search']).'</h2>');

?>

<?php
}

?>
<span style="float: right;">
	<form action="?" method="GET" align="right" style="display: inline;">
		<input type="hidden" name="download" value="" />
		<input type="number" name="random" value="<?=$default_random_count?>" />
		<input type="submit" value="random" title="Generate random music playlist..." />
	</form>
	<form action="?" method="GET" align="right" style="display: inline;">
		<input type="search" name="search" autofocus placeholder="search regexp..."
			title="Search in music/google/lyrics/mp3/youtube; Hint: You can use regular expressions in search query..."
			value="<?=unxss($_GET['search'])?>"
		/>
		<input type="submit" value="search" title="Search in this JuKe!Box..." />
	</form>
</span><?php

if(!isset($_GET['search'])) {
	echo('<br style="clear: both;" />');
} else {

?>
<span style="float: right;">
	<form action="?" method="POST" align="right">
		<input type="password" name="cache-refresh" value="" title="Password for refreshing - good for avoiding DoS Attacks!!!" />
		<input type="submit" value="refresh cache" title="You should refresh cache each time when you add new music or upgrade to newer version of JuKe!Box !!!" />
	&nbsp;
	</form>
</span>
<div align="right" style="clear: right;" title="Aditional search engines...">
<br />
<?php
	$search_prefix = 0;
	foreach($search_engines as $search_desc => $search_link) {
		if(!$search_prefix) {
			echo(unxss($_GET['search'])." @\n");
			$search_prefix = 1;
		}
		echo('<a href="'.$search_link.rawurlencode($_GET['search']).'">'.$search_desc."</a>;\n");
	}
?>
</div><br style="clear: both;" />
<?php
echo('<small>Search DB size: '.(filesize($search_cache)/1024)." kB<br /></small>\n");

if(!($searchfp = fopen($search_cache, 'r')))
	die("Cannot read cache from $outfile<br />Refresh cache or set permissions properly!<br />\n");

$i = 0;
echo('<table border="1" width="100%">');
render_tr_playframe_show();
echo('<tr><td>S</td><td><a href="?download&playlist&search='.unxss($_GET['search']).'">P</a>');
if($GLOBALS['useflash']) echo('/<a href="?f&playlist&search='.unxss($_GET['search']).'" target="'.$GLOBALS['flash_player_frame'].'">F</a>');
echo('</td><td colspan="100%">Search: '.unxss($_GET['search']).'</td></tr>');

while(!feof($searchfp)) {
	$line = trim(fgets($searchfp));
	$parclass=($i%2?'even':'odd'); $parcolor=($i%2?'lightblue':'white');
	if(@eregi(str_replace(' ', '(.*)', unational($_GET['search'])), unational($line))) {
		$i++;
		$filesize = 0; if($i <= $access_limit) $filesize = filesize($line); else $filesize = 'n/a';
		render_file_line('', $line, $music_dir_url, $i, $filesize, true);
	}
}
echo('<tr><td colspan="100%">Total: '.$i.' results...</td></tr></table>');
render_footer(); die();

}
@readfile($header_file);
foreach($indexlist as $index) @readfile($dir.$index); 
?>
<br />
<table border="1" width="100%">
<?php render_tr_playframe_show(); ?>

<tr class="directory"><td>&gt;</td>
<td><a href="?download&playlist&dir=<?=str_replace('%2F', '/', rawurlencode($current_dir))?>" class="icon iplay">P</a>/<a
href="?download&recursive&playlist&dir=<?=str_replace('%2F', '/', rawurlencode($current_dir))?>" class="icon irplay">R</a><?php
if($GLOBALS['useflash']) echo('/<a href="?f&playlist&dir='.str_replace('%2F', '/', rawurlencode($current_dir)).'"  target="'.$GLOBALS['flash_player_frame'].'" 
class="icon ifplay">F</a>'); ?>
</td>
<td colspan="100%"><?=unxss($dir)?></td></tr>
<tr><td>^</td><td>&nbsp;</td><td colspan="100%" class="directory"><span class="icon ifolder">[DIR] </span><a href="?dir=<?=rawurlencode($parent_dir)?>">.. 
(<?=$parent_dir?>)</a></td></tr>
<?php

$i = 0;
$dd = opendir($dir);
for($s=2;$s;$s--) { while(($item = readdir($dd)) != false) {
	if($item == '.' || $item == '..') continue;
	if(($s==2 && is_file($dir.$item)) || ($s!=2 && is_dir($dir.$item))) continue;
	$i++;
	$parclass=($i%2?'even':'odd'); $parcolor=($i%2?'lightblue':'white');
		if(is_file($dir.$item)) {
			if($sort > 1) {
				$i--;
				$items[] = $item;
			} else {
				render_file_line($dir, $item, $music_dir_url, $i, filesize($dir.$item));
			}
		}
		if(is_dir($dir.$item)) {
			$temp=str_replace('%2F', '/', rawurlencode($current_dir)).rawurlencode($item);
			echo("<tr class=\"$parclass directory\" bgcolor=\"$parcolor\">".
			'<td><a href="#up">'.$i.'</a></td><td class="btntd"><a href="?download&playlist&dir='.$temp.'" class="icon iplay">P</a>/'.
			'<a href="?download&recursive&playlist&dir='.$temp.'" class="icon irplay">R</a>');
			if($GLOBALS['useflash']) echo('/<a href="?f&playlist&dir='.$temp.'" target="'.$GLOBALS['flash_player_frame'].'" class="icon ifplay">F</a>');
			echo('</td><td colspan="100%"><span class="icon ifolder">[DIR] </span><a href="?dir='.$temp.'">'.unxss(str_replace('_', ' ', 
$item))."</a></td></tr>\n");
		}
} rewinddir($dd); }
if($sort > 1) {
	@sort($items);
	foreach($items as $item) {
		$i++;
		render_file_line($dir, $item, $music_dir_url, $i, filesize($dir.$item));
	}
}

?></table>

<?php
render_footer();
