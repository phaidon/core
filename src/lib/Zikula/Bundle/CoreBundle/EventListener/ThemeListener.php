<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */
namespace Zikula\Bundle\CoreBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Zikula\Core\Controller\AbstractController;
use Zikula\Core\Theme\AssetBag;
use Zikula\Core\Theme\Engine;
use Zikula\Core\Theme\ParameterBag;
use Zikula_View_Theme;
use Doctrine\Common\Util\ClassUtils;

class ThemeListener implements EventSubscriberInterface
{
    private $loader;
    private $themeEngine;
    private $cssAssetBag;
    private $jsAssetBag;
    private $pageVars;

    function __construct(\Twig_Loader_Filesystem $loader, Engine $themeEngine, AssetBag $jsAssetBag, AssetBag $cssAssetBag, ParameterBag $pageVars)
    {
        $this->loader = $loader;
        $this->themeEngine = $themeEngine;
        $this->jsAssetBag = $jsAssetBag;
        $this->cssAssetBag = $cssAssetBag;
        $this->pageVars = $pageVars;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        if (\System::isInstalling()) {
            return;
        }

        $response = $event->getResponse();
        $route = $event->getRequest()->attributes->has('_route') ? $event->getRequest()->attributes->get('_route') : '0'; // default must not be '_'
        if (!($response instanceof Response)
            || is_subclass_of($response, '\Symfony\Component\HttpFoundation\Response')
            || $event->getRequest()->isXmlHttpRequest()
            || $route[0] === '_' // the profiler and other symfony routes begin with '_' @todo this is still too permissive
        ) {
            return;
        }

        // all responses are assumed to be themed. PlainResponse will have already returned.
        $twigThemedResponse = $this->themeEngine->wrapResponseInTheme($response);
        if ($twigThemedResponse) {
            $event->setResponse($twigThemedResponse);
        } else {
            // theme is not a twig based theme, revert to smarty
            $smartyThemedResponse = Zikula_View_Theme::getInstance()->themefooter($response);
            $event->setResponse($smartyThemedResponse);
        }
    }

    /**
     * The ThemeEngine::requestAttributes MUST be updated based on EACH Request and not only the initial Request.
     * @param GetResponseEvent $event
     */
    public function setThemeEngineRequestAttributes(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        $this->themeEngine->setRequestAttributes($event->getRequest());
    }

    /**
     * Add all default assets to every page (scripts and stylesheets)
     * @param GetResponseEvent $event
     */
    public function setDefaultPageAssets(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        $basePath = $event->getRequest()->getBasePath();

        // add default javascripts to jsAssetBag
        $this->jsAssetBag->add(array(
            $basePath . '/web/jquery/jquery.min.js',
            $basePath . '/web/bootstrap/js/bootstrap.min.js',
            $basePath . '/javascript/helpers/bootstrap-zikula.js',
//            $basePath . '/javascript/helpers/Zikula.js', // @todo legacy remove at Core 2.0
            $basePath . '/web/bundles/fosjsrouting/js/router.js',
            $basePath . '/web/js/fos_js_routes.js',
        ));

        // add default stylesheets to cssAssetBag
        $this->cssAssetBag->add(array(
            $basePath . '/web/bootstrap-font-awesome.css',
            $basePath . '/style/core.css',
        ));
    }

    /**
     * Add default pagevar settings to every page
     * @param GetResponseEvent $event
     */
    public function setDefaultPageVars(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        // set some defaults
        $this->pageVars->set('lang', \ZLanguage::getLanguageCode());
        $this->pageVars->set('title', \System::getVar('defaultpagetitle'));
        $this->pageVars->set('meta.description', \System::getVar('defaultmetadescription'));
        $this->pageVars->set('meta.keywords', \System::getVar('metakeywords'));
    }

    /**
     * Add ThemePath to searchable paths when locating templates using name-spaced scheme
     * @param FilterControllerEvent $event
     * @throws \Twig_Error_Loader
     */
    public function setUpThemePathOverrides(FilterControllerEvent $event)
    {
        // @todo check isMasterRequest() ????
        // add theme path to template locator
        // This 'twig.loader' functions only when @Bundle/template (name-spaced) name-scheme is used
        // if old name-scheme (Bundle:template) or controller annotations (@Template) are used
        // the \Zikula\Bundle\CoreBundle\HttpKernel\ZikulaKernel::locateResource method is used instead
        $controller = $event->getController()[0];
        if ($controller instanceof AbstractController) {
            $theme = $this->themeEngine->getTheme();
            $bundleName = $controller->getName();
            if ($theme) {
                $overridePath = $theme->getPath() . '/Resources/' . $bundleName . '/views';
                if (is_readable($overridePath)) {
                    $paths = $this->loader->getPaths($bundleName);
                    // inject themeOverridePath before the original path in the array
                    array_splice($paths, count($paths) - 1, 0, array($overridePath));
                    $this->loader->setPaths($paths, $bundleName);
                }
            }
        }
    }

    /**
     * Read the controller annotations and change theme if the annotation indicate that need
     * @param FilterControllerEvent $event
     */
    public function readControllerAnnotations(FilterControllerEvent $event)
    {
        if (!$event->isMasterRequest()) {
            // prevents calling this for controller usage within a template or elsewhere
            return;
        }
        $controller = $event->getController();
        list($controller, $method) = $controller;
        // the controller could be a proxy, e.g. when using the JMSSecuriyExtraBundle or JMSDiExtraBundle
        $controllerClassName = ClassUtils::getClass($controller);
        $this->themeEngine->changeThemeByAnnotation($controllerClassName, $method);
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(
                array('setThemeEngineRequestAttributes', 32),
                array('setDefaultPageAssets', 201),
                array('setDefaultPageVars', 201),
            ),
            KernelEvents::CONTROLLER => array(
                array('readControllerAnnotations'),
                array('setUpThemePathOverrides'),
            ),
            KernelEvents::RESPONSE => array(
                array('onKernelResponse')
            ),
        );
    }
}
