<?php

/**
 * Entry point
 */
function wfSpecialAddResource ($par) {
	global $wgOut;
	$page = new AddResource();
	$page->execute($par);
}

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
	} 

	$s .= '<tr><td style=font-size: 95%;>';
	$s .= $text;
	$s .= '</td></tr></table></div>';
	return $s;
}

/**
 * actual class...
 */
class AddResource extends SpecialPage
{
	/**
	 * constructor, only does the basic stuff...
	 */
	function AddResource() {
		self::loadMessages();
		SpecialPage::SpecialPage( wfMsg('addresource') );
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
		if ( $par )
			$title = Title::newFromText( $par );
		else { /* if nothing was specified */
			$wgOut->addWikiText(wfMsg('noParameterHelp'));
			return;
		}
	
		$pageTitle = $title->getFullText();
		$wgOut->addWikiText( wfMsg('header', $pageTitle) );

		# a little user-check:
		if ( ! $wgUser->isAllowed('edit') ) {
			if ( $wgUser->isLoggedIn() )
				$wgOut->addHTML( addBanner( wfMsg('not_allowed') ) );
			else {
				$loginPage = $skin->makeKnownLink( wfMsg('login_page'),
                                                wfMsg('login_text'), 'returnto=' . wfMsg('addresourcePage')
						. '/' . $par );
				$wgOut->addHTML( addBanner( wfMsg('not_allowed_anon', $loginPage)) );
			}
			return;
		}

		/* redirect to new subpage */
		if ( ($new_subpage = $wgRequest->getVal('new_subpage')) != '' && $title->exists() ) {
			$redir = Title::newFromText( $par . '/' . $new_subpage);
			if ( $redir->exists() )
				$wgOut->redirect($redir->getFullURL() );
			else
				$wgOut->redirect($redir->getFullURL() . '?action=edit' );
		}

			
		/* This automatically adds an ExternalRedirect. */
		if ( $wgEnableExternalRedirects == True ) {
			$externalLinkURL = $wgRequest->getVal('externalLinkURL');
			$externalLinkTitle = $wgRequest->getVal('externalLinkTitle');
			if ($externalLinkURL != '' and $externalLinkTitle != '' ) {
				$newTitle = Title::NewFromText( $par . '/' . $externalLinkTitle );
				if ( $newTitle->exists() ) {
					# article already exists!
					$editPage = $skin->makeKnownLink( $newTitle->getFullText(),
						wfMsg('link_title_exists_1'), 'action=edit');
					$listSubpages = $skin->makeKnownLink( wfMsg('resources_page') . '/' .
						$pageTitle, wfMsg('link_title_exists_2'), 'showAllSubpages=true');

					$wgOut->addHTML( addBanner( wfMsg('link_title_exists', $editPage, $listSubpages), 'link_title_exists' ) );
					$preloadURL = $externalLinkURL;
					$preloadTitle = $externalLinkTitle;
				} else {
					# create new article
					$newArticle = new Article( $newTitle );
					$newArticleText = '#REDIRECT [[' . $externalLinkURL . '|' . $externalLinkTitle . ']]';

					$link = $newTitle->getFullURL() . '?redirect=no';
					
					$newArticle->doEdit( $newArticleText, wfMsg('commit_message', $link, $externalLinkURL), EDIT_NEW );
					$view = $skin->makeKnownLink( $newTitle->getFullText(), wfMsg('link_created_view'),
						'redirect=no');
					$edit = $skin->makeKnownLink( $newTitle->getFullText(), wfMsg('link_created_edit'),
						'action=edit');
					$gothere = $skin->makeKnownLink( $newTitle->getFullText(), wfMsg('link_created_gothere'));
					$wgOut->addHTML( addBanner( wfMsg('link_created', $view, $edit, $gothere), 'link_created', 'green' ) );
				}

# TODO: add $par/$externalLinkTitle with content '#REDIRECT [[$externalLinkURL]]'
			} elseif ( $externalLinkURL != '' and $externalLinkTitle == '') {
				$wgOut->addHTML( addBanner( wfMsg('forgot_title'), 'forgot_title') );
				$preloadURL = $externalLinkURL;
			} elseif ( $externalLinkURL == '' and $externalLinkTitle != '') {
				$wgOut->addHTML( addBanner( wfMsg('forgot_url'), 'forgot_url') );
				$preloadTitle = $externalLinkTitle;
			} 
		}
		
		/* display a Banner if article doesn't exist: */
		if ( ! $title->exists() ) {
			$message = wfMsg( 'article_not_exists', $pageTitle,
				$skin->makeBrokenLink($pageTitle, 'create the page', 'action=edit') );
			$wgOut->addHTML( addBanner( $message, 'article_not_exists') );
		}
		
		$wgOut->addWikiText( wfMsg('explanation', $pageTitle ) );

		/* add the various chapters */
		if ( $wgEnableUploads == True )
			$this->upload($title, $skin);
		if ( $title->exists() ) 
			$this->subpage($title);
		if ( $wgEnableExternalRedirects == True )
			$this->link($title, $skin, $preloadURL, $preloadTitle);
	}

