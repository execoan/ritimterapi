<?php if (!defined('RITIM')) { http_response_code(403); exit; } ?>
</main>
<footer class="alt-bilgi yazdirmada-gizle">
  <div class="kapsayici">
    <?= e(APP_NAME) ?> — ritim atölyesi yönetim aracı. Eğitim aracıdır; sağlık ürünü değildir.
  </div>
</footer>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
