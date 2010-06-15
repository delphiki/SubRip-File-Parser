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

class srtFileEntry{
	/**
	 * Start Timecode (format: hh:mm:ss,msmsms)
	 *
	 * @var string
	 */
	private $startTC;

	/**
	 * Start Timecode (in milliseconds)
	 *
	 * @var int
	 */
	private $start = null;

	/**
	 * Stop Timecode (format: hh:mm:ss,msmsms)
	 *
	 * @var string
	 */
	private $stopTC;

	/**
	 * Stop Timecode (in milliseconds)
	 *
	 * @var int
	 */
	private $stop = null;

	/**
	 * Brut text (with ends of lines)
	 *
	 * @var string
	 */
	private $text;

	/**
	 * Pre-formated text (without ends of lines) generated to compute satistics
	 *
	 * @var string
	 */
	private $strippedText;

	/**
	 * Brut text (with ends of lines) without Advanced SSA tags
	 *
	 * @var string
	 */
	private $noTagText;

	/**
	 * Entry duration in milliseconds
	 *
	 * @var int
	 */
	private $durationMS;

	/**
	 * Caracters / second
	 *
	 * @var float
	 */
	private $CPS;

	/**
	 * Reading Speed (based on VisualSubSync algorithm)
	 *
	 * @var float
	 */
	private $readingSpeed;
	
	/**
	 * srtFileEntry constructor
	 *
	 * @param string $_start Start timecode
	 * @param string $_stop End timcode
	 * @param string $_text Text of the entry
	 */
	public function __construct($_start, $_stop, $_text){
		$this->startTC = $_start;
		$this->stopTC = $_stop;
		$this->text = trim($_text);
	}
	
	public function prepForStats(){
		$this->genStrippedText();
		$this->calcDuration();
		$this->calcCPS();
		$this->calcRS();
	}
	
	/**
	 * Getters
	 */

	/**
	 * Returns the text of the entry
	 *
	 * @param boolean $stripTags
	 * @param boolean $stripBasic
	 * @param array $replacements
	 * @return string
	 */
	public function getText($stripTags = false, $stripBasic = false, $replacements = array()){
		if($stripTags){
			$this->stripTags($stripBasic, $replacements);
			return $this->noTagText;
		}
		else
			return $this->text;
	}

	public function getStartTC(){
		return $this->startTC;
	}
	public function getStopTC(){
		return $this->stopTC;
	}
	public function getStart(){
		if($this->start == null)
			$this->calcDuration();
		return $this->start;
	}
	public function getStop(){
		if($this->stop == null)
			$this->calcDuration();
		return $this->stop;
	}
	public function getStrippedText(){
		$this->genStrippedText();
		return $this->strippedText;
	}
	public function getDurationMS(){
		return $this->durationMS;
	}
	public function getCPS(){
	 return $this->CPS;
	}
	public function getReadingSpeed(){
		return $this->readingSpeed;
	}

	/**
	 * Get the full timecode of the entry
	 *
	 * @return string
	 */
	public function getTimeCodeString(){ 
		return $this->startTC.' --> '.$this->stopTC; 
	}

	/**
	 * Sets a new text value
	 *
	 * @param string $text the new text
	 */
	public function setText($_text){
		$this->text = $_text;
	}

	/**
	 * Sets the start timecode
	 *
	 * @param string $_start
	 */
	public function setStartTC($_start){
		$this->startTC = $_start;
		$this->start = self::tc2ms($_start);
	}

	/**
	 * Sets the stop timecode
	 *
	 * @param string $_stop
	 */
	public function setStopTC($_stop){
		$this->stopTC = $_stop;
		$this->stop = self::tc2ms($_stop);
	}

	/**
	 * Sets the start timecode as milliseconds
	 *
	 * @param int $_start
	 */
	public function setStart($_start){
		$this->start = $_start;
		$this->startTC = self::ms2tc($_start);
	}

