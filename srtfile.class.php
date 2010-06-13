<?php
class srtFileEntry{
	private $startTC;
	private $start;
	private $stopTC;
	private $stop;
	private $text;
	private $strippedText; // stats
	private $noTagText; // noTag
	private $durationMS;
	private $CPS;
	private $readingSpeed;
	
	/**
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
	
	/*
	 * Getters
	 */

	/**
	 * @description Returns the text of the entry
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
	public function setText($text){
		$this->text = $text;
	}
	public function getStartTC(){
		return $this->startTC;
	}
	public function getStopTC(){
		return $this->stopTC;
	}
	public function getStart(){
		return $this->start;
	}
	public function getStop(){
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
	public function getTimeCodeString(){ 
		return $this->startTC.' --> '.$this->stopTC; 
	}
		
	/*
	 * @description Generate stipped text in order to compute statistics
	 */
	private function genStrippedText(){
		$this->stripTags(true);
		
		$patterns = array("/(\r\n|\n|\r)/");
		$repl = array(" ");
		$this->strippedText = preg_replace($patterns, $repl, $this->noTagText);
	}
	
	/**
	 * @description Strip tags
	 * @param boolean $stripBasic If true, <i>, <b> and <u> tags will be stripped
	 * @param array $replacements
	 * @return boolean (true if tags actually stripped)
	 */
	public function stripTags($stripBasic = false, $replacements = array()){
		if($stripBasic)
			$this->noTagText = strip_tags($this->text);
		else
			$this->noTagText = $this->text;

		$patterns = array("/{[^}]+}/");
		$repl = array("");
		$this->noTagText = preg_replace($patterns, $repl, $this->noTagText);

		foreach($replacements as $key => $replacement){
			$this->noTagText = str_replace($key, $replacement, $this->noTagText);
		}
		
		return ($this->text != $this->noTagText);
	}
	
	/**
	 * @description Returns the *real* string length
	 * @return int
	 */
	public function strlen(){
		if($this->strippedText == '')
			$this->genStrippedText();
		return mb_strlen($this->strippedText, 'UTF-8');
	}
	
	/*
	 * @description Convert time code string into milliseconds
	 * @param string $tc timecode as string 
	 * @return int
	 */
	public static function tc2ms($tc){
		$tab = explode(':', $tc);
		$durMS = $tab[0]*60*60*1000 + $tab[1]*60*1000 + floatval(str_replace(',','.',$tab[2]))*1000;
		
		return $durMS;
	}
		
	/*
	 * @description Computes entry duration in milliseconds
	 */
	public function calcDuration(){
		$this->start = self::tc2ms($this->startTC);
		$this->stop = self::tc2ms($this->stopTC);
		
		$this->durationMS = $this->stop - $this->start;
	}
	
	/*
	 * @description Computes car. / second
	 */
	private function calcCPS(){
		$this->CPS = round($this->strlen() / ($this->durationMS / 1000), 1);
	}
	
	/*
	 * @description Computes Reading Speed (based on VisualSubSybc algorithm)
	 */
	private function calcRS(){
		if($this->durationMS < 500)
			$this->durationMS = 500;
			
		$this->readingSpeed = ($this->strlen() * 1000) / ($this->durationMS-500);
	}
}


class srtFile{
	private $filename;
	private $file_content;
	private $file_content_notag;
	private $encoding;
	private $subs = array();
	private $stats = array();
	
	/**
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
	
	/*
	 * @description Loads file content and detect file encoding if undefined
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
	
	/*
	 * @description Parses file content into srtFileEntry objects
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
	
	/*
	 * @param string $word
	 * @param boolean $case_sensitive
	 * @return array containing ids of entries
	 */
	public function searchWord($word, $case_sensitive = false){
		$list = array();
		$i = 0;
		$pattern = '#'.preg_quote($word,'#').'#';
		if($case_sensitive)
			$pattern .= 'i';
		foreach($this->subs as $sub){
			if(preg_match($pattern, $sub->getText()))
				$list[] = $i;
			$i++;
		}

		return (count($list) > 0)?$list:-1;
	}
	
