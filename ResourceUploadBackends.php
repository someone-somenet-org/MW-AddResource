<?php
/**
 * This extension requires the mReferer-Patch for SpecialUpload.php.
 * You can get it at
 * 	http://svn.fsinf.at/mediawiki/patches/SpecialUpload.php/
 * 
 * Together with the patch, this extension allows you to load
 * Special:Upload with '?referer=something', which will:
 * * Prefix the user-given filename with "something_-_"
 * * append a comment linking to the page "something"
 */

$wgExtensionCredits['other'][] = array (
	'name' => 'ManipulateUpload',
	'description' => 'Manipulate the input of the Upload-Page. This extension is very customized to our environment.',
	'version' => '2.0-1.16.0',
	'author' => 'Mathias Ertl, Klaus Purer',
	'url' => 'http://fs.fsinf.at/wiki/ManipulateInput',
);

# hook definitions
$wgHooks['UploadFormInitDescriptor'][] = 'wgManipulateUploadAddReferer';
$wgHooks['UploadCreateFromRequest'][] = 'wgManipulateUploadGetUploadRequestHandler';
$wgHooks['SpecialUploadComplete'][] = 'wgManipulateUploadHandleCompletion';

/**
 * Generic function to add the comment linking back to the original title
 */
function getResourceComment() {
	global $wgResourcesCategory, $wgContLang, $wgRequest;
	
	$referer = $wgRequest->getText( 'wpReferer' );
	$pageText .= "\n\n<!-- Don't edit below this line! -->\n[[" . $referer . "]] ";
	$pageText .= "([[" . SpecialPage::getTitleFor( 'Resources' )
			. '/' . $referer . '|' . wfMsgForContent('resources') . ']])';
	if ( $wgResourcesCategory != NULL && gettype($wgResourcesCategory) == "string" ) {
		$categoryText = $wgContLang->getNSText ( NS_CATEGORY );
		$pageText .= "\n[[" . $categoryText . ":" . 
			$wgResourcesCategory . "]]";
	}
	return $pageText;
}

/**
 * Upload handler class that extends the normal UploadFromFile class to modify
 * the desired destination name and add the generic comment
 */
class UploadResourceFromFile extends UploadFromFile {
	/**
	 * Modify the desired destination name.
	 */
	function initializeFromRequest( &$request ) {
		$desiredDestName = $request->getText( 'wpDestFile' );
		if( !$desiredDestName ) {
			$desiredDestName = $request->getFileName( 'wpUploadFile' );
		}

		$referer =  $request->getText( 'wpReferer' );

		# filenames can never contain slashes, but the referer might contain them, if
		# its a subpage:
		$prefix = preg_replace('/\//', '-', $referer);
		$destName = $prefix . ' - ' . $desiredDestName;
		$request->setVal( 'wpDestFile', $destName );

		return parent::initializeFromRequest( $request );
	}

	/**
	 * Append the generic comment.
	 *
	 * @param $comment string The comment describing the change in the changelog
	 * @param $pageText string The text of the page. This string is modified
	 *	with a link back to the original article referred to by the
	 * 	wpReferer variable.
	 */	
	function performUpload( $comment, $pageText, $watch, $user ) {
		$pageText .= getResourceComment();
		return parent::performUpload( $comment, $pageText, $watch, $user );
	}
}

/**
 * This class is used when a file is uploaded via Special:AddResource and a
 * warning is displayed for some reason. In this case, Special:Upload gets
 * loaded and the file is in fact already uploaded (as temporary file).
 */
class UploadResourceFromStash extends UploadFromStash {
	/**
	 * This function just appends the generic comment
	 */
	function performUpload( $comment, $pageText, $watch, $user ) {
		$pageText .= getResourceComment();
		return parent::performUpload( $comment, $pageText, $watch, $user );
	}
}

/**
 * Sets the upload handler to our special class in case the POST data includes
 * the wpReferer value
 */
function wgManipulateUploadGetUploadRequestHandler( $type, $className ) {
	global $wgRequest;
	if ( ! $wgRequest->getText( 'wpReferer' ) ) {
		return true;
	}

	switch ( $type ) {
		case "File":
			$className = 'UploadResourceFromFile';
			break;
		case "Stash":
			$className = 'UploadResourceFromStash';
			break;
		default:
			print( "wpReferer: " . $wgRequest->getText( 'wpReferer' ) . "<br />" );
			print( "wpDestFile: "  . $wgRequest->getText( 'wpDestFile' ) . "<br />") ;
			die( $type );
	}
	return true;
}

/**
 * Adds the wpReferer input field to the upload form. This is used when the
 * destination filename already exists and Special:Upload gets displayed.
 */
function wgManipulateUploadAddReferer ( $descriptor ) {
	global $wgRequest;
	$descriptor['wpReferer'] = array(
		'type' => 'hidden',
		'id' => 'wpReferer',
		'default' => $wgRequest->getText( 'wpReferer' ),
	);
	return true;
}

/**
 * Redirect to the original AddResource page upon successfull completion.
 */
function wgManipulateUploadHandleCompletion( $page ) {
	global $wgRequest;
	$referer = ( $wgRequest->getText( 'wpReferer' ) );

	if( $referer ) {
		global $wgOut;
		$specialPage = SpecialPage::getTitleFor( 'AddResource' );
		$refererPage = Title::newFromText( $referer );
		$target = Title::newFromText( $specialPage->getPrefixedText() .
			'/' . $refererPage->getPrefixedText() );
		header( 'Location: ' . $target->getFullURL() . '?wpDestFile=' .
			$page->mDesiredDestName );
		die(); // $wgOut->redirect doesn't work for some reason :-(
	} else {
		return true;
	}
}

?>
