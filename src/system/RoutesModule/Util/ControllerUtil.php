<?php
/**
 * Routes.
 *
 * @copyright Zikula contributors (Zikula)
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @author Zikula contributors <support@zikula.org>.
 * @link http://www.zikula.org
 * @link http://zikula.org
 * @version Generated by ModuleStudio 0.7.0 (http://modulestudio.de).
 */

namespace Zikula\RoutesModule\Util;

use Zikula\RoutesModule\Util\Base\ControllerUtil as BaseControllerUtil;
use Zikula_Request_Http;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use FOS\JsRoutingBundle\Command\DumpCommand;
use JMS\I18nRoutingBundle\Router\I18nLoader;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Utility implementation class for controller helper methods.
 */
class ControllerUtil extends BaseControllerUtil
{
    /**
     * Dump the routes exposed to javascript to '/web/js/fos_js_routes.js'
     *
     * @param null $lang
     * @return string
     * @throws \Exception
     */
    public function dumpJsRoutes($lang = null)
    {
        // determine list of supported languages
        $langs = array();
        $installedLanguages = \ZLanguage::getInstalledLanguages();
        if (isset($lang) && in_array($lang, $installedLanguages)) {
            // use provided lang if available
            $langs = array($lang);
        } else {
            $multilingual = (bool)\System::getVar('multilingual', 0);
            if ($multilingual) {
                // get all available locales
                $langs = $installedLanguages;
            } else {
                // get only the default locale
                $langs = array(\System::getVar('language_i18n', 'en')); //$this->getContainer()->getParameter('locale');
            }
        }

        $errors = '';

        // force deletion of existing file
        $targetPath = sprintf('%s/../web/js/fos_js_routes.js', $this->getContainer()->getParameter('kernel.root_dir'));
        if (file_exists($targetPath)) {
            try {
                unlink($targetPath);
            } catch (\Exception $e) {
                $errors .= __f("Error: Could not delete '%s' because %s", array($targetPath, $e->getMessage()));
            }
        }

        foreach ($langs as $lang) {
            $command = new DumpCommand();
            $command->setContainer($this->getContainer());
            $input = new ArrayInput(array('--locale' => $lang . I18nLoader::ROUTING_PREFIX));
            $output = new NullOutput();
            try {
                $command->run($input, $output);
            } catch (\RuntimeException $e) {
                $errors .= $e->getMessage() . ". ";
            }
        }

        return $errors;
    }

    /**
     * Reload routes for one module by name
     * @param string $moduleName (default: ZikulaRoutesModule)
     * @return boolean $hadRoutes
     */
    public function reloadRoutesByModule($moduleName = "ZikulaRoutesRoutes")
    {
        $routeRepository = $this->entityManager->getRepository('ZikulaRoutesModule:RouteEntity');
        $module = \ModUtil::getModule($moduleName);
        if ($module === null) {
            throw new NotFoundHttpException();
        }
        /** @var \Zikula\RoutesModule\Entity\Repository\Route $routeRepository */
        $hadRoutes = $routeRepository->removeAllOfModule($module);

        /** @var \Zikula\RoutesModule\Routing\RouteFinder $routeFinder */
        $routeFinder = $this->get('zikularoutesmodule.routing_finder');
        $routeCollection = $routeFinder->find($module);
        if ($routeCollection->count() > 0) {
            $routeRepository->addRouteCollection($module, $routeCollection);
        }

        return $hadRoutes;
    }
}
