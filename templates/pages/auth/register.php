<?php
/** @var string $csrf_token Jeton CSRF pour le formulaire d’inscription. */
/** @var string $baseUrl URL de base pour les liens. */
/** @var array $flashes Messages flash éventuels. */
/** @var int|null $currentUserId Identifiant utilisateur si déjà connecté. */
$title = $title ?? 'Inscription';
ob_start();
?>
    <section class="card auth-card">
        <h1>Inscription</h1>
        <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/register" class="stack">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <label>
                Email
                <input type="email" name="email" required>
            </label>
            <label>
                Mot de passe
                <input type="password" name="password" required>
            </label>
            <label>
                Confirmation
                <input type="password" name="password_confirm" required>
            </label>
            <button type="submit">Créer un compte</button>
        </form>
        <p class="auth-switch">Déjà enregistré ? <a href="<?= htmlspecialchars($baseUrl) ?>/login">Se connecter</a></p>
    </section>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/base.php';
