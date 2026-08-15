# bx.imagewebp

Универсальный модуль **1C-Bitrix** для асинхронной конвертации изображений элементов инфоблоков в формат **WebP** с **заменой оригинала**.

Конвертация выполняется вне HTTP-запроса (агент Bitrix и/или systemd CLI-воркер), чтобы не нагружать импорт и пользовательские страницы.

- **Репозиторий:** [github.com/dimabresky/bx.imagewebp](https://github.com/dimabresky/bx.imagewebp)
- **Разработчик:** [dimabresky](https://github.com/dimabresky)

---

## Git submodule для проекта сайта

В корне репозитория сайта:

```bash
git submodule add https://github.com/dimabresky/bx.imagewebp.git local/modules/bx.imagewebp
git submodule update --init --recursive
```

Если каталог модуля уже существовал без submodule, перенесите файлы во временную папку, выполните `git submodule add`, затем при необходимости синхронизируйте содержимое с репозиторием модуля.

После клонирования сайта без `--recurse-submodules`:

```bash
git submodule update --init --recursive
```

---

## Возможности

- Постановка задач в очередь при `OnAfterIBlockElementAdd` / `OnAfterIBlockElementUpdate`.
- Обработка настраиваемых **полей элемента** (whitelist: `DETAIL_PICTURE`, `PREVIEW_PICTURE`).
- Обработка настраиваемых **свойств типа «Файл»** (в т.ч. множественных), по умолчанию `MORE_PHOTO`.
- Выбор одного или нескольких инфоблоков в опциях модуля.
- Замена исходного png/jpg/jpeg на WebP в том же поле/значении свойства.
- Удаление успешных записей очереди (success не копятся).
- Retry с лимитом попыток; ошибки остаются в очереди со статусом `E`.
- Лог в `/upload/bx_imagewebp/convert.log` и `AddMessage2Log`.
- Bitrix-агент + CLI `tools/worker.php` + unit-файлы systemd.
- CLI backfill для уже существующих элементов.
- Защита от циклов (обновление из воркера не ставит задачу повторно).
- Exclusive lock (`flock`), чтобы агент и systemd не обрабатывали очередь параллельно.

---

## Требования

| Компонент | Требование |
|-----------|------------|
| Bitrix | Управление сайтом / Интернет-магазин (ядро с `Bitrix\Main\File\Image`, поддержка `FORMAT_WEBP`) |
| PHP | 8.x (как на стенде проекта) |
| WebP engine | **Imagick** с форматом WEBP **или** GD с `imagewebp` / `imagecreatefromjpeg` / `imagecreatefrompng` |
| Права | запись в `/upload/bx_imagewebp/` |
| Модули | `iblock`, `main` |

Рекомендуется Imagick (`main.imageEngine` → `\Bitrix\Main\File\Image\Imagick` в `/bitrix/.settings.php`). На VMBitrix: `Manage PHP extensions` → Enable imagick.

Проверка окружения отображается на странице настроек модуля. Imagick WEBP считается доступным, если `Imagick::queryFormats('WEBP')` содержит `WEBP` среди ключей **или** среди значений (оба варианта встречаются в разных сборках); в диагностике выводятся флаги `imagick-webp-key` / `imagick-webp-value` и итоговый `imagick-webp`.

---

## Установка

1. Скопируйте каталог модуля в `local/modules/bx.imagewebp/` (уже в репозитории проекта).
2. В админке: **Настройки → Настройки продукта → Модули** → установите **«Конвертация изображений в WebP»** (`bx.imagewebp`).
3. При установке модуль:
   - создаёт таблицу `b_bx_image_webp_queue`;
   - регистрирует обработчики событий инфоблока;
   - регистрирует агент `\Bx\ImageWebp\Agent::run();`.
4. Откройте настройки модуля и задайте `iblock_ids` (без этого enqueue не работает).
5. При необходимости настройте systemd (см. ниже) — можно использовать вместе с агентом (lock исключает гонки).

---

## Настройки модуля

Путь: **Настройки → Настройки продукта → Настройки модулей → bx.imagewebp**  
(или `/bitrix/admin/settings.php?mid=bx.imagewebp`).

| Опция | Ключ | По умолчанию | Описание |
|-------|------|--------------|----------|
| Модуль включён | `enabled` | `Y` | При `N` enqueue и worker ничего не делают |
| ID инфоблоков | `iblock_ids` | *(пусто)* | Список ID через запятую: `12,34`. Пусто = очередь не наполняется |
| Поля элемента | `element_fields` | `DETAIL_PICTURE,PREVIEW_PICTURE` | Только эти два кода; прочие игнорируются |
| Коды свойств | `property_codes` | `MORE_PHOTO` | Свойства типа `F` (файл), в т.ч. множественные |
| Качество WebP | `quality` | `82` | 1–100 |
| Макс. длинная сторона | `max_side` | `0` | `0` = без downscale; иначе пропорциональное уменьшение |
| Размер батча | `batch_size` | `5` | Сколько задач за один проход воркера |
| Макс. попыток | `max_attempts` | `5` | После лимита статус `E` |
| Интервал агента | `agent_interval` | `60` | Секунды; при сохранении опций агент пересоздаётся |
| Писать лог | `log_enabled` | `Y` | Файл + `AddMessage2Log` на ошибках |

### Примеры конфигурации

Каталог товаров ID `15`, стандартные поля и галерея:

```text
iblock_ids=15
element_fields=DETAIL_PICTURE,PREVIEW_PICTURE
property_codes=MORE_PHOTO
```

Два инфоблока, только детальная картинка, без галереи:

```text
iblock_ids=15,22
element_fields=DETAIL_PICTURE
property_codes=
```

Несколько файловых свойств:

```text
property_codes=MORE_PHOTO,PHOTOS,DOCS_IMAGE
```

(не-file свойства при enqueue пропускаются).

---

## Как это работает

```text
Save element (admin / import / API)
        │
        ▼
Handlers (лёгкий путь)
        │  проверка enabled, iblock_ids, mime/ext
        ▼
INSERT b_bx_image_webp_queue (STATUS=P)
        │
        ▼
Agent и/или systemd → Worker (flock)
        │  STATUS=W → convert → replace field/property
        ▼
успех: DELETE строки очереди
ошибка: ATTEMPTS++, снова P или E
```

### Что конвертируется

- MIME: `image/jpeg`, `image/jpg`, `image/png`
- или расширение файла: `jpg`, `jpeg`, `png`
- Уже WebP — пропускается
- Остальные типы — пропускаются

### Замена

- **Поле** (`DETAIL_PICTURE` / `PREVIEW_PICTURE`): `CIBlockElement::Update` новым файлом WebP (Bitrix обычно отвязывает предыдущую картинку поля).
- **Свойство**: точечная замена значения по `PROPERTY_VALUE_ID` через `SetPropertyValuesEx` с сохранением остальных значений и порядка (важно для галереи Aspro `MORE_PHOTO`).
- Модуль **не вызывает** `CFile::Delete` вручную: один `FILE_ID` может быть общим для нескольких полей/элементов. Осиротевшие файлы оставляются штатной очистке Bitrix.

### Идемпотентность и циклы

- Активная задача уникальна в рамках слота: элемент + тип/код цели + `PROPERTY_VALUE_ID` + `FILE_ID` (один физический файл может дать отдельные jobs для DETAIL и PREVIEW).
- Перед заменой воркер сверяет, что в слоте всё ещё тот же `FILE_ID`; иначе job снимается как устаревший.
- Обновление элемента из воркера помечено флагом `EnqueueService` — повторный enqueue не выполняется.
- Если файл уже WebP к моменту обработки — job удаляется без ошибки.

---

## Очередь (SQL)

Таблица: `b_bx_image_webp_queue`.

| Поле | Смысл |
|------|--------|
| `TARGET_TYPE` | `F` — поле элемента, `P` — свойство |
| `TARGET_CODE` | `DETAIL_PICTURE` / `PREVIEW_PICTURE` / код свойства |
| `PROPERTY_VALUE_ID` | ID значения множественного свойства (для `P`) |
| `FILE_ID` | исходный файл |
| `STATUS` | `P` pending, `W` working, `E` error |
| `ATTEMPTS` | число неудачных попыток |
| `LAST_ERROR` | текст последней ошибки |

Успешные задачи **удаляются** из таблицы.

### Мониторинг

```sql
-- размер очереди
SELECT STATUS, COUNT(*) AS cnt
FROM b_bx_image_webp_queue
GROUP BY STATUS;

-- ошибки
SELECT ID, ELEMENT_ID, IBLOCK_ID, TARGET_CODE, FILE_ID, ATTEMPTS, LAST_ERROR, DATE_UPDATE
FROM b_bx_image_webp_queue
WHERE STATUS = 'E'
ORDER BY ID DESC
LIMIT 50;

-- зависшие working (если процесс убит)
SELECT *
FROM b_bx_image_webp_queue
WHERE STATUS = 'W'
  AND DATE_UPDATE < NOW() - INTERVAL 1 HOUR;
```

Возврат зависших `W` в pending (при необходимости вручную):

```sql
UPDATE b_bx_image_webp_queue
SET STATUS = 'P', DATE_UPDATE = NOW()
WHERE STATUS = 'W'
  AND DATE_UPDATE < NOW() - INTERVAL 1 HOUR;
```

Повтор ошибочных после исправления окружения:

```sql
UPDATE b_bx_image_webp_queue
SET STATUS = 'P', ATTEMPTS = 0, LAST_ERROR = NULL
WHERE STATUS = 'E';
```

---

## Агент Bitrix

При установке регистрируется периодический агент:

```php
\Bx\ImageWebp\Agent::run();
```

- Модуль: `bx.imagewebp`
- Интервал: опция `agent_interval` (сек)
- При сохранении настроек агент пересоздаётся с новым интервалом

Проверка: **Настройки → Инструменты → Агенты** → фильтр по `bx.imagewebp` / `ImageWebp`.

Для работы агентов нужен cron на `bitrix/modules/main/tools/cron_events.php` (или аналог на стенде).

---

## CLI worker

Из корня сайта:

```bash
php -f local/modules/bx.imagewebp/tools/worker.php
php -f local/modules/bx.imagewebp/tools/worker.php -- --batches=3
```

Вывод:

```text
processed=5 success=4 failed=1 lock_skip=no
```

Коды выхода:

| Code | Значение |
|------|----------|
| 0 | нормальное завершение |
| 1 | ошибка bootstrap / модуля / фатал |
| 2 | lock занят другим процессом (для systemd считается успехом) |

---

## Systemd

Шаблоны:

- `install/systemd/bx-imagewebp.service`
- `install/systemd/bx-imagewebp.timer`

### Установка на сервере

1. Скопируйте unit-файлы:

```bash
sudo cp local/modules/bx.imagewebp/install/systemd/bx-imagewebp.service /etc/systemd/system/
sudo cp local/modules/bx.imagewebp/install/systemd/bx-imagewebp.timer /etc/systemd/system/
```

2. Отредактируйте service: подставьте реальный `DOCUMENT_ROOT` и путь к `php`.

Пример:

```ini
WorkingDirectory=/home/bitrix/www
ExecStart=/usr/bin/php -f /home/bitrix/www/local/modules/bx.imagewebp/tools/worker.php -- --batches=3
User=bitrix
Group=bitrix
SuccessExitStatus=0 2
```

3. Включите timer:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now bx-imagewebp.timer
sudo systemctl list-timers | grep bx-imagewebp
sudo systemctl status bx-imagewebp.service
```

4. Ручной прогон:

```bash
sudo systemctl start bx-imagewebp.service
journalctl -u bx-imagewebp.service -n 50
```

### Совместная работа с агентом

Можно держать и агент, и timer: оба вызывают один `Worker` с `flock` на `/upload/bx_imagewebp/worker.lock`. Второй процесс просто выйдет с `lock_skip`.

Для максимальной изоляции CPU от веб-пула предпочтителен systemd; агент можно оставить как fallback.

---

## Backfill (существующий каталог)

Постановка в очередь без конвертации:

```bash
php -f local/modules/bx.imagewebp/tools/backfill.php -- --iblock=15 --limit=200 --from-id=0
```

Параметры:

| Параметр | Описание |
|----------|----------|
| `--iblock=ID` | один ИБ из списка опций |
| `--limit=N` | сколько элементов за запуск (default 200) |
| `--from-id=N` | продолжить после элемента с ID > N |

Пагинация курсором по ID — **только с `--iblock`** (или если в опциях один инфоблок):

```bash
# первый проход
php -f local/modules/bx.imagewebp/tools/backfill.php -- --iblock=15 --limit=500 --from-id=0
# в выводе last_id=12345 — следующий:
php -f local/modules/bx.imagewebp/tools/backfill.php -- --iblock=15 --limit=500 --from-id=12345
```

При нескольких `iblock_ids` без `--iblock` скрипт обрабатывает каждый ИБ отдельно с `from_id=0` и `--limit` на каждый; `--from-id` в этом режиме запрещён.

После наполнения очереди обрабатывайте воркером/агентом/systemd.

---

## Логи

| Куда | Когда |
|------|--------|
| `/upload/bx_imagewebp/convert.log` | info/error при `log_enabled=Y` |
| `AddMessage2Log` | ошибки (смотреть лог PHP / настройки главного модуля) |

Каталог создаётся автоматически при первом обращении.

---

## Удаление модуля

В админке снимите модуль. `DoUninstall`:

- снимает обработчики событий;
- удаляет агенты модуля;
- `DROP TABLE b_bx_image_webp_queue`;
- удаляет опции модуля.

Файлы в `/upload/bx_imagewebp/` и уже сконвертированные WebP в элементах **не откатываются**.

Не забудьте отключить systemd timer, если он установлен:

```bash
sudo systemctl disable --now bx-imagewebp.timer
```

---

## Ограничения v1

- Нет dual-storage (оригинал + webp): только замена.
- Нет админ-UI очереди (мониторинг через SQL и лог).
- Поля элемента — только `DETAIL_PICTURE` и `PREVIEW_PICTURE`.
- Не конвертирует SVG/GIF/BMP и не-file свойства.
- Не трогает ресайз-кэш Bitrix старых fileId явно (после delete файла кэши «осиротеют»).
- Требуется корректный cron агентов и/или systemd для фактической обработки.

---

## Структура модуля

```text
local/modules/bx.imagewebp/
├── README.md
├── include.php
├── default_option.php
├── options.php
├── install/
│   ├── index.php
│   ├── version.php
│   ├── db/mysql/install.sql
│   ├── db/mysql/uninstall.sql
│   └── systemd/
├── lang/ru/
├── lib/
│   ├── Config.php
│   ├── Capability.php
│   ├── Logger.php
│   ├── QueueTable.php
│   ├── EnqueueService.php
│   ├── Handlers.php
│   ├── Converter.php
│   ├── ElementImageReplacer.php
│   ├── Worker.php
│   └── Agent.php
└── tools/
    ├── worker.php
    └── backfill.php
```

---

## Быстрый чеклист после деплоя

1. Imagick/GD WebP доступен (блок на странице опций = OK).
2. Модуль установлен.
3. Заполнены `iblock_ids` (и при необходимости поля/свойства).
4. Агент активен и/или systemd timer запущен.
5. Загрузить тестовый jpg в товар → строка в `b_bx_image_webp_queue`.
6. Дождаться worker → файл стал `.webp`, строка очереди исчезла.
7. Проверить карточку товара / галерею / фид.
