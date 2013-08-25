<?php
/**
 * SubRip File Parser
 * Allows manipulation of .srt files
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *      
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *      
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA. 
 *
 * @author Julien 'delphiki' Villetorte <gdelphiki@gmail.com>
 * @link http://www.lackofinspiration.com
 * @license http://www.gnu.org/licenses/lgpl.html LGNU Public License 2+
*/
namespace SrtParser;

require_once 'srtFileEntry.php';

define ('UTF32_BIG_ENDIAN_BOM'   , chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF));
define ('UTF32_LITTLE_ENDIAN_BOM', chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00));
define ('UTF16_BIG_ENDIAN_BOM'   , chr(0xFE) . chr(0xFF));
define ('UTF16_LITTLE_ENDIAN_BOM', chr(0xFF) . chr(0xFE));
define ('UTF8_BOM'               , chr(0xEF) . chr(0xBB) . chr(0xBF));

class srtFile{
	/**
	 * Pattern used for the parsing regex
	 */
	const pattern = '#[0-9]+(?:\r\n|\r|\n)([0-9]{2}:[0-9]{2}:[0-9]{2}(?:,|\.)[0-9]{3}) --> ([0-9]{2}:[0-9]{2}:[0-9]{2}(?:,|\.)[0-9]{3})(?:\r\n|\r|\n)((?:.*(?:\r\n|\r|\n))*?)(?:\r\n|\r|\n)#';
	
	/**
	 * Default encoding if unable to detect
	 */
	const default_encoding = 'Windows-1252';
	
	/**
	 * Original filename
	 * 
	 * @var string
	 */
	private $filename;

	/**
	 * Current buffer of the file
	 * 
	 * @var string
	 */
	private $file_content;

	/**
	 * Current buffer of the file without Advanced SSA tags
	 * 
	 * @var string
	 */
	private $file_content_notag;

	/**
	 * Original file encoding
	 * 
	 * @var string
	 */
	private $encoding;

	/**
	 * File has BOM or not 
	 *
	 * @var boolean
	 */
	private $has_BOM = false;

	/**
	 * Array containing all entries (srtFileEntry) of the subtitle
	 * 
	 * @var array
	 */
	private $subs = array();

	/**
	 * Array containing reading speed ranges
	 * 
	 * @var array
	 */
	private $stats = array();

	/**
	 * Build as WebVTT file
	 * 
	 * @var boolean
	 */
	private $is_WebVTT = false;
	
	/**
	 * srtFile constructor
	 * 
	 * @param string filename
	 * @param string encoding of the file
	 */
	public function __construct($_filename, $_encoding = ''){
		$this->filename = $_filename;
		$this->encoding = $_encoding;
		$this->stats = array(
			'tooSlow' => 0,
			'slowAcceptable' => 0,
			'aBitSlow' => 0,
			'goodSlow' => 0,
			'perfect' => 0,
			'goodFast' => 0,
			'aBitFast' => 0,
			'fastAcceptable' => 0,
			'tooFast' => 0
		);
		$this->loadContent();
		$this->parseSubtitles();
	}

	/**
	 * Getters
	 */
	public function getFileContent(){
		return $this->file_content;
	}
	public function getEncoding(){
		return $this->encoding;
	}
	public function getSub($idx){
		return isset($this->subs[$idx])?$this->subs[$idx]:false;
	}
	public function getSubs(){
		return $this->subs;
	}
	public function getSubCount(){
		return count($this->subs);
	}
	public function getFilename(){
		return $this->filename;
	}
	public function isWebVTT(){
		return $this->is_WebVTT;
	}
	public function setWebVTT($_is_WebVTT = true){
		$this->is_WebVTT = (bool)$_is_WebVTT;

		if($this->is_WebVTT)
			$this->encoding = 'utf-8';
	}
	
	/**
	 * Checks if the file is a valid SubRip file
	 * @param string $_filename The path to the file
	 * @return boolean
	 */
	
	public static function isValid($_filename){
		$file_content = file_get_contents($_filename);

		return preg_match(self::pattern, $file_content);
	}

