<?php

use Zikula\Bundle\CoreBundle\DynamicConfigDumper;
use Zikula\Bundle\CoreBundle\HttpKernel\ZikulaKernel as Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class ZikulaKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle(),
            new Zikula\Bundle\CoreBundle\CoreBundle(),
            new Zikula\Bundle\CoreInstallerBundle\ZikulaCoreInstallerBundle(),
            new Zikula\Bundle\FormExtensionBundle\ZikulaFormExtensionBundle(),
            new Zikula\Bundle\JQueryBundle\ZikulaJQueryBundle(),
            new Zikula\Bundle\JQueryUIBundle\ZikulaJQueryUIBundle(),
            new JMS\I18nRoutingBundle\JMSI18nRoutingBundle(),
            new JMS\TranslationBundle\JMSTranslationBundle(),
            new FOS\JsRoutingBundle\FOSJsRoutingBundle(),
            new Matthias\SymfonyConsoleForm\Bundle\SymfonyConsoleFormBundle(),
        );

        $this->registerCoreModules($bundles);

        $bundles[] = new CustomBundle\CustomBundle();

        if (in_array($this->getEnvironment(), array('dev', 'test'))) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Elao\WebProfilerExtraBundle\WebProfilerExtraBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Zikula\Bundle\GeneratorBundle\ZikulaGeneratorBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->rootDir.'/config/config_'.$this->getEnvironment().'.yml');
        $loader->load($this->rootDir.'/config/parameters.yml');
        if (is_readable($this->rootDir.'/config/custom_parameters.yml')) {
            $loader->load($this->rootDir.'/config/custom_parameters.yml');
        }

        if (!is_readable($this->rootDir . '/config/' . DynamicConfigDumper::CONFIG_GENERATED)) {
            // There is no generated configuration (yet), load default values.
            // This only happens at the very first time Symfony is started.
            $loader->load($this->rootDir . '/config/' . DynamicConfigDumper::CONFIG_DEFAULT);
        } else {
            $loader->load($this->rootDir . '/config/' . DynamicConfigDumper::CONFIG_GENERATED);
        }
    }

    private function registerCoreModules(array &$bundles)
    {
        $bundles[] = new Zikula\AdminModule\ZikulaAdminModule();
        $bundles[] = new Zikula\BlocksModule\ZikulaBlocksModule();
        $bundles[] = new Zikula\CategoriesModule\ZikulaCategoriesModule();
        $bundles[] = new Zikula\ExtensionsModule\ZikulaExtensionsModule();
        $bundles[] = new Zikula\GroupsModule\ZikulaGroupsModule();
        $bundles[] = new Zikula\MailerModule\ZikulaMailerModule();
        $bundles[] = new Zikula\PageLockModule\ZikulaPageLockModule();
        $bundles[] = new Zikula\PermissionsModule\ZikulaPermissionsModule();
        $bundles[] = new Zikula\SearchModule\ZikulaSearchModule();
        $bundles[] = new Zikula\SecurityCenterModule\ZikulaSecurityCenterModule();
        $bundles[] = new Zikula\SettingsModule\ZikulaSettingsModule();
        $bundles[] = new Zikula\ThemeModule\ZikulaThemeModule();
        $bundles[] = new Zikula\UsersModule\ZikulaUsersModule();
        $bundles[] = new Zikula\RoutesModule\ZikulaRoutesModule();

        $boot = new \Zikula\Bundle\CoreBundle\Bundle\Bootstrap();
        $boot->getPersistedBundles($this, $bundles);
    }

    /**
     * Is this a Bundle?
     *
     * @param $name
     * @param bool $first
     * @return bool
     */
    public function isBundle($name, $first = true)
    {
        try {
            $this->getBundle($name, $first);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
