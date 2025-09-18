# Notes de développement

## Gestion de session

Le composant `App\Infrastructure\Http\Session\Session` encapsule désormais l'état de la session PHP. Il expose des méthodes `get()/set()/has()/remove()` pour la lecture et l'écriture, ainsi que des helpers `flash()` et `pull()` pour gérer les messages temporaires. L'instance est construite à partir de `$_SESSION` (et partagée via le conteneur) afin que les modifications restent synchronisées avec la session native.

Le `Request` HTTP possède une propriété `Session` en lecture seule. On ne manipule plus le tableau `$_SESSION` directement : utilisez `Request::getSession()` ou l'injection du service `SessionInterface` dans les contrôleurs et cas d'usage.

Pour invalider la session ou manipuler manuellement les données pendant un test, créez une instance avec un tableau séparé :

```php
$storage = [];
$session = new Session($storage);
$session->set('user_id', 42);
```

Les tests unitaires couvrent les méthodes principales (`tests/Unit/SessionTest.php`).
