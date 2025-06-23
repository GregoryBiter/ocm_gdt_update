# Система обновления модулей для OpenCart (GDT Update)

Эта система позволяет автоматически обновлять модули OpenCart через админ-панель.

## Структура проекта

```
ocm_gdt_update/
├── client/                  # Файлы для OpenCart
│   ├── admin/               # Файлы администратора
│   │   ├── controller/     
│   │   ├── language/
│   │   └── view/
│   └── system/
│       └── hook/            # Файлы-хуки для идентификации модулей
└── server/                  # Сервер обновлений
    ├── modules/             # Директория с модулями
    │   └── gdt_system/      # Пример модуля
    │       └── info.json    # Информация о модуле
    ├── check.php            # API для проверки обновлений
    ├── download.php         # API для загрузки обновлений
    └── index.php            # Главная страница сервера
```

## Установка модуля обновления в OpenCart

1. Скопируйте содержимое директории `client/` в корневую директорию вашего сайта OpenCart.
2. Войдите в панель администратора и откройте "Расширения" > "Модули".
3. Найдите в списке "GDT Updater" и нажмите "Установить".
4. После установки нажмите "Редактировать".
5. Введите URL вашего сервера обновлений и включите модуль.
6. Сохраните настройки.

## Настройка сервера обновлений

1. Загрузите содержимое директории `server/` на ваш веб-сервер.
2. Убедитесь, что PHP имеет права на запись в директорию `modules/`.

## Добавление нового модуля на сервер обновлений

1. Создайте директорию с кодом модуля в `modules/`, например `modules/my_module/`.
2. Создайте файл `info.json` со следующим содержимым:
   ```json
   {
       "name": "Название модуля",
       "code": "my_module",
       "version": "1.0.0",
       "description": "Описание модуля"
   }
   ```
3. Создайте ZIP-архив с обновлением и поместите его в директорию модуля с именем `my_module_1.0.0.zip`.

## Идентификация модулей в OpenCart

Для того чтобы система обновления могла определить ваш модуль, создайте файл в директории `system/hook/` вашего сайта OpenCart с содержимым:

```php
<?php
/*
name: Название модуля
description: Описание модуля
code: код_модуля
version: 1.0.0
*/
```

## Пример использования API сервера обновлений

### Проверка наличия обновлений

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://ваш-сервер/check.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'code' => 'gdt_system',
    'version' => '1.0.0'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$update_info = json_decode($response, true);
if (isset($update_info['status']) && $update_info['status'] == 'update_available') {
    echo "Доступно обновление до версии {$update_info['version']}";
}
```

### Загрузка обновления

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://ваш-сервер/download.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'code' => 'gdt_system',
    'version' => '1.0.1'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

file_put_contents('update.zip', $response);
```