	/**
	 * Sets the stop timecode as milliseconds
	 *
	 * @param int $_stop
	 */
	public function setStop($_stop){
		$this->stop = $_stop;
		$this->stopTC = self::ms2tc($_stop);
	}


	/**
	 * Generates stripped text in order to compute statistics
	 */
	private function genStrippedText(){
		$this->stripTags(true);
		
		$pattern = "/(\r\n|\n|\r)/";
		$repl = " ";
		$this->strippedText = preg_replace($pattern, $repl, $this->noTagText);
	}
	
	/**
	 * Strips Advanced SSA tags
	 *
	 * @param boolean $stripBasic If true, <i>, <b> and <u> tags will be stripped
	 * @param array $replacements
	 * @return boolean (true if tags were actually stripped)
	 */
	public function stripTags($stripBasic = false, $replacements = array()){
		if($stripBasic)
			$this->noTagText = strip_tags($this->text);
		else
			$this->noTagText = $this->text;

		$patterns = "/{[^}]+}/";
		$repl = "";
		$this->noTagText = preg_replace($patterns, $repl, $this->noTagText);

		foreach($replacements as $key => $replacement){
			$this->noTagText = str_replace($key, $replacement, $this->noTagText);
		}
		
		return ($this->text != $this->noTagText);
	}
	
	/**
	 * Returns the *real* string length
	 *
	 * @return int
	 */
	public function strlen(){
		if($this->strippedText == '')
			$this->genStrippedText();
		return mb_strlen($this->strippedText, 'UTF-8');
	}
	
	/**
	 * Converts timecode string into milliseconds
	 *
	 * @param string $tc timecode as string 
	 * @return int
	 */
	public static function tc2ms($tc){
		$tab = explode(':', $tc);
		$durMS = $tab[0]*60*60*1000 + $tab[1]*60*1000 + floatval(str_replace(',','.',$tab[2]))*1000;
		
		return $durMS;
	}

	/**
	 * Converts milliseconds into timecode string
	 *
	 * @param int $ms
	 * @return string
	 */
	public static function ms2tc($ms){
		$tc_ms = round((($ms / 1000) - intval($ms / 1000)) * 1000);
		$x = $ms / 1000;
		$tc_s = intval($x % 60);
		$x /= 60;
		$tc_m = intval($x % 60);
		$x /= 60;
		$tc_h = intval($x % 24);
	
		$timecode = str_pad($tc_h, 2, '0', STR_PAD_LEFT).':'
			.str_pad($tc_m, 2, '0', STR_PAD_LEFT).':'
			.str_pad($tc_s, 2, '0', STR_PAD_LEFT).','
			.str_pad($tc_ms, 3, '0', STR_PAD_LEFT);		
		return $timecode;
	}
		
	/**
	 * Computes entry duration in milliseconds
	 */
	public function calcDuration(){
		$this->start = self::tc2ms($this->startTC);
		$this->stop = self::tc2ms($this->stopTC);
		
		$this->durationMS = $this->stop - $this->start;
	}
	
	/**
	 * Computes car. / second ratio
	 */
	private function calcCPS(){
		$this->CPS = round($this->strlen() / ($this->durationMS / 1000), 1);
	}
	
	/**
	 * Computes Reading Speed (based on VisualSubSync algorithm)
	 */
	private function calcRS(){
		if($this->durationMS < 500)
			$this->durationMS = 500;
			
		$this->readingSpeed = ($this->strlen() * 1000) / ($this->durationMS-500);
	}
}


