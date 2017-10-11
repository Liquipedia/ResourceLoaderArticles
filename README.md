MediaWiki-ResourceLoader-for-Articles
=====================================
MediaWiki extension that allows to load the content of multiple pages through the resource loader.

Installation
============
* Extract the extension folder to extensions/ResourceLoaderArticles/
* Add the following line to LocalSettings.php:

	wfLoadExtension( 'ResourceLoaderArticles' );

Usage
=====
Include a line similar to the ones shown here to your MediaWiki:Common.js or MediaWiki:Common.css page.

	/load.php?articles=ArticleOne.js|ArticleTwo.js&only=scripts&mode=articles
	/load.php?articles=ArticleOne.css|ArticleTwo.css&only=styles&mode=articles

Version
=======
This version is for MediaWiki 1.26 and 1.27, for a MediaWiki 1.25 version go to the 1.25 branch.