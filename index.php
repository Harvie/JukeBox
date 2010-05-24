<?php require_once('./http_auth.php'); /*Delete this line to disable password protection*/ ?>
<?php /*
Harvie's JuKe!Box
///////////////////////////////////////////////////////////////////////
Version info:
 0.2	- Few new functions (search playlist, random,...)
 0.1.1	- Few little fixups, written help.html in Czech language ;D
 0.1	- All functions works - TODO: bugfix & replace ugly code
///////////////////////////////////////////////////////////////////////
*/

//Config-basic
$title = 		'Harvie\'s&nbsp;JuKe!Box'; //Title of jukebox
$music_dir = 		'./music'; //Local path to directory with music
$music_dir_url = 	'http://192.168.2.163/pub/m3gen/music'; //URL path to same directory
//$music_dir_url = 	'http://music.harvie.cz/music';
//$music_dir_url = 	'http://softz.harvie.cz/jukebox/demo/music';
$cache_passwd = 	'reload'; //You need this passwd to refresh search cache
$sort =			3; //Sort? 0 = none, 1 = playlists, 2 = 1+listings; 3 = 2+search-EXPERIMENTAL! (sorting could eat lot of memory)
$access_limit =		20; //How many files could be accessed without using cache (while searching)

//Playlist settings
$playlist_name = 	'playlist.m3u'; //Name of downloaded pl
$m3u_exts = 		'ogg|mp[0-9]|wma|wmv|wav'; //Allow only these files
$default_random_count =	30; //How many random songs by defaul?

//External files
$indexlist = 		array('index.html', 'index.txt'); //Search for this file in each directory
$bonus_dir =		'./jukebox-bonus'; //Misc. files directory
$search_cache = 	$bonus_dir.'/cache.db'; //Database for searching music (php +rw) - .htaccess: Deny from all!!!
$flash_player =		$bonus_dir.'/musicplayer.swf'; //path to musicplayer
$css_file =		$bonus_dir.'/jukebox.css'; //CSS
$header_file =		$bonus_dir.'/header.html'; //header file
$footer_file =		$bonus_dir.'/footer.html'; //footer file

//Security
error_reporting(0);

//Init
srand(time());
$current_dir = ereg_replace('/+', '/', '/'.$_GET['dir'].'/');
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

