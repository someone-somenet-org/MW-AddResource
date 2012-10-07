<?php

/**
 * The AddResource special page
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
        parent::__construct('AddResource');
        wfLoadExtensionMessages('AddResource');

        global $wgRequest;
        $this->loadRequest(is_null($request) ? $wgRequest : $request);
    }

    private function loadRequest($request) {
        global $wgUser;
        $this->mRequest = $request;
        $this->mAction = $request->getInt(ADD_RESOURCE_ACTION_FIELD);

        switch ($this->mAction) {
        case ADD_RESOURCE_ACTION_UPLOAD;
            $this->mUpload = UploadBase::createFromRequest($request);
            # used by copied processUpload()
            $this->mUploadClicked = true;
            $this->mComment = $request->getVal('wpUploadDescription');
            break;
        case ADD_RESOURCE_ACTION_SUBPAGE;
            $this->mSubpageDest = $request->getVal('wpSubpageDest');
            break;
        case ADD_RESOURCE_ACTION_LINK:
            $this->mLinkUrl = $request->getVal('wpLinkUrl');
            $this->mLinkTitle = $request->getVal('wpLinkTitle');
            $this->mLinkDesc = $request->getVal('wpLinkDesc');
            break;
        default:
            break;
        };

        $this->mTokenOk = $wgUser->matchEditToken(
            $request->getVal('wpEditToken')
        );
        $this->mCancelUpload = false;
    }

    private function addSectionHeader($message, $class) {
        global $wgOut;
        $wgOut->wrapWikiMsg("<h2 class='mw-addresourcesection' id='mw-addresource-$class'>$1</h2>", $message);
    }

    /**
     * this is the main worker function that calls all other functions,
     * also depending on HTTP-variables (?foo=something). After this
     * function you have a complete special page...
     */
    function execute($par) {
        global $wgOut, $wgRequest, $wgUser, $wgEnableUploads, $wgEnableExternalRedirects;
        $skin = $wgUser->getSkin();
        $this->setHeaders();

        /* make a Title object from $par */
        if ($par) {
            $this->targetTitle = Title::newFromText($par);
            $this->param = $par;
        } else { /* if nothing was specified */
            $wgOut->addWikiText(wfMsg('noParameterHelp'));
            return;
        }

        /* header text, title */
        $wgOut->setPagetitle(wfMsg('addResourcesPageTitle', $this->targetTitle->getPrefixedText()));
        $wgOut->addWikiText(
            wfMsg('explanation',
                $this->targetTitle->getFullText(),
                SpecialPage::getTitleFor('Resources'),
                wfMsg('upload_header'),
                wfMsg('subpage_header'),
                wfMsg('link_header')
            )
        );

        # If we are not allowed to do *anything*, we display a red warning message.
        if (!($wgUser->isAllowed('edit') && $wgUser->isAllowed('createpage'))
                && ! $wgUser->isAllowed('upload')) {
            if ($wgUser->isLoggedIn())
                $wgOut->addHTML(getBanner(wfMsg('not_allowed')));
            else {
                $loginPage = $this->getLoginLink(wfMsg('login_text'));
                $wgOut->addHTML(getBanner(wfMsg('not_allowed_anon', $loginPage)));
            }
            return;
        }

        /* add the various chapters */
        if ($wgEnableUploads == True)
            $this->uploadChapter();
        if ($this->targetTitle->exists())
            $this->subpageChapter();
        if ($wgEnableExternalRedirects == True)
            $this->linkChapter();
    }

    /**
     * Display the upload chapter.
     *
     * Parts of this function are a 1:1 copy of SpecialUpload::execute() found
     * in includes/specials/SpecialUpload.php. See inline-comments for exact
     * details.
     */
    private function uploadChapter() {
        global $wgOut, $wgUser;

        # Unsave the temporary file in case this was a cancelled upload
        if ($this->mCancelUpload) {
            if (!$this->unsaveUploadedFile()) {
                # Something went wrong, so unsaveUploadedFile showed a warning
                return;
            }
        }

        # we need a header no matter what:
        $this->addSectionHeader('upload_header', 'upload');
        global $wgRequest;

        /**
         * Start copy of SpecialUpload::execute()
         */
        if (
                $this->mTokenOk && !$this->mCancelUpload &&
                ($this->mUpload && $this->mUploadClicked)
        ) {
            $this->processUpload();
        } else {
            # check if we are allowed to upload:
            if (! $wgUser->isAllowed('upload')) {
                $link = $this->getLoginLink(wfMsg('login_text'));
                $this->addWarning(wfMsg('upload_not_allowed', $link),
                                  'upload_not_allowed');
                return;
            }

            $this->showUploadForm($this->getUploadForm());
        }

        # Cleanup
        if ($this->mUpload) {
            $this->mUpload->cleanupTempFile();
        }
        /**
         * END copy of SpecialUpload::execute()
         */
    }

    /**
     * Implementation of getUploadForm()
     *
     * TODO: merge functionaility from upstream
     */
    protected function getUploadForm($message = '', $sessionKey = '', $hideIgnoreWarning = false) {
        $form = new UploadFileForm($this->targetTitle);
        $form->setTitle($this->getTitle($this->targetTitle));

        # display any upload error
        $form->addPreText($message);

        return $form;
    }

    /**
     * Implementation of showUploadForm()
     *
     * TODO: merge functionality from upstream
     */
    protected function showUploadForm($form) {
        $form->show();
    }

    /**
     * This functionis a 1:1 copy of class SpecialUpload found in
     * includes/specials/SpecialUpload.php, version 1.19.2. The only
     * difference is the different redirect at the end.
     */
    private function processUpload() {
        // Fetch the file if required
        $status = $this->mUpload->fetchFile();
        if (!$status->isOK()) {
            $this->showUploadError($this->getOutput()->parse($status->getWikiText()));
            return;
        }

        if (!wfRunHooks('UploadForm:BeforeProcessing', array(&$this))) {
            wfDebug("Hook 'UploadForm:BeforeProcessing' broke processing the file.\n");
            // This code path is deprecated. If you want to break upload processing
            // do so by hooking into the appropriate hooks in UploadBase::verifyUpload
            // and UploadBase::verifyFile.
            // If you use this hook to break uploading, the user will be returned
            // an empty form with no error message whatsoever.
            return;
        }

        // Upload verification
        $details = $this->mUpload->verifyUpload();
        if ($details['status'] != UploadBase::OK) {
            $this->processVerificationError($details);
            return;
        }

        // Verify permissions for this title
        $permErrors = $this->mUpload->verifyTitlePermissions($this->getUser());
        if ($permErrors !== true) {
            $code = array_shift($permErrors[0]);
            $this->showRecoverableUploadError(wfMsgExt($code,
                    'parseinline', $permErrors[0]));
            return;
        }

        $this->mLocalFile = $this->mUpload->getLocalFile();

        // Check warnings if necessary
        if (!$this->mIgnoreWarning) {
            $warnings = $this->mUpload->checkWarnings();
            if ($this->showUploadWarning($warnings)) {
                return;
            }
        }

        // Get the page text if this is not a reupload
        if (!$this->mForReUpload) {
            $pageText = self::getInitialPageText($this->mComment, $this->mLicense,
                $this->mCopyrightStatus, $this->mCopyrightSource);
        } else {
            $pageText = false;
        }
        $status = $this->mUpload->performUpload($this->mComment, $pageText, $this->mWatchthis, $this->getUser());
        if (!$status->isGood()) {
            $this->showUploadError($this->getOutput()->parse($status->getWikiText()));
            return;
        }

        // Success, redirect to description page
        $this->mUploadSuccessful = true;
        //wfRunHooks('SpecialUploadComplete', array(&$this));
        //$this->getOutput()->redirect($this->mLocalFile->getTitle()->getFullURL());

        /**
         * The previous two lines are in the original function. We don't need
         * the hook and we don't need the redirect.
         */
    }

    /**
     * This functionis a 1:1 copy of class SpecialUpload found in
     * includes/specials/SpecialUpload.php, version 1.19.2.
     */
    protected function processVerificationError($details) {
        global $wgFileExtensions;

        switch($details['status']) {

            /** Statuses that only require name changing **/
            case UploadBase::MIN_LENGTH_PARTNAME:
                $this->showRecoverableUploadError(wfMsgHtml('minlength1'));
                break;
            case UploadBase::ILLEGAL_FILENAME:
                $this->showRecoverableUploadError(wfMsgExt('illegalfilename',
                    'parseinline', $details['filtered']));
                break;
            case UploadBase::FILENAME_TOO_LONG:
                $this->showRecoverableUploadError(wfMsgHtml('filename-toolong'));
                break;
            case UploadBase::FILETYPE_MISSING:
                $this->showRecoverableUploadError(wfMsgExt('filetype-missing',
                    'parseinline'));
                break;
            case UploadBase::WINDOWS_NONASCII_FILENAME:
                $this->showRecoverableUploadError(wfMsgExt('windows-nonascii-filename',
                    'parseinline'));
                break;

            /** Statuses that require reuploading **/
            case UploadBase::EMPTY_FILE:
                $this->showUploadError(wfMsgHtml('emptyfile'));
                break;
            case UploadBase::FILE_TOO_LARGE:
                $this->showUploadError(wfMsgHtml('largefileserver'));
                break;
            case UploadBase::FILETYPE_BADTYPE:
                $msg = wfMessage('filetype-banned-type');
                if (isset($details['blacklistedExt'])) {
                    $msg->params($this->getLanguage()->commaList($details['blacklistedExt']));
                } else {
                    $msg->params($details['finalExt']);
                }
                $msg->params($this->getLanguage()->commaList($wgFileExtensions),
                    count($wgFileExtensions));

                // Add PLURAL support for the first parameter. This results
                // in a bit unlogical parameter sequence, but does not break
                // old translations
                if (isset($details['blacklistedExt'])) {
                    $msg->params(count($details['blacklistedExt']));
                } else {
                    $msg->params(1);
                }

                $this->showUploadError($msg->parse());
                break;
            case UploadBase::VERIFICATION_ERROR:
                unset($details['status']);
                $code = array_shift($details['details']);
                $this->showUploadError(wfMsgExt($code, 'parseinline', $details['details']));
                break;
            case UploadBase::HOOK_ABORTED:
                if (is_array($details['error'])) { # allow hooks to return error details in an array
                    $args = $details['error'];
                    $error = array_shift($args);
                } else {
                    $error = $details['error'];
                    $args = null;
                }

                $this->showUploadError(wfMsgExt($error, 'parseinline', $args));
                break;
            default:
                throw new MWException(__METHOD__ . ": Unknown value `{$details['status']}`");
        }
    }

    /* the subpage chapter */
    private function subpageChapter() {
        global $wgOut, $wgUser;

        $this->addSectionHeader('subpage_header', 'subpage');

        # check if we are allowed to create subpages:
        if (!($wgUser->isAllowed('edit') && $wgUser->isAllowed('createpage'))) {
            $link = $this->getLoginLink(wfMsg('login_text'));
            $wgOut->addHTML(getBanner(wfMsg('createpage_not_allowed', wfMsg('subpages'), $link),
                'createpage_not_allowed', 'grey'));
            return;
        }

        $form = new SubpageForm($this->mAction, $this->targetTitle, array(
            'dest' => $this->mSubpageDest,
        ));
        $form->setTitle($this->getTitle($this->targetTitle));
        if ($this->mAction != 'subpage') {
#            $form->setMethod('get');
        }
        $form->show();
    }

    /* the link chapter */
    private function linkChapter() {
        global $wgOut, $wgUser;
        $this->addSectionHeader('link_header', 'link');

        # check if we are allowed to create subpages:
        if (!($wgUser->isAllowed('edit') && $wgUser->isAllowed('createpage'))) {
            $link = $this->getLoginLink(wfMsg('login_text'));
            $wgOut->addHTML(getBanner(wfMsg('createpage_not_allowed', wfMsg('links'),  $link),
                'createpage_not_allowed', 'grey'));
            return;
        }

        $form = new ExternalRedirectForm($this->mAction, $this->targetTitle, array(
            'desturl'   => $this->mLinkUrl,
            'desttitle' => $this->mLinkTitle,
            'destdesc'  => $this->mLinkDesc
        ));
        $form->setTitle($this->getTitle($this->targetTitle));
        if ($this->mAction != 'link') {
        }
        $form->show();
    }

    function getLoginLink($login_text) {
        global $wgUser;
        $skin = $wgUser->getSkin();
        $userlogin = SpecialPage::getTitleFor('Userlogin');
        $userlogin = $userlogin->getPrefixedText();
        $loginPage = $skin->makeKnownLink($userlogin,
            $login_text, 'returnto=' . wfMsg('addresourcePage')
            . '/' . $this->param);
        return $loginPage;
    }

    /**
     * TODO: deprecate theis function inf favour of getError()
     */
    private function addError($msg, $id = 'error') {
        global $wgOut;
        $wgOut->addHTML(getBanner($msg, $id, 'red'));
    }

    /**
     * TODO: deprecate theis function inffavour of getWarning()
     */
    private function addWarning($msg, $id = 'warning') {
        global $wgOut;
        $wgOut->addHTML(getBanner($msg, $id, 'grey'));
    }

    /**
     * TODO: deprecate theis function inf favour of getNotification()
     */
    private function addNotification($msg, $id = 'notification') {
        global $wgOut;
        $wgOut->addHTML(getBanner($msg, $id, 'green'));
    }

    protected function getError($msg) {
        return getBanner($msg, 'error', 'red');
    }

    protected function getWarning($msg) {
        return getBanner($msg, 'error', 'grey');
    }

    protected function getNotification($msg) {
        return getBanner($msg, 'error', 'green');
    }

    /**
     * Wrapper-functions for addError, used by various copied functions
     */
    private function showUploadError($msg) {
        $this->showUploadForm($this->getUploadForm($this->getError($msg)));
    }

    private function showUploadWarning($msg) {
        $this->showUploadForm($this->getUploadForm($this->getWarning($msg)));
    }

    /**
     * This function returns the text used in the description of a newly
     * uploaded file.
     *
     * TODO: Really implement this function
     */
    protected function getInitialPageText($comment, $license, $copyrightStatus, $copyrightSource) {
        return $comment;
    }
}

?>
