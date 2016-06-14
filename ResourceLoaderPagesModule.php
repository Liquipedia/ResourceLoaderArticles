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

	/** 1.26 version
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	public function getStyles( ResourceLoaderContext $context ) {
		$styles = [];
		foreach ( $this->getPages( $context ) as $titleText => $options ) {
			if ( $options['type'] !== 'style' ) {
				continue;
			}
			$media = isset( $options['media'] ) ? $options['media'] : 'all';
			$style = $this->getContent( $titleText );
			if ( strval( $style ) === '' ) {
				continue;
			}
			
			/* start of less parser */
			if ( strpos($style, '@import') === false ) {
				try {
					$lessc = new Less_Parser;
					$lessc->parse($style);
					$style = $lessc->getCss();
				} catch (exception $e) {
					$style = '/* invalid less: ' . $e->getMessage() . ' */';
				}
			} else {
				$style = '/* using @import is forbidden */';
			}
			/* end of less parser */
			
			if ( $this->getFlip( $context ) ) {
				$style = CSSJanus::transform( $style, true, false );
			}
			$style = MemoizedCallable::call( 'CSSMin::remap',
				[ $style, false, $this->getConfig()->get( 'ScriptPath' ), true ] );
			if ( !isset( $styles[$media] ) ) {
				$styles[$media] = [];
			}
			$style = ResourceLoader::makeComment( $titleText ) . $style;
			$styles[$media][] = $style;
		}
		return $styles;
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
