# SubRip File PHP Parser
Julien Villetorte, 2010 (gdelphiki@gmail.com)

## Installation with [composer](http://getcomposer.org/):

add this to your composer.json file:
``` json
"require": 
{
        "delphiki/subrip-file-parser": "1.*"
}
```

run 

``` sh
php composer update
```

## Usage:

``` php
<?php 

require('src/SrtParser/srtFile.php');

try{
	$file = new \SrtParser\srtFile('./subtitles.srt');

	// display the text of the first entry
	echo $file->getSub(0)->getText();

	// merge 2 files and save the new generated file
	$file2 = new srtFile('./subtitles2.srt');
	$file->mergeSrtFile($file2);
	$file->build();
	$file->save('./new_subtitles.srt');

}
catch(Exception $e){
	echo 'Error: '.$e->getMessage()."\n";
}
```
