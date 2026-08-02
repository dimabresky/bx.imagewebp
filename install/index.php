<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;

Loc::loadMessages(__FILE__);

/**
 * Installer for local module bx.imagewebp.
 */
class bx_imagewebp extends CModule
{
    /** @var string */
    public $MODULE_ID = 'bx.imagewebp';

    /** @var string */
    public $MODULE_VERSION;

    /** @var string */
    public $MODULE_VERSION_DATE;

    /** @var string */
    public $MODULE_NAME;

    /** @var string */
    public $MODULE_DESCRIPTION;

    /** @var string */
    public $PARTNER_NAME;

    /** @var string */
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = Loc::getMessage('BX_IMAGEWEBP_MODULE_NAME') ?: 'Image to WebP converter';
        $this->MODULE_DESCRIPTION = Loc::getMessage('BX_IMAGEWEBP_MODULE_DESC')
            ?: 'Async png/jpg/jpeg to WebP conversion for iblock element fields and file properties. Repository: https://github.com/dimabresky/bx.imagewebp';
        $this->PARTNER_NAME = Loc::getMessage('BX_IMAGEWEBP_PARTNER_NAME') ?: 'dimabresky';
        $this->PARTNER_URI = Loc::getMessage('BX_IMAGEWEBP_PARTNER_URI') ?: 'https://github.com/dimabresky';
    }

    public function DoInstall(): bool
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->installDB();
        $this->installEvents();
        $this->installAgent();

        return true;
    }

    public function DoUninstall(): bool
    {
        $this->unInstallAgent();
        $this->unInstallEvents();
        $this->unInstallDB();
        Option::delete($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    public function installDB(): bool
    {
        $connection = Application::getConnection();
        $sqlFile = __DIR__ . '/db/mysql/install.sql';
        if (is_readable($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            if (is_string($sql) && $sql !== '') {
                foreach ($this->splitSql($sql) as $query) {
                    $connection->queryExecute($query);
                }
            }
        }

        return true;
    }

    public function unInstallDB(): bool
    {
        $connection = Application::getConnection();
        $sqlFile = __DIR__ . '/db/mysql/uninstall.sql';
        if (is_readable($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            if (is_string($sql) && $sql !== '') {
                foreach ($this->splitSql($sql) as $query) {
                    $connection->queryExecute($query);
                }
            }
        }

        return true;
    }

    public function installFiles(): bool
    {
        return true;
    }

    public function unInstallFiles(): bool
    {
        return true;
    }

    public function installEvents(): void
    {
        $em = EventManager::getInstance();
        $em->registerEventHandler(
            'iblock',
            'OnAfterIBlockElementAdd',
            $this->MODULE_ID,
            '\\Bx\\ImageWebp\\Handlers',
            'onAfterIBlockElementAdd'
        );
        $em->registerEventHandler(
            'iblock',
            'OnAfterIBlockElementUpdate',
            $this->MODULE_ID,
            '\\Bx\\ImageWebp\\Handlers',
            'onAfterIBlockElementUpdate'
        );
    }

    public function unInstallEvents(): void
    {
        $em = EventManager::getInstance();
        $em->unRegisterEventHandler(
            'iblock',
            'OnAfterIBlockElementAdd',
            $this->MODULE_ID,
            '\\Bx\\ImageWebp\\Handlers',
            'onAfterIBlockElementAdd'
        );
        $em->unRegisterEventHandler(
            'iblock',
            'OnAfterIBlockElementUpdate',
            $this->MODULE_ID,
            '\\Bx\\ImageWebp\\Handlers',
            'onAfterIBlockElementUpdate'
        );
    }

    public function installAgent(): void
    {
        $interval = (int)Option::get($this->MODULE_ID, 'agent_interval', '60');
        if ($interval < 10) {
            $interval = 60;
        }

        \CAgent::AddAgent(
            '\\Bx\\ImageWebp\\Agent::run();',
            $this->MODULE_ID,
            'N',
            $interval,
            '',
            'Y',
            '',
            100
        );
    }

    public function unInstallAgent(): void
    {
        \CAgent::RemoveModuleAgents($this->MODULE_ID);
    }

    /**
     * @return list<string>
     */
    private function splitSql(string $sql): array
    {
        $parts = preg_split('/;\s*[\r\n]+/', trim($sql)) ?: [];
        $queries = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $queries[] = $part;
            }
        }

        return $queries;
    }
}
