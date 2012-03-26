<?php

/**
 * Manipulation with gettext .po and .mo files
 * @author Pavel Železný <info@pavelzelezny.cz>
 */
class GettextDictionary {

	/** @var array */
	private $defaultHeaders = array();

	/** @var array */
	public $files = array();

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
	 * @param string $identifier Optional dictionary name
	 * @return void
	 */
	public function __construct($path = NULL, $identifier = NULL) {
		if ($path !== NULL) {
			$this->loadDictionary($path, $identifier);
		} elseif (($path === NULL) && ($identifier !== NULL)) {
			throw new InvalidArgumentException('New empty dictionary can not have name.');
		}

		$this->setDefaultHeaders(array(
			'Project-Id-Version' => '',
			'POT-Creation-Date' => date("Y-m-d H:iO"),
			'PO-Revision-Date' => date("Y-m-d H:iO"),
			'Language-Team' => '',
			'MIME-Version' => '1.0',
			'Content-Type' => 'text/plain; charset=UTF-8',
			'Content-Transfer-Encoding' => '8bit',
			'Plural-Forms' => 'nplurals=2; plural=(n==1)? 0 : 1;',
			'Last-Translator' => 'JAO NetteTranslator <info@pavelzelezny.cz>',
			'X-Poedit-Language' => '',
			'X-Poedit-Country' => '',
			'X-Poedit-SourceCharset' => 'utf-8',
			'X-Poedit-KeywordsList' => '',
			'X-Poedit-Basepath' => '.',
			'X-Poedit-SearchPath-0' => '.',
			'X-Poedit-SearchPath-1' => '..',
		));
	}

