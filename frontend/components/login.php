<?php
$csrf = function_exists('generateCsrfToken') ? generateCsrfToken() : '';
$err = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
?>
<div class="container" style="max-width:420px;margin-top:60px;">
	<div class="card">
		<div class="card-header">
			<h5 class="card-title mb-0"><i class="fas fa-lock"></i> Вход в панель</h5>
		</div>
		<div class="card-body">
			<?php if ($err): ?>
			<div class="alert alert-danger" role="alert"><?php echo escape($err); ?></div>
			<?php endif; ?>
			<form method="post" action="?section=login&action=login">
				<div class="mb-3">
					<label for="username" class="form-label">Логин</label>
					<input type="text" class="form-control" id="username" name="username" required autofocus>
				</div>
				<div class="mb-3">
					<label for="password" class="form-label">Пароль</label>
					<input type="password" class="form-control" id="password" name="password" required>
				</div>
				<input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
				<button type="submit" class="btn btn-primary w-100">Войти</button>
			</form>
		</div>
	</div>
	<p class="text-center mt-3 text-muted">Демо: admin / admin123</p>
</div>
