<?php
/**
 * Manipulation with gettext .po and .mo files
 * @author Pavel Železný <info@pavelzelezny.cz>
 */
class Gettext {
	/** @var array **/
	private $defaultHeaders = array(
			'Project-Id-Version'        => '',
			'POT-Creation-Date'         => '', // default value is set in constructor
			'PO-Revision-Date'          => '', // default value is set in constructor
			'Language-Team'             => '',
			'MIME-Version'              => '1.0',
			'Content-Type'              => 'text/plain; charset=UTF-8',
			'Content-Transfer-Encoding' => '8bit',
			'Plural-Forms'              => 'nplurals=2; plural=(n==1)? 0 : 1;',
			'Last-Translator'           => 'JAO NetteTranslator',
		);

	/** @var array **/
	private $headers = array();

	/** @var array **/
	private $translations = array();

	/**
	 * Constructor
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Optional gettext dictionary file path
	 */
	public function __construct($path=NULL) {
		if($path!==NULL){
			$this->loadDictionary($path);
		}

		$this->setDefaultHeader('POT-Creation-Date',date("Y-m-d H:iO"));
		$this->setDefaultHeader('PO-Revision-Date',date("Y-m-d H:iO"));
		$this->setHeader('PO-Revision-Date',date("Y-m-d H:iO"));
	}

	/**
	 * Load gettext dictionary file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext dictionary file path
	 * @return \Gettext  provides a fluent interface
	 * @throws \InvalidArgumentException
	 */
	public function loadDictionary($path){
		switch (pathinfo($path,PATHINFO_EXTENSION)) {
			case 'mo':
				return $this->loadMoFile($path);
				break;
			case 'po':
				return $this->loadPoFile($path);
				break;
			default:
				throw new \InvalidArgumentException('Unsupported file type');
		}
	}

	/**
	 * Save gettext dictionary file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext dictionary file path
	 * @return \Gettext  provides a fluent interface
	 * @throws \InvalidArgumentException
	 */
	public function saveDictionary($path){
		switch (pathinfo($path,PATHINFO_EXTENSION)) {
			case 'mo':
				return $this->saveMoFile($path);
				break;
			case 'po':
				return $this->savePoFile($path);
				break;
			default:
				throw new \InvalidArgumentException('Unsupported file type');
		}
	}

	/**
	 * Load data from .po gettext file for future work
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext .po file path
	 * @return \Gettext  provides a fluent interface
	 * @todo Add detection of file exist, readable, .po format
	 */
	private function loadPoFile($path) {
		$this->parsePoFile($path);
		return $this;
	}

	/**
	 * Save data into .po gettext file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext .po file path
	 * @return \Gettext  provides a fluent interface
	 * @todo Add detection of directory and optional file exist and writeable
	 */
	private function savePoFile($path) {
		$this->generatePoFile($path);
		return $this;
	}

	/**
	 * Load data from .mo gettext file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext .mo file path
	 * @return \Gettext  provides a fluent interface
	 * @todo Add detection of file exist, readable, .mo format
	 */
	private function loadMoFile($path) {
		$this->parseMoFile($path);
		return $this;
	}

	/**
	 * Save data into .mo gettext file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext .mo file path
	 * @return \Gettext  provides a fluent interface
	 * @todo Add detection of directory and optional file exist and writeable
	 */
	private function saveMoFile($path) {
		$this->generateMoFile($path);
		return $this;
	}

	/**
	 * Parse content of gettext .mo file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext .mo file path
	 * @return \Gettext  provides a fluent interface
	 * @see http://www.gnu.org/savannah-checkouts/gnu/gettext/manual/html_node/PO-Files.html#PO-Files
	 * @todo Missing some advanced features (comments)
	 */
	private function parsePoFile($path){
		$fp = @fopen($path, 'r');

		while(feof($fp)!=TRUE){
			$buffer = fgets($fp);
			if(preg_match('/^msgid "(.*)"$/', $buffer,$matches)){
				$original[0] = $matches[1];
				if($original[0]!=''){
				    $this->translations[$original[0]]['original'][0]=$original[0];
				}
			} elseif(preg_match('/^msgid_plural "(.*)"$/', $buffer,$matches)){
				$original[1] = $matches[1];
				$this->translations[$original[0]]['original'][1]=$original[1];
			} elseif ((preg_match('/^msgstr "(.*)"$/', $buffer,$matches))&&($original[0]!='')) {
				$this->translations[$original[0]]['translation'][0] = str_replace('\n',"\n",$matches[1]);
			} elseif ((preg_match('/^msgstr\[([0-9]+)\] "(.*)"$/', $buffer,$matches))&&($original[0]!='')) {
				$lastIndex = $matches[1];
				$this->translations[$original[0]]['translation'][$lastIndex] = str_replace('\n',"\n",$matches[2]);
			} elseif (preg_match('/^"(.*)"$/', $buffer,$matches)){
				if($original[0]!=''){
					$this->translations[$original[0]]['translation'][isset($lastIndex)?$lastIndex:0] .= str_replace('\n',"\n",$matches[1]);
				} else {
					$delimiter = strpos($matches[1],': ');
					$this->headers[substr($matches[1],0,$delimiter)] = trim(substr(str_replace('\n',"\n",$matches[1]),$delimiter+2));
				}
			} elseif ( $buffer == ''){
				unset($original,$lastIndex);
			}
		}

		fclose($fp);
		return $this;
	}