	/**
	 * Load gettext dictionary file
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $path Gettext dictionary file path
	 * @param string $identifier Optional dictionary name
	 * @return \Gettext  provides a fluent interface
	 * @throws \InvalidArgumentException \InvalidArgumentException
	 * @todo Change logic of force loading more than one file
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
				} catch (\InvalidArgumentException $exception) {
					throw new \InvalidArgumentException('Dictionary file structure is not correct.', 0, $exception);
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
	 * @param string $identifier Optional dictionary name
	 * @return \Gettext  provides a fluent interface
	 * @throws \InvalidArgumentException
	 */
	public function saveDictionary($path, $identifier = NULL) {
		// Force datetime change of newly generated dictionary
		$this->setHeader('PO-Revision-Date', date("Y-m-d H:iO"), $identifier);

		if (((file_exists($path) === TRUE) && (is_writable($path))) || ((file_exists($path) === FALSE) && (is_writable(dirname($path))))) {
			switch (pathinfo($path, PATHINFO_EXTENSION)) {
				case 'mo':
					return $this->generateMoFile($path, $identifier);
					break;
				case 'po':
					return $this->generatePoFile($path, $identifier);
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
	 * @throws \InvalidArgumentException
	 */
	private function parsePoFile($path) {
		$path = realpath($path);
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
			} elseif (preg_match('/^msgctxt "(.*)"$/', $buffer, $matches)) {
				$context = $matches[1];
			} elseif (preg_match('/^msgid "(.*)"$/', $buffer, $matches)) {
				$original = $matches[1];
				if ($matches[1] != '') {
					$translations = & $this->addOriginal($original, isset($context) ? $context : '', $path);
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
					$this->setHeader(substr($matches[1], 0, $delimiter), trim(substr(str_replace('\n', "\n", $matches[1]), $delimiter + 2)), $path);
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
	 * @throws \InvalidArgumentException
	 */
	private function parseMoFile($path) {
		$path = realpath($path);
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
				$this->addOriginal($original, $context, $path, $translation);
			} else {
				$this->setHeaders($this->parseMoMetadata(current($translation)), $path);
			}
		}
		fclose($fp);

		return $this;
	}

	/**
	 * Gettext .mo file metadata parser
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $input
	 * @return array
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
	 * @param string $identifier Optional dictionary name
	 * @return void
	 * @todo Optimize generating by use $this->Translations($identifier);
	 */
	private function generatePoFile($path, $identifier = NULL) {
		$fp = fopen($path, 'w');
		fwrite($fp, $this->encodeGettxtPoBlock('', '', implode($this->generateHeaders($identifier))));
		foreach ($this->getTranslations() as $translation) {
			foreach ($translation as $context => $filesId) {
				foreach ($filesId as $fileId => $object) {
					if ($fileId == $this->getDictionaryFileId($identifier)) {
						fwrite($fp, $this->encodeGettxtPoBlock($object->getOriginal(), $context, $object->getTranslations(), $object->getComments()));
					}
				}
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

		$block .= $context != '' ? 'msgctxt "' . $context . '"' . "\n" : '';
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
	 * @param string $identifier Optional dictionary name
	 * @return void
	 * @todo Optimize generating by use $this->Translations($identifier);
	 */
	private function generateMoFile($path, $identifier = NULL) {
		$metadata = implode($this->generateHeaders($identifier));
		$identifier = $this->getDictionaryFileId($identifier);
		$items = $this->getTranslationsCount($identifier) + 1;
		$strings = $metadata . iconv('UTF-32BE', 'UTF-8' . '//IGNORE', pack('N', 0x00));
		$idsOffsets = array(0, 28 + $items * 16);
		$stringsOffsets = array(array(0, strlen($metadata)));
		$ids = '';

		foreach ($this->getTranslations() as $translation) {
			foreach ($translation as $context => $filesId) {
				foreach ($filesId as $fileId => $object) {
					if ($fileId == $identifier) {
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
	 * @param string $identifier Optional dictionary name
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	private function generateHeaders($identifier = NULL) {
		$headers = array();
		foreach ($this->getHeaders($this->getDictionaryFileId($identifier)) as $key => $val) {
			$headers[] = $key . ': ' . $val . "\n";
		}
		return $headers;
	}

	/**
	 * Return dictionary headers
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $identifier Optional dictionary name
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	public function getHeaders($identifier = NULL) {
		if (($identifier !== NULL) && (($this->getDictionaryFileId($identifier) === FALSE) || (isset($this->headers[$this->getDictionaryFileId($identifier)]) === FALSE))) {
			throw new InvalidArgumentException('Required dictionary identifier is not exist.');
		}

		$output = array();
		foreach (array_keys($this->headers) as $fileId) {
			$output[$fileId] = array_merge($this->defaultHeaders, $this->headers[$fileId]);
		}

		return $identifier === NULL ? $output : $output[$this->getDictionaryFileId($identifier)];
	}

	/**
	 * Return dictionary translation objects
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $identifier Optional dictionary name
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	public function getTranslations($identifier = NULL) {
		if (($identifier !== NULL) && ($this->getDictionaryFileId($identifier) === FALSE)) {
			throw new InvalidArgumentException('Required dictionary identifier is not exist.');
		}

		if ($identifier === NULL) {
			return $this->translations;
		} else {
			$output = array();
			foreach ($this->translations as $original => $contexts) {
				foreach ($contexts as $context => $fileIds) {
					foreach ($fileIds as $fileId => $translation) {
						if ($fileId == $this->getDictionaryFileId($identifier)) {
							$output[$original][$context] = $translation;
						}
					}
				}
			}
			return $output;
		}
	}

	/**
	 * How many translation objects are in dictionary
	 * Each context variant is counted as one
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $identifier Optional dictionary name
	 * @return int
	 * @throws \InvalidArgumentException
	 */
	private function getTranslationsCount($identifier = NULL) {
		if (($identifier !== NULL) && ($this->getDictionaryFileId($identifier) === FALSE)) {
			throw new InvalidArgumentException('Required dictionary identifier is not exist.');
		}

		$count = 0;
		foreach ($this->getTranslations() as $translation) {
			foreach ($translation as $context) {
				foreach (array_keys($context) as $fileId) {
					if (($identifier === NULL) || ($fileId == $this->getDictionaryFileId($identifier))) {
						$count = $count + 1;
					}
				}
			}
		}
		return $count;
	}

	/**
	 * Set default dictionary headers
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param array $headers
	 * @return \GettextTranslation  provides a fluent interface
	 */
	public function setDefaultHeaders($headers) {
		foreach ($headers as $index => $value) {
			$this->setdefaultHeader($index, $value);
		}
		return $this;
	}

	/**
	 * Set default dictionary header
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $index
	 * @param string $value
	 * @return \GettextTranslation  provides a fluent interface
	 */
	public function setDefaultHeader($index, $value) {
		$this->defaultHeaders[$index] = $value;
		return $this;
	}

	/**
	 * Set dictionary headers
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param array $headers
	 * @param string $identifier Optional dictionary name
	 * @return \GettextTranslation  provides a fluent interface
	 * @throws \InvalidArgumentException
	 */
	public function setHeaders($headers, $identifier = NULL) {
		foreach ($headers as $index => $value) {
			$this->setHeader($index, $value, $identifier);
		}
		return $this;
	}

	/**
	 * Set dictionary header
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $index
	 * @param string $value
	 * @param string $identifier Optional dictionary name
	 * @return \GettextTranslation  provides a fluent interface
	 * @throws \InvalidArgumentException
	 */
	public function setHeader($index, $value, $identifier = NULL) {
		if (($identifier !== NULL) && ($this->getDictionaryFileId($identifier) === FALSE)) {
			throw new InvalidArgumentException('Required dictionary headers are not exist.');
		}

		$this->headers[$this->getDictionaryFileId($identifier)][$index] = $value;
		return $this;
	}

	/**
	 * Add translation object to dictionary
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string|array $original
	 * @param string $context
	 * @param string $identifier Optional dictionary name
	 * @param string|array $translation
	 * @return \GettextTranslation  provides a fluent interface
	 * @throws \InvalidArgumentException
	 */
	public function addOriginal($original, $context = '', $identifier = NULL, $translation = NULL) {
		$identifier = $this->getDictionaryFileId($identifier);

		if (((is_array($translation) === TRUE) && (count($translation) > 1)) && ((count($original) < 2) || (is_array($original) === FALSE))) {
			throw new \InvalidArgumentException('Translation with plurals need to have plural definition.');
		} elseif (trim(is_array($original) ? $original[0] : $original) == '') {
			throw new \InvalidArgumentException('Untranslated string cannot be empty.');
		} elseif (is_string($context) === FALSE) {
			throw new \InvalidArgumentException('Context have to be string.');
		} elseif (isset($this->translations[is_array($original) ? $original[0] : $original][$context][$this->generateDictionaryFileIdentifier($identifier)])) {
			throw new \InvalidArgumentException('Same defined original is exist.');
		} elseif ($identifier === FALSE) {
			throw new \InvalidArgumentException('Required dictionary source is not exist.');
		}

		return $this->translations[is_array($original) ? $original[0] : $original][$context][$this->generateDictionaryFileIdentifier($identifier)] = new GettextTranslation($original, $context, $translation);
	}

	/**
	 * Get translation object from dictionary
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string|array $original
	 * @param string $context
	 * @param string $identifier Optional dictionary name
	 * @return \GettextTranslation  provides a fluent interface
	 * @throws \InvalidArgumentException
	 */
	public function getOriginal($original, $context = '', $identifier = NULL) {
		$identifier = $this->getDictionaryFileId($identifier);

		if (!isset($this->translations[is_array($original) ? $original[0] : $original][$context][$identifier])) {
			throw new \InvalidArgumentException('Required original is not exist.');
		} elseif ($identifier === FALSE) {
			throw new \InvalidArgumentException('Required dictionary source is not exist.');
		}

		return $this->translations[is_array($original) ? $original[0] : $original][$context][$identifier];
	}

	/**
	 * Alias to getOriginal Method
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string|array $original
	 * @param string $context
	 * @param string $identifier Optional dictionary name
	 * @return \GettextTranslation  provides a fluent interface
	 * @throws \InvalidArgumentException
	 */
	public function getTranslation($original, $context = '', $identifier = NULL) {
		return $this->getOriginal($original, $context, $identifier);
	}

	/**
	 * Remove unwanted translation
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $original
	 * @param string $context if NULL, remove whole translation  otherwise remove defined context
	 * @param string $identifier Optional dictionary name
	 * @return void
	 */
	public function removeTranslation($original, $context = NULL, $identifier = NULL) {
		$identifier = $this->getDictionaryFileId($identifier);

		if (($context != NULL) && (isset($this->translations[$original][$context][$identifier]))) {
			unset($this->translations[$original][$context][$identifier]);
		} elseif (($context === NULL) && (isset($this->translations[$original]))) {
			foreach ($this->translations[$original] as $context => $fileIdentifier) {
				if ($fileIdentifier === $identifier) {
					unset($this->translations[$original][$context][$fileIdentifier]);
				}
			}
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
		if ($fileDefinition === '') {
			return '';
		} elseif ($fileDefinition === NULL) {
			return (count($this->files) == 1) ? 0 : '';
		} else {
			$output = FALSE;
			foreach ($this->files as $internalId => $file) {
				if ($file['identifier'] == $fileDefinition) {
					return $internalId;
				} elseif ((($file['mobileObject'] === TRUE) && (($file['filename'] . '.mo' == $fileDefinition) || ($file['path'] . DIRECTORY_SEPARATOR . $file['filename'] . '.mo' == $fileDefinition))) ||
						(($file['portableObject'] === TRUE) && (($file['filename'] . '.po' == $fileDefinition) || ($file['path'] . DIRECTORY_SEPARATOR . $file['filename'] . '.po' == $fileDefinition)))) {
					if ($output === FALSE) {
						$output = $internalId;
					} else {
						throw new \InvalidArgumentException('More than one file with same definition was found.');
					}
				}
			}
			return $output;
		}
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
				'filename' => basename($path, '.' . $type),
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
		$identifier = strtolower(basename($path, '.' . $type));
		$possibleId = $this->getDictionaryFileId($identifier);
		if (($possibleId === FALSE) || (($path === '') && ($possibleId === '')) || (($type == 'mo') && ($this->files[$possibleId]['mobileObject'] === FALSE) && ($this->colaborativeMode === TRUE)) || (($type == 'po') && ($this->files[$possibleId]['mobileObject'] === FALSE) && ($this->colaborativeMode === TRUE))) {
			return $identifier;
		} else {
			throw new \InvalidArgumentException('Unable to generate unique file identifier.');
		}
	}

}