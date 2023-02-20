<?php

/**
 * Baseclass for all forms in this extension.
 */
abstract class AddResourceForm extends HTMLForm {
    protected $formAction;

    public function __construct($title) {
        $this->title = $title;

        $descriptors = $this->getDescriptors();
        parent::__construct($descriptors, 'addresource');
    }

    /**
     * Get form fields common to all fields.
     */
    protected function getCommonFields() {
        return array(
            # Remember what article we upload a resource for. This information
            # is used by our upload backends in ResourceUploadBackends.php
            ADD_RESOURCE_REFERER_NAME => array(
                'type' => 'hidden',
                'id' => ADD_RESOURCE_REFERER_FIELD,
                'default' => $this->title->getPrefixedDBkey()
            ),

            # Remember the action we performed.
            ADD_RESOURCE_ACTION_NAME => array(
                'type' => 'hidden',
                'id' => ADD_RESOURCE_ACTION_FIELD,
                'default' => $this->formAction,
            ),
        );
    }

    /**
     * Returns true if this particular form was clicked, which is decided
     * via ADD_RESOURCE_ACTION_FIELD.
     */
    function wasClicked() {
        global $wgRequest;
        $action =  $wgRequest->getInt(ADD_RESOURCE_ACTION_FIELD);
        return $wgRequest->wasPosted() && $action === $this->formAction;
    }

    /**
     * Override the function in HTMLForm to only load the data when this form
     * was actually clicked.
     *
     * We do nothing if the form wasn't clicked.
     */
    function loadData() {
        if ($this->wasClicked()) {
            parent::loadData();
        } else if ($this->mFieldData === null) {
            $this->mFieldData = [];
        }
    }

    /**
     * Override function in baseclass to only try an authorized submit if this
     * form was actually clicked.
     */
    function tryAuthorizedSubmit() {
        if ($this->wasClicked()) {
            return parent::tryAuthorizedSubmit();
        } else {
            return false;
        }
    }
}

/**
 * Superclass for creating links and subpages. Only contains common validator.
 */
abstract class PageCreationForm extends AddResourceForm {
    /**
     * Callback that validates that a page does *not* exist.
     */
    public function validatePageNotExists($value, $alldata) {
        $value = str_replace('/', '-', $value);
        $title = Title::NewFromText(
            $alldata[ADD_RESOURCE_REFERER_NAME] . '/' . $value);
        if ($title->exists()) {
            return wfMessage('page-exists')->text();
        } else {
            return true;
        }
    }
}

/**
 */
class UploadFileForm extends AddResourceForm {
    protected $formAction = ADD_RESOURCE_ACTION_UPLOAD;

    private $mDesiredDestName;
    private $mForReUpload;
    private $mComment;

    public function __construct($title, $options = array()) {
        parent::__construct($title);

        # Set some form properties
        $this->setSubmitText(wfMessage('uploadbtn')->text());
        $this->setSubmitName('submit'); #TODO: maybe interesting to get type of submission?
        # Used message keys: 'accesskey-upload', 'tooltip-upload'
        $this->setSubmitTooltip('upload');
        $this->setId('mw-upload-form');

        $image_list_link = Linker::specialLink('Listfiles', 'upload_exp_linktext');
        $this->addHeaderText(wfMessage('upload_exp', $image_list_link)->text()
        );
        $this->addPostText('<br />' . wfMessage('upload_footer',
            Linker::makeExternalLink(
                wfMessage('upload_footer_url')->text(),
                wfMessage('upload_footer_linktext')->text()
            ))->text()
        );

        $this->mSubmitCallback = array($this, 'submit');
    }

    public function submit() {
    }

