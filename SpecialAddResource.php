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
 * This class is so far not used. It will be used as soon as
 * we have the specialized java script working...
 */
class UploadResourceForm extends UploadForm {
	function getSourceSection() {
		$arr = parent::getSourceSection();
#		foreach ( $arr as $key =>$elem ) {
#			$elem['section'] = 'description';
#			$arr[$key] = $elem;
#		}
		return $arr;
	}

	function getDescriptionSection() {
		$arr = parent::getDescriptionSection();
#		unset( $arr['License'] );
#		foreach( $arr as $key =>$elem ) {
#			$arr[$key] = $elem;
#		}
		return $arr;
	}

	function getOptionsSection() {
		$descriptor['wpDestFileWarningAck'] = array(
                        'type' => 'hidden',
                        'id' => 'wpDestFileWarningAck',
                        'default' => $this->mDestWarningAck ? '1' : '',
                );
		return $descriptor;
	}
}


/**
 * actual class...
 */
class AddResource extends SpecialPage
{

	function __construct() {
		parent::__construct( 'AddResource' );
		wfLoadExtensionMessages('AddResource');
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
			$title = Title::newFromText( $par );
			$this->param = $par;
		} else { /* if nothing was specified */
			$wgOut->addWikiText(wfMsg('noParameterHelp'));
			return;
		}
		
		$wgOut->setPagetitle( wfMsg('addResourcesPageTitle', $title->getPrefixedText() ) );
		$pageTitle = $title->getFullText();

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

		/* redirect to new subpage */
		if ( ($new_subpage = $wgRequest->getVal('new_subpage')) != '' && $title->exists() ) {
			/* replace Slashes with hyphens (slashes cause problems) */
			$new_subpage = str_replace('/', '-',$new_subpage);
			$redir = Title::newFromText( $par . '/' . $new_subpage);
			if ( $redir->exists() )
				$wgOut->redirect($redir->getFullURL() );
			else
				$wgOut->redirect($redir->getFullURL() . '?action=edit' );
		}

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
		
		$specialResourceText = SpecialPage::getTitleFor( 'Resources' );
		$wgOut->addWikiText( wfMsg('explanation', $pageTitle, $specialResourceText,
				wfMsg( 'upload_header' ),
				wfMsg( 'subpage_header' ),
				wfMsg( 'link_header' )
			) );
		
