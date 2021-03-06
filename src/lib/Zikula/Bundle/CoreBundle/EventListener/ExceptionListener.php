<?php

/**
 * Copyright Zikula Foundation 2014 - Zikula Application Framework
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

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use UserUtil;
use Zikula\RoutesModule\Util\ControllerUtil;
use Zikula\Bundle\CoreBundle\CacheClearer;

/**
 * ExceptionListener catches exceptions and converts them to Response instances.
 */
class ExceptionListener implements EventSubscriberInterface
{
    private $logger;
    private $router;
    private $dispatcher;
    private $routesControllerUtil;
    private $cacheClearer;

    public function __construct(LoggerInterface $logger = null, RouterInterface $router = null, EventDispatcherInterface $dispatcher = null, ControllerUtil $util, CacheClearer $cacheClearer)
    {
        $this->logger = $logger;
        $this->router = $router;
        $this->dispatcher = $dispatcher;
        $this->routesControllerUtil = $util;
        $this->cacheClearer = $cacheClearer;
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::EXCEPTION => array(
                array('onKernelException', 31),
            )
        );
    }

    /**
     * Handles exceptions.
     *
     * @param GetResponseForExceptionEvent $event An GetResponseForExceptionEvent instance
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        // for BC only, remove in 2.0.0
        $this->handleLegacyExceptionEvent($event);

        if (!$event->getRequest()->isXmlHttpRequest()) {
            $exception = $event->getException();
            $userLoggedIn = UserUtil::isLoggedIn();
            do {
                if ($exception instanceof AccessDeniedException) {
                    $this->handleAccessDeniedException($event, $userLoggedIn, $exception->getMessage());
                } elseif ($exception instanceof RouteNotFoundException) {
                    $this->handleRouteNotFoundException($event, $userLoggedIn);
                }
                // list and handle additional exceptions here
            } while (null !== $exception = $exception->getPrevious());

            // force all exception to render in BC theme (remove in 2.0.0)
            $event->getRequest()->attributes->set('_legacy', true);
        }
    }

    /**
     * Handle an AccessDeniedException
     *
     * @param GetResponseForExceptionEvent $event
     * @param $userLoggedIn
     * @param string $message a custom error message (default: 'Access Denied') (The default message from Symfony)
     * @see http://api.symfony.com/2.6/Symfony/Component/Security/Core/Exception/AccessDeniedException.html
     */
    private function handleAccessDeniedException(GetResponseForExceptionEvent $event, $userLoggedIn, $message = 'Access Denied')
    {
        if (!$userLoggedIn) {
            $message = ($message == 'Access Denied') ? __('You do not have permission. You must login first.') : $message;
            $event->getRequest()->getSession()->getFlashBag()->add('error', $message);

            $params = array('returnpage' => urlencode($event->getRequest()->getSchemeAndHttpHost() . $event->getRequest()->getRequestUri()));
            // redirect to login page
            $route = $this->router->generate('zikulausersmodule_user_login', $params, RouterInterface::ABSOLUTE_URL);
        } else {
            $message = ($message == 'Access Denied') ? __('You do not have permission for that action.') : $message;
            $event->getRequest()->getSession()->getFlashBag()->add('error', $message);

            // redirect to previous page
            $route = $event->getRequest()->server->get('HTTP_REFERER', \System::getHomepageUrl());
        }
        // optionally add logging action here

        $response = new RedirectResponse($route);
        $event->setResponse($response);
        $event->stopPropagation();
    }

    /**
     * Handle an RouteNotFoundException
     *
     * @param GetResponseForExceptionEvent $event
     * @param $userLoggedIn
     */
    private function handleRouteNotFoundException(GetResponseForExceptionEvent $event, $userLoggedIn)
    {
        $message = $event->getException()->getMessage();
        $event->getRequest()->getSession()->getFlashBag()->add('error', $message);
        if ($userLoggedIn && \SecurityUtil::checkPermission('ZikulaRoutesModule::', '::', ACCESS_ADMIN)) {
            try {
                $url = $this->router->generate('zikularoutesmodule_route_reload', array('lct' => 'admin'), RouterInterface::ABSOLUTE_URL);
                $link = "<a href='$url'>". __('re-loading the routes') . "</a>";
                $event->getRequest()->getSession()->getFlashBag()->add('error', __f('You might try %s for the extension in question.', $link));
            } catch (RouteNotFoundException $e) {

            }
//            if (!array_key_exists('zikularoutesmodule_route_reload', $originalRouteCollection)) {
//                // reload routes for the Routes module first
//                $this->routesControllerUtil->reloadRoutesByModule('ZikulaRoutesModule');
//                $this->cacheClearer->clear("symfony.routing");
//            }
//            $url = $this->router->generate('zikularoutesmodule_route_reload', array('lct' => 'admin'), RouterInterface::ABSOLUTE_URL);
//            $frontController = \System::getVar('entrypoint', 'index.php');
//            if (strpos($url, "$frontController/") !== false) {
//                $url = str_ireplace("$frontController/", "", $url);
//            }
//            $event->getRequest()->getSession()->getFlashBag()->add('error', __('You might try re-loading the routes for the extension in question.'));
//            $event->setResponse(new RedirectResponse($url));
//            $event->stopPropagation();
        }
    }

    /**
     * Dispatch and handle the legacy event `frontcontroller.exception`
     *
     * @deprecated removal scheduled for 2.0.0
     *
     * @param GetResponseForExceptionEvent $event
     */
    private function handleLegacyExceptionEvent(GetResponseForExceptionEvent $event)
    {
        $modinfo = \ModUtil::getInfoFromName($event->getRequest()->attributes->get('_zkModule'));
        $legacyEvent = new \Zikula\Core\Event\GenericEvent($event->getException(),
            array('modinfo' => $modinfo,
                'type' => $event->getRequest()->attributes->get('_zkType'),
                'func' => $event->getRequest()->attributes->get('_zkFunc'),
                'arguments' => $event->getRequest()->attributes->all()));
        $this->dispatcher->dispatch('frontcontroller.exception', $legacyEvent);
        if ($legacyEvent->isPropagationStopped()) {
            $event->getRequest()->getSession()->getFlashBag()->add('error', __f('The \'%1$s\' module returned an error in \'%2$s\'. (%3$s)', array(
                $event->getRequest()->attributes->get('_zkModule'),
                $event->getRequest()->attributes->get('_zkFunc'),
                $legacyEvent->getArgument('message'))),
                    $legacyEvent->getArgument('httpcode'));
            $route = $event->getRequest()->server->get('referrer');
            $response = new RedirectResponse($route);
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}
