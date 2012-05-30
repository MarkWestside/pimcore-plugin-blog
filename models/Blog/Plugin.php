<?php

/**
 * ModernWeb
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.modernweb.pl/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@modernweb.pl so we can send you a copy immediately.
 *
 * @category    Pimcore
 * @package     Plugin_Blog
 * @author      Rafał Gałka <rafal@modernweb.pl>
 * @copyright   Copyright (c) 2007-2012 ModernWeb (http://www.modernweb.pl)
 * @license     http://www.modernweb.pl/license/new-bsd     New BSD License
 */

/**
 * Core plugin class.
 *
 * @category    Pimcore
 * @package     Plugin_Blog
 * @author      Rafał Gałka <rafal@modernweb.pl>
 * @copyright   Copyright (c) 2007-2012 ModernWeb (http://www.modernweb.pl)
 */
class Blog_Plugin extends Pimcore_API_Plugin_Abstract implements Pimcore_API_Plugin_Interface
{
    /**
     * @var Zend_Translate
     */
    protected static $_translate;

    /**
     * @return string $statusMessage
     */
    public static function install()
    {
        $install = new Blog_Plugin_Install();

        // create object classes
        $blogCategory = $install->createClass('BlogCategory');
        $blogEntry = $install->createClass('BlogEntry');

        // classmap
        $install->setClassmap();

        // create root object folder with subfolders
        $blogFolder = $install->createFolders();

        // create custom view for blog objects
        $install->createCustomView($blogFolder, array(
            $blogEntry->getId(),
            $blogCategory->getId(),
        ));

        // create static routes
        self::_importStaticRoutes();

        return self::getTranslate()->_('blog_installed_successfully');
    }

    /**
     * @return string $statusMessage
     */
    public static function uninstall()
    {
        try {
            // remove static routes
            $conf = new Zend_Config_Xml(PIMCORE_PLUGINS_PATH . '/Blog/install/staticroutes.xml');
            foreach ($conf->routes->route as $def) {
                $route = Staticroute::getByName($def->name);
                if ($route) {
                    $route->delete();
                }
            }

            // remove custom view
            $customViews = Pimcore_Tool::getCustomViewConfig();
            if ($customViews) {
                foreach ($customViews as $key => $view) {
                    if ($view['name'] == 'Blog') {
                        unset($customViews[$key]);
                        break;
                    }
                }
                $writer = new Zend_Config_Writer_Xml(array(
                    'config' => new Zend_Config(array('views'=> array('view' => $customViews))),
                    'filename' => PIMCORE_CONFIGURATION_DIRECTORY . '/customviews.xml'
                ));
                $writer->write();
            }

            // remove object folder with all childs
            $blogFolder = Object_Folder::getByPath('/blog');
            if ($blogFolder) {
                $blogFolder->delete();
            }

            // remove classes
            $class = Object_Class::getByName('BlogEntry');
            if ($class) {
                $class->delete();
            }
            $class = Object_Class::getByName('BlogCategory');
            if ($class) {
                $class->delete();
            }

            return self::getTranslate()->_('blog_uninstalled_successfully');
        } catch (Exception $e) {
            Logger::crit($e);
            return self::getTranslate()->_('blog_uninstall_failed');
        }
    }

    /**
     * @return boolean $isInstalled
     */
    public static function isInstalled()
    {
        $entry = Object_Class::getByName('BlogEntry');
        $category = Object_Class::getByName('BlogCategory');

        if ($entry && $category) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public static function getTranslationFileDirectory()
    {
        return PIMCORE_PLUGINS_PATH . '/Blog/static/texts';
    }

    /**
     * @param string $language
     * @return string path to the translation file relative to plugin direcory
     */
    public static function getTranslationFile($language)
    {
        if (is_file(self::getTranslationFileDirectory() . "/$language.csv")) {
            return "/Blog/static/texts/$language.csv";
        } else {
            return '/Blog/static/texts/en.csv';
        }
    }

    /**
     * @return Zend_Translate
     */
    public static function getTranslate()
    {
        if(self::$_translate instanceof Zend_Translate) {
            return self::$_translate;
        }

        try {
            $lang = Zend_Registry::get('Zend_Locale')->getLanguage();
        } catch (Exception $e) {
            $lang = 'en';
        }

        self::$_translate = new Zend_Translate(
            'csv',
            PIMCORE_PLUGINS_PATH . self::getTranslationFile($lang),
            $lang,
            array('delimiter' => ',')
        );
        return self::$_translate;
    }

    /**
     * @param string $name
     * @return Object_Class
     */
    protected static function _importClass($name)
    {
        $conf = new Zend_Config_Xml(PIMCORE_PLUGINS_PATH . "/Blog/install/class_$name.xml");

        $class = Object_Class::create();
        $class->setName($name);
        $class->setUserOwner(self::_getUser()->getId());
        $class->setLayoutDefinitions(
            Object_Class_Service::generateLayoutTreeFromArray(
                $conf->layoutDefinitions->toArray()
            )
        );
        $class->setIcon($conf->icon);
        $class->setAllowInherit($conf->allowInherit);
        $class->setAllowVariants($conf->allowVariants);
        $class->setParentClass($conf->parentClass);
        $class->setPreviewUrl($conf->previewUrl);
        $class->setPropertyVisibility($conf->propertyVisibility);
        $class->save();

        return $class;
    }

    protected static function _setClassmap()
    {

    }

    protected static function _importStaticRoutes()
    {
        $conf = new Zend_Config_Xml(PIMCORE_PLUGINS_PATH . '/Blog/install/staticroutes.xml');

        foreach ($conf->routes->route as $def) {
            $route = Staticroute::create();
            $route->setName($def->name);
            $route->setPattern($def->pattern);
            $route->setReverse($def->reverse);
            $route->setModule($def->module);
            $route->setController($def->controller);
            $route->setAction($def->action);
            $route->setVariables($def->variables);
            $route->setPriority($def->priority);
            $route->save();
        }
    }

    /**
     * @return User
     */
    protected static function _getUser()
    {
        return Zend_Registry::get('pimcore_user');
    }

}