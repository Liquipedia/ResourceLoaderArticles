<?php

namespace Liquipedia\Extension\ResourceLoaderArticles\SpecialPage;

use HTMLForm;
use Title;

class SpecialResourceLoaderArticles extends \SpecialPage {

	public function __construct() {
		parent::__construct( 'ResourceLoaderArticles', 'adminresourceloaderarticles' );
	}

	/**
	 * @return string
	 */
	public function getGroupName() {
		return 'liquipedia';
	}

	/**
	 * @return bool
	 */
	public function isListed() {
		return false;
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return;
		}
		$this->setHeaders();
		$output = $this->getOutput();
		$params = explode( '/', $par ?? '' );

		$output->addWikiTextAsContent(
			'[[Special:ResourceLoaderArticles|'
			. $this->msg( 'resourceloaderarticles-show-list' )->text()
			. ']]'
		);
		$output->addWikiTextAsContent(
			'[[Special:ResourceLoaderArticles/add|'
			. $this->msg( 'resourceloaderarticles-add-page' )->text()
			. ']]'
		);

		if ( $params[ 0 ] === 'add' ) {
			$this->addPage();
		} elseif ( $params[ 0 ] === 'edit' && count( $params ) === 2 ) {
			$this->editPage( intval( $params[ 1 ] ) );
		} elseif ( $params[ 0 ] === 'delete' && count( $params ) === 2 ) {
			$this->deletePage( intval( $params[ 1 ] ) );
		} else {
			$this->listPages();
		}
	}

	private function listPages() {
		$output = $this->getOutput();
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'resourceloaderarticles',
			'*',
			[],
			__METHOD__,
			[ 'ORDER BY' => 'rla_type ASC, rla_priority DESC, rla_page ASC, rla_wiki ASC' ]
		);
		$output->addHTML( '<div class="table-responsive"><table class="wikitable">' );
		$output->addHTML(
			'<tr><th>' . $this->msg( 'resourceloaderarticles-id' )->text()
			. '</th><th>' . $this->msg( 'resourceloaderarticles-priority' )->text()
			. '</th><th>' . $this->msg( 'resourceloaderarticles-page' )->text()
			. '</th><th>' . $this->msg( 'resourceloaderarticles-wiki' )->text()
			. '</th><th>' . $this->msg( 'resourceloaderarticles-type' )->text()
			. '</th><th></th></tr>'
		);
		$prevResType = '';
		foreach ( $res as $row ) {
			$deleteTitle = Title::newFromText( 'ResourceLoaderArticles/delete/' . $row->rla_id, NS_SPECIAL );
			$editTitle = Title::newFromText( 'ResourceLoaderArticles/edit/' . $row->rla_id, NS_SPECIAL );
			if ( $prevResType !== $row->rla_type ) {
				$output->addHTML(
					'<tr><th colspan="6">'
					. $this->msg( 'resourceloaderarticles-resourcetype-' . $row->rla_type )->text()
					. '</th></tr>'
				);
				$prevResType = $row->rla_type;
			}
			$output->addHTML(
				'<tr><td>' . $row->rla_id
				. '</td><td>' . $row->rla_priority
				. '</td><td>' . $row->rla_page
				. '</td><td>' . $row->rla_wiki
				. '</td><td>' . $row->rla_type
				. '</td><td>'
				. '<a class="btn btn-primary" href="' . $editTitle->getLocalURL() . '">'
				. $this->msg( 'resourceloaderarticles-edit' )->text()
				. '</a>'
				. '<a class="btn btn-primary" href="' . $deleteTitle->getLocalURL() . '">'
				. $this->msg( 'resourceloaderarticles-delete' )->text()
				. '</a>'
				. '</td></tr>'
			);
		}
		$output->addHTML( '</table></div>' );
	}

	private function addPage() {
		$formDescriptor = [
			'Page' => [
				'label-message' => 'resourceloaderarticles-page',
				'help-message' => 'resourceloaderarticles-help-page',
				'type' => 'text',
				'required' => true,
				'filter-callback' => [ $this, 'trimValueCB' ],
				'validation-callback' => [ $this, 'validatePageCB' ],
			],
			'Wiki' => [
				'label-message' => 'resourceloaderarticles-wiki',
				'type' => 'text',
				'required' => true,
				'default' => 'all',
				'filter-callback' => [ $this, 'trimValueCB' ],
			],
			'Type' => [
				'class' => 'HTMLSelectField',
				'label' => 'Select an option',
				'options-messages' => [
					'resourceloaderarticles-resourcetype-script' => 'script',
					'resourceloaderarticles-resourcetype-style' => 'style',
				],
			],
			'Priority' => [
				'label-message' => 'resourceloaderarticles-priority',
				'help-message' => 'resourceloaderarticles-help-priority',
				'type' => 'int',
				'required' => true,
				'default' => '0',
				'min' => -1000,
				'max' => 1000,
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( $this->msg( 'resourceloaderarticles-add-page' )->text() );
		$htmlForm->setFormIdentifier( 'addPageCB' );
		$htmlForm->setSubmitCallback( [ $this, 'addPageCB' ] );

		$htmlForm->show();
	}

	/**
	 * @param array $formData
	 */
	public function addPageCB( $formData ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->insert(
			'resourceloaderarticles',
			[
				'rla_page' => $formData[ 'Page' ],
				'rla_wiki' => $formData[ 'Wiki' ],
				'rla_type' => $formData[ 'Type' ],
				'rla_priority' => intval( $formData[ 'Priority' ] )
			]
		);
		$output = $this->getOutput();
		$output->addWikiTextAsContent(
			'<div class="success">'
			. $this->msg( 'resourceloaderarticles-success-add' )->text()
			. '</div>'
		);
	}

	/**
	 * @param int $id
	 */
	private function editPage( $id ) {
		$dbr = wfGetDB( DB_PRIMARY );
		$res = $dbr->select( 'resourceloaderarticles', '*', [ 'rla_id' => $id ] );
		$row = $res->fetchObject();
		$formDescriptor = [
			'Id' => [
				'label-message' => 'resourceloaderarticles-id',
				'type' => 'int',
				'required' => true,
				'disabled' => true,
				'default' => $row->rla_id,
			],
			'Page' => [
				'label-message' => 'resourceloaderarticles-page',
				'help-message' => 'resourceloaderarticles-help-page',
				'type' => 'text',
				'required' => true,
				'default' => $row->rla_page,
				'filter-callback' => [ $this, 'trimValueCB' ],
				'validation-callback' => [ $this, 'validatePageCB' ],
			],
			'Wiki' => [
				'label-message' => 'resourceloaderarticles-wiki',
				'type' => 'text',
				'required' => true,
				'default' => $row->rla_wiki,
				'filter-callback' => [ $this, 'trimValueCB' ],
			],
			'Type' => [
				'class' => 'HTMLSelectField',
				'label' => 'Select an option',
				'options-messages' => [
					'resourceloaderarticles-resourcetype-script' => 'script',
					'resourceloaderarticles-resourcetype-style' => 'style',
				],
				'default' => $row->rla_type,
			],
			'Priority' => [
				'label-message' => 'resourceloaderarticles-priority',
				'help-message' => 'resourceloaderarticles-help-priority',
				'type' => 'int',
				'required' => true,
				'default' => $row->rla_priority,
				'min' => -1000,
				'max' => 1000,
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( $this->msg( 'resourceloaderarticles-edit-page' )->text() );
		$htmlForm->setFormIdentifier( 'editPageCB' );
		$htmlForm->setSubmitCallback( [ $this, 'editPageCB' ] );

		$htmlForm->show();
	}

	/**
	 * @param array $formData
	 */
	public function editPageCB( $formData ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->update(
			'resourceloaderarticles',
			[
				'rla_page' => $formData[ 'Page' ],
				'rla_wiki' => $formData[ 'Wiki' ],
				'rla_type' => $formData[ 'Type' ],
				'rla_priority' => intval( $formData[ 'Priority' ] )
			],
			[
				'rla_id' => $formData[ 'Id' ]
			]
		);
		$output = $this->getOutput();
		$output->addWikiTextAsContent(
			'<div class="success">'
			. $this->msg( 'resourceloaderarticles-success-edit' )->text()
			. '</div>'
		);
	}

	/**
	 * @param int $id
	 */
	private function deletePage( $id ) {
		$dbr = wfGetDB( DB_PRIMARY );
		$res = $dbr->select( 'resourceloaderarticles', '*', [ 'rla_id' => $id ] );
		$row = $res->fetchObject();
		$formDescriptor = [
			'Id' => [
				'label-message' => 'resourceloaderarticles-id',
				'type' => 'int',
				'required' => true,
				'disabled' => true,
				'default' => $row->rla_id,
			],
			'Page' => [
				'label-message' => 'resourceloaderarticles-page',
				'help-message' => 'resourceloaderarticles-help-page',
				'type' => 'text',
				'required' => true,
				'disabled' => true,
				'default' => $row->rla_page,
			],
			'Wiki' => [
				'label-message' => 'resourceloaderarticles-wiki',
				'type' => 'text',
				'required' => true,
				'disabled' => true,
				'default' => $row->rla_wiki,
			],
			'Type' => [
				'class' => 'HTMLSelectField',
				'label' => 'Select an option',
				'options-messages' => [
					'resourceloaderarticles-resourcetype-script' => 'script',
					'resourceloaderarticles-resourcetype-style' => 'style',
				],
				'disabled' => true,
				'default' => $row->rla_type,
			],
			'Priority' => [
				'label-message' => 'resourceloaderarticles-priority',
				'help-message' => 'resourceloaderarticles-help-priority',
				'type' => 'int',
				'required' => true,
				'disabled' => true,
				'default' => $row->rla_priority,
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( $this->msg( 'resourceloaderarticles-delete-page' )->text() );
		$htmlForm->setFormIdentifier( 'deletePageCB' );
		$htmlForm->setSubmitCallback( [ $this, 'deletePageCB' ] );

		$htmlForm->show();
	}

	/**
	 * @param array $formData
	 */
	public function deletePageCB( $formData ) {
		$output = $this->getOutput();
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->delete( 'resourceloaderarticles', [ 'rla_id' => $formData[ 'Id' ] ] );
		$output->addWikiTextAsContent(
			'<div class="success">'
			. $this->msg( 'resourceloaderarticles-success-delete' )->text()
			. '</div>'
		);
	}

	/**
	 * @param string $value
	 * @return string
	 */
	public function trimValueCB( $value ) {
		return trim( $value );
	}

	/**
	 * @param string $value
	 * @param array $alldata
	 * @return bool|string
	 */
	public function validatePageCB( $value, $alldata ) {
		if (
			(
				$alldata[ 'Type' ] === 'style'
				&& !(
					( strlen( $value ) > 4 && substr( $value, -4 ) === '.css' )
					|| ( strlen( $value ) > 5 && substr( $value, -5 ) === '.less' )
					|| ( strlen( $value ) > 5 && substr( $value, -5 ) === '.scss' )
				)
			)
			|| (
				$alldata[ 'Type' ] === 'script'
				&& !( strlen( $value ) > 3 && substr( $value, -3 ) === '.js' )
			)
		) {
			return $this->msg( 'resourceloaderarticles-error-page-invalid' )->text();
		}
		return true;
	}

}
