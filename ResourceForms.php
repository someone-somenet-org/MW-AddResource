<?php

/**
 * Superclass for creating links and subpages. Only contains common validator.
 */
class PageCreationForm extends HTMLForm {
	protected $mMyAction;
	protected $mAction;

	/**
	 * Callback that validates that a page does *not* exist.
	 */
	public function validatePageNotExists( $value, $alldata) {
		if ($this->mAction != $this->mMyAction) {
			return true;
		}
		$value =str_replace('/', '-', $value);
		$title = Title::NewFromText( $alldata['BasePage'] . '/' . $value );
		if ( $title->exists() ) {
            return wfMsg('page-exists');
		} else {
			return true;
		}
	}
}

/**
 */
class UploadFileForm extends HTMLForm {
	private $mDesiredDestName;
	private $mForReUpload;
	private $mComment;

	public function __construct( $title, $options = array() ) {
        $this->title = $title;

		global $wgUser;
		$descriptor = $this->getUploadDescriptors();
        parent::__construct( $descriptor, 'addresource' );

		# Set some form properties
		$this->setSubmitText( wfMsg( 'uploadbtn' ) );
		$this->setSubmitName( 'submit' ); #TODO: maybe interesting to get type of submission?
		# Used message keys: 'accesskey-upload', 'tooltip-upload'
		$this->setSubmitTooltip( 'upload' );
		$this->setId( 'mw-upload-form' );

		$this->addHeaderText( wfMsg( 'upload_exp', $wgUser->getSkin()->linkKnown(
				SpecialPage::getTitleFor( 'Imagelist' ),
				wfMsg( 'upload_exp_linktext' ) ))
		);
		$this->addPostText( '<br />' . wfMsg('upload_footer',
			$wgUser->getSkin()->makeExternalLink(
				wfMsg( 'upload_footer_url' ),
				wfMsg( 'upload_footer_linktext' )
			))
		);
	}

	protected function getUploadDescriptors() {
		global $wgUser, $wgLang, $wgMaxUploadSize;

		$descriptor = array();
		$descriptor['UploadFile'] = array(
			'class' => 'UploadSourceField',
#			'section' => 'file',
			'type' => 'file',
			'id' => 'wpUploadFile',
			'label-message' => 'sourcefilename',
			'upload-type' => 'File',
			'radio' => &$radio,
			'help' => wfMsgExt( 'upload-maxfilesize',
					array( 'parseinline', 'escapenoentities' ),
					$wgLang->formatSize(
						wfShorthandToInteger( min(
							wfShorthandToInteger(
								ini_get( 'upload_max_filesize' )
							), $wgMaxUploadSize
						) )
					)
				) . ' ' . wfMsgHtml( 'upload_source_file' ),
			'checked' => true, #$selectedSourceType == 'file',
		);

		$descriptor['Extensions'] = array(
			'type' => 'info',
#			'section' => 'file',
			'default' => $this->getExtensionsMessage(),
			'raw' => true,
		);

		$descriptor['DestFile'] = array(
				'type' => 'text',
#				'section' => 'description',
				'id' => 'wpDestFile',
				'label-message' => 'destfilename',
				'size' => 60,
				'default' => $this->mDesiredDestName,
				# FIXME: hack to work around poor handling of the 'default' option in HTMLForm
				'nodata' => strval( $this->mDesiredDestName ) !== '',
		);
		$descriptor['UploadDescription'] = array(
			'type' => 'textarea',
#			'section' => 'description',
			'id' => 'wpUploadDescription',
			'label-message' => $this->mForReUpload
				? 'filereuploadsummary'
				: 'fileuploadsummary',
			'default' => $this->mComment,
			'cols' => intval( $wgUser->getOption( 'cols' ) ),
			'rows' => 8,
		);
		$descriptor['IgnoreWarning'] = array(
			'type' => 'hidden',
			'id' => 'wpIgnoreWarning',
			'label-message' => 'ignorewarnings',
			'default' => '1',
		);

		$descriptor['DestFileWarningAck'] = array(
			'type' => 'hidden',
			'id' => 'wpDestFileWarningAck',
			'default' => '1',
        );
#		$descriptor['SourceType'] = array(
#			'type' => 'hidden',
#			'default' => 'Stash',
#		);

#		if ( $this->mForReUpload ) {
			$descriptor['ForReUpload'] = array(
				'type' => 'hidden',
				'id' => 'wpForReUpload',
				'default' => '1',
			);
#        }

        # Remember what article we upload a resource for. This information
        # is used by our upload backends in ResourceUploadBackends.php
        $descriptor['ForArticle'] = array(
            'type' => 'hidden',
            'id' => 'wpForArticle',
            'default' => $this->title->getPrefixedDBkey(),
        );

		$descriptor['Action'] = array(
			'type' => 'hidden',
			'id' => 'action-upload',
			'default' => 'upload',
		);

		return $descriptor;
	}