		/* add the various chapters */
		if ( $wgEnableUploads == True )
			$this->upload($title, $skin);
		if ( $title->exists() ) 
			$this->subpage($title);
		if ( $wgEnableExternalRedirects == True )
			$this->link($title, $skin, $externalLinkURL, $externalLinkTitle, $externalLinkDesc);
	}

	/* the upload chapter */
	function upload($title, $skin) {
		global $wgRequest, $wgOut, $wgContLang, $wgUser, $wgFileExtensions;
		$vars['wgFileExtensions'] = $wgFileExtensions;
		$variablesScript = Skin::makeVariablesScript( $vars ); # == makeGlobalVariablesScript
		$wgOut->addScript( $variablesScript );
		
		# we need a header no matter what:
		$imgListTitle = SpecialPage::getTitleFor( 'Imagelist' );
		$this->addSectionHeader( 'upload_header', 'upload' );
		$wgOut->addWikiText( wfMsg( 'upload_exp', $imgListTitle->getPrefixedText() ) );
		
		# check if we are allowed to upload:
		if ( ! $wgUser->isAllowed('upload') ) {
			$link = $this->getLoginLink( wfMsg('login_text' ));
			$wgOut->addHTML( addBanner( wfMsg( 'upload_not_allowed', $link), 
				'upload_not_allowed', 'grey' ) );
			return;
		}

#		# ok, we are allowed to upload:
#		$form = new UploadResourceForm();
#		
		# set the form handler:
#		$form->setTitle( SpecialPage::getTitleFor( 'Upload' ) );
#
#		$form->show();
#		return;

		# add javascript - more or less copied from UploadForm:addUploadJS:
                $scriptVars = array(
			# for now, AjaxDestCheck is disabled, because we cannot check for the filename
			# modified by the ManipulateUpload extension
                        'wgAjaxUploadDestCheck' => false,
			# we do not want to display it, so it should always be false!
                        'wgAjaxLicensePreview' => false,
                        'wgUploadAutoFill' => true,
                        'wgUploadSourceIds' => array('wpUploadFile'),
                );
                $wgOut->addScript( Skin::makeVariablesScript( $scriptVars ) );
                // For <charinsert> support
                $wgOut->addScriptFile( 'edit.js' );
		$wgOut->addScriptFile( 'upload.js' );
		
		$titleObj = SpecialPage::getTitleFor( 'Upload' );
		$action = $titleObj->escapeLocalURL();
		$align1 = $wgContLang->isRTL() ? 'left' : 'right';
		$align2 = $wgContLang->isRTL() ? 'right' : 'left';

		$sourcefilename = wfMsgHtml( 'sourcefilename' );
		$destfilename = wfMsgHtml( 'destfilename' );
		$summary = wfMsgExt( 'fileuploadsummary', 'parseinline' );
		$ulb = wfMsgHtml( 'uploadbtn' );
		$cols = intval($wgUser->getOption( 'cols' ));
		$ew = $wgUser->getOption( 'editwidth' );
		if ( $ew ) $ew = " style=\"width:100%\"";
		else $ew = '';

		$encDestName = $wgRequest->getText( 'wpDestFile' );
		$encComment = htmlspecialchars( $wgRequest->getText('wpUploadDescription') );
		$example = wfMsg( 'filename_example', date('Y-m-d') );

		$wgOut->addHTML( <<<EOT
	<form name="new_upload" id='upload' method='post' enctype='multipart/form-data' action="$action" enctype="multipart/form-data" id="mw-upload-form" >
		<table id="mw-htmlform-description" border='0'>
		<tr>
			<td align='$align1' valign='top'><label for='wpUploadFile'>{$sourcefilename}</label></td>
			<td align='$align2'><input tabindex='1' type='file' name='wpUploadFile' id='wpUploadFile' onchange='fillDestFilename("wpUploadFile")' size='40' />
		</tr>
		<tr>
			<td align='$align1' valign='top'><label for='wpDestFile'>{$destfilename}</label></td>
			<td align='$align2'>
				<input tabindex='2' type='text' name='wpDestFile' id='wpDestFile' size='40'
					value="$encDestName" $destOnkeyup />
					<br /><span style="font-size:0.8em; color:darkgrey;">$example</span>
			</td>
		</tr>
		<tr>
			<td align='$align1' style="width: 11em"><label for='wpUploadDescription'>{$summary}</label></td>
			<td align='$align2'>
				<textarea tabindex='3' name='wpUploadDescription' id='wpUploadDescription' rows='3'
					cols='{$cols}'{$ew}>$encComment</textarea>
			</td>
		</tr>
EOT
			);
		$wgOut->addHtml( "<tr>
			<td></td>
			<td align='$align2'><input tabindex='9' type='submit' name='wpUpload' value=\"{$ulb}\"" . $wgUser->getSkin()->tooltipAndAccesskey( 'upload' ) . " /></td>
		</tr>
		</table>
		<input type='hidden' name='wpDestFileWarningAck' id='wpDestFileWarningAck' value='1'/>
		<input type='hidden' name='wpIgnoreWarning' id='wpIgnoreWarning' value='1'/>
		<input type='hidden' name='wpReferer' id='wpReferer' value='" . $title->getPrefixedText() . "'/>
	</form>" );
		$wgOut->addWikiText( wfMsg('upload_footer') );
	}
	
	/* the subpage chapter */
	function subpage ($title) {
		global $wgOut, $wgContLang, $wgUser;
		
		$this->addSectionHeader( 'subpage_header', 'subpage' );
		$wgOut->addWikiText( wfMsg('subpage_exp', wfMsg('subpage_button')) );

		# check if we are allowed to create subpages:
		if ( ! ( $wgUser->isAllowed( 'edit' ) && $wgUser->isAllowed('createpage') ) ) {
			$link = $this->getLoginLink( wfMsg('login_text' ));
			$wgOut->addHTML( addBanner( wfMsg( 'createpage_not_allowed', wfMsg( 'subpages' ), $link ), 
				'createpage_not_allowed', 'grey' ) );
			return;
		}


		$align1 = $wgContLang->isRTL() ? 'left' : 'right';
		$align2 = $wgContLang->isRTL() ? 'right' : 'left';

		/* display input-form */
		$wgOut->addHTML('<form name=\'new_subpage\' method=\'get\'><table><tr>
					<td align="' . $align1 . '" style="width: 11em">' . wfMsg('subpage_inputTitle') . '</td>
					<td align="' . $align2 . '"><input size=40 type=\'text\' name=\'new_subpage\'></td>
					<td><input type=\'submit\' value=\'' . wfMsg('subpage_button')  . '\'></td>
					</tr></table></form>');
		$wgOut->addWikiText ( wfMsg('subpage_after_exp') );
	}

	/* the link chapter */
	function link ( $title, $skin, $preloadURL = '', $preloadTitle = '', $preloadDesc = '' ) {
		global $wgOut, $wgContLang, $wgUser;
		$this->addSectionHeader( 'link_header', 'link' );
		$wgOut->addWikiText( wfMsg('link_exp',
					wfMsg('link_button')
		));

		# check if we are allowed to create subpages:
		if ( ! ( $wgUser->isAllowed( 'edit' ) && $wgUser->isAllowed('createpage') ) ) {
			$link = $this->getLoginLink( wfMsg('login_text' ));
			$wgOut->addHTML( addBanner( wfMsg( 'createpage_not_allowed', wfMsg( 'links' ),  $link ), 
				'createpage_not_allowed', 'grey' ) );
			return;
		}

		$align1 = $wgContLang->isRTL() ? 'left' : 'right';
		$align2 = $wgContLang->isRTL() ? 'right' : 'left';

		/* display the input-form */
		$wgOut->addHTML('<form name="new_link" method="get"><table><tr>
					<td align="' . $align1 . '" style="width: 11em">' . wfMsg('link_url') . '</td>
					<td align="' . $align2 . '"><input type="text" name="externalLinkURL" value="' . $preloadURL . '"></td>
					</tr><tr>
					<td align="' . $align1 . '">' . wfMsg('link_title') . '</td>
					<td align="' . $align2 . '"><input type="text" name="externalLinkTitle" value="' . $preloadTitle . '"></td>
					</tr><tr>
					<td align="' . $align1 . '">' . wfMsg('link_desc') . '</td>
					<td align="' . $align2 . '"><input type="text" name="externalLinkDesc"  value="' . $preloadDesc . '"></td>
					</tr><tr>
					<td></td>
					<td><input type="submit" value="' . wfMsg('link_button') . '"></td>
					</tr></table></form>');

		$wgOut->addHTML( wfMsg('link_footer',
			$title->getFullText(),
			$skin->makeKnownLink( SpecialPage::getTitleFor( 'Prefixindex' ) . '/' .
						$title->getFullText() . '/',
					wfMsg('link_footer_linktext'),
					"namespace=" . $title->getNamespace() ) 
				)
		); 

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
