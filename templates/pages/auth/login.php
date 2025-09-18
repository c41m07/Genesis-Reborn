<?php
/** @var string $csrf_token */
/** @var string $baseUrl */
/** @var array $flashes */
/** @var int|null $currentUserId */
$title = $title ?? 'Connexion';
ob_start();
?>
<section class="card auth-card">
    <h1>Connexion</h1>
    <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/login" class="stack">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <label>
            Email
            <input type="email" name="email" required>
        </label>
        <label>
            Mot de passe
            <input type="password" name="password" required>
        </label>
        <button type="submit">Se connecter</button>
    </form>
    <p class="auth-switch">Pas encore de compte ? <a href="<?= htmlspecialchars($baseUrl) ?>/register">Créer un compte</a></p>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
