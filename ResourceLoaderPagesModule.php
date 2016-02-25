<?php
/**
 * Resource loader module for site customizations.
 *
 * @file
 * @author Alex Winkler
 */

/**
 * MediaWiki Extension to add additional Resource Loader module
 */

class ResourceLoaderPagesModule extends ResourceLoaderWikiModule {

	/**
	 * Get list of pages used by this module
	 *
	 * @param ResourceLoaderContext $context
	 * @return array List of pages
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		$request = $context->getRequest();
		$articles = $request->getVal('articles');
		$articles = explode('|', $articles);
		if ( empty( $articles ) ) {
			return;
		}
		$pages = array();
		foreach($articles as $article) {
			if(substr($article, -3) == '.js') {
				$pages[$article] = array( 'type' => 'script' );
			} elseif(substr($article, -4) == '.css') {
				$pages[$article] = array( 'type' => 'style' );
			}
		}
		return $pages;
	}

	/**
	 * Get group name
	 *
	 * @return string
	 */
	public function getGroup() {
		return 'pages';
	}
}
