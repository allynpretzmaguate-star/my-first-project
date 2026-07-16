</main>
    </div>
</div>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<?php if (!empty($extraScripts)): ?>
    <?php foreach ($extraScripts as $script): ?>
        <script src="<?= BASE_URL . h($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>