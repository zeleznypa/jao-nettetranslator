<?php

/**
 * Manipulation with gettext .po and .mo files
 * @author Pavel Železný <info@pavelzelezny.cz>
 */
class GettextDictionary {

	/** @var array */
	private $defaultHeaders = array(
		'Project-Id-Version' => '',
		'POT-Creation-Date' => '', // default value is set in constructor
		'PO-Revision-Date' => '', // default value is set in constructor
		'Language-Team' => '',
		'MIME-Version' => '1.0',
		'Content-Type' => 'text/plain; charset=UTF-8',
		'Content-Transfer-Encoding' => '8bit',
		'Plural-Forms' => 'nplurals=2; plural=(n==1)? 0 : 1;',
		'Last-Translator' => 'JAO NetteTranslator',
	);

	/** @var array */
	private $files = array();

	/** @var array */
	private $headers = array();

	/** @var array */
	private $translations = array();

	/** @var bool */
	private $colaborativeMode = FALSE;

	/**
	 * Constructor
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Optional gettext dictionary file path
	 * @return void
	 * @todo Optionaly dictionary identifier can be set
	 */
	public function __construct($path = NULL) {
		if ($path !== NULL) {
			$this->loadDictionary($path);
		}

		$this->setDefaultHeader('POT-Creation-Date', date("Y-m-d H:iO"));
		$this->setDefaultHeader('PO-Revision-Date', date("Y-m-d H:iO"));
	}

