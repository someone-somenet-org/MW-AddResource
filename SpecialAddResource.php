<?php

/**
 * primitive function that returns HTML for a Banner with the given text.
 * color is either red or green, default is red.
 */
function addBanner( $text, $div_id = 'random banner', $color = 'red' ) {
	$s = '<div id="' . $div_id . '">';
	$s .= '<table align="center" border="0" cellpadding="5" cellspacing="2"';
	switch ($color) {
		case 'red':
			$s .= '    style="border: 1px solid #FFA4A4; background-color: #FFF3F3; border-left: 5px solid #FF6666">';
			break;
		case 'green':
			$s .= '    style="border: 1px solid #A4FFA4; background-color: #F3FFF3; border-left: 5px solid #66FF66">';
			break;
		case 'grey':
			$s .= '    style="border: 1px solid #BDBDBD; background-color: #E6E6E6; border-left: 5px solid #6E6E6E">';
	}

	$s .= '<tr><td style=font-size: 95%;>';
	$s .= $text;
	$s .= '</td></tr></table></div>';
	return $s;
}

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
 * This class is so far not used. It will be used as soon as
 * we have the specialized java script working...
 */
class UploadFileForm extends HTMLForm {
	private $mDesiredDestName;
	private $mForReUpload;
	private $mComment;

