<div class="entrada__marca">
    <span class="marca__selo">SH</span>
    <h1><?= e((string) config('app.nome')) ?></h1>
    <p><?= e((string) config('app.orgao')) ?></p>
</div>

<div class="cartao">
    <form method="post" action="?p=login">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <div class="campo">
            <label for="login">Login</label>
            <input type="text" id="login" name="login" autocomplete="username" autofocus required>
        </div>
        <div class="campo">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" autocomplete="current-password" required>
        </div>
        <button class="botao" style="width:100%">Entrar</button>
    </form>
</div>
