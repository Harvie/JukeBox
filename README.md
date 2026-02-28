#Harvie's JuKe!Box

###EN
This web application allows you to browse, share, download and stream music using a webserver with PHP and an in-browser (HTML5) player or external audio player (WMP, Audacious, Winamp, Totem, etc...).
Basicaly it's easy to use, easy to install, fully configurable and it looks like this:

###CZ
Tato webová aplikace vám umožní procházet, sdílet, stahovat a streamovat hudbu pomocí webserveru s podporou PHP a vestavěného (HTML5) přehrávače v prohlížeči, nebo externího přehrávače (WMP, Audacious, Winamp, Totem, apod...).
Zjednodušeně je aplikace lehce použitelná, lehce se instaluje, vše se dá nastavit a vypadá takto:


##ScreenShots

###Latest
![screenshot](http://img835.imageshack.us/img835/378/harviejukebox036.png)

###Obsolete
  * http://code.google.com/p/h-jukebox/wiki/ScreenShots


##ScreenCasts

###Latest
  * n/a

###Obsolete
  * http://www.youtube.com/watch?v=UucMVLs1xfg
  * http://www.youtube.com/watch?v=DQZqnDdQDDk


##TODO
  * Autodetect absolute URL of music directory (replace https with http as some music players are not able to handle TLS)
  * Turn jukebox into universal filelisting with plugins for playing music, videos, documents,...
    * some system for registering regex-based hooks on filenames
    * directory and file lines should be rendered by the same function (using those hooks - directory ends with slash)
  * ~~Use HTML5 for playback instead of flash~~ (done)
  * Nice URLs so Juke!Box will be traversable using lftp and similar tools (PATH_INFO, mod_rewrite?)
    * Need to add function that will return URL to directory listing of desired directory (should detect if PATH_INFO is in use)
  * VLC plugin to browse, search and stream using JuKe!Box on server side
    * there's some LUA API to do this... just need to check some examples...
  * Documentation ( reuse old one: http://code.google.com/p/h-jukebox/wiki/AboutHelp )
  * Comment source a bit...


##Anotation/Anotace

###EN
This project (which is part of school-leaving exams) is aiming to create web application which enables user to browse audio library stored on web server using web browser 
and then it offers playback of those audio records directly in web browser, in external software audio player or download to harddrive.

###CZ
Cílem tohoto maturitního projektu je vytvořit webovou aplikaci, která umožní uživateli procházet knihovnu zvukových záznamů na webovém serveru pomocí webového prohlížeče a 
potom umožní tyto záznamy přehrávat přímo v prohlížeči, streamovat do jiného přehrávače, nebo stáhnout na disk.