	public function __construct( $title, $options = array() ) {
		global $wgUser;
		$descriptor = $this->getUploadDescriptors();
		parent::__construct( $descriptor, 'addresource' );
		$this->title = $title;

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
#		}

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


/**
 * actual class...
 */
class AddResource extends SpecialPage
{
	private $mAction;
	private $mRequest;

	private $mSubpageDest;

	private $mLinkUrl;
	private $mLinkTitle;
	private $mLinkDesc;

	private $mUpload;
	private $mTokenOk;
	private $mCancelUpload;
	private $mUploadClicked;

	function __construct($request = null) {
		parent::__construct( 'AddResource' );
		wfLoadExtensionMessages('AddResource');

		global $wgRequest;
		$this->loadRequest( is_null( $request ) ? $wgRequest : $request );


	}

	private function loadRequest( $request ) {
		global $wgUser;
		$this->mRequest = $request;
		$this->mAction = $request->getVal( 'wpAction' );

		$this->mSubpageDest = $request->getVal( 'wpSubpageDest' );

		$this->mLinkUrl = $request->getVal( 'wpLinkUrl' );
		$this->mLinkTitle = $request->getVal( 'wpLinkTitle' );
		$this->mLinkDesc = $request->getVal( 'wpLinkDesc' );


#TODO: get correct value for these variables:
		$this->mUpload = UploadBase::createFromRequest( $request );
		$this->mUploadClicked = $request->wasPosted() && $this->mAction == 'upload'; # TODO: 'upload' replace by static variable #mMyAction #mAction
		$this->mTokenOk = $wgUser->matchEditToken(
			$request->getVal( 'wpEditToken' )
		);
#TODO: Is there a sensefull way to cancel?
		$this->mCancelUpload = false;
	}

	private function addSectionHeader( $message, $class ) {
		global $wgOut;
		$wgOut->wrapWikiMsg( "<h2 class='mw-addresourcesection' id='mw-addresource-$class'>$1</h2>", $message );
	}

	/**
	 * this is the main worker function that calls all other functions,
	 * also depending on HTTP-variables (?foo=something). After this
	 * function you have a complete special page...
	 */
	function execute( $par ) {
		global $wgOut, $wgRequest, $wgUser, $wgEnableUploads, $wgEnableExternalRedirects;
		$skin = $wgUser->getSkin();
		$this->setHeaders();

		/* make a Title object from $par */
		if ( $par ) {
			$this->targetTitle = Title::newFromText( $par );
			$this->param = $par;
		} else { /* if nothing was specified */
			$wgOut->addWikiText(wfMsg('noParameterHelp'));
			return;
		}

		/* header text, title */
		$wgOut->setPagetitle( wfMsg('addResourcesPageTitle', $this->targetTitle->getPrefixedText()) );
		$wgOut->addWikiText(
			wfMsg('explanation',
				$this->targetTitle->getFullText(),
				SpecialPage::getTitleFor( 'Resources' ),
				wfMsg( 'upload_header' ),
				wfMsg( 'subpage_header' ),
				wfMsg( 'link_header' )
			)
		);

		# If we are not allowed to do *anything*, we display a red warning message.
		if ( !( $wgUser->isAllowed('edit') && $wgUser->isAllowed( 'createpage' ) )
				&& ! $wgUser->isAllowed( 'upload' ) ) {
			if ( $wgUser->isLoggedIn() )
				$wgOut->addHTML( addBanner( wfMsg('not_allowed') ) );
			else {
				$loginPage = $this->getLoginLink( wfMsg( 'login_text' ) );
				$wgOut->addHTML( addBanner( wfMsg('not_allowed_anon', $loginPage)) );
			}
			return;
		}

		/* add the various chapters */
		if ( $wgEnableUploads == True )
			$this->uploadChapter();
		if ( $this->targetTitle->exists() )
			$this->subpageChapter();
		if ( $wgEnableExternalRedirects == True )
			$this->linkChapter();
	}

	private function handleUploadSubmission() {
		/* Add a banner if we successfully added a file */
		$wpDestFile = $wgRequest->getVal( 'wpDestFile' );
		if( $wpDestFile ) {
			$filename = wfStripIllegalFilenameChars($wpDestFile);
			$targetTitle = Title::makeTitleSafe( NS_IMAGE, $filename );
			$detailLink = $skin->link( $targetTitle, #Beschreibung
				wfMsg( 'file_created_details' ) );
			$directLink = $skin->makeMediaLinkObj( $targetTitle,
				wfMsg( 'file_created_download') ); #direct herunterladen

			$wgOut->addHTML( addBanner( wfMsg('file_created', $detailLink, $directLink ),
				'file_uploaded', 'green' ) );
		}
	}

	private function handleSubpageSubmission() {
		/* redirect to new subpage */
		if ( ($new_subpage = $wgRequest->getVal('new_subpage')) != '' && $title->exists() ) {
			/* replace Slashes with hyphens (slashes cause problems) */
			$new_subpage = str_replace('/', '-',$new_subpage);
			$redir = Title::newFromText( $par . '/' . $new_subpage);
			if ( $redir->exists() )
				$wgOut->redirect($redir->getFullURL() );
			else
			$new_subpage = str_replace('/', '-',$new_subpage);
				$wgOut->redirect($redir->getFullURL() . '?action=edit' );
		}
	}

	private function handleLinkSubmission() {
		/* This automatically adds an ExternalRedirect. */
		if ( $wgEnableExternalRedirects == True ) {
			$externalLinkURL = $wgRequest->getVal('externalLinkURL');
			$externalLinkTitle = $wgRequest->getVal('externalLinkTitle');
			$externalLinkDesc = $wgRequest->getVal('externalLinkDesc');
			if ($externalLinkURL != '' and $externalLinkTitle != '' ) {
				/* replace Slashes with hyphens (slashes cause problems) */
				$externalLinkTitle = str_replace('/', '-', $externalLinkTitle);
				$newTitle = Title::NewFromText( $par . '/' . $externalLinkTitle );
				if ( $newTitle->exists() ) {
					# article already exists!
					$editPage = $skin->makeKnownLink( $newTitle->getFullText(),
						wfMsg('link_title_exists_1'), 'action=edit');
					$listSubpages = $skin->makeKnownLink( wfMsg('resources_page') . '/' .
						$pageTitle, wfMsg('link_title_exists_2'), 'showAllSubpages=true');

					$wgOut->addHTML( addBanner( wfMsg('link_title_exists', $editPage, $listSubpages), 'link_title_exists' ) );
				} else {
					# create new article
					$newArticle = new Article( $newTitle );
					global $wgExternalRedirectProtocols;
					$preg_protos = '(?:' . implode( "|", $wgExternalRedirectProtocols ) .')';
					if ( ! preg_match( '/^' . $preg_protos . ':\/\//', $externalLinkURL ) ) {
						$wgOut->addHTML( addBanner( wfMsg('wrong_proto') ) );
					} else {

						$newArticleText = '#REDIRECT [[' . $externalLinkURL;
						if ( $externalLinkDesc != '' )
							$newArticleText .= '|' . $externalLinkDesc;
						$newArticleText .= ']]';

						# add a category:
						global $wgResourcesCategory;
						if ( $wgResourcesCategory != NULL && gettype($wgResourcesCategory) == "string" ) {
							global $wgContLang;
							$category_text = $wgContLang->getNSText ( NS_CATEGORY );
							$newArticleText .= "\n[[" . $category_text . ":" . $wgResourcesCategory . "]]";
						}

						$link = $newTitle->getFullURL() . '?redirect=no';

						$newArticle->doEdit( $newArticleText, wfMsg('commit_message', $link, $externalLinkURL), EDIT_NEW );
						$view = $skin->makeKnownLink( $newTitle->getFullText(), wfMsg('link_created_view'),
							'redirect=no');
						$edit = $skin->makeKnownLink( $newTitle->getFullText(), wfMsg('link_created_edit'),
							'action=edit');
						$gothere = $skin->makeKnownLink( $newTitle->getFullText(), wfMsg('link_created_gothere'));
						$wgOut->addHTML( addBanner( wfMsg('link_created', $view, $edit, $gothere), 'link_created', 'green' ) );
						$externalLinkURL = '';
						$externalLinkTitle = '';
						$externalLinkDesc = '';

					}
				}
# TODO: add $par/$externalLinkTitle with content '#REDIRECT [[$externalLinkURL]]'
			} elseif ( $externalLinkURL != '' and $externalLinkTitle == '') {
				$wgOut->addHTML( addBanner( wfMsg('forgot_title'), 'forgot_title') );
			} elseif ( $externalLinkURL == '' and $externalLinkTitle != '') {
				$wgOut->addHTML( addBanner( wfMsg('forgot_url'), 'forgot_url') );
			}
		}

		/* display a Banner if article doesn't exist: */
		if ( ! $title->exists() ) {
			$message = wfMsg( 'article_not_exists', $pageTitle,
				$skin->makeBrokenLink($pageTitle, 'create the page', 'action=edit') );
			$wgOut->addHTML( addBanner( $message, 'article_not_exists') );
		}


	}

	/* the upload chapter */
	private function uploadChapter() {
		global $wgOut, $wgUser;

		# Unsave the temporary file in case this was a cancelled upload
		if ( $this->mCancelUpload ) {
			if ( !$this->unsaveUploadedFile() ) {
				# Something went wrong, so unsaveUploadedFile showed a warning
				return;
			}
		}

		if (
				$this->mTokenOk && !$this->mCancelUpload &&
				( $this->mUpload && $this->mUploadClicked )
		) {
			$this->processUpload();
		} else {
			# we need a header no matter what:
			$this->addSectionHeader( 'upload_header', 'upload' );

			# check if we are allowed to upload:
			if ( ! $wgUser->isAllowed('upload') ) {
				$link = $this->getLoginLink( wfMsg('login_text' ));
				$wgOut->addHTML( addBanner( wfMsg( 'upload_not_allowed', $link),
					'upload_not_allowed', 'grey' ) );
				return;
			}

			$form = new UploadFileForm( $this->targetTitle );
			$form->setTitle( $this->getTitle($this->targetTitle) );
			$form->show();
		}

		# Cleanup
		if ( $this->mUpload ) {
			$this->mUpload->cleanupTempFile();
		}

	}

	private function processUpload() {
		die( 'processing upload.' );
	}

	/* the subpage chapter */
	private function subpageChapter() {
		global $wgOut, $wgUser;

		$this->addSectionHeader( 'subpage_header', 'subpage' );

		# check if we are allowed to create subpages:
		if ( ! ( $wgUser->isAllowed( 'edit' ) && $wgUser->isAllowed('createpage') ) ) {
			$link = $this->getLoginLink( wfMsg('login_text' ));
			$wgOut->addHTML( addBanner( wfMsg( 'createpage_not_allowed', wfMsg( 'subpages' ), $link ),
				'createpage_not_allowed', 'grey' ) );
			return;
		}

		$form = new SubpageForm( $this->mAction, $this->targetTitle, array(
			'dest' => $this->mSubpageDest,
		));
		$form->setTitle( $this->getTitle($this->targetTitle) );
		if ( $this->mAction != 'subpage' ) {
#			$form->setMethod( 'get' );
		}
		$form->show();
	}

	/* the link chapter */
	private function linkChapter() {
		global $wgOut, $wgUser;
		$this->addSectionHeader( 'link_header', 'link' );

		# check if we are allowed to create subpages:
		if ( ! ( $wgUser->isAllowed( 'edit' ) && $wgUser->isAllowed('createpage') ) ) {
			$link = $this->getLoginLink( wfMsg('login_text' ));
			$wgOut->addHTML( addBanner( wfMsg( 'createpage_not_allowed', wfMsg( 'links' ),  $link ),
				'createpage_not_allowed', 'grey' ) );
			return;
		}

		$form = new ExternalRedirectForm($this->mAction, $this->targetTitle, array(
			'desturl'   => $this->mLinkUrl,
			'desttitle' => $this->mLinkTitle,
			'destdesc'  => $this->mLinkDesc
		));
		$form->setTitle( $this->getTitle($this->targetTitle) );
		if ( $this->mAction != 'link' ) {
		}
		$form->show();
	}

	function getLoginLink( $login_text ) {
		global $wgUser;
		$skin = $wgUser->getSkin();
		$userlogin = SpecialPage::getTitleFor( 'Userlogin' );
		$userlogin = $userlogin->getPrefixedText();
		$loginPage = $skin->makeKnownLink( $userlogin,
			$login_text, 'returnto=' . wfMsg('addresourcePage')
			. '/' . $this->param );
		return $loginPage;
	}
}

?>
