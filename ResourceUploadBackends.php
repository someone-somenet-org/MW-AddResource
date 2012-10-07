<?php

/**
 * Generic function to add the comment linking back to the original title
 */
function getResourceComment() {
	global $wgResourcesCategory, $wgContLang, $wgRequest;

	$referer = $wgRequest->getText(ADD_RESOURCE_REFERER_FIELD);
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

		$referer =  $request->getText(ADD_RESOURCE_REFERER_FIELD);

		# filenames can never contain slashes, but the referer might contain them, if
		# its a subpage:
		$prefix = preg_replace('/\//', '-', $referer);
		$destName = $prefix . ' - ' . $desiredDestName;

        $init = parent::initializeFromRequest( $request );
        $this->mDesiredDestName = $destName;

        return $init;
	}

	/**
	 * Append the generic comment.
	 *
	 * @param $comment string The comment describing the change in the changelog
	 * @param $pageText string The text of the page. This string is modified
	 *	with a link back to the original article referred to by the
	 * 	ADD_RESOURCE_REFERER_FIELD variable.
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
?>
