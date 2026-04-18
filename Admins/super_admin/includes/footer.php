<style>
/* Admin global footer */
.admin-footer {
    margin: 30px auto 18px;
    padding: 6px 0;
    color: #6b7280;
    font-size: 13px;
    text-align: center;
    max-width: 1100px;
}
.admin-footer a {
    color: #4b1d65;
    text-decoration: none;
    font-weight: 600;
}
.admin-footer a:hover {
    text-decoration: underline;
}
</style>

<?php
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($scriptName !== 'login.php' && $scriptName !== 'index.php'):
?>
<div class="admin-footer"><p>&copy; <?php echo date('Y'); ?> <a href="https://webdevzm.tech/" target="_blank" rel="noopener noreferrer">webdevzm.tech</a> - About</p></div>
<?php endif; ?>
</body>
</html>
