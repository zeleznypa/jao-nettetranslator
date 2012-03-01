<?php
/**
 * Manipulation with one gettext translation
 * @author Pavel Železný <info@pavelzelezny.cz>
 */
class GettextTranslation {
	/** @var array  Singular and optionaly plural orginal untranslated string */
	private $original = array();

	/** @var string  Context of translation */
	private $context = NULL;

	/** @var array  Translated strings */
	private $translation = array();

	/** @var array  Comments */
	private $comments = array();

	/**
	 * Constructor
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string|array $original  singular and optionaly plural form of orginal untranslated string
	 * @param string|array $context  context of untranslated string
	 * @param string|array $translation  singular and optionaly plural form of translated string
	 * @return void
	 */
	public function __construct($original=NULL,$context=NULL,$translation=NULL) {
		if($original !== NULL){
			$this->setOriginal($original);
		}

		if($context !== NULL){
			$this->setContext($context);
		}

		if($translation !== NULL){
			if(is_array($translation)){
				$this->setTranslations(array_values($translation));
			} else {
				$this->setTranslation($translation,0);
			}
		}
	}

	/**
	 * Original getter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return array
	 */
	public function getOriginal(){
		return $this->original;
	}

	/**
	 * Context getter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return string
	 */
	public function getContext(){
		return $this->context;
	}

	/**
	 * Translations getter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return array
	 */
	public function getTranslations(){
		return $this->translation;
	}

	/**
	 * Translations getter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param int $index Index of translation
	 * @return array
	 */
	public function getTranslation($index){
		if(!isset($this->translation[$index])){
			throw new \BadMethodCallException('Defined index of translation is not exist.');
		}
		return $this->translation[$index];
	}

    /**
	 * Comments getter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return array
	 */
	public function getComments(){
		return $this->comments;
	}

	/**
	 * Comment getter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $type  Type of returned comment
	 * @return array
	 */
	public function getComment($type){
		if(in_array($type, $this->getAllowedCommentTypes()) === FALSE){
			throw new \BadMethodCallException('Unsupported comment type. Supported type is one from following: '.implode(', ',$this->getAllowedCommentTypes()).'.');
		} elseif(!isset($this->comment[$type])){
			throw new \BadMethodCallException('Defined type of comment is not exist.');
		}
		return $this->comment[$type];
	}

	/**
	 * Allowed comment types
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @return array
	 */
	private function getAllowedCommentTypes(){
		return array(
			'comment',
			'reference',
			'flag',
			'previous-context',
			'previous-untranslated-string',
		);
	}

	/**
	 * Original setter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string|array $original  singular and optionaly plural form of orginal untranslated string
	 * @return \GettextTranslation  provides a fluent interface
	 * @throw \BadMethodCallException
	 */
	protected function setOriginal($original){
		if (trim(is_array($original) ? $original[0] : $original) == ''){
			throw new \BadMethodCallException('Untranslated string cannot be empty.');
		} elseif(count($this->original) > 0) {
			throw new \BadMethodCallException('Unable to change original untranslated string. Make new translation instead.');
		}
		$this->original = (array) $original;
		return $this;
	}

	/**
	 * Context setter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $original  context of untranslated string
	 * @return \GettextTranslation  provides a fluent interface
	 * @throw \BadMethodCallException
	 */
	public function setContext($context){
		if (is_string($context)===FALSE){
			throw new \BadMethodCallException('Context have to be string.');
		} elseif($this->context !== NULL) {
			throw new \BadMethodCallException('Unable to change context of original untranslated string. Make new translation instead.');
		}
		$this->context = $context;
		return $this;
	}

	/**
	 * Singular and plural variant of translation setter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param array $translation  singular and optionaly plural form of translated string
	 * @return \GettextTranslation  provides a fluent interface
	 * @throw \BadMethodCallException
	 */
	public function setTranslations($translations){
		foreach (array_values($translations) as $index => $translation){
			$this->setTranslation($translation, $index);
		}
	}

	/**
	 * One translation setter (singular or plural)
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string|array $translation  singular and optionaly plural form of translated string
	 * @param string $index  optionaly we can set index of plural
	 * @return \GettextTranslation  provides a fluent interface
	 * @throw \BadMethodCallException
	 */
	public function setTranslation($translation,$index=0){
		if(is_string($translation)===FALSE){
			throw new \BadMethodCallException('Translation have to be string.');
		} elseif(is_int($index)===FALSE){
			throw new \BadMethodCallException('Index of translation have to be integer.');
		} elseif(($index > 0)&&(count($this->original) < 2)){
		    throw new \BadMethodCallException('Translation with plurals need to have plural definition.');
		}
		$this->translation[$index] = (string) $translation;
		return $this;
	}

	/**
	 * Many comments setter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param array $comments key mean type and value mean text of comment
	 * @return \GettextTranslation  provides a fluent interface
	 * @throw \BadMethodCallException
	 */
	public function setComments($comments){
		foreach($comments as $type => $comment){
			$this->setComment($type, $comment);
		}
		return $this;
	}

	/**
	 * One comment setter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $type type of comment
	 * @param string $comment text of comment
	 * @return \GettextTranslation  provides a fluent interface
	 * @throw \BadMethodCallException
	 */
	public function setComment($type,$comment){
		if(in_array($type, $this->getAllowedCommentTypes()) === FALSE){
			throw new \BadMethodCallException('Unsupported comment type. Supported type is one from following: '.implode(', ',$this->getAllowedCommentTypes()).'.');
		}
		$this->comments[$type]=$comment;
		return $this;
	}

	/**
	 * Plural form of original untranslated string setter
	 * @author Pavel Železný <info@pavelzelezny.cz>
	 * @param string $plural  plural form of untranslated string
	 * @return \GettextTranslation  provides a fluent interface
	 * @throw \BadMethodCallException
	 */
	public function setPlural($plural){
		if(count($this->original)==0){
		    throw new \BadMethodCallException('Plural form cannot be set without singular variant.');
		} elseif (count($this->original)>1){
			throw new \BadMethodCallException('Unable to change plural form of original untranslated string. Make new translation instead.');
		} elseif (is_string($plural)===FALSE){
			throw new \BadMethodCallException('Plural have to be string.');
		}
		$this->original[1] = $plural;
		return $this;
	}
}