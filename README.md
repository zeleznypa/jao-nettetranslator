#JAO NetteTranslator (cc)#
Pavel Železný (2bfree), 2012 ([www.pavelzelezny.cz](http://www.pavelzelezny.cz))

## Requirements ##

[Nette Framework 2.0](http://nette.org) or higher. (PHP 5.3 edition)

## Documentation ##

Here will be my from scratch rewritten variant of great but old [marten-cz/NetteTranslator](https://github.com/marten-cz/NetteTranslator) what I used before.

Now here is just Gettex library to manipulate with gettext .po and .mo files.

## Examples ##

### 1) Load dictionary from .po file ###

	$gettext = new Gettext();
	$gettext->loadDictionary('./dictionary.po');
	printf("<h2>Translations:</h2><pre>%s</pre>",print_r($gettext->getTranslations(),true));


### 2) Alternative loading dictionary from .po file ###

	$gettext = new Gettext('./dictionary.po');
	printf("<h2>Translations:</h2><pre>%s</pre>",print_r($gettext->getTranslations(),true));


### 3) Load dictionary from .mo file ###

	$gettext = new Gettext();
	$gettext->loadDictionary('./dictionary.mo');
	printf("<h2>Translations:</h2><pre>%s</pre>",print_r($gettext->getTranslations(),true));


### 4) Alternative loading dictionary from .mo file ###

	$gettext = new Gettext('./dictionary.mo');
	printf("<h2>Translations:</h2><pre>%s</pre>",print_r($gettext->getTranslations(),true));


### 5) Generating new dictionary ###

	$gettext = new Gettext();

	// Setting optional but basic headers.
	$gettext->setHeader('Project-Id-Version','Test dictionary 1.0');
	$gettext->setHeader('Language-Team','Elite translator monkeys');
	$gettext->setHeader('Plural-Forms','nplurals=3; plural=(n==1)? 0 : (n>=2 && n<=4)? 1 : 2;');
	$gettext->setHeader('Last-Translator','JAO NetteTranslator');
	$gettext->setHeader('X-Poedit-Language','Czech');

	// Set simple translation without plurals
	$gettext->setTranslation('Translation team','Překladatelský team');

	// Set translation with plurals
	$gettext->setTranslation(array('%s monkey','%s monkeys'),array('%s opice','%s opičky','%s opiček'));

	// Set untranslated string for translator team
	$gettext->setTranslation('Translate this please',NULL);

	// Optional show content of dictionary
	printf("<h2>Headers:</h2><pre>%s</pre>",print_r($gettext->getHeaders(),true));
	printf("<h2>Translations:</h2><pre>%s</pre>",print_r($gettext->getTranslations(),true));

	// Save dictionary
	$gettext->saveDictionary('./dictionary.po');
	$gettext->saveDictionary('./dictionary.mo');

### 6) Discussion ###
When you need to discuss, write in [Nette forum](http://forum.nette.org/cs/10020-jao-nettetranslator-translatorpanel-jinak-a-mozna-nekdy-i-lepe) in czech or english language.