	/**
	 * Parse binary content of gettext .mo file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext .mo file path
	 * @return \Gettext  provides a fluent interface
	 * @see http://www.gnu.org/savannah-checkouts/gnu/gettext/manual/html_node/MO-Files.html#MO-Files
	 * @todo Need review for some advanced features
	 */
	private function parseMoFile($path){
		$fp = @fopen($path,'rb');

		/* binary block read function */
		$endian = FALSE;
		$read = function($bytes,$seekBytes=NULL,$unpack=TRUE) use ($fp, $endian) {
			if($seekBytes!==NULL){
				fseek($fp,$seekBytes);
			}
			$data = fread($fp, $bytes);
			return $unpack === TRUE ? ($endian === FALSE ? unpack('V'.$bytes/4, $data) : unpack('N'.$bytes/4, $data)) : $data;
		};

		$endian = (mb_strtolower(substr(dechex(current($read(1))), -8),'UTF-8') == "950412de") ? FALSE : TRUE; /* Is file endian */
		$total = current($read(4,8)); /* Count of translated strings */
		$originalIndex = $read(8 * $total, current($read(4,12))); /* Array contain binary position of each original string */
		$translationIndex = $read(8 * $total, current($read(4,16))); /* Array contain binary position of each translated string */

		for ($i = 0; $i < $total; ++$i) {
			$original = ($originalIndex[$i * 2 + 1] != 0) ? explode(iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N',0x00)), $read($originalIndex[$i * 2 + 1],$originalIndex[$i * 2 + 2],FALSE)) : '';
			$translation = ($translationIndex[$i * 2 + 1] != 0) ? explode(iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N',0x00)), $translation = $read($translationIndex[$i * 2 + 1],$translationIndex[$i * 2 + 2],FALSE)) : "";

			if ($original != "") {
				$this->translations[is_array($original)? $original[0] : $original]['original'] = $original;
				$this->translations[is_array($original)? $original[0] : $original]['translation'] = $translation;
			} else {
				$this->headers = $this->parseMoMetadata(current($translation));
			}
		}
		fclose($fp);

		return $this;
	}

	/**
	 * Gettext .mo file metadata parser
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $input
	 * @return array;
	 */
	private function parseMoMetadata($input) {
		$input = preg_split('/[\n,]+/',trim($input));

		$output = array();
		foreach ($input as $metadata) {
			$tmp = preg_split("(: )", $metadata);
			$output[trim($tmp[0])] = count($tmp) > 2 ? ltrim(strstr($metadata,': '),': ') : $tmp[1];
		}

		return $output;
	}

	/**
	 * Save dictionary data into gettext .po file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext .po file path
	 * @return void
	 */
	private function generatePoFile($path){
		$eol = "\n";
		foreach($this->getHeaders() as $key => $val){
			$headers[] = $key.': '.$val.$eol;
		}

		$fp = fopen($path,'w');
		fwrite($fp,  $this->encodeGettxtPoBlock('',implode($headers)));
		foreach($this->getTranslations() as $data){
			fwrite($fp,  $this->encodeGettxtPoBlock($data['original'], $data['translation'],$eol));
		}
		fclose($fp);
	}


	/**
	 * Encode one translation to .po gettext file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param array $original Original untranslated string
	 * @param array $translations Translation string
	 * @param atring $eol End of line symbol
	 * @return string
	 */
	private function encodeGettxtPoBlock($original,$translations,$eol="\n"){
		$original = (array) $original;
		$translations = (array) $translations;

		$translationsCount = count($translations);

		$block  = 'msgid "'.current($original).'"'.$eol;

		if(count($original)>1){
			$block .= 'msgid_plural "'.end($original).'"'.$eol;
		}

		foreach($translations as $key => $translation){
			if(strpos(trim($translation),"\n")>0){
				$translation = '"'.$eol.'"'.str_replace("\n",'\n"'.$eol.'"',trim($translation)).'\n';
			}
			$block .= 'msgstr'.($translationsCount>1? '['.$key.']' : '').' "'.$translation.'"'.$eol;
		}
		$block .= $eol;
		return $block;
	}

	/**
	 * Save dictionary data into gettext .mo file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext .mo file path
	 * @return void
	 * @todo currently doing shit
	 */
	private function generateMoFile($path){
		$fp = fopen($path,'w');
		fclose($fp);
	}

	/**
	 * Return dictionary headers
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return array
	 */
	public function getHeaders(){
		return array_merge($this->defaultHeaders,$this->headers);
	}

	/**
	 * Return dictionary translations
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return type
	 */
	public function getTranslations(){
		return $this->translations;
	}

	/**
	 * Set default dictionary header
	 * @author Pavel Železný <info™pavelzelezny.cz>
	 * @param string $index
	 * @param string $value
	 */
	public function setDefaultHeader($index,$value){
		$this->defaultHeaders[$index] = $value;
	}

	/**
	 * Set dictionary header
	 * @author Pavel Železný <info™pavelzelezny.cz>
	 * @param string $index
	 * @param string $value
	 */
	public function setHeader($index,$value){
		$this->headers[$index] = $value;
	}

	/**
	 * Set translation
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string|array $original
	 * @param string|array $translation
	 * @return void
	 * @throw \BadMethodCallException
	 */
	public function setTranslation($original,$translation){
		if((is_array($translation)===TRUE)&&((count($original) < 2)||(is_array($original)===FALSE))){
		    throw new \BadMethodCallException('Translation with plurals need to have plural definition.');
		} elseif ($original==''){
			throw new \BadMethodCallException('Untranslated string cannot be empty.');
		}

		$this->translations[is_array($original) ? $original[0] : $original]['original'] = is_array($original) ? array_values($original) : array('0' => $original);
		$this->translations[is_array($original) ? $original[0] : $original]['translation'] = is_array($translation) ? array_values($translation) : array('0' => $translation);
	}
}