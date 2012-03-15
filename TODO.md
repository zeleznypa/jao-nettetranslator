#JAO NetteTranslator (cc) - TODO#
Pavel Železný (2bfree), 2012 ([www.pavelzelezny.cz](http://www.pavelzelezny.cz))

## Major ##

## Minor ##

## Big feature ##


Great translation system ;)

## Notes ##
->LoadDictionary($path,$identifier=NULL)
	Will load dictionary from defined .po or .mo file
	When loading .po file after samenamed .mo file from same dir is loaded and when colaborative mode is enable, load aditional data into dictionary (comments,...)

->LoadDictionaries($path,$recursive=FALSE)
	Will load all gettext files from defined dictionary
	Normaly load just .mo files, when colaborative mode is enable, load aditional data from .po files into dictionary (comments,...)

Maybe there will be possible to load dictionary without identifier and set it in the future.