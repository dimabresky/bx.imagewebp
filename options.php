<?php

/**
 * Admin settings page for bx.imagewebp.
 */

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bx\ImageWebp\Capability;
use Bx\ImageWebp\Config;

global $APPLICATION;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$mid = 'bx.imagewebp';
$MODULE_RIGHT = $APPLICATION->GetGroupRight($mid);
if ($MODULE_RIGHT < 'R') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

Loader::includeModule($mid);

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && $MODULE_RIGHT >= 'W'
    && !empty($_REQUEST['Update'])
    && check_bitrix_sessid()
) {
    $enabled = !empty($_REQUEST['enabled']) ? 'Y' : 'N';
    $logEnabled = !empty($_REQUEST['log_enabled']) ? 'Y' : 'N';

    Option::set($mid, 'enabled', $enabled);
    Option::set($mid, 'iblock_ids', (string)($_REQUEST['iblock_ids'] ?? ''));
    Option::set($mid, 'element_fields', (string)($_REQUEST['element_fields'] ?? 'DETAIL_PICTURE,PREVIEW_PICTURE'));
    Option::set($mid, 'property_codes', (string)($_REQUEST['property_codes'] ?? 'MORE_PHOTO'));
    Option::set($mid, 'quality', (string)(int)($_REQUEST['quality'] ?? 82));
    Option::set($mid, 'max_side', (string)(int)($_REQUEST['max_side'] ?? 0));
    Option::set($mid, 'batch_size', (string)max(1, (int)($_REQUEST['batch_size'] ?? 5)));
    Option::set($mid, 'max_attempts', (string)max(1, (int)($_REQUEST['max_attempts'] ?? 5)));
    Option::set($mid, 'agent_interval', (string)max(10, (int)($_REQUEST['agent_interval'] ?? 60)));
    Option::set($mid, 'log_enabled', $logEnabled);

    // Reschedule agent interval.
    \CAgent::RemoveModuleAgents($mid);
    \CAgent::AddAgent(
        '\\Bx\\ImageWebp\\Agent::run();',
        $mid,
        'N',
        Config::getAgentInterval(),
        '',
        'Y',
        '',
        100
    );
}

$enabled = Option::get($mid, 'enabled', 'Y');
$iblockIds = Option::get($mid, 'iblock_ids', '');
$elementFields = Option::get($mid, 'element_fields', 'DETAIL_PICTURE,PREVIEW_PICTURE');
$propertyCodes = Option::get($mid, 'property_codes', 'MORE_PHOTO');
$quality = Option::get($mid, 'quality', '82');
$maxSide = Option::get($mid, 'max_side', '0');
$batchSize = Option::get($mid, 'batch_size', '5');
$maxAttempts = Option::get($mid, 'max_attempts', '5');
$agentInterval = Option::get($mid, 'agent_interval', '60');
$logEnabled = Option::get($mid, 'log_enabled', 'Y');
$capabilityOk = Capability::canConvertToWebp();
$capabilityDesc = Capability::describe();

$APPLICATION->SetTitle(Loc::getMessage('BX_IMAGEWEBP_OPT_TITLE'));
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$tabs = [
    [
        'DIV' => 'bx_imagewebp_main',
        'TAB' => Loc::getMessage('BX_IMAGEWEBP_OPT_TAB_MAIN'),
        'TITLE' => Loc::getMessage('BX_IMAGEWEBP_OPT_TAB_MAIN'),
    ],
];
$tabControl = new CAdminTabControl('bxImageWebpTab', $tabs);
$tabControl->Begin();
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($mid) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->BeginNextTab(); ?>
    <tr>
        <td width="40%"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_CAPABILITY') ?>:</td>
        <td>
            <?php if ($capabilityOk): ?>
                <span style="color:green;font-weight:bold;"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_CAPABILITY_OK') ?></span>
            <?php else: ?>
                <span style="color:red;font-weight:bold;"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_CAPABILITY_FAIL') ?></span>
            <?php endif; ?>
            <div class="adm-info-message-wrap"><div class="adm-info-message"><?= htmlspecialcharsbx($capabilityDesc) ?></div></div>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('BX_IMAGEWEBP_OPT_ENABLED') ?>:</td>
        <td><input type="checkbox" name="enabled" value="Y"<?= $enabled === 'Y' ? ' checked' : '' ?>></td>
    </tr>
    <tr>
        <td class="adm-detail-valign-top"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_IBLOCK_IDS') ?>:</td>
        <td>
            <input type="text" name="iblock_ids" value="<?= htmlspecialcharsbx($iblockIds) ?>" size="50">
            <div class="adm-info-message-wrap"><div class="adm-info-message"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_IBLOCK_IDS_HINT') ?></div></div>
        </td>
    </tr>
    <tr>
        <td class="adm-detail-valign-top"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_ELEMENT_FIELDS') ?>:</td>
        <td>
            <input type="text" name="element_fields" value="<?= htmlspecialcharsbx($elementFields) ?>" size="50">
            <div class="adm-info-message-wrap"><div class="adm-info-message"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_ELEMENT_FIELDS_HINT') ?></div></div>
        </td>
    </tr>
    <tr>
        <td class="adm-detail-valign-top"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_PROPERTY_CODES') ?>:</td>
        <td>
            <input type="text" name="property_codes" value="<?= htmlspecialcharsbx($propertyCodes) ?>" size="50">
            <div class="adm-info-message-wrap"><div class="adm-info-message"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_PROPERTY_CODES_HINT') ?></div></div>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('BX_IMAGEWEBP_OPT_QUALITY') ?>:</td>
        <td><input type="text" name="quality" value="<?= htmlspecialcharsbx($quality) ?>" size="10"></td>
    </tr>
    <tr>
        <td class="adm-detail-valign-top"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_MAX_SIDE') ?>:</td>
        <td>
            <input type="text" name="max_side" value="<?= htmlspecialcharsbx($maxSide) ?>" size="10">
            <div class="adm-info-message-wrap"><div class="adm-info-message"><?= Loc::getMessage('BX_IMAGEWEBP_OPT_MAX_SIDE_HINT') ?></div></div>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('BX_IMAGEWEBP_OPT_BATCH_SIZE') ?>:</td>
        <td><input type="text" name="batch_size" value="<?= htmlspecialcharsbx($batchSize) ?>" size="10"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('BX_IMAGEWEBP_OPT_MAX_ATTEMPTS') ?>:</td>
        <td><input type="text" name="max_attempts" value="<?= htmlspecialcharsbx($maxAttempts) ?>" size="10"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('BX_IMAGEWEBP_OPT_AGENT_INTERVAL') ?>:</td>
        <td><input type="text" name="agent_interval" value="<?= htmlspecialcharsbx($agentInterval) ?>" size="10"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('BX_IMAGEWEBP_OPT_LOG_ENABLED') ?>:</td>
        <td><input type="checkbox" name="log_enabled" value="Y"<?= $logEnabled === 'Y' ? ' checked' : '' ?>></td>
    </tr>
    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="Update" value="<?= Loc::getMessage('BX_IMAGEWEBP_OPT_SAVE') ?>" class="adm-btn-save">
    <?php $tabControl->End(); ?>
</form>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
