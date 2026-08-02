<?php

namespace Bx\ImageWebp;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * ORM for b_bx_image_webp_queue.
 */
class QueueTable extends DataManager
{
    public const STATUS_PENDING = 'P';
    public const STATUS_WORKING = 'W';
    public const STATUS_ERROR = 'E';

    public static function getTableName(): string
    {
        return 'b_bx_image_webp_queue';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),
            (new IntegerField('ELEMENT_ID'))
                ->configureRequired(true),
            (new IntegerField('IBLOCK_ID'))
                ->configureRequired(true),
            (new StringField('TARGET_TYPE'))
                ->configureSize(1)
                ->configureRequired(true),
            (new StringField('TARGET_CODE'))
                ->configureSize(50)
                ->configureRequired(true),
            (new IntegerField('PROPERTY_VALUE_ID'))
                ->configureNullable(true),
            (new IntegerField('FILE_ID'))
                ->configureRequired(true),
            (new StringField('STATUS'))
                ->configureSize(1)
                ->configureDefaultValue(self::STATUS_PENDING),
            (new IntegerField('ATTEMPTS'))
                ->configureDefaultValue(0),
            (new TextField('LAST_ERROR'))
                ->configureNullable(true),
            (new DatetimeField('DATE_INSERT'))
                ->configureRequired(true),
            (new DatetimeField('DATE_UPDATE'))
                ->configureNullable(true),
        ];
    }
}
