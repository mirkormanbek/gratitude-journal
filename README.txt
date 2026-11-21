# Дневник роста — облачная версия

Файлы:
- index.php — приложение
- login.php / logout.php — вход
- setup.php — создание первого пользователя
- api.php — API для синхронизации
- db.php — подключение к БД
- config.example.php — шаблон конфигурации

## Установка
1. Загрузите все файлы в корень сайта journal.luckysport.kz.
2. Скопируйте config.example.php в config.php.
3. В config.php замените CHANGE_ME на пароль пользователя БД luckysp_journal.
4. Откройте https://journal.luckysport.kz/setup.php и создайте логин/пароль.
5. После успешного создания пользователя удалите setup.php.
6. Откройте https://journal.luckysport.kz/ и войдите.

Важно: старые записи с GitHub Pages хранятся в localStorage другого домена и сами не появятся. Сначала не удаляйте старое приложение.
