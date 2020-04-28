<?php

/**
 * MediaWiki Extension to add additional Resource Loader module
 */

namespace Liquipedia\ResourceLoaderArticles;

use CSSJanus;
use Less_Parser;
use MemoizedCallable;
use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderWikiModule;

class ResourceLoaderArticlesModule extends ResourceLoaderWikiModule {

	/**
	 * Get list of pages used by this module
	 *
	 * @param ResourceLoaderContext $context
	 * @return array List of pages
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		$request = $context->getRequest();
		$articles = $request->getVal( 'articles' );
		$articles = explode( '|', $articles );
		if ( empty( $articles ) ) {
			return;
		}
		$pages = [];
		foreach ( $articles as $article ) {
			if ( substr( $article, -3 ) === '.js' ) {
				$pages[ 'MediaWiki:Common.js/' . $article ] = [ 'type' => 'script' ];
			} elseif ( substr( $article, -4 ) === '.css' ) {
				$pages[ 'MediaWiki:Common.css/' . $article ] = [ 'type' => 'style' ];
			}
		}
		return $pages;
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	public function getStyles( ResourceLoaderContext $context ) {
		$styles = [];
		foreach ( $this->getPages( $context ) as $titleText => $options ) {
			if ( $options[ 'type' ] !== 'style' ) {
				continue;
			}
			$media = isset( $options[ 'media' ] ) ? $options[ 'media' ] : 'all';
			$style = $this->getContent( $titleText );
			if ( strval( $style ) === '' ) {
				continue;
			}

			if ( strpos( $style, '@import' ) !== false ) {
				$style = '/* using @import is forbidden */';
			}

			if ( !isset( $styles[ $media ] ) ) {
				$styles[ $media ] = [];
				$styles[ $media ][ 0 ] = '';
			}
			$style = ResourceLoader::makeComment( $titleText ) . $style;
			$styles[ $media ][ 0 ] .= $style;
		}
		foreach ( $styles as $media => $styleItem ) {
			/* start of less parser */
			try {
				$lessc = new Less_Parser;
				$lessc->parse( $styleItem[ 0 ] );
				$style = $lessc->getCss();
			} catch ( exception $e ) {
				$style = '/* invalid less: ' . $e->getMessage() . ' */';
			}
			/* end of less parser */
			if ( $this->getFlip( $context ) ) {
				$style = CSSJanus::transform( $style, true, false );
			}
			$style = MemoizedCallable::call(
					'CSSMin::remap',
					[ $style, false, $this->getConfig()->get( 'ScriptPath' ), true ]
			);
			$styles[ $media ][ 0 ] = $style;
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