    protected function getDescriptors() {
        global $wgUser, $wgLang, $wgMaxUploadSize;

        $descriptor = $this->getCommonFields();

        $size = $wgLang->formatSize(
            wfShorthandToInteger(min(
                wfShorthandToInteger(
                    ini_get('upload_max_filesize')
                ), $wgMaxUploadSize
            ))
        );
        $upload_source_file = wfMessage('upload_source_file')->escaped();

        $descriptor['UploadFile'] = array(
            'class' => 'UploadSourceField',
            'type' => 'file',
            'id' => 'wpUploadFile',
            'label-message' => 'sourcefilename',
            'upload-type' => 'File',
            'radio' => &$radio,
            'help' => wfMessage('upload-maxfilesize', $size)->text() . " $upload_source_file",
            'checked' => true, #$selectedSourceType == 'file',
        );

        $descriptor['Extensions'] = array(
            'type' => 'info',
            'default' => $this->getExtensionsMessage(),
            'raw' => true,
        );

        $descriptor['DestFile'] = array(
                'type' => 'text',
                'id' => 'wpDestFile',
                'label-message' => 'destfilename',
                'size' => 60,
                'default' => $this->mDesiredDestName,
                # FIXME: hack to work around poor handling of the 'default' option in HTMLForm
                'nodata' => strval($this->mDesiredDestName) !== '',
        );
        $descriptor['UploadDescription'] = array(
            'type' => 'textarea',
            'id' => 'wpUploadDescription',
            'label-message' => $this->mForReUpload
                ? 'filereuploadsummary'
                : 'fileuploadsummary',
            'default' => $this->mComment,
            'cols' => intval($wgUser->getOption('cols')),
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
        $descriptor['ForReUpload'] = array(
            'type' => 'hidden',
            'id' => 'wpForReUpload',
            'default' => '1',
        );

        return $descriptor;
    }

    /**
     * Get the messages indicating which extensions are preferred and prohibitted.
     *
     * Exact copy of UploadForm::getExtensionsMessage() in 1.21.1
     *
     * @return String: HTML string containing the message
     */
     protected function getExtensionsMessage() {
        # Print a list of allowed file extensions, if so configured.  We ignore
        # MIME type here, it's incomprehensible to most people and too long.
        global $wgCheckFileExtensions, $wgStrictFileExtensions,
        $wgFileExtensions, $wgFileBlacklist;

        if ($wgCheckFileExtensions) {
            if ($wgStrictFileExtensions) {
                # Everything not permitted is banned
                $extensionsList =
                    '<div id="mw-upload-permitted">' .
                    $this->msg('upload-permitted', $this->getContext()->getLanguage()->commaList($wgFileExtensions))->parseAsBlock() .
                    "</div>\n";
            } else {
                # We have to list both preferred and prohibited
                $extensionsList =
                    '<div id="mw-upload-preferred">' .
                        $this->msg('upload-preferred', $this->getContext()->getLanguage()->commaList($wgFileExtensions))->parseAsBlock() .
                    "</div>\n" .
                    '<div id="mw-upload-prohibited">' .
                        $this->msg('upload-prohibited', $this->getContext()->getLanguage()->commaList($wgFileBlacklist))->parseAsBlock() .
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
    protected $formAction = ADD_RESOURCE_ACTION_SUBPAGE;

    private $mDest;

    public function __construct($action, $title, $options = array()) {
        parent::__construct($title);

        $this->mDest = isset($options['dest']) ? $options['dest'] : '';
        $this->mSubmitCallback = array($this, 'submit');

        # Set some form properties
        $this->setSubmitText(wfMessage('subpage_button')->text());
        $this->setSubmitName('submit');
        # Used message keys: 'accesskey-upload', 'tooltip-upload'
        $this->setSubmitTooltip('create');
        $this->setId('mw-upload-form');

        $this->addHeaderText(wfMessage('subpage_exp', wfMessage('subpage_button')->text())->text());
        $this->addPostText('<br />' . wfMessage('subpage_after_exp')->text());
    }

    public function submit() {
        global $wgOut;
        $subpage = str_replace('/', '-', $this->mDest);

        $wgOut->redirect($this->title->getFullURL() . '/' . $subpage . '?action=edit');
    }

    protected function getDescriptors() {
        global $wgUser, $wgLang, $wgMaxUploadSize;

        $descriptor = $this->getCommonFields();

        $descriptor['SubpageDest'] = array(
                'type' => 'text',
                'id' => 'wpSubpageDest',
                'label-message' => 'subpage_inputTitle',
                'size' => 60,
                'default' => $this->mDest,
                'validation-callback' => array($this, 'validatePageNotExists'),
                'required' => true,
        );

        return $descriptor;
    }
}

class ExternalRedirectForm extends PageCreationForm {
    protected $formAction = ADD_RESOURCE_ACTION_LINK;

    private $mLinkUrl;
    private $mLinkTitle;
    private $mLinkDesc;

    public function __construct($action, $title, $options = array()) {
        parent::__construct($title);

        $this->mLinkUrl = isset($options['desturl']) ? $options['desturl'] : '';
        $this->mLinkTitle = isset($options['desttitle']) ? $options['desttitle'] : '';
        $this->mLinkDesc = isset($options['destdesc']) ? $options['destdesc'] : '';


        # Set some form properties
        $this->setSubmitText(wfMessage('link_button')->text());
        $this->setSubmitName('submit');
        # Used message keys: 'accesskey-upload', 'tooltip-upload'
        $this->setSubmitTooltip('create');
        $this->setId('mw-upload-form');

        $this->addHeaderText(wfMessage('link_exp', wfMessage('link_button')->text())->text());
        $this->addPostText('<br />' . wfMessage('link_footer',
            $title->getFullText(),
            Linker::linkKnown(
                SpecialPage::getTitleFor('Prefixindex', $title->getPrefixedText()),
                wfMessage('link_footer_linktext')->text(),
                array(),
                array('namespace' => $title->getNamespace())
            )
        )->text());

        $this->mSubmitCallback = array($this, 'submit');
    }

    public function submit() {
        global $wgOut;

        $subpage = str_replace('/', '-', $this->mLinkTitle);
        $title = Title::NewFromText($this->title->getPrefixedText() . '/' . $subpage);
        $article = new Article($title);

        $text = '#REDIRECT [[' . $this->mLinkUrl;
        if ($this->mLinkDesc != '')
            $text .= '|' . $this->mLinkDesc;
        $text .= ']]';

        # add a category:
        global $wgResourcesCategory;
        if ($wgResourcesCategory != NULL && gettype($wgResourcesCategory) == "string") {
            global $wgContLang;
            $category_text = $wgContLang->getNSText(NS_CATEGORY);
            $text .= "\n[[" . $category_text . ":" . $wgResourcesCategory . "]]";
        }
        $link = $title->getFullURL() . '?redirect=no';
        $article->doEditContent(ContentHandler::makeContent($text, $title), wfMessage('commit_message', $link, $this->mLinkUrl)->text(), EDIT_NEW);

        $redir = SpecialPage::getTitleFor('Resources', $this->title->getPrefixedText());
        $wgOut->redirect($redir->getFullURL() . '?highlight=' . $subpage);
    }

    public function validateUrl($value, $alldata) {
#TODO: actually validate
        return true;
    }

    protected function getDescriptors() {
        $descriptor = $this->getCommonFields();
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
        return $descriptor;
    }
}

?>
