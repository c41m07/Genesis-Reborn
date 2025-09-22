composer dump-autoload --optimize
composer db:create

exec apache2-foreground