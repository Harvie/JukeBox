<?php
/*
 * Harvie's JuKe!Box configuration file.
 *
 * Quick Instalation HowTo:
 * 1.) Change $users array and $music_dir_url!
 * 2.) Rename this file to: _config.php
 * 3.) Place your music into the $music_dir ($music_dir_url must point to the same directory through webserver)
 * 4.) create file jbx/cache.db writeable by webserver (set permissions)
 * 5.) Refresh search database using $cache_passwd on search page
 */
//Config-basic
$title =                'Harvie\'s&nbsp;JuKe!Box'; //Title of jukebox
$music_dir =            './music'; //Local path to directory with music
$music_dir_url =        'http://your-server.net/jukebox/music'; //URL path to the same directory CHANGE IT!
$cache_passwd =         'renew123'; //You need this passwd to refresh search cache CHANGE IT!
//Login
$realm =                'music'; //HTTP Auth Banner
$users = array(         //List of 'user' => 'password' pairs
                        'music' => 'Default-Secr3t_PaSsw0rd' //CHANGE IT!
);
$require_login =        true;
//Additional search engines
/*
$search_engines = array_merge($search_engines, array(
        		'Harvie\'s blog!' => 'http://blog.harvie.cz/?s='
));
*/
//Bonuses
if(isset($bonus_dir) && is_dir($bonus_dir)) {
	$css_file =	$bonus_dir.'/themes/default/jukebox.css'; //CSS (Design/Theme)
}