	/**
	 * Load gettext dictionary file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext dictionary file path
	 * @return \Gettext  provides a fluent interface
	 * @throws \InvalidArgumentException \BadMethodCallException
	 * @todo Add info about loaded file into $this->files property
	 * @todo Change logic of force loading more than one file
	 * @todo Optionaly dictionary identifier can be set
	 */
	public function loadDictionary($path, $identifier = NULL) {
		if ($this->getTranslationsCount() == 0) {
			if ((file_exists($path)) && (is_readable($path))) {
				try {
					switch (pathinfo($path, PATHINFO_EXTENSION)) {
						case 'mo':
							if (filesize($path) > 10) {
								$this->setDictionaryFileId($path, $identifier);
								return $this->parseMoFile(basename($path));
							} else {
								throw new \InvalidArgumentException('Dictionary file is not .mo compatible');
							}
							break;
						case 'po':
							$this->setDictionaryFileId($path, $identifier);
							return $this->parsePoFile(basename($path));
							break;
						default:
							throw new \InvalidArgumentException('Unsupported file type');
					}
					$this->setDictionaryFileId($path, $identifier);
				} catch (\BadMethodCallException $exception) {
					throw new \BadMethodCallException('Dictionary file structure is not correct.', 0, $exception);
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
	 * @todo Optionaly dictionary identifier can be set otherwise new data will be saved
	 */
	public function saveDictionary($path) {
		// Force datetime change of newly generated dictionary
		$this->setHeader('PO-Revision-Date', date("Y-m-d H:iO"));

		if (((file_exists($path) === TRUE) && (is_writable($path))) || ((file_exists($path) === FALSE) && (is_writable(dirname($path))))) {
			switch (pathinfo($path, PATHINFO_EXTENSION)) {
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
	 * @throws \BadMethodCallException
	 * @todo index of file is automaticaly given from $this->files property
	 */
	private function parsePoFile($path) {
		$fp = @fopen($path, 'r');

		while (feof($fp) != TRUE) {
			$buffer = fgets($fp);
			if (preg_match('/^#([.:, ]|(\| msgctxt)|(\| msgid)) (.*)$/', $buffer, $matches)) {
				switch ($matches[1]) {
					case ' ':
						$comments['comment'] = $matches[4];
						break;
					case '.':
						$comments['extracted-comment'] = $matches[4];
						break;
					case ':':
						$comments['reference'] = $matches[4];
						break;
					case ',':
						$comments['flag'] = $matches[4];
						break;
					case '| msgctxt':
						$comments['previous-context'] = $matches[4];
						break;
					case '| msgid':
						$comments['previous-untranslated-string'] = $matches[4];
						break;
				}
			} elseif (preg_match('/^msgctx "(.*)"$/', $buffer, $matches)) {
				$context = $matches[1];
			} elseif (preg_match('/^msgid "(.*)"$/', $buffer, $matches)) {
				$original = $matches[1];
				if ($matches[1] != '') {
					$translations = & $this->addOriginal($original, isset($context) ? $context : '');
					if (isset($comments)) {
						$translations->setComments($comments);
					}
				}
			} elseif (preg_match('/^msgid_plural "(.*)"$/', $buffer, $matches)) {
				$translations->setPlural($matches[1]);
			} elseif ((preg_match('/^msgstr "(.*)"$/', $buffer, $matches)) && ($original != '')) {
				$translations->setTranslation(str_replace('\n', "\n", $matches[1]), 0);
			} elseif ((preg_match('/^msgstr\[([0-9]+)\] "(.*)"$/', $buffer, $matches)) && ($original[0] != '')) {
				$lastIndex = (int) $matches[1];
				$translations->setTranslation(str_replace('\n', "\n", $matches[2]), $lastIndex);
			} elseif (preg_match('/^"(.*)"$/', $buffer, $matches)) {
				if ((isset($original)) && ($original != '')) {
					$translations->setTranslation($translations->getTranslation(isset($lastIndex) ? $lastIndex : 0) . str_replace('\n', "\n", $matches[1]), isset($lastIndex) ? $lastIndex : 0);
				} else {
					$delimiter = strpos($matches[1], ': ');
					$this->headers[substr($matches[1], 0, $delimiter)] = trim(substr(str_replace('\n', "\n", $matches[1]), $delimiter + 2));
				}
			} elseif (trim($buffer) == '') {
				unset($comments, $original, $lastIndex, $comment, $context, $translations);
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
	 * @throws \BadMethodCallException
	 * @todo index of file is automaticaly givven from $this->files property
	 */
	private function parseMoFile($path) {
		$fp = @fopen($path, 'rb');

		/* binary block read function */
		$endian = FALSE;
		$read = function($bytes, $seekBytes = NULL, $unpack = TRUE) use ($fp, $endian) {
					if ($seekBytes !== NULL) {
						fseek($fp, $seekBytes);
					}
					$data = fread($fp, $bytes);
					return $unpack === TRUE ? ($endian === FALSE ? unpack('V' . $bytes / 4, $data) : unpack('N' . $bytes / 4, $data)) : $data;
				};

		$endian = (mb_strtolower(substr(dechex(current($read(1))), -8), 'UTF-8') == "950412de") ? FALSE : TRUE; /* Is file endian */
		$total = current($read(4, 8)); /* Count of translated strings */
		$originalIndex = $read(8 * $total, current($read(4, 12))); /* Array contain binary position of each original string */
		$translationIndex = $read(8 * $total, current($read(4, 16))); /* Array contain binary position of each translated string */

		for ($i = 0; $i < $total; ++$i) {
			$temp = ($originalIndex[$i * 2 + 1] != 0) ? array_reverse(explode(iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N', 0x04)), $read($originalIndex[$i * 2 + 1], $originalIndex[$i * 2 + 2], FALSE))) : array('0' => '');

			$context = isset($temp[1]) ? $temp[1] : '';
			$original = explode(iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N', 0x00)), $temp[0]);
			$translation = ($translationIndex[$i * 2 + 1] != 0) ? explode(iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N', 0x00)), $translation = $read($translationIndex[$i * 2 + 1], $translationIndex[$i * 2 + 2], FALSE)) : NULL;

			if ($original[0] != "") {
				$this->addOriginal($original, $context, $translation);
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
		$input = preg_split('/[\n,]+/', trim($input));

		$output = array();
		foreach ($input as $metadata) {
			$tmp = preg_split("(: )", $metadata);
			$output[trim($tmp[0])] = count($tmp) > 2 ? ltrim(strstr($metadata, ': '), ': ') : $tmp[1];
		}

		return $output;
	}

	/**
	 * Save dictionary data into gettext .po file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext .po file path
	 * @return void
	 * @todo Optionaly dictionary identifier can be set otherwise new data will be saved
	 */
	private function generatePoFile($path) {
		$fp = fopen($path, 'w');
		fwrite($fp, $this->encodeGettxtPoBlock('', implode($this->generateHeaders())));
		foreach ($this->getTranslations() as $data) {
			foreach ($data as $context => $object) {
				fwrite($fp, $this->encodeGettxtPoBlock($object->getOriginal(), $context, $object->getTranslations(), $object->getComments()));
			}
		}
		fclose($fp);
	}

	/**
	 * Encode one translation to .po gettext file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param array $original Original untranslated string
	 * @param string $context Context of translation
	 * @param array $translations Translation strings
	 * @param array $comments Comments
	 * @return string
	 */
	private function encodeGettxtPoBlock($original, $context = '', $translations = NULL, $comments = array()) {
		$original = (array) $original;
		$translations = (array) $translations;
		$translationsCount = count($translations);

		$block = '';
		foreach ($comments as $type => $comment) {
			switch ($type) {
				case 'comment':
					$block .= '#  ' . $comment . "\n";
					break;
				case 'extracted-comment':
					$block .= '#. ' . $comment . "\n";
					break;
				case 'reference':
					$block .= '#: ' . $comment . "\n";
					break;
				case 'flag':
					$block .= '#, ' . $comment . "\n";
					break;
				case 'previous-context':
					$block .= '#| msgctxt ' . $comment . "\n";
					break;
				case 'previous-untranslated-string':
					$block .= '#| msgid ' . $comment . "\n";
					break;
			}
		}

		$block .= $context != '' ? 'msgctx "' . print_r($context,true) . '"' . "\n" : '';
		$block .= 'msgid "' . current($original) . '"' . "\n";

		if (count($original) > 1) {
			$block .= 'msgid_plural "' . end($original) . '"' . "\n";
		}

		foreach ($translations as $key => $translation) {
			if (strpos(trim($translation), "\n") > 0) {
				$translation = '"' . "\n" . '"' . str_replace("\n", '\n"' . "\n" . '"', trim($translation)) . '\n';
			}
			$block .= 'msgstr' . ($translationsCount > 1 ? '[' . $key . ']' : '') . ' "' . $translation . '"' . "\n";
		}
		$block .= "\n";
		return $block;
	}

	/**
	 * Save dictionary data into gettext .mo file
	 * @author Don't know who is first autor but my source is https://github.com/marten-cz/NetteTranslator
	 * @param string $path Gettext .mo file path
	 * @return void
	 * @todo Optionaly dictionary identifier can be set otherwise new data will be saved
	 */
	private function generateMoFile($path) {
		$metadata = implode($this->generateHeaders());
		$items = $this->getTranslationsCount() + 1;
		$strings = $metadata . iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N', 0x00));
		$idsOffsets = array(0, 28 + $items * 16);
		$stringsOffsets = array(array(0, strlen($metadata)));
		$ids = '';
		foreach ($this->getTranslations() as $translation) {
			foreach ($translation as $context => $object) {
				$original = $object->getOriginal();
				$id = $context != '' ? $context . iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N', 0x04)) . $original[0] : $original[0];
				if (count($original) > 1) {
					$id .= iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N', 0x00)) . end($original);
				}

				$string = implode(iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N', 0x00)), $object->getTranslations());
				$idsOffsets[] = strlen($id);
				$idsOffsets[] = strlen($ids) + 28 + $items * 16;
				$stringsOffsets[] = array(strlen($strings), strlen($string));
				$ids .= $id . iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N', 0x00));
				$strings .= $string . iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N', 0x00));
			}
		}

		$valuesOffsets = array();
		foreach ($stringsOffsets as $offset) {
			list ($all, $one) = $offset;
			$valuesOffsets[] = $one;
			$valuesOffsets[] = $all + strlen($ids) + 28 + $items * 16;
		}
		$offsets = array_merge($idsOffsets, $valuesOffsets);

		$mo = pack('Iiiiiii', 0x950412de, 0, $items, 28, 28 + $items * 8, 0, 28 + $items * 16);
		foreach ($offsets as $offset)
			$mo .= pack('i', $offset);

		file_put_contents($path, $mo . $ids . $strings);
	}

	/**
	 * Return headers to store in file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return array
	 */
	private function generateHeaders() {
		foreach ($this->getHeaders() as $key => $val) {
			$headers[] = $key . ': ' . $val . "\n";
		}
		return $headers;
	}

	/**
	 * Return dictionary headers
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return array
	 * @todo Optionaly dictionary identifier can be set otherwise header for new data will be returned
	 */
	public function getHeaders() {
		return array_merge($this->defaultHeaders, $this->headers);
	}

	/**
	 * Return dictionary translation objects
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return array
	 * @todo Optionaly dictionary identifier can be set otherwise all translations will be returned
	 */
	public function getTranslations() {
		return $this->translations;
	}

	/**
	 * How many translation objects are in dictionary
	 * Each context variant is counted as one
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return int
	 * @todo Optionaly dictionary identifier can be set otherwise all translations count will be returned
	 */
	private function getTranslationsCount() {
		$count = 0;
		foreach ($this->getTranslations() as $translation) {
			$count += count($translation);
		}
		return $count;
	}

	/**
	 * Set default dictionary headers
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param array $headers
	 * @return void
	 * @todo Optionaly dictionary identifier can be set otherwise new dictionary headers will be set
	 * @todo Investigate posibility of fluent interface here
	 */
	public function setDefaultHeaders($headers) {
		foreach ($headers as $index => $value) {
			$this->setdefaultHeaders($index, $value);
		}
	}

	/**
	 * Set default dictionary header
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $index
	 * @param string $value
	 * @return void
	 * @todo Optionaly dictionary identifier can be set otherwise new dictionary headers will be set
	 * @todo Investigate posibility of fluent interface here
	 */
	public function setDefaultHeader($index, $value) {
		$this->defaultHeaders[$index] = $value;
	}

	/**
	 * Set dictionary headers
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param array $headers
	 * @return void
	 * @todo Optionaly dictionary identifier can be set otherwise new dictionary headers will be set
	 * @todo Investigate posibility of fluent interface here
	 */
	public function setHeaders($headers) {
		foreach ($headers as $index => $value) {
			$this->setHeader($index, $value);
		}
	}

	/**
	 * Set dictionary header
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $index
	 * @param string $value
	 * @return void
	 * @todo Optionaly dictionary identifier can be set otherwise new dictionary header will be set
	 * @todo Investigate posibility of fluent interface here
	 */
	public function setHeader($index, $value) {
		$this->headers[$index] = $value;
	}

	/**
	 * Add translation object to dictionary
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string|array $original
	 * @param string $context
	 * @param string|array $translation
	 * @return \GettextTranslation  provides a fluent interface
	 * @throws \BadMethodCallException
	 * @todo Optionaly dictionary identifier can be set otherwise new dictionary original will be set
	 */
	public function addOriginal($original, $context = '', $translation = NULL) {
		if (((is_array($translation) === TRUE) && (count($translation) > 1)) && ((count($original) < 2) || (is_array($original) === FALSE))) {
			throw new \BadMethodCallException('Translation with plurals need to have plural definition.');
		} elseif (trim(is_array($original) ? $original[0] : $original) == '') {
			throw new \BadMethodCallException('Untranslated string cannot be empty.');
		} elseif (is_string($context) === FALSE) {
			throw new \BadMethodCallException('Context have to be string.');
		} elseif (isset($this->translations[is_array($original) ? $original[0] : $original][$context])) {
			throw new \BadMethodCallException('Same defined original is exist.');
		}

		return $this->translations[is_array($original) ? $original[0] : $original][$context] = new GettextTranslation($original, $context, $translation);
	}

	/**
	 * Get translation object from dictionary
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string|array $original
	 * @param string $context
	 * @return \GettextTranslation  provides a fluent interface
	 * @throws \BadMethodCallException
	 * @todo Optionaly dictionary identifier can be set otherwise get original from all dictionaries
	 */
	public function getOriginal($original, $context = '') {
		if (!isset($this->translations[is_array($original) ? $original[0] : $original][$context])) {
			throw new \BadMethodCallException('Required original is not exist.');
		}

		return $this->translations[is_array($original) ? $original[0] : $original][$context];
	}

	/**
	 * Alias to getOriginal Method
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string|array $original
	 * @param string $context
	 * @return \GettextTranslation  provides a fluent interface
	 * @throws \BadMethodCallException
	 * @todo Optionaly dictionary identifier can be set otherwise get original from all dictionaries
	 */
	public function getTranslation($original, $context = '') {
		return $this->getOriginal($original, $context);
	}

	/**
	 * Remove unwanted translation
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $original
	 * @param string $context if NULL, remove whole translation otherwise remove defined context
	 * @return void
	 * @todo Optionaly dictionary identifier can be set otherwise remove original from new dictionary
	 */
	public function removeTranslation($original, $context = NULL) {
		if (($context != NULL) && (isset($this->translations[$original][$context]))) {
			unset($this->translations[$original][$context]);
		} elseif (($context === NULL) && (isset($this->translations[$original]))) {
			unset($this->translations[$original]);
		}
	}

	/**
	 * Return dictionary file internal id if exist, FALSE otherwise
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $fileDefinition Can be used fullpath, filename or defined identifier
	 * @return int | FALSE
	 * @throws \InvalidArgumentException
	 */
	private function getDictionaryFileId($fileDefinition) {
		$output = FALSE;

		foreach ($this->files as $internalId => $file) {
			if ($file['identifier'] == $fileDefinition) {
				return $internalId;
			} elseif ((($file['mobileObject'] === TRUE) && (($file['filename'] . '.mo' == $fileDefinition) || ($file['path'] . DIRECTORY_SEPARATOR . $file['filename'] . '.mo' == $fileDefinition))) || (($file['portableObject'] === TRUE) && (($file['filename'] . '.po' == $fileDefinition) || ($file['path'] . DIRECTORY_SEPARATOR . $file['filename'] . '.po' == $fileDefinition)))) {
				if ($output !== FALSE) {
					$output = $internalId;
				} else {
					throw new \InvalidArgumentException('More than one file with same definition was found.');
				}
			}
		}

		return $output;
	}

	/**
	 * Set info about loaded file into register
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path file path of dictionary
	 * @param string $identifier optionaly specified identifier
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	private function setDictionaryFileId($path, $identifier = NULL) {
		$path = realpath($path);
		$type = pathinfo($path, PATHINFO_EXTENSION);
		$internalId = $this->getDictionaryFileId($identifier !== NULL ? $identifier : $path);

		if ($internalId === FALSE) {
			$this->files[] = array(
				'identifier' => $identifier !== NULL ? $identifier : $this->generateDictionaryFileIdentifier($path),
				'path' => dirname($path),
				'filename' => basename($path, $type),
				'portableObject' => ($type == 'po') ? TRUE : FALSE,
				'mobileObject' => ($type == 'mo') ? TRUE : FALSE,
			);
		} elseif ($type == 'mo') {
			$this->files[$internalId]['mobileObject'] = TRUE;
		} elseif ($type == 'po') {
			$this->files[$internalId]['portableObject'] = TRUE;
		}
	}

	/**
	 * Generate dictionary file identifier automaticaly
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	private function generateDictionaryFileIdentifier($path) {
		$type = pathinfo($path, PATHINFO_EXTENSION);
		$identifier = strtolower(basename($path, $type));
		$possibleId = $this->getDictionaryFileId($identifier);
		if (($possibleId === FALSE) || (($type == 'mo') && ($this->files[$possibleId]['mobileObject'] === FALSE) && ($this->colaborativeMode === TRUE)) || (($type == 'po') && ($this->files[$possibleId]['mobileObject'] === FALSE) && ($this->colaborativeMode === TRUE))) {
			return $identifier;
		} else {
			throw new \InvalidArgumentException('Unable to generate unique file identifier.');
		}
	}

}