	/**
	 * Converts Windows-1252 (CP-1252) to UTF-8
	 * @param string $text The string to be converted
	 */
	public static function cp1252_to_utf8($text) {
		$buffer = $text;
		$cp1252 = array(
			128 => "€", # euro sign
			130 => "‚", # single low-9 quotation mark
			131 => "ƒ", # latin small letter f with hook
			132 => "„", # double low-9 quotation mark
			133 => "…", # horizontal ellipsis
			134 => "†", # dagger
			135 => "‡", # double dagger
			136 => "ˆ", # modifier letter circumflex accent
			137 => "‰", # per mille sign
			138 => "Š", # latin capital letter s with caron
			139 => "‹", # single left-pointing angle quotation mark
			140 => "Œ", # latin capital ligature oe
			142 => "Ž", # latin capital letter z with caron
			145 => "‘", # left single quotation mark
			146 => "’", # right single quotation mark
			147 => "“", # left double quotation mark
			148 => "”", # right double quotation mark
			149 => "•", # bullet
			150 => "–", # en dash
			151 => "—", # em dash
			152 =>  "˜", # small tilde
			153 => "™", # trade mark sign
			154 =>  "š", # latin small letter s with caron
			155 => "›", # single right-pointing angle quotation mark
			156 => "œ", # latin small ligature oe
			158 => "ž", # latin small letter z with caron
			159 => "Ÿ" # latin capital letter y with diaeresis
		);
		$buffer_encoded = utf8_encode($buffer);
		
		foreach($cp1252 as $ord => $encoded){
			$buffer_encoded=str_replace(utf8_encode(chr($ord)), $encoded, $buffer_encoded);
		}
		return $buffer_encoded;
	}

	/**
	 * Detects UTF encoding
	 * @param string $text
	 */
	public static function detect_utf_encoding($text) {
		$first2 = substr($text, 0, 2);
		$first3 = substr($text, 0, 3);
		$first4 = substr($text, 0, 4);

		if ($first3 == UTF8_BOM) return 'UTF-8';
		elseif ($first4 == UTF32_BIG_ENDIAN_BOM) return 'UTF-32BE';
		elseif ($first4 == UTF32_LITTLE_ENDIAN_BOM) return 'UTF-32LE';
		elseif ($first2 == UTF16_BIG_ENDIAN_BOM) return 'UTF-16BE';
		elseif ($first2 == UTF16_LITTLE_ENDIAN_BOM) return 'UTF-16LE';
		else return false;
	}

	/**
	 * Converts file content to UTF-8
	 */
	private function convertEncoding(){
		$exec = array();
		exec('file -i '.$this->filename, $exec);
		$res_exec = explode('=', $exec[0]);


		if(empty($res_exec[1]))
			throw new \Exception('Unable to detect file encoding.');
		$this->encoding = $res_exec[1];
		
		$tmp_encoding = strtolower($this->encoding);
		
		switch($tmp_encoding){
			case 'unknown':
			case 'windows-1252':
			case 'iso-8859-1':
			case 'iso-8859-15':
				$this->encoding = self::default_encoding;
				$this->file_content = self::cp1252_to_utf8($this->file_content);
			break;
			default:
				if(strtolower(substr($this->encoding, 0, 3)) == 'utf' && self::detect_utf_encoding($this->file_content))
					$this->has_BOM = true;

				if(strtolower(substr($this->encoding, 0, 7)) == 'unknown'){
					$this->encoding = self::default_encoding;
					$this->file_content = self::cp1252_to_utf8($this->file_content);		
				}
				else{
					$this->file_content = mb_convert_encoding($this->file_content, 'UTF-8', $this->encoding);
				}
			break;
		}
	}

	/**
	 * Loads file content and detect file encoding if undefined
	 */
	private function loadContent(){
		if(!file_exists($this->filename))
			throw new \Exception('File "'.$this->filename.'" not found.');

		if(!($this->file_content = file_get_contents($this->filename)))
			throw new \Exception($this->filename.' is not a proper .srt file.');

		$this->file_content .= "\n\n"; // fixes files missing blank lines at the end
		$this->convertEncoding();
	}

