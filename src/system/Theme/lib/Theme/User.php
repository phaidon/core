<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c) 2004, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id$
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_System_Modules
 * @subpackage Theme
 */

class Theme_User extends Zikula_Controller
{
    /**
     * display theme changing user interface
     */
    public function main()
    {
        // check if theme switching is allowed
        if (!System::getVar('theme_change')) {
            return LogUtil::registerError($this->__('Notice: Theme switching is currently disabled.'));
        }

        if (!SecurityUtil::checkPermission('Theme::', '::', ACCESS_COMMENT)) {
            return LogUtil::registerPermissionError();
        }

        // Create output object
        $renderer = Renderer::getInstance('Theme');

        // get some use information about our environment
        $currenttheme = ThemeUtil::getInfo(ThemeUtil::getIDFromName(UserUtil::getTheme()));

        // get all themes in our environment
        $themes = ThemeUtil::getAllThemes(ThemeUtil::FILTER_USER);

        $previewthemes = array();
        $currentthemepic = null;
        foreach ($themes as $themeinfo) {
            $themename = $themeinfo['name'];
            if (file_exists($themepic = 'themes/'.DataUtil::formatForOS($themeinfo['directory']).'/images/preview_medium.png')) {
                $themeinfo['previewImage'] = $themepic;
            }
            else {
                $themeinfo['previewImage'] = 'system/Theme/images/preview_medium.png';
            }
            $previewthemes[$themename] = $themeinfo;
            if ($themename == $currenttheme['name']) {
                $currentthemepic = $themepic;
            }
        }

        $renderer->assign('currentthemepic', $currentthemepic);
        $renderer->assign('currenttheme', $currenttheme);
        $renderer->assign('themes', $previewthemes);
        $renderer->assign('defaulttheme', ThemeUtil::getInfo(ThemeUtil::getIDFromName(System::getVar('Default_Theme'))));

        // Return the output that has been generated by this function
        return $renderer->fetch('theme_user_main.htm');
    }

    /**
     * reset the current users theme to the site default
     */
    public function resettodefault()
    {
        ModUtil::apiFunc('Theme', 'user', 'resettodefault');
        LogUtil::registerStatus($this->__('Done! Theme has been reset to the default site theme.'));
        return System::redirect(ModUtil::url('Theme'));
    }
}