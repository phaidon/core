<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c) 2001, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id$
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_System_Modules
 * @subpackage Mailer
 */

class Mailer_Admin extends Zikula_Controller
{
    /**
     * the main administration function
     * This function is the default function, and is called whenever the
     * module is initiated without defining arguments.  As such it can
     * be used for a number of things, but most commonly it either just
     * shows the module menu and returns or calls whatever the module
     * designer feels should be the default function (often this is the
     * view() function)
     * @author Mark West
     * @return string HTML string
     */
    public function main()
    {
        // Security check will be done in modifyconfig()
        return $this->modifyconfig();
    }

    /**
     * This is a standard function to modify the configuration parameters of the
     * module
     * @author Mark West
     * @return string HTML string
     */
    public function modifyconfig()
    {
        // security check
        if (!SecurityUtil::checkPermission('Mailer::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // create a new output object
        $renderer = Renderer::getInstance('Mailer', false);

        // assign the module mail agent types
        $renderer->assign('mailertypes', array(1 => DataUtil::formatForDisplay($this->__("PHP 'mail()' function")),
                2 => DataUtil::formatForDisplay($this->__('Sendmail message transfer agent')),
                3 => DataUtil::formatForDisplay($this->__('QMail message transfer agent')),
                4 => DataUtil::formatForDisplay($this->__('SMTP mail transfer protocol'))));

        // assign all module vars
        $renderer->assign(ModUtil::getVar('Mailer'));

        return $renderer->fetch('mailer_admin_modifyconfig.htm');
    }

    /**
     * This is a standard function to update the configuration parameters of the
     * module given the information passed back by the modification form
     * @author Mark West
     * @see Mailer_admin_updateconfig()
     * @param int mailertype Mail transport agent
     * @param string charset default character set of the message
     * @param string encoding default encoding
     * @param bool html send html e-mails by default
     * @param int wordwrap word wrap column
     * @param int msmailheaders include MS mail headers
     * @param string sendmailpath path to sendmail
     * @param int smtpauth enable SMTPAuth
     * @param string smtpserver ip address of SMTP server
     * @param int smtpport port number of SMTP server
     * @param int smtptimeout SMTP timeout
     * @param string smtpusername SMTP username
     * @param string smtppassword SMTP password
     * @return bool true if update successful
     */
    public function updateconfig()
    {
        // security check
        if (!SecurityUtil::checkPermission('Mailer::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // confirm our forms authorisation key
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Mailer','admin','main'));
        }

        // set our new module variable values
        $mailertype = (int)FormUtil::getPassedValue('mailertype', 1, 'POST');
        ModUtil::setVar('Mailer', 'mailertype', $mailertype);

        $charset = (string)FormUtil::getPassedValue('charset', ZLanguage::getEncoding(), 'POST');
        ModUtil::setVar('Mailer', 'charset', $charset);

        $encoding = (string)FormUtil::getPassedValue('encoding', '8bit', 'POST');
        ModUtil::setVar('Mailer', 'encoding', $encoding);

        $html = (bool)FormUtil::getPassedValue('html', false, 'POST');
        ModUtil::setVar('Mailer', 'html', $html);

        $wordwrap = (int)FormUtil::getPassedValue('wordwrap', 50, 'POST');
        ModUtil::setVar('Mailer', 'wordwrap', $wordwrap);

        $msmailheaders = (bool)FormUtil::getPassedValue('msmailheaders', false, 'POST');
        ModUtil::setVar('Mailer', 'msmailheaders', $msmailheaders);

        $sendmailpath = (string)FormUtil::getPassedValue('sendmailpath', '/usr/sbin/sendmail', 'POST');
        ModUtil::setVar('Mailer', 'sendmailpath', $sendmailpath);

        $smtpauth = (bool)FormUtil::getPassedValue('smtpauth', false, 'POST');
        ModUtil::setVar('Mailer', 'smtpauth', $smtpauth);

        $smtpserver = (string)FormUtil::getPassedValue('smtpserver', 'localhost', 'POST');
        ModUtil::setVar('Mailer', 'smtpserver', $smtpserver);

        $smtpport = (int)FormUtil::getPassedValue('smtpport', 25, 'POST');
        ModUtil::setVar('Mailer', 'smtpport', $smtpport);

        $smtptimeout = (int)FormUtil::getPassedValue('smtptimeout', 10, 'POST');
        ModUtil::setVar('Mailer', 'smtptimeout', $smtptimeout);

        $smtpusername = (string)FormUtil::getPassedValue('smtpusername', '', 'POST');
        ModUtil::setVar('Mailer', 'smtpusername', $smtpusername);

        $smtppassword = (string)FormUtil::getPassedValue('smtppassword', '', 'POST');
        ModUtil::setVar('Mailer', 'smtppassword', $smtppassword);

        // Let any other modules know that the modules configuration has been updated
        ModUtil::callHooks('module', 'updateconfig', 'Mailer', array('module' => 'Mailer'));

        // the module configuration has been updated successfuly
        LogUtil::registerStatus($this->__('Done! Saved module configuration.'));

        // This function generated no output, and so now it is complete we redirect
        // the user to an appropriate page for them to carry on their work
        return System::redirect(ModUtil::url('Mailer', 'admin', 'main'));
    }

    /**
     * This function displays a form to sent a test mail
     * @author Mark West
     * @return string HTML string
     */
    public function testconfig()
    {
        // security check
        if (!SecurityUtil::checkPermission('Mailer::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // create a new output object
        $renderer = Renderer::getInstance('Mailer', false);

        // Return the output that has been generated by this function
        return $renderer->fetch('mailer_admin_testconfig.htm');
    }

    /**
     * This function processes the results of the test form
     * @author Mark West
     * @param string args['toname '] name to the recipient
     * @param string args['toaddress'] the address of the recipient
     * @param string args['subject'] message subject
     * @param string args['body'] message body
     * @param int args['html'] HTML flag
     * @return bool true
     */
    public function sendmessage($args)
    {
        // security check
        if (!SecurityUtil::checkPermission('Mailer::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        $toname = (string)FormUtil::getPassedValue('toname', isset($args['toname']) ? $args['toname'] : null, 'POST');
        $toaddress = (string)FormUtil::getPassedValue('toaddress', isset($args['toaddress']) ? $args['toaddress'] : null, 'POST');
        $subject = (string)FormUtil::getPassedValue('subject', isset($args['subject']) ? $args['subject'] : null, 'POST');
        $body = (string)FormUtil::getPassedValue('body', isset($args['body']) ? $args['body'] : null, 'POST');
        $altBody = (string)FormUtil::getPassedValue('altbody', isset($args['altbody']) ? $args['altbody'] : null, 'POST');
        $System::mail = (bool)FormUtil::getPassedValue('System::mail', isset($args['System::mail']) ? $args['System::mail'] : false, 'POST');
        $html = (bool)FormUtil::getPassedValue('html', isset($args['html']) ? $args['html'] : false, 'POST');

        // confirm our forms authorisation key
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Mailer','admin','main'));
        }

        // set the email
        if ($System::mail) {
            $from = System::getVar('adminmail');
            $result = System::mail($toaddress, $subject, $body, "From: $from\nX-Mailer: PHP/" . phpversion(), $html, $altBody);
        } else {
            $result = ModUtil::apiFunc('Mailer', 'user', 'sendmessage',
                    array('toname' => $toname,
                    'toaddress' => $toaddress,
                    'subject' => $subject,
                    'body' => $body,
                    'altbody' => $altBody,
                    'html' => $html));
        }

        // check our result and return the correct error code
        if ($result === true) {
            // Success
            LogUtil::registerStatus($this->__('Done! Message sent.'));
        } elseif ($result === false) {
            // Failiure
            LogUtil::registerError($this->__f('Error! Could not send message. %s', ''));
        } else {
            // Failiure with error
            LogUtil::registerError($this->__f('Error! Could not send message. %s', $result));
        }

        // This function generated no output, and so now it is complete we redirect
        // the user to an appropriate page for them to carry on their work
        return System::redirect(ModUtil::url('Mailer', 'admin', 'main'));
    }
}