	/**
	 * Parses file content into srtFileEntry objects
	 */
	private function parseSubtitles(){
		$matches = array();
		$res = preg_match_all(self::pattern, $this->file_content, $matches);

		if(!$res || $res == 0)
			throw new \Exception($this->filename.' is not a proper .srt file.');

		if(strpos('.', $matches[1][0])) $this->is_WebVTT = true;

		$entries_count = sizeof($matches[1]);
		for($i=0; $i<$entries_count; $i++){
			$sub = new srtFileEntry($matches[1][$i], $matches[2][$i], $matches[3][$i]);
			$this->subs[] = $sub;
		}
	}

	/**
	 * Searchs a word/expression and returns ids of the matched entries
	 *
	 * @param string $word
	 * @param boolean $case_sensitive
	 * @param boolean $strict
	 * @return array containing ids of entries
	 */
	public function searchWord($word, $case_sensitive = false, $strict = false){
		$list = array();
		$pattern = preg_quote($word,'#');

		$pattern = str_replace(' ', '( |\r\n|\r|\n)', $pattern);

		if($strict)
			$pattern = '($| |\r\n|\r|\n|\?|\!|\.|,	)'.$pattern.'(^| |\r\n|\r|\n|\?|\!|\.|,)';

		$pattern = '#'.$pattern.'#';

		if(!$case_sensitive)
			$pattern .= 'i';
		
		$keys = array_keys($this->subs);
		$sub_count = sizeof($keys);
		$i = 0;
		foreach($this->subs as $sub){
			if(preg_match($pattern, $sub->getText()))
				$list[] = $i;
			$i++;
		}

		return (count($list) > 0)?$list:-1;
	}

	public function searchCueFromTimecode($_start){
		$start = srtFileEntry::tc2ms($_start);

		$prev_stop = 0;
		$res = false;
		$i = 0;
		foreach($this->subs as $sub){
			if(($start > $prev_stop && $start < $sub->getStart()) || ($start >= $sub->getStart() && $start < $sub->getStop())){
				$res = $sub;
				break;
			}
			$prev_stop = $sub->getStop();
			$i++;
		}

		return $i;
	}

	/**
	 * Imports subtitles from another srtFile object
	 *
	 * @param srtFile $_srtFile another srtFile object
	 */
	public function mergeSrtFile($_srtFile){
		if(!$_srtFile instanceof srtFile)
		$this->subs = array_merge($this->subs, $_srtFile->getSubs());
		
		$this->sortSubs();
	}
	
	/**
	 * Deletes a cue
	 *
	 * @param int $_index
	 */
	public function deleteSub($_index){
		if(isset($this->subs[$_index])){
			unset($this->subs[$_index]);
		}
	}

	/**
	 * Sorts srtFile entries
	 */
	public function sortSubs(){
		$tmp = array();
		$keys = array_keys($this->subs);
		$sub_count = sizeof($keys);
		$count = 0; // useful if 2 cues start at the same time code
		foreach($this->subs as $sub){
			$tmp[srtFileEntry::tc2ms($sub->getStartTC()).'.'.$count] = $sub;
			$count++;
		}

		ksort($tmp);

		$this->subs = array();
		
		$keys_tmp = array_keys($tmp);
		$tmp_count = sizeof($keys_tmp);
		
		foreach($tmp as $sub)
			$this->subs[] = $sub;
	}

	/**
	 * Converts timecodes based on the specified FPS ratio
	 *
	 * @param float $old_fps
	 * @param float $new_fps
	 */
	public function changeFPS($old_fps, $new_fps){
		$keys = array_keys($this->subs);
		$sub_count = sizeof($keys);
		for($i=0; $i < $sub_count; $i++){
			$old_start = $this->subs[$keys[$i]]->getStart();
			$old_stop = $this->subs[$keys[$i]]->getStop();

			$new_start = round($old_start * ($new_fps / $old_fps));
			$new_stop = round($old_stop * ($new_fps / $old_fps));

			$this->subs[$keys[$i]]->setStart($new_start);
			$this->subs[$keys[$i]]->setStop($new_stop);
		}
	}

