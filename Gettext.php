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
		if(empty($this->translations)){
			if((file_exists($path))&&(is_readable($path))){
				switch (pathinfo($path,PATHINFO_EXTENSION)) {
					case 'mo':
						if(filesize($path)>10){

							return $this->parseMoFile(basename($path));
						} else {
							throw new \InvalidArgumentException('Dictionary file is not .mo compatible');
						}
						break;
					case 'po':
						return $this->parsePoFile(basename($path));
						break;
					default:
						throw new \InvalidArgumentException('Unsupported file type');
				}
			} else {
				throw new \InvalidArgumentException('Dictionary file is not exist or is not readable');
			}
		} else {
			throw new \InvalidArgumentException('Dictionary is not empty');
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
		if(((file_exists($path)===TRUE)&&(is_writable($path)))||((file_exists($path)===FALSE)&&(is_writable(dirname($path))))){
			switch (pathinfo($path,PATHINFO_EXTENSION)) {
				case 'mo':
					return $this->generateMoFile($path);
					break;
				case 'po':
					return $this->generatePoFile($path);
					break;
				default:
					throw new \InvalidArgumentException('Unsupported file type');
			}
		} else {
			throw new \InvalidArgumentException('Destination is not writeable');
		}
	}

	/**
	 * Parse content of gettext .po file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext .po file path
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
		$fp = fopen($path,'w');
		fwrite($fp,  $this->encodeGettxtPoBlock('',implode($this->generateHeaders())));
		foreach($this->getTranslations() as $data){
			fwrite($fp,$this->encodeGettxtPoBlock($data['original'],$data['translation']));
		}
		fclose($fp);
	}


	/**
	 * Encode one translation to .po gettext file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param array $original Original untranslated string
	 * @param array $translations Translation string
	 * @return string
	 */
	private function encodeGettxtPoBlock($original,$translations){
		$original = (array) $original;
		$translations = (array) $translations;

		$translationsCount = count($translations);
		$block  = 'msgid "'.current($original).'"'."\n";

		if(count($original)>1){
			$block .= 'msgid_plural "'.end($original).'"'."\n";
		}

		foreach($translations as $key => $translation){
			if(strpos(trim($translation),"\n")>0){
				$translation = '"'."\n".'"'.str_replace("\n",'\n"'."\n".'"',trim($translation)).'\n';
			}
			$block .= 'msgstr'.($translationsCount>1? '['.$key.']' : '').' "'.$translation.'"'."\n";
		}
		$block .= "\n";
		return $block;
	}

	/**
	 * Save dictionary data into gettext .mo file
	 * @author Don't know who is first autor but my source is https://github.com/marten-cz/NetteTranslator
	 * @param string $path Gettext .mo file path
	 * @return void
	 */
	private function generateMoFile($path){
		$dictionary = $this->getTranslations();
		$metadata = implode($this->generateHeaders());
		$items = count($dictionary) + 1;
		$strings = $metadata.iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N',0x00));
		$idsOffsets = array(0, 28 + $items * 16);
		$stringsOffsets = array(array(0, strlen($metadata)));
		$ids='';
		foreach ($dictionary as $value) {
			$id = $value['original'][0];
			if (is_array($value['original']) && count($value['original']) > 1)
				$id .= iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N',0x00)).end($value['original']);

			$string = implode(iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N',0x00)), $value['translation']);
			$idsOffsets[] = strlen($id);
			$idsOffsets[] = strlen($ids) + 28 + $items * 16;
			$stringsOffsets[] = array(strlen($strings), strlen($string));
			$ids .= $id.iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N',0x00));
			$strings .= $string.iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N',0x00));
		}

		$valuesOffsets = array();
		foreach ($stringsOffsets as $offset) {
			list ($all, $one) = $offset;
			$valuesOffsets[] = $one;
			$valuesOffsets[] = $all + strlen($ids) + 28 + $items * 16;
		}
		$offsets= array_merge($idsOffsets, $valuesOffsets);

		$mo = pack('Iiiiiii', 0x950412de, 0, $items, 28, 28 + $items * 8, 0, 28 + $items * 16);
		foreach ($offsets as $offset)
			$mo .= pack('i', $offset);

		file_put_contents($path, $mo.$ids.$strings);
	}


	/**
	 * Return headers to store in file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return array
	 */
	private function generateHeaders(){
		foreach($this->getHeaders() as $key => $val){
			$headers[] = $key.': '.$val."\n";
		}
		return $headers;
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

	/**
	 * Remove unwanted translation
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $original
	 * @return void
	 */
	public function removeTranslation($original){
		if(isset($this->translations[$original])){
			unset($this->translations[$original]);
		}

	}
}