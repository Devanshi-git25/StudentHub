
    <footer>
        <p>&copy; 2026 StudentHub. All Rights Reserved.</p>
    </footer>

    <script src="js/script.js"></script>
<?php foreach (($extraScripts ?? []) as $script): ?>
    <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>

</body>

</html>