	/**
	 * Builds file content (file_content[_notag])
	 *
	 * @param boolean $stripTags If true, {\...} tags will be stripped
	 * @param boolean $stripBasics If true, <i>, <b> and <u> tags will be stripped
	 * @param array $replacements
	 */
	public function build($stripTags = false, $stripBasic = false, $replacements = array()){
		$buffer = ($this->is_WebVTT) ? "WEBVTT\r\n\r\n" : "";

		$i = 0;
		foreach($this->subs as $key => $sub){
			$buffer .= ($i+1)."\r\n";
			$buffer .= $this->subs[$key]->getTimeCodeString($this->is_WebVTT)."\r\n";
			$buffer .= $this->subs[$key]->getText($stripTags, $stripBasic, $replacements)."\r\n";
			$buffer .= "\r\n";
			$i++;
		}
		if($stripTags)
			$this->file_content_notag = $buffer;
		else
			$this->file_content = $buffer;
	}

	/**
	 * Builds file content (file_content[_notag]) from entry $from to entry $to
	 *
	 * @param int $from Id of the first entry
	 * @param int $to Id of the last entry
	 * @param boolean $stripTags If true, {\...} tags will be stripped
	 * @param boolean $stripBasics If true, <i>, <b> and <u> tags will be stripped
	 * @param array $replacements
	 */
	public function buildPart($from, $to, $stripTags = false, $stripBasic = false, $replacements = array()){
		$i = 1;
		$buffer = "";
		if($from < 0 || $from >= $this->getSubCount()) $from = 0;
		if($to < 0 || $to >= $this->getSubCount()) $to = $this->getSubCount()-1;

		for($j = $from; $j <= $to; $j++){
			$buffer .= $i."\r\n";
			$buffer .= $this->subs[$j]->getTimeCodeString()."\r\n";
			$buffer .= $this->subs[$j]->getText($stripTags, $stripBasic, $replacements)."\r\n";
			$buffer .= "\r\n";
			$i++;
		}
		if($stripTags)
			$this->file_content_notag = $buffer;
		else
			$this->file_content = $buffer;	
	}

	/**
	 * Saves the file
	 *
	 * @param string $filename
	 * @param boolean $stripTags If true, use file_content_notag instead of file_content
	 */
	public function save($filename = null, $stripTags = false){
		if($filename == null)
			$filename = $this->filename;
		
		$content = $stripTags ? $this->file_content_notag : $this->file_content;
			
		if(strtolower($this->encoding) != 'utf-8')
			$file_content = mb_convert_encoding($content, $this->encoding, 'UTF-8');
		elseif(strtolower($this->encoding) == 'utf-8' && $this->has_BOM && substr($content, 0, 3) != UTF8_BOM)
			$file_content = UTF8_BOM . $content;
		else
			$file_content = $content;
		
		$res = file_put_contents($filename, $file_content);
		if(!$res)
			throw new \Exception('Unable to save the file.');
	}

	/**
	 * Computes statistics regarding reading speed
	 */
	public function calcStats(){
		$keys = array_keys($this->subs);
		$sub_count = sizeof($keys);
		for($i=0; $i < $sub_count; $i++){
			$this->subs[$keys[$i]]->prepForStats();
			$rs = $this->subs[$keys[$i]]->getReadingSpeed();
			if($rs < 5)
				$this->stats['tooSlow']++;
			elseif($rs < 10)
				$this->stats['slowAcceptable']++;
			elseif($rs < 13)
				$this->stats['aBitSlow']++;
			elseif($rs < 15)
				$this->stats['goodSlow']++;
			elseif($rs < 23)
				$this->stats['perfect']++;
			elseif($rs < 27)
				$this->stats['goodFast']++;
			elseif($rs < 31)
				$this->stats['aBitFast']++;
			elseif($rs < 35)
				$this->stats['fastAcceptable']++;
			else
				$this->stats['tooFast']++;
		}
	}

	/**
	 * @return int
	 */
	private function getPercent($nb){
		return round($nb * 100 / count($this->subs), 1);
	}