	/**
	 * Get the messages indicating which extensions are preferred and prohibitted.
	 *
	 * Exact copy of UploadForm::getExtensionsMessage() in 1.17.0
	 *
	 * @return String: HTML string containing the message
	 */
	protected function getExtensionsMessage() {
		# Print a list of allowed file extensions, if so configured.  We ignore
		# MIME type here, it's incomprehensible to most people and too long.
		global $wgLang, $wgCheckFileExtensions, $wgStrictFileExtensions,
		$wgFileExtensions, $wgFileBlacklist;

		if( $wgCheckFileExtensions ) {
			if( $wgStrictFileExtensions ) {
				# Everything not permitted is banned
				$extensionsList =
					'<div id="mw-upload-permitted">' .
					wfMsgWikiHtml( 'upload-permitted', $wgLang->commaList( $wgFileExtensions ) ) .
					"</div>\n";
			} else {
				# We have to list both preferred and prohibited
				$extensionsList =
					'<div id="mw-upload-preferred">' .
					wfMsgWikiHtml( 'upload-preferred', $wgLang->commaList( $wgFileExtensions ) ) .
					"</div>\n" .
					'<div id="mw-upload-prohibited">' .
					wfMsgWikiHtml( 'upload-prohibited', $wgLang->commaList( $wgFileBlacklist ) ) .
					"</div>\n";
			}
		} else {
			# Everything is permitted.
			$extensionsList = '';
		}
		return $extensionsList;
	}

}

class SubpageForm extends PageCreationForm {
	private $mDest;

	public function __construct( $action, $title, $options = array() ) {
		$this->mAction = $action;
		$this->mMyAction = 'subpage';
		$this->title = $title;
		$descriptor = $this->getDescriptors();
		parent::__construct( $descriptor, 'addresource' );

		$this->mDest = isset( $options['dest'] ) ? $options['dest'] : '';
		$this->mSubmitCallback = array( $this, 'submit' );

		# Set some form properties
		$this->setSubmitText( wfMsg( 'subpage_button' ) );
		$this->setSubmitName( 'submit' );
		# Used message keys: 'accesskey-upload', 'tooltip-upload'
		$this->setSubmitTooltip( 'create' );
		$this->setId( 'mw-upload-form' );

		$this->addHeaderText( wfMsg('subpage_exp', wfMsg('subpage_button')) );
		$this->addPostText( '<br />' . wfMsg('subpage_after_exp' ) );
	}

	public function submit() {
		global $wgOut;
		$subpage = str_replace('/', '-', $this->mDest);

		$wgOut->redirect($this->title->getFullURL() . '/' . $subpage . '?action=edit' );
	}

	protected function getDescriptors() {
		global $wgUser, $wgLang, $wgMaxUploadSize;

		$descriptor = array();
		$descriptor['SubpageDest'] = array(
				'type' => 'text',
				'id' => 'wpSubpageDest',
				'label-message' => 'subpage_inputTitle',
				'size' => 60,
				'default' => $this->mDest,
				'validation-callback' => array($this, 'validatePageNotExists'),
				'required' => true,
		);
		$descriptor['Action'] = array(
			'type' => 'hidden',
			'default' => 'subpage',
		);
		$descriptor['BasePage'] = array(
			'type' => 'hidden',
			'id' => 'action-link',
			'default' => $this->title->getPrefixedText(),
		);
		return $descriptor;
	}

	function tryAuthorizedSubmit() {
		if ($this->mAction === 'subpage') {
			return parent::tryAuthorizedSubmit();
		} else {
			return false;
		}
	}
}