class srtFile{
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
	 * Current buffer of the file without SubRip tags
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
		return isset($this->subs[$idx])?$this->subs[$idx]:0;
	}
	public function getSubs(){
		return $this->subs;
	}
	public function getSubCount(){
		return count($this->subs);
	}
	
	/**
	 * Loads file content and detect file encoding if undefined
	 */
	private function loadContent(){
		if(!file_exists($this->filename))
			throw new Exception('File "'.$this->filename.'" not found.');
	
		$this->file_content = file_get_contents($this->filename);
		
		if($this->encoding == ''){
			$exec = array();
			exec('file -i '.$this->filename, $exec);
			$res_exec = explode('=', $exec[0]);
		

			if(empty($res_exec[1]))
				throw new Exception('Unable to detect file encoding.');
			$this->encoding = $res_exec[1];
		}
		
		$this->file_content = mb_convert_encoding($this->file_content, 'UTF-8', $this->encoding);
	}
	
	/**
	 * Parses file content into srtFileEntry objects
	 */
	private function parseSubtitles(){
		$pattern = '#[0-9]+(?:\r\n|\r|\n)'
			.'([0-9]{2}:[0-9]{2}:[0-9]{2},[0-9]{3}) --> ([0-9]{2}:[0-9]{2}:[0-9]{2},[0-9]{3})(?:\r\n|\r|\n)'
			.'((?:.+(?:\r\n|\r|\n))+?)'
			.'(?:\r\n|\r|\n)#';
		
		$matches = array();
		$res = preg_match_all($pattern, $this->file_content, $matches);
		
		if(!$res || $res == 0)
			throw new Exception($this->filename.' is not a proper .srt file.');
			
		for($i=0; $i<count($matches[1]); $i++){
			$sub = new srtFileEntry($matches[1][$i], $matches[2][$i], $matches[3][$i]);
			$this->subs[] = $sub;
		}
	}
	
	/**
	 * Searchs a word/expression and returns ids of the matched entries
	 *
	 * @param string $word
	 * @param boolean $case_sensitive
	 * @return array containing ids of entries
	 */
	public function searchWord($word, $case_sensitive = false){
		$list = array();
		$i = 0;
		$pattern = '#'.preg_quote($word,'#').'#';
		if(!$case_sensitive)
			$pattern .= 'i';
		foreach($this->subs as $sub){
			if(preg_match($pattern, $sub->getText()))
				$list[] = $i;
			$i++;
		}

		return (count($list) > 0)?$list:-1;
	}
	
	/**
	 * Imports subtitles from another srtFile object
	 *
	 * @param srtFile $_srtFile another srtFile object
	 */
	public function mergeSrtFile($_srtFile){
		if(!$_srtFile instanceof srtFile)
			throw new Exception('srtFile object parameter exepected.');
		$this->subs = array_merge($this->subs, $_srtFile->getSubs());
		
		$this->sortSubs();
	}
	
	/**
	 * Sorts srtFile entries
	 */
	public function sortSubs(){
		$tmp = array();
		foreach($this->subs as $sub)
			$tmp[srtFileEntry::tc2ms($sub->getStartTC())] = $sub;
			
		ksort($tmp);
		
		$this->subs = array();
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
		foreach($this->subs as $sub){
			$old_start = $sub->getStart();
			$old_stop = $sub->getStop();
			
			$new_start = round($old_start * ($new_fps / $old_fps));
			$new_stop = round($old_stop * ($new_fps / $old_fps));

			$sub->setStart($new_start);
			$sub->setStop($new_stop);
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
		$i = 1;
		$buffer = "";
		foreach($this->subs as $sub){
			$buffer .= $i."\r\n";
			$buffer .= $sub->getTimeCodeString()."\r\n";
			$buffer .= $sub->getText($stripTags, $stripBasic, $replacements)."\r\n";
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
			$buffer .= $this->getSub($j)->getTimeCodeString()."\r\n";
			$buffer .= $this->getSub($j)->getText($stripTags, $stripBasic, $replacements)."\r\n";
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
		
		$file_content = mb_convert_encoding($stripTags?$this->file_content_notag:$this->file_content, $this->encoding, 'UTF-8');
		$res = file_put_contents($filename, $file_content);
		if(!$res)
			throw new Exception('Unable to save the file.');
	}
	
	/**
	 * Computes statistics regarding reading speed
	 */
	public function calcStats(){
		foreach($this->subs as $sub){
			$sub->prepForStats();
			$rs = $sub->getReadingSpeed();
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
	public function saveStats($filename){
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
		
		file_put_contents($filename, $tmp);
	}
}