	/* the upload chapter */
	function upload($title, $skin) {
		global $wgOut;
		$wgOut->addWikiText( wfMsg('upload_header') );
		$wgOut->addWikiText( wfMsg('upload_exp') );
		// note that this may change in the future:
		$wgOut->addHTML( wfMsg('upload_pretext') . $skin->makeKnownLink( wfMsg('upload_page'), wfMsg('upload_linktext'), 'referer=' . $title->getPrefixedText() ) );
		$wgOut->addWikiText( wfMsg('upload_footer') );
	}
	
	/* the subpage chapter */
	function subpage ($title) {
		global $wgOut;
		$wgOut->addWikiText( wfMsg('subpage_header') );
		$wgOut->addWikiText( wfMsg('subpage_exp', wfMsg('subpage_button')) );

		/* display input-form */
		$wgOut->addHTML('<form name=\'new_subpage\' method=\'get\'>');
		$wgOut->addHTML('  <input type=\'text\' name=\'new_subpage\'>');
		$wgOut->addHTML('  <input type=\'submit\' value=\'' . wfMsg('subpage_button')  . '\'>');
		$wgOut->addHTML('</form>');
		$wgOut->addWikiText ( wfMsg('subpage_after_exp') );
	}

	/* the link chapter */
	function link ( $title, $skin, $preloadURL = '', $preloadTitle = '' ) {
		global $wgOut;
		$wgOut->addWikiText( wfMsg('link_header') );
		$wgOut->addWikiText( wfMsg('link_exp',
					wfMsg('link_url'),
					wfMsg('link_title'),
					wfMsg('link_button')
		));

		/* display the input-form */
		$wgOut->addHTML('<form name="new_link" method="get"><table><tr>');
		$wgOut->addHTML('  <th>' . wfMsg('link_url') . ':</th>');
		$wgOut->addHTML('  <th>' . wfMsg('link_title') . ':</th>');
		$wgOut->addHTML('  <th></th></tr>');
		$wgOut->addHTML(' <tr>');
		$wgOut->addHTML('  <td><input type="text" name="externalLinkURL" value="' . $preloadURL . '"></td>');
		$wgOut->addHTML('  <td><input type="text" name="externalLinkTitle" value="' . $preloadTitle . '"></td>');
		$wgOut->addHTML('  <td><input type="submit" value="' . wfMsg('link_button') . '"></td>');
		$wgOut->addHTML(' </tr></table></form>');

		$wgOut->addHTML( wfMsg('link_footer',
			$title->getFullText(),
			$skin->makeKnownLink( wfMsg('resources_page') . '/' .
				$title->getFullText(),
				wfMsg('link_footer_linktext'), 'showAllSubpages=true') )
		); 

	}

	/* internationalization stuff */
	function loadMessages() {
		static $messagesLoaded = false;
		global $wgMessageCache;
		if ( $messagesLoaded )
			return true;
		$messagesLoaded = true;

		require( dirname( __FILE__ ) . '/AddResource.i18n.php' );
		foreach ( $allMessages as $lang => $langMessages ) {
			$wgMessageCache->addMessages( $langMessages, $lang );
		}
		return true;
	}
}

?>
