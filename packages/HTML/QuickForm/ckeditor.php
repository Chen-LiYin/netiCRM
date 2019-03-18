<?php

require_once('HTML/QuickForm/textarea.php');

/**
 * HTML Quickform element for CKeditor
 *
 * CKeditor is a WYSIWYG HTML editor which can be obtained from
 * http://ckeditor.com. I tried to resemble the integration instructions
 * as much as possible, so the examples from the docs should work with this one.
 * 
 * @author       Kurund Jalmi 
 * @access       public
 */
class HTML_QuickForm_CKeditor extends HTML_QuickForm_textarea
{
    /**
     * The width of the editor in pixels or percent
     *
     * @var string
     * @access public
     */
    var $width = '94%';
    
    /**
     * The height of the editor in pixels or percent
     *
     * @var string
     * @access public
     */
    var $height = '400';
            
    /**
     * Class constructor
     *
     * @param   string  FCKeditor instance name
     * @param   string  FCKeditor instance label
     * @param   array   Config settings for FCKeditor
     * @param   string  Attributes for the textarea
     * @access  public
     * @return  void
     */
    function __construct($elementName=null, $elementLabel=null, $attributes=null, $options=array())
    {
        parent::__construct($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_type = 'CKeditor';
        // set editor height smaller if schema defines rows as 4 or less
        if ( is_array($attributes) && array_key_exists( 'rows', $attributes ) && $attributes['rows'] <= 4 ) {
            $this->height = 175;
        }
    }    

    /**
     * Add js to to convert normal textarea to ckeditor
     *
     * @access public
     * @return string
     */
    function toHtml()
    {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
            $perm = CRM_Core_Permission::check('access CiviCRM');
            $name = $this->getAttribute('name');
            $fullPage = $this->getAttribute('fullpage');
            if($perm) {
              $allowedContent = "editor.config.allowedContent = true;";
              $allowedCkeditor = TRUE;
            }
            else{
              $allowedContent = "editor.config.allowedContent = 'h1 h2 h3 p blockquote; strong em; a[!href]; img(left,right)[!src,alt,width,height];';";
              $allowedCkeditor = FALSE;
            }
            if ($fullPage) {
              $fullPage = "editor.config.fullPage = true;";
            }
            else {
              $fullPage = "editor.config.fullPage = false;";
            }
            if ($allowedCkeditor) {
              $html = parent::toHtml() . "<script type='text/javascript'>
                cj( function( ) {
                    if (cj('#{$name}').hasClass('ckeditor-processed')) {
                      return;
                    }
                    else {
                      cj('#{$name}').addClass('ckeditor-processed');
                    }
                    CKEDITOR.replace('{$name}');
                    var editor = CKEDITOR.instances['{$name}'];
                    if ( editor ) {
                        editor.on( 'key', function( evt ){
                            global_formNavigate = false;
                        } );
                        editor.config.width  = '".$this->width."';
                        editor.config.height = '".$this->height."';
                        ".$allowedContent."
                        ".$fullPage."
                    }
                }); 
            </script>";
            }
            else {
              $html = parent::toHtml();
            }
            return $html;
        }
    }
    
    /**
     * Returns the htmlarea content in HTML
     * 
     * @access public
     * @return string
     */
    function getFrozenHtml()
    {
        return $this->getValue();
    }
}

?>