	/**
	 * Saves statistics as XML file
	 *
	 * @param string $filename
	 */
	public function saveStats($filename, $output = 'html'){
		if($output == 'xml'){
			$tmp = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
			$tmp .= '<statistics file="'.$this->filename.'">'."\n";
			$tmp .= '<range name="tooSlow" color="#9999FF" value="'.$this->stats['tooSlow'].'" percent="'.$this->getPercent($this->stats['tooSlow']).'" />'."\n";
			$tmp .= '<range name="slowAcceptable" color="#99CCFF" value="'.$this->stats['slowAcceptable'].'" percent="'.$this->getPercent($this->stats['slowAcceptable']).'" />'."\n";
			$tmp .= '<range name="aBitSlow" color="#99FFFF" value="'.$this->stats['aBitSlow'].'" percent="'.$this->getPercent($this->stats['aBitSlow']).'" />'."\n";
			$tmp .= '<range name="goodSlow" color="#99FFCC" value="'.$this->stats['goodSlow'].'" percent="'.$this->getPercent($this->stats['goodSlow']).'" />'."\n";
			$tmp .= '<range name="perfect" color="#99FF99" value="'.$this->stats['perfect'].'" percent="'.$this->getPercent($this->stats['perfect']).'" />'."\n";
			$tmp .= '<range name="goodFast" color="#CCFF99" value="'.$this->stats['goodFast'].'" percent="'.$this->getPercent($this->stats['goodFast']).'" />'."\n";
			$tmp .= '<range name="aBitFast" color="#FFFF99" value="'.$this->stats['aBitFast'].'" percent="'.$this->getPercent($this->stats['aBitFast']).'" />'."\n";
			$tmp .= '<range name="fastAcceptable" color="#FFCC99" value="'.$this->stats['fastAcceptable'].'" percent="'.$this->getPercent($this->stats['fastAcceptable']).'" />'."\n";
			$tmp .= '<range name="tooFast" color="#FF9999" value="'.$this->stats['tooFast'].'" percent="'.$this->getPercent($this->stats['tooFast']).'" />'."\n";
			$tmp .= '</statistics>';
		}
		elseif($output == 'html'){
			$tmp = '<ul class="srt_stats">';
			$tmp .= '<li style="background-color:#9999FF">TOO SLOW = <span style="float:right;">'.$this->stats['tooSlow'].' ('.$this->getPercent($this->stats['tooSlow']).'%)</span></li>';
			$tmp .= '<li style="background-color:#99CCFF">Slow, acceptable = <span style="float:right;">'.$this->stats['slowAcceptable'].' ('.$this->getPercent($this->stats['slowAcceptable']).'%)</span></li>';
			$tmp .= '<li style="background-color:#99FFFF">A bit slow = <span style="float:right;">'.$this->stats['aBitSlow'].' ('.$this->getPercent($this->stats['aBitSlow']).'%)</span></li>';
			$tmp .= '<li style="background-color:#99FFCC">Good = <span style="float:right;">'.$this->stats['goodSlow'].' ('.$this->getPercent($this->stats['goodSlow']).'%)</span></li>';
			$tmp .= '<li style="background-color:#99FF99">Perfect = <span style="float:right;">'.$this->stats['perfect'].' ('.$this->getPercent($this->stats['perfect']).'%)</span></li>';
			$tmp .= '<li style="background-color:#CCFF99">Good = <span style="float:right;">'.$this->stats['goodFast'].' ('.$this->getPercent($this->stats['goodFast']).'%)</span></li>';
			$tmp .= '<li style="background-color:#FFFF99">A bit fast = <span style="float:right;">'.$this->stats['aBitFast'].' ('.$this->getPercent($this->stats['aBitFast']).'%)</span></li>';
			$tmp .= '<li style="background-color:#FFCC99">Fast, acceptable = <span style="float:right;">'.$this->stats['fastAcceptable'].' ('.$this->getPercent($this->stats['fastAcceptable']).'%)</span></li>';
			$tmp .= '<li style="background-color:#FF9999">TOO FAST = <span style="float:right;">'.$this->stats['tooFast'].' ('.$this->getPercent($this->stats['tooFast']).'%)</span></li>';
			$tmp .= '</ul>';
		}

		file_put_contents($filename, $tmp);
	}
}