class ExternalRedirectForm extends PageCreationForm {
	private $mLinkUrl;
	private $mLinkTitle;
	private $mLinkDesc;

	public function __construct( $action, $title, $options = array() ) {
		$this->mAction = $action;
		$this->mMyAction = 'link';
		$this->title = $title;

		$this->mLinkUrl = isset( $options['desturl'] ) ? $options['desturl'] : '';
		$this->mLinkTitle = isset( $options['desttitle'] ) ? $options['desttitle'] : '';
		$this->mLinkDesc = isset( $options['destdesc'] ) ? $options['destdesc'] : '';

		$descriptor = $this->getDescriptors();
		parent::__construct( $descriptor, 'addresource' );

		# Set some form properties
		$this->setSubmitText( wfMsg( 'link_button' ) );
		$this->setSubmitName( 'submit' );
		# Used message keys: 'accesskey-upload', 'tooltip-upload'
		$this->setSubmitTooltip( 'create' );
		$this->setId( 'mw-upload-form' );

		global $wgUser;
		$this->addHeaderText( wfMsg('link_exp', wfMsg('link_button') ) );
		$this->addPostText( '<br />' . wfMsg('link_footer',
			$title->getFullText(),
			$wgUser->getSkin()->linkKnown(
				SpecialPage::getTitleFor( 'Prefixindex', $title->getPrefixedText() ),
				wfMsg('link_footer_linktext'), null,
				array( 'namespace' => $title->getNamespace() )
			)
		));

		$this->mSubmitCallback = array( $this, 'submit' );
	}

	public function submit() {
		global $wgOut;

		$subpage = str_replace('/', '-', $this->mLinkTitle);
		$title = Title::NewFromText( $this->title->getPrefixedText() . '/' . $subpage);
		$article = new Article( $title );

		$text = '#REDIRECT [[' . $this->mLinkUrl;
		if ( $this->mLinkDesc != '' )
			$text .= '|' . $this->mLinkDesc;
		$text .= ']]';

		# add a category:
		global $wgResourcesCategory;
		if ( $wgResourcesCategory != NULL && gettype($wgResourcesCategory) == "string" ) {
			global $wgContLang;
			$category_text = $wgContLang->getNSText ( NS_CATEGORY );
			$text .= "\n[[" . $category_text . ":" . $wgResourcesCategory . "]]";
		}
		$link = $title->getFullURL() . '?redirect=no';
		$article->doEdit( $text, wfMsg('commit_message', $link, $this->mLinkUrl), EDIT_NEW );

		$redir = SpecialPage::getTitleFor( 'Resources', $this->title->getPrefixedText() );
		$wgOut->redirect($redir->getFullURL() . '?highlight=' . $subpage );
	}

	public function validateUrl( $value, $alldata ) {
#TODO: actually validate
		return true;
	}

	protected function getDescriptors() {
		$descriptor = array();
		$descriptor['LinkUrl'] = array(
				'type' => 'text',
				'id' => 'wpLinkUrl',
				'label-message' => 'link_url',
				'size' => 60,
				'default' => $this->mLinkUrl,
				'validation-callback' => array($this, 'validateUrl'),
				'required' => true,
		);
		$descriptor['LinkTitle'] = array(
				'type' => 'text',
				'id' => 'wpLinkTitle',
				'label-message' => 'link_title',
				'size' => 60,
				'default' => $this->mLinkTitle,
				'validation-callback' => array($this, 'validatePageNotExists'),
				'required' => true,
		);
		$descriptor['LinkDesc'] = array(
				'type' => 'text',
				'id' => 'wpLinkDesc',
				'label-message' => 'link_desc',
				'size' => 60,
				'default' => $this->mLinkDesc,
		);
		$descriptor['BasePage'] = array(
			'type' => 'hidden',
			'id' => 'action-link',
			'default' => $this->title->getPrefixedText(),
		);
		$descriptor['Action'] = array(
			'type' => 'hidden',
			'id' => 'action-link',
			'default' => 'link',
		);
		return $descriptor;
	}

	function tryAuthorizedSubmit() {
		if ($this->mAction === 'link') {
			return parent::tryAuthorizedSubmit();
		} else {
			return false;
		}
	}
}

?>
