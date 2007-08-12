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

// actual class
class AddResource extends SpecialPage
{
	function AddResource() {
		global $wgOut;
		self::loadMessages();
		SpecialPage::SpecialPage( wfMsg('addresource_title') );
	}

	function execute( $par ) {
		global $wgOut, $wgRequest, $wgUser;
		$skin = $wgUser->getSkin();

		$this->setHeaders();

		/* redirect to new subpage */
		if ( ($new_subpage = $wgRequest->getVal('new_subpage')) != '' ) {
			$redir = Title::newFromText( $par . '/' . $new_subpage);
			if ( $redir->exists() )
				$wgOut->redirect($redir->getFullURL() );
			else
				$wgOut->redirect($redir->getFullURL() . '?action=edit' );
		}

		/* make a Title object from $par */
		if ( $par )
			$title = Title::newFromText( $par );
		else { /* if nothing was specified */
			$wgOut->addWikiText(wfMsg('noParameterHelp'));
			return;
		}
		
		$pageTitle = $title->getFullText();

		$wgOut->addWikiText( wfMsg('header', $pageTitle) );

		/* display a Banner if article doesn't exist: */
		if ( ! $title->exists() ) {
			$wgOut->addHTML('<div id="article_not_exists">
<table align="center" border="0" cellpadding="5" cellspacing="2" style="border: 1px solid #FFA4A4; background-color: #FFF3F3; border-left: 5px solid #FF6666">
<tr><td style=font-size: 95%;>' . wfMsg( 'article_not_exists_1', $pageTitle ) . $skin->makeBrokenLink($pageTitle, 'create the page', 'action=edit' ));
			$wgOut->addWikiText(wfMsg( 'article_not_exists_2' ), false);
			$wgOut->addHTML( '</td></tr></table></div>');
		}
		
		$wgOut->addWikiText( wfMsg('explanation', $pageTitle ) );

		/* add the various chapters */
		$this->upload($title, $skin);
		if ( $title->exists() ) 
			$this->subpage($title);
		$this->link($title);
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
	function link ($title) {
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
		$wgOut->addHTML('  <td><input type="text" name="new_link_url"></td>');
		$wgOut->addHTML('  <td><input type="text" name="new_link_title"></td>');
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
