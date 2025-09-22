composer dump-autoload --optimize
composer db:migrate

exec apache2-foreground