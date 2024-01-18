<?php

/**
 * MediaWiki Extension to add additional Resource Loader module
 */

namespace Liquipedia\Extension\ResourceLoaderArticles\ResourceLoader;

use CSSJanus;
use Less_Parser;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MemoizedCallable;
use Peast\Peast;
use Peast\Syntax\Exception as PeastSyntaxException;
use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderWikiModule;

class ResourceLoaderArticlesModule extends ResourceLoaderWikiModule {

	private const USERJSPARSE_CACHE_VERSION = 3;

	/**
	 * Get list of pages used by this module
	 *
	 * @param ResourceLoaderContext $context
	 * @return array List of pages
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		$request = $context->getRequest();
		$articles = $request->getVal( 'articles' );
		$articles = explode( '|', $articles ?? '' );
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
	 * Override of the same function in order to support ES6 files
	 * Duplicate of https://gerrit.wikimedia.org/g/mediawiki/core/+/6fd9245f4ce47a77dc76f70994952cd6da2d1db7/includes/ResourceLoader/Module.php#1083
	 * Can be removed when moving to a MW version >= 1.4
	 * @param string $fileName
	 * @param string $contents
	 * @return string
	 */
	protected function validateScriptFile( $fileName, $contents ) {
		if ( !$this->getConfig()->get( MainConfigNames::ResourceLoaderValidateJS ) ) {
			return $contents;
		}
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		// Cache potentially slow parsing of JavaScript code during the
		// critical path. This happens lazily when responding to requests
		// for modules=site, modules=user, and Gadgets.
		$error = $cache->getWithSetCallback(
			$cache->makeKey(
				'resourceloader-userjsparse',
				self::USERJSPARSE_CACHE_VERSION,
				md5( $contents ),
				$fileName
			),
			$cache::TTL_WEEK,
			static function () use ( $contents, $fileName ) {
				try {
					Peast::ES2020( $contents )->parse();
				} catch ( PeastSyntaxException $e ) {
					return $e->getMessage() . " on line " . $e->getPosition()->getLine();
				}
				// Cache success as null
				return null;
			}
		);
		if ( $error ) {
			// Send the error to the browser console client-side.
			// By returning this as replacement for the actual script,
			// we ensure user-provided scripts are safe to include in a batch
			// request, without risk of a syntax error in this blob breaking
			// the response itself.
			return 'mw.log.error(' .
				json_encode(
					'Parse error: ' . $error . ' for file ' . $fileName
				) .
				');';
		}
		return $contents;
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
			$style = $this->getContent( $titleText, $context );
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
