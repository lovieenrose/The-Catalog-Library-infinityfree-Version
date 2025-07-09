<?php
/**
 * Footer Component for The Cat-alog Library
 * Include this file at the bottom of your pages before closing </body> tag
 * 
 * Usage: <?php include 'includes/footer.php'; ?>
 */
?>

<style>
/* Footer Styles */
.main-footer {
    margin-top: auto;
    width: 100%;
    background-color: var(--caramel);
    color: var(--white);
    text-shadow: #000000 1px 1px 2px;
    font-size: 0.9rem;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #ccc;
    box-shadow: 0 -1px 4px rgba(0, 0, 0, 0.05);
}

@media (max-width: 600px) {
    .main-footer {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
}
</style>

<footer class="main-footer">
    <div class="footer-left">© The Cat-alog Library 2025 | Developed by MingMao</div>
    <div class="footer-right">Version 1.0.0</div>
</footer>

<script src="/library-system/assets/js/script.js"></script>
</body>
</html>