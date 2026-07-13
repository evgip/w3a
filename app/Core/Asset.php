<?php

namespace App\Core;

use App\Core\Logger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class Asset
{
    private static string $distCssFile;
    private static string $distAdminCssFile;
    private static string $distJsFile;
    
    /** @var Container|null Контейнер для получения сервисов */
    private static ?Container $container = null;

    /**
     * Установить контейнер (вызывается при инициализации приложения)
     */
    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    /**
     * Получить логгер из контейнера
     */
    private static function getLogger(): Logger
    {
        if (self::$container === null) {
            if (!isset($GLOBALS['app_container'])) {
                throw new \RuntimeException('Container not initialized');
            }
            self::$container = $GLOBALS['app_container'];
        }
        
        return self::$container->get(Logger::class);
    }

    private static function init(): void
    {
        self::$distCssFile      = dirname(__DIR__, 2) . '/public/css/app.min.css';
        self::$distAdminCssFile = dirname(__DIR__, 2) . '/public/css/admin.min.css';
        self::$distJsFile       = dirname(__DIR__, 2) . '/public/js/app.min.js';
    }

    /**
     * Публичный CSS
     */
    public static function css(): string
    {
        self::init();
        $config = require dirname(__DIR__) . '/Config/config.php';
        if (($config['app']['env'] ?? 'development') === 'development') {
            self::compileCssIfNeeded();
        }
        $version = file_exists(self::$distCssFile) ? filemtime(self::$distCssFile) : time();
        return "/css/app.min.css?v=" . $version;
    }

    /**
     * Админский CSS
     */
    public static function adminCss(): string
    {
        self::init();
        $config = require dirname(__DIR__) . '/Config/config.php';
        if (($config['app']['env'] ?? 'development') === 'development') {
            self::compileCssIfNeeded();
        }
        $version = file_exists(self::$distAdminCssFile) ? filemtime(self::$distAdminCssFile) : time();
        return "/css/admin.min.css?v=" . $version;
    }

    public static function js(): string
    {
        self::init();
        $config = require dirname(__DIR__) . '/Config/config.php';
        if (($config['app']['env'] ?? 'development') === 'development') {
            self::compileJsIfNeeded();
        }
        $version = file_exists(self::$distJsFile) ? filemtime(self::$distJsFile) : time();
        return "/js/app.min.js?v=" . $version;
    }

    public static function forceRebuild(): void
    {
        self::init();
        self::buildCss();
        self::buildJs();
    }

    private static function discoverFiles(string $extension): array
    {
        $modulesPath = dirname(__DIR__) . '/Modules';
        if (!is_dir($modulesPath)) {
            return [];
        }

        $directory = new RecursiveDirectoryIterator($modulesPath);
        $iterator  = new RecursiveIteratorIterator($directory);
        $regex     = new RegexIterator($iterator, '/^.+\.' . $extension . '$/i', RegexIterator::GET_MATCH);

        $discovered = [];
        foreach ($regex as $file) {
            $discovered[] = $file[0];
        }

        sort($discovered);
        return $discovered;
    }

    private static function compileCssIfNeeded(): void
    {
        $cssFiles = self::discoverFiles('css');
        $needRebuild = false;

        $mtimeApp = file_exists(self::$distCssFile) ? filemtime(self::$distCssFile) : 0;
        $mtimeAdmin = file_exists(self::$distAdminCssFile) ? filemtime(self::$distAdminCssFile) : 0;

        foreach ($cssFiles as $path) {
            if (file_exists($path)) {
                $isAdminFile = (strpos($path, 'Modules' . DIRECTORY_SEPARATOR . 'Admin') !== false);
                $targetMtime = $isAdminFile ? $mtimeAdmin : $mtimeApp;

                if (filemtime($path) > $targetMtime) {
                    $needRebuild = true;
                    break;
                }
            }
        }

        if ($needRebuild || $mtimeApp === 0 || $mtimeAdmin === 0) {
            self::buildCss();
        }
    }

    private static function buildCss(): void
    {
        $files = self::discoverFiles('css');

        $appCss = "/* Public CSS Bundle: " . date('Y-m-d H:i:s') . " */" . PHP_EOL;
        $adminCss = "/* Admin CSS Bundle: " . date('Y-m-d H:i:s') . " */" . PHP_EOL;

        $appCount = 0;
        $adminCount = 0;

        foreach ($files as $path) {
            if (file_exists($path)) {
                $short = str_replace(dirname(__DIR__, 2), '', $path);
                $content = "/* Source: {$short} */" . PHP_EOL . file_get_contents($path) . PHP_EOL;

                if (strpos($path, 'Modules' . DIRECTORY_SEPARATOR . 'Admin') !== false) {
                    $adminCss .= $content;
                    $adminCount++;
                } else {
                    $appCss .= $content;
                    $appCount++;
                }
            }
        }

        $minify = function ($css) {
            $css = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $css);
            $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
            $css = preg_replace('/ {2,}/', ' ', $css);
            return str_replace([' {', '{ ', '; '], ['{', '{', ';'], $css);
        };

        $dir = dirname(self::$distCssFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        file_put_contents(self::$distCssFile, $minify($appCss));
        file_put_contents(self::$distAdminCssFile, $minify($adminCss));

        $logger = self::getLogger();
        $logger->info("Asset Compiler: Сборка CSS завершена. app.min.css (файлов: {$appCount}), admin.min.css (файлов: {$adminCount})");
    }

    private static function compileJsIfNeeded(): void
    {
        $distMtime = file_exists(self::$distJsFile) ? filemtime(self::$distJsFile) : 0;
        $needRebuild = false;
        $files = self::discoverFiles('js');

        foreach ($files as $path) {
            if (file_exists($path) && filemtime($path) > $distMtime) {
                $needRebuild = true;
                break;
            }
        }
        if ($needRebuild || $distMtime === 0) {
            self::buildJs();
        }
    }

    private static function buildJs(): void
    {
        $compiled = "/* JavaScript Bundle: " . date('Y-m-d H:i:s') . " */" . PHP_EOL;
        $files = self::discoverFiles('js');

        $priorityFile = dirname(__DIR__) . '/Modules/Common/Views/js/core_utils.js';
        
        $orderedFiles = [];
        $otherFiles = [];
        
        foreach ($files as $path) {
            if (realpath($path) === realpath($priorityFile)) {
                array_unshift($orderedFiles, $path);
            } else {
                $otherFiles[] = $path;
            }
        }
        
        $files = array_merge($orderedFiles, $otherFiles);

        foreach ($files as $path) {
            if (file_exists($path)) {
                $short = str_replace(dirname(__DIR__, 2), '', $path);
                $compiled .= ";" . PHP_EOL . "/* Source: {$short} */" . PHP_EOL . file_get_contents($path) . PHP_EOL;
            }
        }

        $compiled = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $compiled);
        $compiled = preg_replace('/^[ \t]*\/\/.*$/m', '', $compiled);
        $compiled = str_replace("\t", " ", $compiled);
        $compiled = preg_replace('/ +/', ' ', $compiled);

        $dir = dirname(self::$distJsFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents(self::$distJsFile, $compiled);

        $logger = self::getLogger();
        $logger->info("Asset Compiler: JS сборка обновлена. Файлов: " . count($files));
    }
}
