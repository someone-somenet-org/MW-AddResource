<?php

// this seems to have no effect?
$wgSpecialPages[ 'AddResource' ] = 'AddResource';

/**
 * Entry point
 */
function wfSpecialAddResource ($par) {
	global $wgOut;
	$page = new AddResource();
	$page->execute($par);
}

function addBanner( $text, $div_id = 'random banner' ) {
	$s = '<div id="' . $div_id . '">';
	$s .= '<table align="center" border="0" cellpadding="5" cellspacing="2"';
	$s .= '    style="border: 1px solid #FFA4A4; background-color: #FFF3F3; border-left: 5px solid #FF6666">';
	$s .= '<tr><td style=font-size: 95%;>';
	$s .= $text;
	$s .= '</td></tr></table></div>';
	return $s;
}

// actual class
class AddResource extends SpecialPage
{
	function AddResource() {
		global $wgOut;
		self::loadMessages();
		SpecialPage::SpecialPage( wfMsg('addresource_title') );
	}

	function execute( $par ) {
		global $wgOut, $wgRequest, $wgUser, $wgEnableExternalRedirects;
		$skin = $wgUser->getSkin();

		$this->setHeaders();

		/* make a Title object from $par */
		if ( $par )
			$title = Title::newFromText( $par );
		else { /* if nothing was specified */
			$wgOut->addWikiText(wfMsg('noParameterHelp'));
			return;
		}
	
		/* redirect to new subpage */
		if ( ($new_subpage = $wgRequest->getVal('new_subpage')) != '' && $par->exists() ) {
			$redir = Title::newFromText( $par . '/' . $new_subpage);
			if ( $redir->exists() )
				$wgOut->redirect($redir->getFullURL() );
			else
				$wgOut->redirect($redir->getFullURL() . '?action=edit' );
		}

		$pageTitle = $title->getFullText();
		$wgOut->addWikiText( wfMsg('header', $pageTitle) );

		/* This will hopefully one day automatically add an ExternalRedirect. */
		if ( $wgEnableExternalRedirects == True ) {
			$externalLinkURL = $wgRequest->getVal('externalLinkURL');
			$externalLinkTitle = $wgRequest->getVal('externalLinkTitle');
			if ($externalLinkURL != '' and $externalLinkTitle != '' ) {
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
#	. wfMsg( 'article_not_exists_1', $pageTitle ) .  ));
#			$wgOut->addWikiText(wfMsg( 'article_not_exists_2' ), false);
		}
		
		$wgOut->addWikiText( wfMsg('explanation', $pageTitle ) );

		/* add the various chapters */
		$this->upload($title, $skin);
		if ( $title->exists() ) 
			$this->subpage($title);
		if ( $wgEnableExternalRedirects == True )
			$this->link($title, $preloadURL, $preloadTitle);
	}

	/* the upload chapter */
	function upload($title, $skin) {
		global $wgOut;
		$wgOut->addWikiText( wfMsg('upload_header') );
		$wgOut->addWikiText( wfMsg('upload_exp') );
		// note that this will change in the future:
		$wgOut->addHTML( wfMsg('upload_pretext') . $skin->makeKnownLink( wfMsg('upload_page'), wfMsg('upload_linktext'), 'summary=%5B%5B' . $title->getPrefixedDBkey() . '%5D%5D') );
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
	function link ( $title, $preloadURL = '', $preloadTitle = '' ) {
		global $wgOut;
		$wgOut->addWikiText( wfMsg('link_header') );
		$wgOut->addWikiText('\'\'\'STILL NON-FUNCTIONAL\'\'\'');
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

	}

	/* internationalization stuff */
	function loadMessages() {
		static $messagesLoaded = false;
		global $wgMessageCache;
		if ( $messagesLoaded ) return;
		$messagesLoaded = true;

		require( dirname( __FILE__ ) . '/AddResource.i18n.php' );
		foreach ( $allMessages as $lang => $langMessages ) {
			$wgMessageCache->addMessages( $langMessages, $lang );
		}
	}
}

?>
