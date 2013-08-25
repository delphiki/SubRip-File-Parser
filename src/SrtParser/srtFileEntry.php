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
		$this->start = self::tc2ms($_start);
		$this->stop = self::tc2ms($_stop);
		
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
	public function getTimeCodeString($_WebVTT = false){ 
		$res = $this->startTC.' --> '.$this->stopTC;
		
		if($_WebVTT) $res = str_replace(',', '.', $res);

		return $res;
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
		$repl = "  ";
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

		if(count($replacements) > 0){
			$this->noTagText = str_replace(array_keys($replacements), array_values($replacements), $this->noTagText);
			$this->noTagText = iconv('UTF-8', 'UTF-8//IGNORE', $this->noTagText);
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
		if($this->durationMS <= 500)
			$this->durationMS = 501;

		$this->readingSpeed = ($this->strlen() * 1000) / ($this->durationMS-500);
	}
}