#JAO NetteTranslator (cc)#
Pavel Železný (2bfree), 2012 ([www.pavelzelezny.cz](http://www.pavelzelezny.cz))

## Requirements ##

[Nette Framework 2.0](http://nette.org) or higher. (PHP 5.3 edition)

## Documentation ##

Here will be my from scratch rewritten variant of great but old [marten-cz/NetteTranslator](https://github.com/marten-cz/NetteTranslator) what I used before.

Now here is just Gettex library to manipulate with gettext .po and .mo files.

## Examples ##

### 1) Load dictionary from .po file ###

	$dictionary = new GettextDictionary();
	$dictionary->loadDictionary('./dictionary.po');
	printf("<h2>Translations:</h2><pre>%s</pre>",print_r($dictionary->getTranslations(),true));


### 2) Alternative loading dictionary from .po file ###

	$dictionary = new GettextDictionary('./dictionary.po');
	printf("<h2>Translations:</h2><pre>%s</pre>",print_r($dictionary->getTranslations(),true));


### 3) Load dictionary from .mo file ###

	$dictionary = new GettextDictionary();
	$dictionary->loadDictionary('./dictionary.mo');
	printf("<h2>Translations:</h2><pre>%s</pre>",print_r($dictionary->getTranslations(),true));


### 4) Alternative loading dictionary from .mo file ###

	$dictionary = new GettextDictionary('./dictionary.mo');
	printf("<h2>Translations:</h2><pre>%s</pre>",print_r($dictionary->getTranslations(),true));


### 5) Generating new dictionary ###

	$dictionary = new GettextDictionary();

	// Setting optional but basic headers.
	$dictionary->setHeader('Project-Id-Version','Test dictionary 1.0');
	$dictionary->setHeader('Language-Team','Elite translator monkeys');
	$dictionary->setHeader('Plural-Forms','nplurals=3; plural=(n==1)? 0 : (n>=2 && n<=4)? 1 : 2;');
	$dictionary->setHeader('Last-Translator','JAO NetteTranslator');
	$dictionary->setHeader('X-Poedit-Language','Czech');

	// Set simple untranslated string for translator team
	$dictionary->addOriginal('Translate this please');

	// Set simple translation without plurals
	$dictionary->addOriginal('Translation team')->setTranslation('Překladatelský team');

	// Set translation with plurals (most complicated way)
	$dictionary->addOriginal('%s monkey')->setPlural('%s monkeys')->setTranslation('%s opice',0)->setTranslation('%s opičky',1)->setTranslation('%s opiček',2);

	// Set Translation with plurals easier
	$dictionary->addOriginal(array('%s dog','%s dogs'))->setTranslations(array('%s pes','%s psi','%s psů'));

	// Set same translation with another context
	// Many apps have problems with context or multiple same untranslated strings
	$dictionary->addOriginal('New','New man')->setTranslation('Nový');
	$dictionary->addOriginal('New','New woman')->setTranslation('Nová');

	// Easiest way to define translation
	$dictionary->addOriginal('Cat','','Kočka'); //simple
	$dictionary->addOriginal('Cat','Beautifull woman','Kočička'); // another context
	$dictionary->addOriginal(array('%s cat','%s cats'),'',array('%s kočka','%s kočky','%s koček')); // with plurals

	// Optional show content of dictionary
	printf("<h2>Headers:</h2><pre>%s</pre>",print_r($dictionary->getHeaders(),true));
	printf("<h2>Translations:</h2><pre>%s</pre>",print_r($dictionary->getTranslations(),true));

	// There is also support for adding many types of comments
	$dictionary->getOriginal('Translate this please')->setComment('comment','translator-comments');
	$dictionary->getOriginal('Translate this please')->setComment('extracted-comment','extracted-comments');
	$dictionary->getOriginal('Translate this please')->setComment('reference','reference...');
	$dictionary->getOriginal('Translate this please')->setComment('flag','flag...');
	$dictionary->getOriginal('Translate this please')->setComment('previous-context','previous-context');
	$dictionary->getOriginal('Translate this please')->setComment('previous-untranslated-string','previous-untranslated-string');

	// Comments can be set by array
	$dictionary->getOriginal('test')->setComments(
		array(
			'comment'                      => 'translator-comments',
			'extracted-comment'            => 'extracted-comments',
			'reference'                    => 'reference...',
			'flag'                         => 'flag...',
			'previous-context'             => 'previous-context',
			'previous-untranslated-string' => 'previous-untranslated-string',
		)
	);

	// Save dictionary
	$dictionary->saveDictionary('./dictionary.po');
	$dictionary->saveDictionary('./dictionary.mo');

### 6) Discussion ###
When you need to discuss, write in [Nette forum](http://forum.nette.org/cs/10020-jao-nettetranslator-translatorpanel-jinak-a-mozna-nekdy-i-lepe) in czech language or on [Google+](https://plus.google.com/117076681840647718622) in english language.