<?php
/**
 * Zikula Application Framework
 * @copyright (c) 2001, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id$
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_System_Modules
 * @subpackage Blocks
 */

class Blocks_Block_Lang extends Zikula_Block
{
    /**
     * initialise block
     *
     * @author       The Zikula Development Team
     */
    public function init()
    {
        // Security
        SecurityUtil::registerPermissionSchema('Languageblock::', 'Block title::');
    }

    /**
     * get information on block
     *
     * @author       The Zikula Development Team
     * @return       array       The block information
     */
    public function info()
    {
        // Requirement
        // $requirement_message must contain the error message or be empty
        $requirement_message = '';
        $multilanguageEnable = System::getVar('multilingual');
        if (!$multilanguageEnable) {
            $requirement_message .= $this->__('Notice: This language block will not be display until you enable the multilanguage, you can enable/disable this into into the settings of Zikula.');
        }

        return array('module'          => 'Blocks',
                'text_type'       => $this->__('Language'),
                'text_type_long'  => $this->__('Language selector block'),
                'allow_multiple'  => false,
                'form_content'    => false,
                'form_refresh'    => false,
                'show_preview'    => true,
                'admin_tableless' => true,
                'requirement'     => $requirement_message);
    }

    /**
     * Display the block
     *
     * @param        row           blockinfo array
     */
    public function display($blockinfo)
    {
        // security check
        if (!SecurityUtil::checkPermission('Languageblock::', "$blockinfo[title]::", ACCESS_OVERVIEW)) {
            return;
        }

        // if the site's not an ML site don't display the block
        if (!System::getVar('multilingual')) {
            return;
        }

        // Get current content
        $vars = BlockUtil::varsFromContent($blockinfo['content']);
        $vars['bid'] = $blockinfo['bid'];
        // Defaults
        if (empty($vars['format'])) {
            $vars['format'] = 2;
        }

        if (!isset($vars['languages']) || empty($vars['languages']) || !is_array($vars['languages'])) {
            $vars['languages'] = $this->getAvailableLanguages();
        }

        // Create output object - this object will store all of our output so that
        // we can return it easily when required
        $renderer = Renderer::getInstance('Blocks', false);

        // assign the block vars
        $renderer->assign($vars);

        // what's the current language
        $currentlanguage = ZLanguage::getLanguageCode();
        $renderer->assign('currentlanguage', $currentlanguage);

        // set a block title
        if (empty($blockinfo['title'])) {
            $blockinfo['title'] = $this->__('Choose a language');
        }


        // prepare vars for ModUtil::url
        $module = FormUtil::getPassedValue('module', null, 'GET');
        $type = FormUtil::getPassedValue('type', null, 'GET');
        $func = FormUtil::getPassedValue('func', null, 'GET');
        $get = $_GET;
        if (isset($get['module'])) {
            unset($get['module']);
        }
        if (isset($get['type'])) {
            unset($get['type']);
        }
        if (isset($get['func'])) {
            unset($get['func']);
        }
        if (isset($get['lang'])) {
            unset($get['lang']);
        }

        // make homepage calculations
        $shorturls = System::getVar('shorturls', false);
        $shorturlstype = System::getVar('shorturlstype');
        $dirBased = ($shorturlstype == 0 ? true : false);

        if ($shorturls && $dirBased) {
            $homepage = System::getBaseUrl().System::getVar('entrypoint', 'index.php');
            $forcefqdn = true;
        } else {
            $homepage = System::getVar('entrypoint', 'index.php');
            $forcefqdn = false;
        }

        // build URLS
        $languages = ZLanguage::getInstalledLanguages();
        $urls = array();
        foreach ($languages as $code) {
            $thisurl = ModUtil::url($module, $type, $func, $get, null, null, null, $forcefqdn, $code);
            if ($thisurl == '') {
                $thisurl = ($shorturls && $dirBased ? $code : "$homepage?lang=$code");
            }
            $codeFS = ZLanguage::transformFS($code);

            $flag = "images/flags/flag-$codeFS.png";
            if (!file_exists($flag)) {
                $flag = '';
            }

            $flag = (($flag && $shorturls && $dirBased) ? System::getBaseUrl().$flag : $flag);

            $urls[] = array('code' => $code, 'name' => ZLanguage::getLanguageName($code), 'url' => $thisurl, 'flag' => $flag);
        }
        usort($urls, '_blocks_thelangblock_sort');

        $renderer->assign('urls', $urls);

        // get the block content from the template then end the templating
        $blockinfo['content'] = $renderer->fetch('blocks_block_thelang.htm');

        // return the block to the theme
        return BlockUtil::themeBlock($blockinfo);
    }


    /**
     * modify block settings
     *
     * @author       The Zikula Development Team
     * @param        array       $blockinfo     a blockinfo structure
     * @return       output      the bock form
     */
    public function modify($blockinfo)
    {
        // Get current content
        $vars = BlockUtil::varsFromContent($blockinfo['content']);

        // Defaults
        if (empty($vars['format'])) {
            $vars['format'] = 2;
        }

        // Create output object
        // As Admin output changes often, we do not want caching.
        $renderer = Renderer::getInstance('Blocks', false);

        // assign the approriate values
        $renderer->assign($vars);

        // clear the block cache
        $renderer = Renderer::getInstance('Blocks');
        $renderer->clear_cache('blocks_block_thelang.htm');

        // Return the output that has been generated by this function
        return $renderer->fetch('blocks_block_thelang_modify.htm');
    }


    /**
     * update block settings
     *
     * @author       The Zikula Development Team
     * @param        array       $blockinfo     a blockinfo structure
     * @return       $blockinfo  the modified blockinfo structure
     */
    public function update($blockinfo)
    {
        // Get current content
        $vars = BlockUtil::varsFromContent($blockinfo['content']);

        // Read inputs
        $vars['format'] = FormUtil::getPassedValue('format');

        // Scan for languages and save cached version
        $vars['languages'] = $this->getAvailableLanguages();

        // write back the new contents
        $blockinfo['content'] = BlockUtil::varsToContent($vars);

        // clear the block cache
        $renderer = Renderer::getInstance('Blocks');
        $renderer->clear_cache('blocks_block_thelang.htm');

        return $blockinfo;
    }


    public function getAvailableLanguages()
    {
        $langlist = ZLanguage::getInstalledLanguageNames();

        $list = array();
        foreach ($langlist as $code => $langname)
        {
            $img = file_exists("images/flags/flag-$code.png");

            $list[] = array('code' => $code,
                    'name' => $langname,
                    'flag' => $img ? "images/flags/flag-$code.png" : '');
        }

        usort($list, '_blocks_thelangblock_sort');

        return $list;
    }
}

function _blocks_thelangblock_sort($a, $b)
{
    return strcmp($a['name'], $b['name']);
}