	/*
	 * @description Imports subtitles from another srtFile object
	 * @param srtFile $_srtFile another srtFile object
	 */
	public function mergeSrtFile($_srtFile){
		if(!$_srtFile instanceof srtFile)
			throw new Exception('srtFile object parameter exepected.');
		$this->subs = array_merge($this->subs, $_srtFile->getSubs());
		
		$this->sortSubs();
	}
	
	/*
	 * @description Sorts srtFile entries
	 */
	public function sortSubs(){
		$tmp = array();
		foreach($this-subs as $sub)
			$tmp[srtFileEntry::tc2ms($sub->getStartTC())] = $sub;
			
		ksort($tmp);
		
		$this->subs = array();
		foreach($tmp as $sub)
			$this->subs[] = $sub;
	}
	
	/*
	 * @description Builds file content (file_content[_notag])
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
	
	/*
	 * @description Builds file content (file_content[_notag]) from entry $from to entry $to
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
	 * @description Saves the file
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
	
	/*
	 * @description Computes statistics concerning reading speed
	 */
	public function calcStats(){
		foreach($this->subs as $sub){
			$sub->prepForStats();
			$rs = $sub->getReadingSpeed();
			if($rs <= 5)
				$this->stats['tooSlow']++;
			elseif($rs <= 10)
				$this->stats['slowAcceptable']++;
			elseif($rs <= 13)
				$this->stats['aBitSlow']++;
			elseif($rs <= 15)
				$this->stats['goodSlow']++;
			elseif($rs <= 23)
				$this->stats['perfect']++;
			elseif($rs <= 27)
				$this->stats['goodFast']++;
			elseif($rs <= 31)
				$this->stats['aBitFast']++;
			elseif($rs <= 35)
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
	
	public function saveStats($filename){
		$tmp = '<ul style="padding-left:0;margin:1px;padding:2px;">';
		$tmp .= '<li style="background-color:#9999FF">TOO SLOW = '.$this->stats['tooSlow'].' ('.$this->getPercent($this->stats['tooSlow']).'%)</li>';
		$tmp .= '<li style="background-color:#99CCFF">Slow, acceptable = '.$this->stats['slowAcceptable'].' ('.$this->getPercent($this->stats['slowAcceptable']).'%)</li>';
		$tmp .= '<li style="background-color:#99FFFF">A bit slow = '.$this->stats['aBitSlow'].' ('.$this->getPercent($this->stats['aBitSlow']).'%)</li>';
		$tmp .= '<li style="background-color:#99FFCC">Good = '.$this->stats['goodSlow'].' ('.$this->getPercent($this->stats['goodSlow']).'%)</li>';
		$tmp .= '<li style="background-color:#99FF99">Perfect = '.$this->stats['perfect'].' ('.$this->getPercent($this->stats['perfect']).'%)</li>';
		$tmp .= '<li style="background-color:#CCFF99">Good = '.$this->stats['goodFast'].' ('.$this->getPercent($this->stats['goodFast']).'%)</li>';
		$tmp .= '<li style="background-color:#FFFF99">A bit fast = '.$this->stats['aBitFast'].' ('.$this->getPercent($this->stats['aBitFast']).'%)</li>';
		$tmp .= '<li style="background-color:#FFCC99">Fast, acceptable = '.$this->stats['fastAcceptable'].' ('.$this->getPercent($this->stats['fastAcceptable']).'%)</li>';
		$tmp .= '<li style="background-color:#FF9999">TOO FAST = '.$this->stats['tooFast'].' ('.$this->getPercent($this->stats['tooFast']).'%)</li>';
		$tmp .= '</ul>';
		
		file_put_contents($filename, $tmp);
	}
}