function generate_m3u($dir, $prefix='', $recursive=0) {
	$dir = $dir . '/';
	$dd = opendir($dir);
	while(($item = readdir($dd)) != false) {
        	if($item == '.' || $item == '..') continue;
                if( is_file($dir.$item) && eregi(('\.('.$GLOBALS['m3u_exts'].')$'), $item) ) {
			if($GLOBALS['sort'] > 0) {
				$temp[] = $item;
			} else {
				echo($prefix.'/'.str_replace('%2F', '/', (rawurlencode($dir.$item)))."\r\n");
			}
		}
                if($recursive && is_dir($dir.$item)) {
			generate_m3u($dir.$item, $prefix);
                }
	}
	if($GLOBALS['sort'] > 0) {
		@sort($temp);
		foreach($temp as $item)
			echo($prefix.'/'.str_replace('%2F', '/', (rawurlencode($dir.$item)))."\r\n");
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
	$parclass=($index%2?"even":"odd"); $parcolor=($index%2?"lightblue":"white");
	$temp=str_replace('&', '%26', dirname($dir_url)).'/'.str_replace('%2F', '/', (rawurlencode($dir.$item)));
	if(is_numeric($filesize)) $filesize = round($filesize/(1024*1024), 2);
	echo("<tr class=\"$parclass\" bgcolor=\"$parcolor\">".'<td>'.$index.'</td><td>');
	echo('<a href="?download&song='.rawurlencode($temp).'">P</a>');
	if($parent) {
		echo('/<a href="?dir='.
			substr(str_replace(array('&','%2F'), array('%26','/'), (rawurlencode(dirname($dir.$item)))), strlen($GLOBALS['music_dir'])).
			'">D</a>');
	}
	if(is_file($GLOBALS['flash_player']) && eregi(('\.('.$GLOBALS['m3u_exts'].')$'), $item)) {
		/*echo('/<object type="application/x-shockwave-flash" width=17 height=17  data="'.
		$GLOBALS['flash_player'].'?song_url='.rawurlencode($temp).'"></object>');*/
		echo('/<a href="'.$GLOBALS['flash_player'].'?autoplay=true&song_url='.rawurlencode($temp).'" target="playframe">F</a>/'.
		'<a href="about:blank" target="playframe">S</a>');
	}
	echo('&nbsp;</td><td><a href="'.$temp.'">'.str_replace('_', ' ', $item).'</a></td><td>'.$filesize."&nbsp;MiB&nbsp;</td></tr>\n");			
}

function unxss($string) {
	return str_replace(
		array('&', '"', '\'', '<', '>'),
		array('&amp;', '&quot;', '&#039;', '&lt;', '&gt;'),
		$string);
}

//GET
if(isset($_GET['download'])) serve_download($playlist_name);
if(isset($_GET['song'])) die($_GET['song']."\r\n");

if($_POST['cache-refresh'] == $cache_passwd) {
	generate_search_cache($music_dir, $search_cache);
	die("\n");
}

if(isset($_GET['playlist'])) {
	if(!isset($_GET['search'])) {
		generate_m3u($dir, dirname($music_dir_url), isset($_GET['recursive']));
	} else {
		if(!($searchfp = fopen($search_cache, 'r')))
			die("Cannot read cache from $outfile<br />Refresh cache or set permissions properly!<br />\n");
		while(!feof($searchfp)) {
			$line = trim(fgets($searchfp));
			if(@eregi(str_replace(' ', '(.*)', $_GET['search']), $line)) 
				echo(dirname($music_dir_url).'/'.str_replace('%2F', '/', (rawurlencode($line)))."\r\n");
		}
	}
	die("\n");
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
	die("\n");
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<link rel="stylesheet" type="text/css" href="<?=$css_file?>">

<div align="right" style="position: absolute; top: 0; right: 0;">
	<iframe src="about:blank" name="playframe" width="0" height="0" style="border: none;"></iframe>
	&lt; <a href="javascript: history.go(-1)">BACK</a> | <a href="?">HOME (<?=$music_dir?>)</a> | <a href="?help">ABOUT/HELP</a> | <a href="?logout">LOGOUT</a>
</div>

<?php
if(isset($_GET['help'])) {
        ?><h1>About/Help</h1><?php
        readfile($bonus_dir.'/help.html');
	die();
}

if(!isset($_GET['search'])) {
	echo('<title>'.$title.': '.$dir.'</title>');
	echo('<a href="?" style="color: black;"><h1>'.$title.'</h1></a><h2>Index of: '.$dir.'</h2>');
} else {
	echo('<title>'.$title.': '.unxss($_GET['search']).'</title>');
	echo('<a href="?" style="color: black;"><h1>'.$title.'</h1></a><h2>Searching for: '.unxss($_GET['search']).'</h2>');
}

?>

<div align="right">
	<form action="?" method="GET" align="right" style="display: inline;">
		<input type="hidden" name="download" value="" />
		<input type="text" name="random" value="<?=$default_random_count?>" />
		<input type="submit" value="random" title="Generate random music playlist..." />
	</form>
	<form action="?" method="GET" align="right" style="display: inline;">
		<input type="text" name="search" 
			title="Search in music/google/lyrics/mp3/youtube; Hint: You can use regular expressions in search query..."
			value="<?=unxss($_GET['search'])?>"
		/>
		<input type="submit" value="search" title="Search in this JuKe!Box..." />
	</form>
</div><br /><?php

if(isset($_GET['search'])) {

?><div align="right">
	<form action="http://google.com/search" method="GET" align="right" style="display: inline;">
		<input type="text" name="q" value="<?=unxss($_GET['search'])?>" />
		<input type="submit" value="google" title="Search on Google..." />
	</form>
	<form action="http://www.elyricsworld.com/search.php?phrase=marley" method="GET" align="right" style="display: inline;">
		<input type="text" name="phrase" value="<?=unxss($_GET['search'])?>" />
		<input type="submit" value="lyrics" title="Search for lyrics on internet..." />
	</form>
	<form action="http://jyxo.cz/s" method="GET" align="right" style="display: inline;">
		<input type="hidden" name="d" value="mm" />
		<input type="text" name="q" value="<?=unxss($_GET['search'])?>" />
		<input type="submit" value="jyxo multimedia" title="Search media on internet..." />
	</form>
	<form action="http://youtube.com/results" method="GET" align="right" style="display: inline;">
		<input type="text" name="search_query" value="<?=unxss($_GET['search'])?>" />
		<input type="submit" value="youtube" title="Search on YOUTube..." />
	</form>
</div><br />
<div align="right">
	<form action="?" method="POST" align="right">
		<input type="password" name="cache-refresh" value="" title="Password for refreshing - good for avoiding DoS Attacks!!!" />
		<input type="submit" value="refresh cache" title="You should refresh cache each time when you add new music or upgrade to newer version of JuKe!Box !!!" />
	</form>
</div><?php
echo('Search DB size: '.(filesize($search_cache)/1024)." kB<br />\n");

if(!($searchfp = fopen($search_cache, 'r')))
	die("Cannot read cache from $outfile<br />Refresh cache or set permissions properly!<br />\n");

$i = 0;
echo('<table border="1" width="100%">');
echo('<tr><td>S</td><td><a href="?download&playlist&search='.unxss($_GET['search']).'">P</a></td><td colspan="100%">Search: '.unxss($_GET['search']).'</td></tr>');
while(!feof($searchfp)) {
	$line = trim(fgets($searchfp));
	$parclass=($i%2?"even":"odd"); $parcolor=($i%2?"lightblue":"white");
	if(@eregi(str_replace(' ', '(.*)', $_GET['search']), $line)) {
		$i++;
		echo("<tr class=\"$parclass\" bgcolor=\"$parcolor\">");
		$filesize = 0; if($i <= $access_limit) $filesize = filesize($line); else $filesize = 'n/a';
		render_file_line('', $line, $music_dir_url, $i, $filesize, true);
		echo("</tr>\n");
	}
}
echo("</table>Total: $i results...<br />");
die();

}
@readfile($header_file);
foreach($indexlist as $index) @readfile($dir.$index); 
?>
<br />
<table border="1" width="100%">
<tr><td>&gt;</td>
<td><b><a href="?download&playlist&dir=<?=str_replace('%2F', '/', rawurlencode($current_dir))?>">P</a>/<a
href="?download&recursive&playlist&dir=<?=str_replace('%2F', '/', rawurlencode($current_dir))?>">R</a></b></td>
<td colspan="100%"><?=$dir?></td></tr>
<tr><td>^</td><td>&nbsp;</td><td colspan="100%">[DIR] <a href="?dir=<?=rawurlencode($parent_dir)?>">.. (<?=$parent_dir?>)</a></td></tr>
<?php

$i = 0;
$dd = opendir($dir);
for($s=2;$s;$s--) { while(($item = readdir($dd)) != false) {
	if($item == '.' || $item == '..') continue;
	if(($s==2 && is_file($dir.$item)) || ($s!=2 && is_dir($dir.$item))) continue;
	$i++;
	$parclass=($i%2?"even":"odd"); $parcolor=($i%2?"lightblue":"white");
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
			echo("<tr class=\"$parclass\" bgcolor=\"$parcolor\">".
			'<td>'.$i.'</td><td><a href="?download&playlist&dir='.$temp.'">P</a>/'.
			'<a href="?download&recursive&playlist&dir='.$temp.'">R</a></td>'.
			'<td colspan="100%">[DIR] <a href="?dir='.$temp.'">'.str_replace('_', ' ', $item)."</a></td></tr>\n");
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

echo('<i>'.$quotes[rand(0,sizeof($quotes)-1)]."</i>\n");
@readfile($footer_file);
